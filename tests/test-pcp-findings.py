#!/usr/bin/env python3
"""Contract tests for the draft-only official Plugin Check regression guard."""
from __future__ import annotations

from collections import Counter
import runpy

scanner = runpy.run_path("scripts/verify-pcp-findings.py")
evaluate = scanner["evaluate"]
findings_from = scanner["findings_from"]
expected = [
    {"type": "ERROR", "code": "PluginCheck.Security.DirectDB.UnescapedDBParameter"},
    {"type": "ERROR", "code": "WordPress.DB.PreparedSQL.NotPrepared"},
    {"type": "WARNING", "code": "WordPress.DB.DirectDatabaseQuery.DirectQuery"},
]
known, additional = evaluate({"chronoarchive.php": expected})
assert len(known) == 3 and not additional, "Known SQL baseline not recognized"
assert evaluate({}) == ([], []), "Clean scanner output must pass"
assert evaluate({"chronoarchive.php": expected + [
    {"type": "ERROR", "code": "Unrelated.NewInjection"}
]})[1], "New SQL finding must fail"
assert evaluate({"readme.txt": [expected[0]]})[1], "Known code on new file must fail"
assert evaluate({"chronoarchive.php": [expected[0], expected[0]]})[1], "Duplicate must fail"
assert evaluate({"chronoarchive.php": [
    {"type": "WARNING", "code": expected[0]["code"]}
]})[1], "Changed severity must be reviewed"
for malformed in ([], {"chronoarchive.php": {"code": "x"}}, {"x.php": [42]}):
    try:
        findings_from(malformed)
    except ValueError:
        pass
    else:
        raise AssertionError("Malformed scanner JSON was incorrectly accepted")
print("PASS: scanner baseline, clean, new, duplicate, path and malformed JSON checks")
