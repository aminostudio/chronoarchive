#!/usr/bin/env python3
"""Fail draft CI on *new* WordPress Plugin Check findings without waiving release.

The two existing SQL errors and one direct-query warning are a known
RELEASE-BLOCKING baseline, not approved suppressions. This tool only checks
that the draft QA candidate did not introduce additional scanner findings.
Marking the PR ready still runs the *unmodified* blocking official scanner.
"""
from __future__ import annotations

import collections
import json
import os
from pathlib import Path
import sys

KNOWN_SQL = {
    "PluginCheck.Security.DirectDB.UnescapedDBParameter": "ERROR",
    "WordPress.DB.PreparedSQL.NotPrepared": "ERROR",
    "WordPress.DB.DirectDatabaseQuery.DirectQuery": "WARNING",
}


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


def evaluate(raw: object) -> tuple[list[str], list[str]]:
    findings = findings_from(raw)
    unexpected = []
    known = []
    counts = collections.Counter()
    for path, code, severity in findings:
        if path == "chronoarchive.php" and KNOWN_SQL.get(code) == severity:
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
        raw = json.loads(path.read_text(encoding="utf-8-sig"))
        known, unexpected = evaluate(raw)
    except (OSError, ValueError, UnicodeDecodeError) as error:
        print(f"CHECK FAILED: invalid scanner output: {error}", file=sys.stderr)
        return 1
    lines = [
        "### WordPress.org exact-ZIP scanner review",
        "",
        f"- Existing **unresolved** SQL findings: {len(known)}",
        f"- Unexpected new findings: {len(unexpected)}",
        "- Draft-only regression status is **not WordPress.org release approval**.",
        "- The *official* scanner remains blocking once the PR is ready for review.",
        "",
    ]
    lines += [f"- Existing blocker: \`{item}\`" for item in known]
    lines += [f"- **NEW:** \`{item}\`" for item in unexpected]
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
