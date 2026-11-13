#!/usr/bin/env python3
"""Validate WordPress plugin/readme metadata without pretending to run Plugin Check.

Normal invocation checks schema consistency during development.
Use --release at a stable release boundary to reject prerelease versions.
The official WordPress Plugin Check and human review are separate gates.
"""
from __future__ import annotations

import argparse
from pathlib import Path
import re
import sys


def field(text: str, key: str, *, php_header: bool = False) -> str:
    prefix = r"\s*\*?\s*" if php_header else ""
    match = re.search(
        r"(?mi)^" + prefix + re.escape(key) + r":\s*(.*?)\s*$", text
    )
    return match.group(1).strip() if match else ""


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--release",
        action="store_true",
        help="Require a final, non-prerelease plugin version and stable tag.",
    )
    args = parser.parse_args()

    root = Path(__file__).resolve().parents[1]
    php = root / "chronoarchive.php"
    readme_file = root / "readme.txt"
    errors: list[str] = []

    if not php.is_file() or not readme_file.is_file():
        errors.append("Missing runtime PHP or WordPress.org readme.txt")
        print("\n".join("ERROR: " + error for error in errors))
        return 1

    php_src = php.read_text(encoding="utf-8-sig")
    header = php_src[:5000]
    readme = readme_file.read_text(encoding="utf-8-sig")

    for key in (
        "Plugin Name", "Version", "Requires at least", "Requires PHP",
        "Author", "License", "License URI", "Text Domain",
    ):
        if not field(header, key, php_header=True):
            errors.append(f"chronoarchive.php: missing '{key}'")

    for key in (
        "Contributors", "Requires at least", "Tested up to",
        "Stable tag", "Requires PHP", "License", "License URI", "Tags",
    ):
        if not field(readme, key):
            errors.append(f"readme.txt: missing '{key}'")

    for heading in (
        "== Description ==", "== Installation ==",
        "== Frequently Asked Questions ==", "== Changelog ==",
    ):
        if heading not in readme:
            errors.append(f"readme.txt: missing '{heading}' section")

    domain = field(header, "Text Domain", php_header=True)
    if domain and domain != "chronoarchive":
        errors.append(f"Text Domain must be 'chronoarchive', not {domain!r}")

    if re.search(r",\s*['\"]ChronoArchive['\"]\s*\)", php_src):
        errors.append("Translation functions still use legacy 'ChronoArchive' domain")

    for key in ("Requires at least", "Requires PHP", "License", "License URI"):
        a = field(header, key, php_header=True)
        b = field(readme, key)
        if a and b and a != b:
            errors.append(f"{key}: header {a!r} does not match readme {b!r}")

    version = field(header, "Version", php_header=True)
    stable = field(readme, "Stable tag")
    if version and stable and stable not in (version, "trunk"):
        errors.append(f"Stable tag {stable!r} differs from Version {version!r}")

    tags = [tag.strip() for tag in field(readme, "Tags").split(",") if tag.strip()]
    if len(tags) > 5:
        errors.append(f"Too many tags ({len(tags)}); maximum is 5")

    # Header is bounded by an empty line; the following paragraph is the
    # short description, not the complete == Description == section.
    split = re.split(r"\r?\n\s*\r?\n", readme, maxsplit=1)
    if len(split) < 2:
        errors.append("Missing short description after readme header")
    else:
        first_line = split[1].splitlines()[0].strip()
        if not first_line or first_line.startswith("=="):
            errors.append("Missing WordPress.org short description")
        elif len(first_line) > 150:
            errors.append(f"Short description too long ({len(first_line)} > 150)")

    if re.search(r"wordpress\.org/support/plugin/anual-archive|pluginoven\.com/premium-plugins/archive-pro-matic",
                 php_src, re.IGNORECASE):
        errors.append("Legacy upstream support/promotional links remain in plugin PHP")

    if args.release and (
        not version
        or re.search(r"(?:alpha|beta|dev|rc|preview)", version, re.IGNORECASE)
    ):
        errors.append(
            "RELEASE GATE: a pre-release Version cannot be published as stable"
        )

    if args.release and stable == "trunk":
        errors.append(
            "RELEASE GATE: use a verified release tag, not 'trunk'"
        )

    if errors:
        heading = "RELEASE GATE BLOCKED" if args.release else "METADATA SCHEMA FAILED"
        print(heading + ":")
        for error in errors:
            print("- " + error)
        return 1

    print(
        "Metadata schema OK: plugin header, readme, i18n domain and "
        "minimum versions match."
    )
    if not args.release:
        print(
            "Development check only. Plugin Check, SQL review and release "
            "approval remain separate blockers."
        )
    return 0


if __name__ == "__main__":
    sys.exit(main())
