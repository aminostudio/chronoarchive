#!/usr/bin/env python3
"""WordPress.org release metadata gates. This is NOT a security scanner."""
from __future__ import annotations
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
main = root / "chronoarchive.php"
readme = root / "readme.txt"
issues: list[str] = []

if not main.is_file():
    issues.append("chronoarchive.php: runtime source is missing")
else:
    src = main.read_text(encoding="utf-8-sig")
    header = src[:4000]
    for field in ("Plugin Name", "Version", "Requires at least",
                  "Requires PHP", "License", "License URI", "Text Domain"):
        if not re.search(r"(?m)^\s*\*?\s*" + re.escape(field) + r"\s*:\s*\S", header):
            issues.append(f"chronoarchive.php: missing {field} header")
    if not re.search(r"(?m)^\s*\*?\s*Text Domain:\s*chronoarchive\s*$", header):
        issues.append("chronoarchive.php: Text Domain should be 'chronoarchive'")
    if re.search(r"wordpress\.org/support/plugin/anual-archive|pluginoven\.com/premium-plugins/archive-pro-matic", src, re.I):
        issues.append("chronoarchive.php: obsolete upstream support or promotional links")

if not readme.is_file():
    issues.append("readme.txt: missing WordPress.org readme")
else:
    readme_text = readme.read_text(encoding="utf-8-sig")
    for field in ("Contributors", "Requires at least", "Tested up to",
                  "Stable tag", "Requires PHP", "License"):
        if not re.search(r"(?mi)^" + re.escape(field) + r":\s*\S", readme_text):
            issues.append(f"readme.txt: missing '{field}'")
    for heading in ("== Description ==", "== Installation ==", "== Changelog =="):
        if heading not in readme_text:
            issues.append(f"readme.txt: missing '{heading}' section")

if issues:
    print("RELEASE METADATA BLOCKERS:")
    for issue in issues:
        print("-", issue)
    sys.exit(1)

print("Release metadata baseline checks passed. Manual review still required.")
