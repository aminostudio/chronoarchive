#!/usr/bin/env python3
"""Exercise the real stable packaging scripts on a disposable metadata fixture.

The fixture is not a release candidate and never gets uploaded or tagged.
It ensures that a stable build can pass the gating tools, while incomplete
readme and prerelease metadata cannot.
"""
from __future__ import annotations

from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import zipfile

SOURCE = Path(__file__).resolve().parents[1]
COPIED_FILES = (
    "chronoarchive.php",
    "LICENSE",
    "assets/js/archive-dropdown.js",
    "assets/js/admin-settings.js",
    "assets/css/admin-settings.css",
    "js/widget_form.js",
    "docs/NOTICE.md",
    "scripts/check-release-metadata.py",
    "scripts/build-wporg-zip.py",
)


def run(root: Path, script: str, *args: str, success: bool) -> None:
    result = subprocess.run(
        [sys.executable, str(root / script), *args],
        cwd=root,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        check=False,
    )
    if (result.returncode == 0) != success:
        raise AssertionError(
            f"{script} {args}: expected success={success}, "
            f"exit={result.returncode}\n{result.stdout}"
        )


def substitute_once(text: str, pattern: str, replacement: str) -> str:
    new, count = re.subn(
        pattern, lambda m: m.group(1) + replacement + (m.group(2) if m.lastindex == 2 else ""),
        text, flags=re.MULTILINE,
    )
    if count != 1:
        raise AssertionError(f"Expected one version header/constant match: {pattern}")
    return new


def main() -> None:
    old_php = (SOURCE / "chronoarchive.php").read_text(encoding="utf-8")
    old_readme = (SOURCE / "readme.txt").read_text(encoding="utf-8")
    version_match = re.search(r"(?m)^Version:\s*([0-9]+\.[0-9]+\.[0-9]+)(?:-[a-z0-9.]+)?\s*$", old_php)
    if not version_match:
        raise AssertionError("Source version missing or malformed")
    stable = version_match.group(1)
    with tempfile.TemporaryDirectory(prefix="chrono-stable-gate-") as directory:
        root = Path(directory)
        for name in COPIED_FILES:
            dest = root / name
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(SOURCE / name, dest)
        php = substitute_once(
            old_php, r"^(Version:\s*)[^\r\n]+$", stable
        )
        php = substitute_once(
            php, r"^(\s*const\s+VERSION\s*=\s*')[^']+(';)$", stable
        )
        (root / "chronoarchive.php").write_text(php, encoding="utf-8")
        header = old_readme.split("\n\n", 1)[0]
        header = re.sub(
            r"(?m)^Stable tag:\s*[^\r\n]+$",
            lambda _: "Stable tag: " + stable,
            header,
        )
        readme = header + f"""

Chronological archive navigation for WordPress posts and public post types.

== Description ==

ChronoArchive helps visitors browse content by year, month, week, day and decade.

== Installation ==

Install this plugin ZIP, activate it, then add a [chronoarchive] shortcode.

== Frequently Asked Questions ==

= Who maintains it? =

Amino Studio.

== Changelog ==

= {stable} =

First reviewed public release.

== Upgrade Notice ==

= {stable} =

Update ChronoArchive after backing up your site.
"""
        (root / "readme.txt").write_text(readme, encoding="utf-8")

        run(root, "scripts/check-release-metadata.py", "--release", success=True)
        bundle = root / "release-test.zip"
        run(
            root, "scripts/build-wporg-zip.py",
            "--release", "--output", str(bundle), success=True,
        )
        with zipfile.ZipFile(bundle) as archive:
            assert archive.testzip() is None
            names = archive.namelist()
            assert len(names) == 8
            assert all(name.startswith("chronoarchive/") for name in names)
            assert "chronoarchive/chronoarchive.php" in names

        # A stale "development beta" public description must block stable.
        bad_readme = readme.replace(
            "== Installation ==",
            "Development beta not yet recommended for production.\n\n== Installation ==",
        )
        (root / "readme.txt").write_text(bad_readme, encoding="utf-8")
        run(root, "scripts/check-release-metadata.py", "--release", success=False)

        # Restore a clean readme, then prove matching beta metadata cannot
        # pass either of the two stable-only validation/build commands.
        beta = stable + "-beta99"
        (root / "readme.txt").write_text(
            readme.replace("Stable tag: " + stable, "Stable tag: " + beta),
            encoding="utf-8",
        )
        (root / "chronoarchive.php").write_text(
            php.replace("Version: " + stable, "Version: " + beta, 1)
               .replace("const VERSION = '" + stable, "const VERSION = '" + beta, 1),
            encoding="utf-8",
        )
        run(root, "scripts/check-release-metadata.py", "--release", success=False)
        run(
            root, "scripts/build-wporg-zip.py",
            "--release", "--output", str(root / "never-upload.zip"), success=False,
        )
        assert not (root / "never-upload.zip").exists()

    print("PASS: stable ZIP builds; stale beta copy and prerelease metadata are blocked")


if __name__ == "__main__":
    main()
