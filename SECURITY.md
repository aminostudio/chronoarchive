# Security policy

## Supported versions

There is currently **no supported stable release**. `2.1.0-beta1` is an experimental LAB baseline, not security certified.

## Reporting vulnerabilities

Please **do not post active exploits in public GitHub issues**. Contact the project maintainer privately using [Amino Studio's contact page](https://aminostudio.fr/). The repository owner should enable GitHub Private Vulnerability Reporting under Settings → Security and then update this section with the dedicated reporting link.

Include the affected version, WordPress/PHP environment, minimal reproduction steps and impact. Remove credentials and personal data from reports.

## Upstream security history

The predecessor Annual Archive has documented XSS issues, including `CVE-2023-0178` and `CVE-2024-33598`. Their presence is a reason to inspect the fork's affected code paths. This file **does not assert** the fork is currently vulnerable or fully patched.

## Required release reviews

- Shortcode attributes, widget input and stored settings sanitized and checked again before output.
- Correct escaping for HTML, attributes, URLs, CSS and other output contexts; no unsanitized inline handlers.
- Restrict DB queries to intended post types/status; examine custom query vars for broadened results.
- Validate capability and nonce handling of settings.
- Test against the official WordPress Plugin Check tool, relevant browser regressions and compatibility matrix.
- Verify licensing and external dependency notices.

Neither this policy nor CI syntax checks constitutes a penetration test.
