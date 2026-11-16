#!/usr/bin/env python3
"""Prevent reintroduction of direct database SQL in the shipped plugin.

Plugin Check is the authority; this small assertion catches local regressions
before the much slower official WordPress.org action starts.
"""
from pathlib import Path
import re

php = (Path(__file__).resolve().parents[1] / "chronoarchive.php").read_text(encoding="utf-8")
for pattern in (
    r"\$wpdb\s*->\s*(?:get_results|get_col|get_row|get_var|query|insert|update|delete)\s*\(",
    r"\b(?:mysqli_query|mysql_query|PDO)\s*\(",
):
    matches = re.findall(pattern, php)
    assert not matches, "Raw DB execution in distributed runtime: " + repr(matches)
assert "private static function grouped_archive_rows(" in php
assert "new WP_Query(" in php
assert "'chronoarchive_origin'   => 1" in php
print("PASS: no direct SQL sink, grouped archives use native WP_Query with scoping")
