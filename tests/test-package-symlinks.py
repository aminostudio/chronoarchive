#!/usr/bin/env python3
"""Ensure neither distributable ZIP builder can follow symlinked source paths.

This runs in a temporary directory and NEVER changes the source repository.
It protects against accidental release packaging of out-of-tree files through
a symlinked assets, js or docs directory.
"""
from __future__ import annotations

import runpy
import shutil
from pathlib import Path
import tempfile

ROOT = Path(__file__).resolve().parents[1]
SCRIPTS = (
    ("scripts/build-wporg-zip.py", "RUNTIME", "build"),
    ("scripts/build-lab-zip.py", "RUNTIME_FILES", "verify_source"),
)


def run_fixture(relative_script: str, manifest_name: str, entrypoint: str) -> None:
    namespace = runpy.run_path(str(ROOT / relative_script))
    original = namespace[entrypoint]
    manifest = namespace[manifest_name]
    with tempfile.TemporaryDirectory(prefix="chrono-package-guard-") as directory:
        scratch = Path(directory)
        working = scratch / "plugin"
        working.mkdir()
        for name in set(manifest) | {"chronoarchive.php", "readme.txt"}:
            target = working / name
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(ROOT / name, target)

        globals_ = original.__globals__
        previous_root = globals_["ROOT"]
        globals_["ROOT"] = working
        try:
            if entrypoint == "build":
                output = scratch / "normal.zip"
                original(output, False)
                assert output.is_file(), "Baseline WP.org QA ZIP must build"
            else:
                assert len(original()) == len(manifest), "Baseline LAB manifest must load"

            # Replace a real assets/css directory with a symlink to
            # another directory that has byte-identical file contents.
            # The original faulty check accepted this because both
            # resolved parent paths were equal.
            original_dir = working / "assets" / "css"
            outside_dir = scratch / "outside-css"
            shutil.copytree(original_dir, outside_dir)
            shutil.rmtree(original_dir)
            original_dir.symlink_to(outside_dir, target_is_directory=True)
            try:
                if entrypoint == "build":
                    original(scratch / "forbidden.zip", False)
                else:
                    original()
            except ValueError as error:
                assert "Symlinked package parent" in str(error), str(error)
            else:
                raise AssertionError(relative_script + " accepted a linked ancestor")
            assert not (scratch / "forbidden.zip").exists(), "Invalid archive must not be emitted"
        finally:
            globals_["ROOT"] = previous_root
    print("PASS: " + relative_script + " rejects external symlink directories")


for script, manifest_name, entrypoint in SCRIPTS:
    run_fixture(script, manifest_name, entrypoint)
