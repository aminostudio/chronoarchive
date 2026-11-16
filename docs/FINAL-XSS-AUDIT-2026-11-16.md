# Final source-path security audit: two Annual Archive XSS advisories

Date: 16 November 2026. Reviewed candidate: PR #31, commit
[`a8d4a813b90d5594583543f72e5355a5a0b0eaf7`](https://github.com/aminostudio/chronoarchive/commit/a8d4a813b90d5594583543f72e5355a5a0b0eaf7).
This is a fresh source and execution review, not adoption of the older release
checklist's conclusions. No LAB access or changes, release, tag or merge.

## Conclusion and confidence

**No exploitable inherited stored-XSS path was found in the reviewed candidate.**
The concrete shortcode, widget and CSS injection paths reproduced against the
Annual Archive 1.6.0 source are neutralized by existing ChronoArchive controls.
No runtime patch or version change is warranted by these findings.

For each CVE the defensible disposition is **not reproduced; reviewed inherited
paths mitigated**. This is not an unconditional advisory-specific “unaffected”
certification: the public CNA/researcher records do not disclose an exact field,
payload or patch, and an official byte-verified upstream package could not be
retrieved. A version comparison (`2.1.0 > 1.6.0`) and a clean Plugin Check scan
are not evidence of non-exploitability. This report supplies source-specific
reasons and repeatable tests instead. Maintainers can use this evidence for
release risk acceptance; this audit does not silently grant that approval.

## Sources checked independently

| Source | What it establishes | What it does not establish |
| --- | --- | --- |
| [Patchstack CVE-2023-49847](https://patchstack.com/database/wordpress/plugin/anual-archive/vulnerability/wordpress-annual-archive-plugin-1-6-0-cross-site-scripting-xss-vulnerability) | Annual Archive ≤1.6.0, stored XSS, Contributor privilege; researcher Ngô Thiên An / ancorn_; no official patch | No exact parameter, vulnerable line, payload or patch diff |
| [WPScan CVE-2023-49847](https://wpscan.com/vulnerability/96c8b2d4-9dfd-409a-a83c-a7148fb69c58/) | Contributor+ stored XSS, input/output handling failure | No disclosure-specific reproducer |
| [Patchstack CVE-2024-33598](https://patchstack.com/database/wordpress/plugin/anual-archive/vulnerability/wordpress-annual-archive-plugin-1-6-0-cross-site-scripting-xss-vulnerability-2) | Annual Archive ≤1.6.0, Administrator privilege; researcher Emili Castells; no official patch | Does not identify custom CSS, widgets or another particular field as the confidential exploit |
| [CNA record: CVE-2023-49847](https://github.com/CVEProject/cvelistV5/blob/fdb617bc845ce60916f05b2dd06d67da0db702a4/cves/2023/49xxx/CVE-2023-49847.json) | Patchstack CNA, CWE-79, stored XSS, low required privilege, interaction required; CVSS 6.5 | Broad description only; no additional code-level detail |
| [CNA record: CVE-2024-33598](https://github.com/CVEProject/cvelistV5/blob/fdb617bc845ce60916f05b2dd06d67da0db702a4/cves/2024/33xxx/CVE-2024-33598.json) | Patchstack CNA, CWE-79, stored XSS, high required privilege, interaction required; CVSS 5.9 | Broad description only; no additional code-level detail |

The CNA records have last-update timestamps on 28 April 2026. Patchstack's
publication dates and CVE publication dates differ; neither implies a fix.
Additional searches for both CVE identifiers and their researchers yielded
no verifiable advisory-specific public payload. Wordfence's referenced record
was inaccessible through the web tool. No researcher was contacted and no
private disclosure material was obtained.

### Upstream source provenance

Reviewed [Annual Archive 1.6.0 source at immutable mirror commit `77bdd2f`](https://github.com/common-repository/anual-archive/blob/77bdd2fd5430da66dd5224e4eb9939853bbfc5a4/annual_archive.php).
Its header declares 1.6.0; file SHA256:
`6905e584e985fb6aa72e95d0d520b302d0668d50fadcb117d721f4334c420520`;
Git blob `7a4a0f4551e4e71c288c711dba8974c97befa834`.
Official WordPress.org versioned ZIP, SVN tag and SVN trunk retrieval attempts
returned HTTP 403 from this environment; web-tool SVN retrieval also failed.
Thus this is an immutable **third-party mirror**, not an authenticated official
SVN checksum. Positive controls below establish what those reviewed bytes do.
They do not prove attribution of a given sink to either confidential CVE.

## CVE-2023-49847: contributor-controlled stored XSS

**Published technical cause:** insufficient neutralization of stored content
before HTML output (CWE-79). The precise field is not published.

**Concrete source-derived candidate:** upstream `shortcode()`, lines 423–487,
uses `extract(shortcode_atts(...))` and constructs a container tag with
`esc_attr($tag)`. Attribute escaping does not validate a tag name. The string
`ul onmouseover=alert(1)` has no quote that escaping could neutralize and
produces an actual event attribute when concatenated after `<`. This input is
not labelled the original CVE-2023-49847 PoC. The quoted payload published for
CVE-2023-0178 is a separate advisory and cannot establish the later CVE's path.

**Current protections and exploitability assessment:**

- [`shortcode()`](https://github.com/aminostudio/chronoarchive/blob/a8d4a813b90d5594583543f72e5355a5a0b0eaf7/chronoarchive.php#L819)
  normalizes scalar attributes and selects the container from only `ul`, `ol`,
  `div`, `nav`, `section`, `span`. All three aliases share this callback. An
  injected name falls back to `ul`; no attacker-controlled attribute fragment
  reaches the opening/closing tag.
- `before` and `after` pass through `wp_kses_post()`. `select_text` passes
  through `sanitize_text_field()` and `esc_html()` in option text. Format,
  ordering, post type and archive mode are constrained. Dropdown IDs are
  generated internally and escaped; executable inline dropdown handlers were
  replaced by the static same-origin navigation script.
- [`wp_get_archives_advanced()` final sinks](https://github.com/aminostudio/chronoarchive/blob/a8d4a813b90d5594583543f72e5355a5a0b0eaf7/chronoarchive.php#L779)
  KSES-filter both echo and return output. `option` and `link` use explicit
  allowlists; ordinary/custom markup uses `wp_kses_post()`. Individual post
  titles are stripped to plain text before link assembly (line 772). Existing
  tests include malicious link-filter output and unsafe URL schemes.
- Contributors have neither `manage_options` nor `edit_css`; there is no
  plugin-specific unauthenticated AJAX/REST setting-write handler. Core's
  Settings API performs capability/nonce checks. A contributor cannot reach
  the administrative CSS writer simply by using a shortcode.

**New persisted attack test:** an actual contributor saves nine pending posts
(three aliases × three tag-injection variants). Assertions confirm that the
payload survives WordPress storage unchanged. A separate administrator
publishes each post; a guest then renders the stored shortcode. All outputs
retain real archive links and contain no active payload DOM. Twelve additional
cases cover all aliases and all four output formats with hostile text/wrappers.
These results rule out the reviewed contributor injection path on this SHA;
they are not a claim to have recovered the researcher's private exploit.

## CVE-2024-33598: administrator-controlled stored XSS

**Published technical cause:** stored output not safely neutralized (CWE-79),
with high privilege and user interaction. The precise field is not published.

**Concrete source-derived candidate paths:**

1. Upstream `plugin_head_inject()`, lines 117–123, writes the stored `custom_css`
   verbatim between `<style>` tags. `</style><script>…</script>` exits CSS into
   executable HTML. `admin_init()`, lines 139–142, registers the option without
   a sanitization callback.
2. Upstream widget `update()`, lines 709–713, merges raw settings. Its frontend
   title and link wrappers are emitted without a final safety filter; form
   `before` and `select_text` values at lines 820–822 lack attribute escaping.
   These are independently observed defects, not confirmed CVE field mapping.

**Current protections and exploitability assessment:**

- [`admin_init()` and `sanitize_settings()`](https://github.com/aminostudio/chronoarchive/blob/a8d4a813b90d5594583543f72e5355a5a0b0eaf7/chronoarchive.php#L299)
  register a sanitizer and restrict CSS changes to `edit_css`. A restricted
  administrator cannot overwrite CSS or self-approve inherited CSS by posting
  `custom_css_approved=1`. Other allowed settings remain editable.
- [`safe_custom_css()`](https://github.com/aminostudio/chronoarchive/blob/a8d4a813b90d5594583543f72e5355a5a0b0eaf7/chronoarchive.php#L216)
  rejects nonstrings, limits length, strips HTML and replaces any remaining
  literal `<` with a CSS escape. The HTML tokenizer cannot recognize a closing
  `style` tag without a literal `<`; CSS escapes and `&lt;` do not terminate an
  HTML raw-text style element. The Styles API is used instead of raw echo.
  This boundary applies even to old database values that bypass save-time
  sanitization.
- Multisite suppresses unapproved legacy CSS; real-network tests cover a
  subsite administrator lacking `edit_css`, forged approval and subsequent
  super-administrator approval. CSS remains an intentional capability of
  trusted administrators: this is not a full CSS sanitizer or a restriction
  on CSS resource requests/visual changes, nor support for obsolete browser
  CSS JavaScript execution features.
- [`options_page()`](https://github.com/aminostudio/chronoarchive/blob/a8d4a813b90d5594583543f72e5355a5a0b0eaf7/chronoarchive.php#L947)
  requires `manage_options`, uses the Settings API nonce and outputs saved CSS
  with `esc_textarea()` (line 1062). Stored CSS cannot terminate the textarea.
  The shortcode builder uses `textContent`/form values, not payload `innerHTML`.
- [`ChronoArchive_Widget`](https://github.com/aminostudio/chronoarchive/blob/a8d4a813b90d5594583543f72e5355a5a0b0eaf7/chronoarchive.php#L1270)
  sanitizes updates, escapes form fields, escapes title/placeholder text and
  KSES-filters wrappers and archive output. Raw pre-upgrade settings are safe
  independently of `update()`; the audit deliberately bypasses that sanitizer.

The tested CSS breakouts, raw widget markup and restricted-administrator
writes are therefore not exploitable on this SHA. A default single-site
administrator may already have `unfiltered_html`; that WordPress privilege
is not evidence of a plugin bypass. Restricted administrators and multisite
are explicitly tested rather than relying on that privilege distinction.

## Execution evidence

Local isolated environment: WordPress **7.1.4**, PHP **8.4.27**, MariaDB **11.4**.
Only disposable Docker databases were written. No LAB was accessed.

| Check | Observed result |
| --- | --- |
| Actual mirror 1.6.0 `shortcode(tag=ul onmouseover=alert(1))` | Active event attribute emitted |
| Actual mirror 1.6.0 raw `custom_css` breakout | Actual script element emitted |
| Actual mirror 1.6.0 widget title/wrapper | Active event attribute emitted |
| Actual mirror 1.6.0 widget form `select_text` | Attribute breakout emitted |
| New [stored-XSS path audit](../tests/wp-stored-xss-audit.php) on candidate | **52 passed, 0 failed** |
| Administrator HTTP settings write with invalid nonce | **403**, option unchanged |
| Contributor HTTP settings write using captured administrator nonce | **403**, option unchanged |
| Administrator HTTP settings write with valid nonce and script-bearing CSS | Save succeeds, HTML removed from stored CSS; guest homepage has no injected script |

The 52 checks include real contributor persistence/guest rendering, all three
aliases/four formats, raw inherited widget frontend/form output, six CSS/textarea
breakout variants and restricted-administrator CSS permissions. DOM inspection
checks actual nodes/attributes, not merely the presence of inert payload text;
a positive control verifies that the checker rejects an active event handler.
PHP DOM parsing is not a complete browser implementation. Existing real Chromium
checks cover stored shortcode hover execution and CSS breakout in both permalink
modes; the final audit PR reruns those checks and the four-way PHP/WP matrix.

Local reproduction on a disposable `chronoarchive-ci` WordPress installation:

```sh
wp eval-file wp-content/plugins/chronoarchive/tests/wp-stored-xss-audit.php
```

The workflow runs it after the existing integration suite on WP 6.5.5/PHP 7.2,
WP 6.5.5/PHP 8.2, latest WP/PHP 8.2 and latest WP/PHP 8.4. Tests are not included
in the distributed ZIP. The final PR description records exact CI links/results.
The candidate runtime and its 8-file distribution remain byte-identical to
PR #31: SHA256 `aaea503f86d10797fd7eebc07ecf80bfc3f0b46a45a833ce547c3fe4af4ab655`.

## Remaining limits and release decision

No concrete remaining XSS defect was demonstrated. No existing runtime
protection was bypassed. The findings support a source-path mitigation decision
for both advisory classes. They do not prove that an undisclosed path cannot
exist, authenticate the upstream mirror as the official release, or constitute
an independent third-party certification. If advisory-specific closure is
required, obtain the original disclosure or the researcher's confirmation.
No outreach was performed without authorization.

The LAB acceptance/restorable backup and maintainer publication decision remain
separate. A clean scanner cannot settle this evidence limit. Do not convert
this bounded assessment into a claim that both original CVE PoCs were reproduced.
