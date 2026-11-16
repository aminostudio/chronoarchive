#!/usr/bin/env python3
"""Validate the official Plugin Check result for development and stable candidates.

The historical beta3 SQL baseline was explicitly not release approval.
Stable 2.1.0 preflight rejects ANY scanner errors or warnings, including
reappearance of formerly known SQL codes. No sniffs are suppressed.
"""
from __future__ import annotations

import collections
import json
import os
from pathlib import Path
import re
import sys

KNOWN_SQL = {
    "PluginCheck.Security.DirectDB.UnescapedDBParameter": "ERROR",
    "WordPress.DB.PreparedSQL.NotPrepared": "ERROR",
    "WordPress.DB.DirectDatabaseQuery.DirectQuery": "WARNING",
}


def parse_report(text: str) -> object:
    """Parse either standard JSON or Plugin Check's 'FILE: path' text stream.

    The official wordpress/plugin-check-action@v1 emits sections such as:
      FILE: chronoarchive.php
      [{"type":"ERROR", "code":"..."}]
    They are *not* one top-level JSON document, despite --format=json.
    """
    source = text.lstrip("\ufeff").strip()
    if not source:
        # Official scanner execution must succeed before a blank clean report
        # is accepted. This verifier alone does not grant release approval.
        return {}
    # The official Plugin Check action prints this exact success line rather
    # than JSON when absolutely no issues are present. Only this precise
    # sentinel can bypass FILE: section parsing; reject any appended text.
    if source == "Success: Checks complete. No errors found.":
        return {}
    try:
        return json.loads(source)
    except json.JSONDecodeError:
        pass
    grouped: dict[str, list[object]] = {}
    lines = [line.strip() for line in source.splitlines() if line.strip()]
    index = 0
    while index < len(lines):
        line = lines[index]
        if not line.startswith("FILE: "):
            raise ValueError(f"Unexpected scanner report line: {line[:100]}")
        filename = line[6:].strip()
        if not filename or filename in grouped or index + 1 >= len(lines):
            raise ValueError("Duplicate or missing Plugin Check file section")
        try:
            data = json.loads(lines[index + 1])
        except json.JSONDecodeError as error:
            raise ValueError(f"Malformed Plugin Check JSON for {filename}") from error
        if not isinstance(data, list):
            raise ValueError("Plugin Check file findings must be an array")
        grouped[filename] = data
        index += 2
    return grouped


def findings_from(raw: object) -> list[tuple[str, str, str]]:
    if not isinstance(raw, dict):
        raise ValueError("Expected Plugin Check's JSON object keyed by file")
    parsed: list[tuple[str, str, str]] = []
    for path, records in raw.items():
        if not isinstance(path, str) or not isinstance(records, list):
            raise ValueError("Unexpected Plugin Check file or findings structure")
        for entry in records:
            if not isinstance(entry, dict):
                raise ValueError("Unexpected Plugin Check finding")
            code, severity = entry.get("code"), entry.get("type")
            if not isinstance(code, str) or severity not in ("ERROR", "WARNING"):
                raise ValueError("Unrecognized finding code/severity")
            parsed.append((path, code, severity))
    return parsed


def evaluate(raw: object, *, stable_release: bool = False) -> tuple[list[str], list[str]]:
    findings = findings_from(raw)
    unexpected = []
    known = []
    counts = collections.Counter()
    for path, code, severity in findings:
        if path == "chronoarchive.php" and KNOWN_SQL.get(code) == severity:
            if stable_release:
                unexpected.append(f"{path}: unresolved former SQL blocker {severity}: {code}")
            else:
                counts[(path, code, severity)] += 1
                known.append(f"{severity}: {code}")
        else:
            unexpected.append(f"{path}: {severity}: {code}")
    for (path, code, severity), count in counts.items():
        if count > 1:
            unexpected.append(f"{path}: duplicated finding {code} ({count})")
    return known, unexpected


def main(argv: list[str]) -> int:
    if len(argv) != 2:
        print("Usage: verify-pcp-findings.py <plugin-check-results.txt>", file=sys.stderr)
        return 2
    path = Path(argv[1])
    if not path.is_file():
        print(f"CHECK FAILED: Plugin Check did not produce {path}", file=sys.stderr)
        return 1
    try:
        raw = parse_report(path.read_text(encoding="utf-8-sig"))
        php = Path(__file__).resolve().parents[1] / "chronoarchive.php"
        source = php.read_text(encoding="utf-8")
        stable_release = bool(re.search(r"(?m)^Version:\s*[0-9]+\.[0-9]+\.[0-9]+\s*$", source))
        known, unexpected = evaluate(raw, stable_release=stable_release)
    except (OSError, ValueError, UnicodeDecodeError) as error:
        print(f"CHECK FAILED: invalid scanner output: {error}", file=sys.stderr)
        return 1
    lines = [
        "### WordPress.org exact-ZIP scanner review",
        "",
        f"- Existing **unresolved** SQL findings: {len(known)}",
        f"- Unexpected new findings: {len(unexpected)}",
        "- Scanner findings are strict-blocking on stable candidates; beta3 historical baseline is advisory only.",
        "- The *official* scanner remains blocking once the PR is ready for review.",
        "",
    ]
    lines += [f"- Existing blocker: `{item}`" for item in known]
    lines += [f"- **NEW:** `{item}`" for item in unexpected]
    summary = os.getenv("GITHUB_STEP_SUMMARY")
    if summary:
        with open(summary, "a", encoding="utf-8") as target:
            target.write("\n".join(lines) + "\n")
    for line in lines:
        print(line)
    if unexpected:
        print("CHECK FAILED: additional Plugin Check findings must be triaged.", file=sys.stderr)
        return 1
    if known:
        print("CHECK PASSED WITH KNOWN SQL RELEASE BLOCKERS (NOT A CLEAN PLUGIN CHECK).")
    else:
        print("CHECK PASSED: no findings in the exact package.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
