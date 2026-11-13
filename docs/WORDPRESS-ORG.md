# WordPress.org publication procedure

ChronoArchive has **not** been submitted to WordPress.org, approved by the plugins team, or published in the directory.

1. Confirm that a stable release satisfies `docs/RELEASE-GATES.md` and all actual Plugin Check findings.
2. Keep the main plugin header, `readme.txt`, `Stable tag`, PHP/WP minimum versions and text domain consistent.
3. Confirm the plugin's license is GPL-compatible and preserve fork ancestry and third-party notices.
4. Generate an installable archive with `chronoarchive/` as the root folder and no repository-only test files.
5. Submit that release archive for human review using the official WordPress.org developers form.
6. Address review feedback and follow the SVN repository procedure after acceptance.
7. Coordinate GitHub tags/releases separately from WordPress.org SVN versions.

The repository's public visibility is not WordPress.org approval. Do not create a stable tag merely because syntax tests are green.
