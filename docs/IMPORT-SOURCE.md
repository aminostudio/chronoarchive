# Import provenance

The baseline was read from the connected WordPress staging site `https://labo.aminostudio.fr/`, plugin folder `wp-content/plugins/ChronoArchive/`, with WPVibe's read-only source inspection API.

Version observed: `2.1.0-beta1`. Four files were imported with their original line content:

- `chronoarchive.php` (1,014 lines in WPVibe listing).
- `readme.txt` (193 lines).
- `assets/js/archive-dropdown.js` (19 lines).
- `js/widget_form.js` (42 lines; historical permissive copyright notice retained).

The source was transferred via authenticated tools to GitHub Git blobs and committed to branch `develop/import-lab-beta1`. WordPress was **not** modified. This process is a content-level import, not a verified bit-for-bit ZIP export. WPVibe masks recognized secret patterns: no redaction markers were found in the four transferred sources, but checksums from a direct file export remain a release gate.

Baseline source commit: `e658268146cf3c9ac85e253246b763229cec6f00`.

This is intentionally a faithful historical snapshot. Known defects and documentation mismatches belong in reviewable follow-up fixes, not silent changes to the baseline.
