#!/usr/bin/env python3
"""Build a deterministic, LAB-ONLY ChronoArchive beta ZIP.

This is a development QA artifact, not a WordPress.org stable release.
Only an allowlist of runtime files and attribution is packaged.
"""

from __future__ import annotations

import argparse
import hashlib
from pathlib import Path
import re
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
EXPECTED_VERSION = "2.1.0-beta2"
PACKAGE_ROOT = "chronoarchive"
RUNTIME_FILES = (
    "chronoarchive.php",
    "readme.txt",
    "LICENSE",
    "assets/js/archive-dropdown.js",
    "js/widget_form.js",
    "docs/NOTICE.md",
)
FIXED_ZIP_TIME = (1980, 1, 1, 0, 0, 0)


def exactly_one(pattern: str, source: str, label: str) -> str:
    values = re.findall(pattern, source, re.MULTILINE)
    if len(values) != 1:
        raise ValueError(f"{label}: expected one version, found {len(values)}")
    return values[0].strip()


def verify_source() -> dict[str, bytes]:
    php = (ROOT / "chronoarchive.php").read_text(encoding="utf-8")
    readme = (ROOT / "readme.txt").read_text(encoding="utf-8")

    header_version = exactly_one(r"^Version:\s*(\S+)\s*$", php, "Plugin header")
    class_version = exactly_one(
        r"^\s*var\s+\$version\s*=\s*'([^']+)';",
        php,
        "PHP plugin version property",
    )
    readme_version = exactly_one(r"^Stable tag:\s*(\S+)\s*$", readme, "readme.txt")
    if {header_version, class_version, readme_version} != {EXPECTED_VERSION}:
        raise ValueError(
            "Version metadata mismatch: "
            f"header={header_version}, class={class_version}, "
            f"readme={readme_version}, required={EXPECTED_VERSION}"
        )

    data: dict[str, bytes] = {}
    for relative in RUNTIME_FILES:
        path = ROOT / relative
        if not path.is_file() or path.is_symlink():
            raise ValueError(f"Missing or unsafe package file: {relative}")
        if path.resolve().parent != path.parent.resolve():
            raise ValueError(f"Unsafe package path: {relative}")
        payload = path.read_bytes()
        if not payload:
            raise ValueError(f"Empty package file: {relative}")
        data[relative] = payload
    return data


def build(output: Path) -> tuple[str, int]:
    content = verify_source()
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED) as bundle:
        for relative in sorted(content):
            entry = zipfile.ZipInfo(f"{PACKAGE_ROOT}/{relative}", FIXED_ZIP_TIME)
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            entry.compress_type = zipfile.ZIP_DEFLATED
            bundle.writestr(entry, content[relative], compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)

    with zipfile.ZipFile(output) as bundle:
        expected = [f"{PACKAGE_ROOT}/{relative}" for relative in sorted(content)]
        actual = bundle.namelist()
        if actual != expected:
            raise ValueError(f"Unexpected ZIP members: {actual!r}")
        invalid = bundle.testzip()
        if invalid:
            raise ValueError(f"ZIP integrity failed at {invalid}")
        for relative, payload in content.items():
            if bundle.read(f"{PACKAGE_ROOT}/{relative}") != payload:
                raise ValueError(f"ZIP content mismatch: {relative}")

    output_bytes = output.read_bytes()
    return hashlib.sha256(output_bytes).hexdigest(), len(output_bytes)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--output",
        type=Path,
        default=ROOT / "dist" / f"chronoarchive-{EXPECTED_VERSION}-LAB.zip",
    )
    args = parser.parse_args()
    try:
        digest, size = build(args.output)
    except (OSError, ValueError, zipfile.BadZipFile) as error:
        print(f"LAB PACKAGE BLOCKED: {error}", file=sys.stderr)
        return 1
    print(f"LAB-only beta {EXPECTED_VERSION}: {args.output}")
    print(f"Files: {len(RUNTIME_FILES)}. Bytes: {size}. SHA256: {digest}")
    print("NOT a stable release; Plugin Check/human security review remains blocked.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
