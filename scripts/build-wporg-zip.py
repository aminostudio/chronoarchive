#!/usr/bin/env python3
"""Build a deterministic lowercase-slug WordPress.org QA ZIP.

A prerelease may be built for scanner testing but MUST NOT be submitted.
Pass --release to require an actual stable version and matching readme.
Security, Plugin Check and maintainer sign-off remain separate gates.
"""
from __future__ import annotations

import argparse
import hashlib
from pathlib import Path
import re
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SLUG = "chronoarchive"
RUNTIME = (
    "chronoarchive.php",
    "readme.txt",
    "LICENSE",
    "assets/js/archive-dropdown.js",
    "assets/js/admin-settings.js",
    "assets/css/admin-settings.css",
    "js/widget_form.js",
    "docs/NOTICE.md",
)
FIXED_TIME = (1980, 1, 1, 0, 0, 0)


def read_version(text: str, pattern: str, label: str) -> str:
    found = re.findall(pattern, text, flags=re.MULTILINE)
    if len(found) != 1:
        raise ValueError(f"{label}: expected one version, found {len(found)}")
    return found[0]


def build(output: Path, release: bool) -> tuple[str, str, int]:
    php = (ROOT / "chronoarchive.php").read_text(encoding="utf-8-sig")
    readme = (ROOT / "readme.txt").read_text(encoding="utf-8-sig")
    header = read_version(php, r"^Version:\s*(\S+)\s*$", "Plugin header")
    constant = read_version(
        php, r"^\s*const\s+VERSION\s*=\s*'([^']+)';", "VERSION constant"
    )
    stable = read_version(readme, r"^Stable tag:\s*(\S+)\s*$", "Stable tag")
    if header != constant or header != stable:
        raise ValueError("Plugin header, VERSION constant and Stable tag differ")
    if not re.fullmatch(r"[0-9]+\.[0-9]+\.[0-9]+(?:[-.][a-zA-Z0-9.]+)?", header):
        raise ValueError(f"Invalid version format: {header!r}")
    if release and not re.fullmatch(r"[0-9]+\.[0-9]+\.[0-9]+", header):
        raise ValueError("RELEASE BLOCKED: prerelease version cannot be submitted")

    content = {}
    for name in RUNTIME:
        source = ROOT / name
        if not source.is_file() or source.is_symlink():
            raise ValueError(f"Missing or unsafe release file: {name}")
        # Reject symlinked ancestor folders as well as symlinked files.
        # The previous resolved-parent equality accepted a linked assets/
        # directory and could import external bytes into the release ZIP.
        if any(part.is_symlink() for part in (source.parent, *source.parents)):
            raise ValueError(f"Symlinked package parent: {name}")
        if not source.resolve().is_relative_to(ROOT.resolve()):
            raise ValueError(f"Unsafe path escaping repository: {name}")
        payload = source.read_bytes()
        if not payload:
            raise ValueError(f"Empty release file: {name}")
        content[name] = payload
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED) as archive:
        for name in sorted(content):
            entry = zipfile.ZipInfo(f"{SLUG}/{name}", FIXED_TIME)
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            archive.writestr(entry, content[name], compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    with zipfile.ZipFile(output) as archive:
        expected = [f"{SLUG}/{name}" for name in sorted(content)]
        if archive.namelist() != expected or archive.testzip() is not None:
            raise ValueError("ZIP manifest or integrity validation failed")
        if any(archive.read(f"{SLUG}/{name}") != data for name, data in content.items()):
            raise ValueError("ZIP bytes differ from source")
    return header, hashlib.sha256(output.read_bytes()).hexdigest(), output.stat().st_size


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--release", action="store_true",
                        help="Reject betas/RCs; for signed-off release packaging only")
    args = parser.parse_args()
    try:
        version, digest, size = build(args.output, args.release)
    except (OSError, ValueError, zipfile.BadZipFile) as error:
        print(f"PACKAGE BLOCKED: {error}", file=sys.stderr)
        return 1
    print(f"Lowercase {SLUG} ZIP {version}: {size} bytes, SHA256 {digest}")
    if not args.release:
        print("QA CANDIDATE ONLY. NOT FOR WORDPRESS.ORG SUBMISSION.")
    else:
        print("Metadata verified; requires independent security/Plugin Check sign-off.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
