# Security issue #2 · CSS capability and multisite trust boundary

**Branch:** `security/issue-2-css-capabilities`  
**Status:** security hardening candidate, **not yet a release**.

## Finding

The original ChronoArchive settings page uses the WordPress `manage_options` capability. It also lets that user save unrestricted CSS. On WordPress multisite, subsite administrators have `manage_options` but ordinarily lack `edit_css` and `unfiltered_html`. WordPress core deliberately maps `edit_css` to a stronger capability because arbitrary CSS cannot be reliably sanitized.

References:
- https://developer.wordpress.org/reference/functions/map_meta_cap/
- https://wordpress.org/documentation/article/roles-and-capabilities/
- https://make.wordpress.org/core/2016/11/26/extending-the-custom-css-editor/

This is a **policy mismatch in this fork's settings behavior**. No third-party exploitation or specific CVE is claimed.

## Protection introduced

1. Require `current_user_can('edit_css')` before accepting custom CSS edits. Subsite administrators may still update ordinary plugin settings.
2. When an account lacks `edit_css`, preserve the existing CSS value. Ignore submitted CSS, including a forged approval marker.
3. Add a server-controlled `custom_css_approved` option, set only on a save by a CSS-authorized user.
4. **On multisite networks only**, prevent all old CSS values lacking that marker from rendering. A network super administrator must review and save the CSS again. This is an intentional security-first compatibility change.
5. Disable the CSS textarea in the administration page when the current user lacks `edit_css`; explain the constraint.
6. Keep late escaping of CSS markup and use the WordPress Styles API. The approval marker is not a substitute for a CSS sanitizer, but only authorized network users can approve it.

## Compatibility and upgrade semantics

- Existing *single-site* CSS continues to load as before, protecting historical installations, and is sanitized at the HTML boundary.
- Existing *multisite* CSS is not rendered until an authorized account explicitly re-saves it.
- A user who cannot edit CSS can continue using the options form. A safe read-only field is disabled; its previous database value and approval status are retained by `sanitize_settings()`.
- This is a permission check; it does not make arbitrary CSS completely harmless or prevent legitimate CSS from referencing external resources.

## Validation matrix

- PHP mock suite checks legacy unapproved CSS is suppressed in multisite mode; unprivileged writes cannot replace or approve it; authorized save approves it; subsequent ordinary settings saves cannot rewrite it.
- Real WordPress matrix checks the core `edit_css` capability on a privileged single-site administrator and simulates a subsite-level restriction through `map_meta_cap`. The actual settings UI is inspected to ensure the field is disabled.
- A **true multisite installation with network and subsite administrators** remains necessary before a stable release.
- WordPress Plugin Check, browser network checks and a broader policy review remain release gates.

## Rollback and audit notes

An upgrade does not delete CSS. On multisite it is dormant until reapproved; reverting to an old version could cause old CSS to load again. Do not use this patch as a substitute for investigating any previously stored malicious data.

The patch should remain in a draft PR until CI passes and an independent reviewer checks the documented behavior.
