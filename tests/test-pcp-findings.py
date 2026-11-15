#!/usr/bin/env python3
"""Contract tests for the draft-only official Plugin Check regression guard."""
from __future__ import annotations

import json
import runpy

scanner = runpy.run_path("scripts/verify-pcp-findings.py")
evaluate = scanner["evaluate"]
findings_from = scanner["findings_from"]
parse_report = scanner["parse_report"]
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
assert parse_report("FILE: chronoarchive.php\n" + json.dumps(expected) + "\n") == {
    "chronoarchive.php": expected
}, "Plugin Check's native FILE: stream must parse"
assert parse_report('{"chronoarchive.php":' + json.dumps(expected) + '}') == {
    "chronoarchive.php": expected
}, "Plain JSON output must still parse"
assert parse_report("") == {}, "Empty clean scanner output must be accepted"
for malformed in ("FILE: chronoarchive.php\nnot JSON\n", "Some error output", "FILE:"):
    try:
        parse_report(malformed)
    except ValueError:
        pass
    else:
        raise AssertionError("Malformed Plugin Check file stream was accepted")
print("PASS: scanner JSON + FILE-stream formats, known, new, duplicate and malformed report guards")
