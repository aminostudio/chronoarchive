/**
 * Real Chromium browser checks against a DISPOSABLE WordPress CI instance.
 *
 * Set CHRONO_BROWSER_PAGE_ID to the fixture page created in CI.
 * The test intentionally does not access the protected Amino LAB.
 */
import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const base = process.env.CHRONO_BROWSER_BASE || 'http://127.0.0.1:8093';
const pageId = Number(process.env.CHRONO_BROWSER_PAGE_ID || 0);
assert.ok(Number.isInteger(pageId) && pageId > 0, 'CI browser QA page ID required');
assert.equal(new URL(base).hostname, '127.0.0.1', 'Browser tests must be localhost-only');

const qaURL = new URL('/?page_id=' + pageId, base).href;
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
const javascriptErrors = [];
const offsiteRequests = [];
const unexpectedDialogs = [];
const dropdownResponses = [];
page.on('pageerror', (error) => javascriptErrors.push(error.message));
// A broken javascript: check must fail rather than hang behind an alert.
page.on('dialog', async (dialog) => {
  unexpectedDialogs.push(dialog.type() + ': ' + dialog.message());
  await dialog.dismiss();
});
// The browser suite only targets the local disposable WordPress instance.
// Abort and record EVERY external request, not merely a single test hostname.
await page.route('**/*', async (route) => {
  const requestedURL = new URL(route.request().url());
  if (requestedURL.origin !== new URL(base).origin) {
    offsiteRequests.push(route.request().url());
    await route.abort();
    return;
  }
  await route.continue();
});
page.on('response', (response) => {
  if (response.url().includes('/assets/js/archive-dropdown.js')) {
    dropdownResponses.push({status: response.status(), mime: response.headers()['content-type'] || ''});
  }
});
page.setDefaultTimeout(15000);

async function openQa() {
  const response = await page.goto(qaURL, { waitUntil: 'load' });
  assert.equal(response?.status(), 200, 'QA page must return HTTP 200');
  await page.locator('select.chronoarchive-select').first().waitFor();
}

function pass(message) {
  console.log('PASS: ' + message);
}

try {
  await openQa();
  const dropdowns = page.locator('select.chronoarchive-select');
  assert.equal(await dropdowns.count(), 3, 'Three shortcode dropdowns render');
  const identities = await dropdowns.evaluateAll((nodes) => nodes.map((node) => ({
    id: node.id,
    label: document.querySelector('label[for="' + node.id + '"]')?.textContent?.trim(),
    options: node.options.length,
  })));
  assert.equal(new Set(identities.map((item) => item.id)).size, 3);
  assert.ok(identities.every((item) => item.id && item.label === 'Choose archive'));
  assert.ok(identities.every((item) => item.options >= 2));
  assert.equal(await page.getByRole('combobox', { name: 'Choose archive' }).count(), 3);
  const dropdownAsset = page.locator('script[src*="archive-dropdown.js"]');
  assert.equal(await dropdownAsset.count(), 1, 'Plugin JS must be enqueued on page');
  assert.ok(dropdownResponses.some((response) =>
    response.status === 200 && /javascript/i.test(response.mime)),
  'Dropdown asset must actually load as JavaScript, not HTML from a router');
  pass('Three labelled, unique dropdowns and one script present');

  // Reproduce the public CVE-2023-0178 stored shortcode payload in actual
  // WordPress-rendered HTML. Test active DOM attributes and mouse interaction,
  // not only escaped HTML strings returned by PHP integration functions.
  const xssFixture = page.locator('#chrono-xss-fixture');
  assert.equal(await xssFixture.count(), 1, 'Stored XSS fixture must render');
  const xssLists = xssFixture.locator('ul');
  assert.equal(await xssLists.count(), 6, 'Three aliases and both XSS tag variants render safe lists');
  assert.ok(await xssFixture.locator('a').count() >= 6, 'Each safe list preserves archive links');
  assert.equal(await xssFixture.locator('[onmouseover], [onerror], [onclick], script, svg, iframe, a[href^="javascript:"]').count(), 0,
    'No executable markup or event handlers inside injected shortcode render');
  await xssLists.first().hover();
  await xssLists.last().hover();
  assert.deepEqual(unexpectedDialogs, [], 'Hovering quoted and unquoted tag injection must not execute alert');
  pass('CVE-2023-0178 stored shortcode PoC neutralized in browser DOM');

  // A historic administrator CSS option can contain raw HTML from an
  // unpatched installation. The test stores a literal </style><script>
  // in the disposable database, bypassing the current save sanitizer
  // intentionally, and verifies the frontend's separate output boundary.
  const customStyle = page.locator('style#chronoarchive-custom-inline-css');
  assert.equal(await customStyle.count(), 1, 'Legacy CSS is enqueued via WordPress style API');
  assert.ok((await customStyle.textContent()).includes('.chrono-css-check'),
    'Expected harmless part of legacy CSS remains available');
  assert.equal(await page.locator('script#ca-admin-css-xss').count(), 0,
    'Legacy saved CSS cannot break out into a live script element');
  assert.deepEqual(unexpectedDialogs, [], 'Legacy CSS payload must not execute JavaScript');
  pass('Historic administrator CSS HTML breakout neutralized in browser DOM');

  // WordPress redirects /?page_id=ID to the canonical pretty URL once
  // rewrites are enabled. A no-op selection must preserve that actual URL.
  const canonicalQaURL = page.url();
  const yearSelect = dropdowns.first();
  await yearSelect.focus();
  assert.equal(await yearSelect.evaluate((node) => document.activeElement === node), true);
  await yearSelect.press('Enter'); // Empty placeholder is inert.
  assert.equal(page.url(), canonicalQaURL, 'Empty dropdown selection must not navigate');
  pass('Dropdown accepts keyboard focus and empty selection is inert');

  // Synthetic options are inserted by the test, not source-site data.
  for (const target of [
    // Absolute external URL and scheme-relative URL.
    'https://blocked.example.invalid/unsafe',
    '//blocked.example.invalid/unsafe',
    // A familiar-looking localhost userinfo prefix must not fool origin checks.
    'http://127.0.0.1:8093@blocked.example.invalid/unsafe',
    // A different port on the same host is also a different origin.
    'http://127.0.0.1:8094/unsafe',
    // Non-HTTP protocols must never navigate or execute JavaScript.
    'javascript:alert(document.domain)',
    'java' + String.fromCharCode(9) + 'script:alert(document.domain)',
    'data:text/html,<script>alert(document.domain)</script>',
  ]) {
    const before = page.url();
    await dropdowns.first().evaluate((select, value) => {
      select.add(new Option('Untrusted destination', value));
      select.value = value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }, target);
    await page.waitForTimeout(350);
    assert.equal(page.url(), before, 'Unsafe dropdown navigation refused');
  }
  assert.deepEqual(offsiteRequests, [], 'No external network requests allowed');
  assert.deepEqual(unexpectedDialogs, [], 'javascript: must not execute');
  pass('Off-origin and javascript: dropdown destinations refused');

  const week = page.locator('a[href*="chrono_week=2019-12-30"]').first();
  assert.equal(await week.count(), 1, 'Cross-year week link generated');
  await Promise.all([
    page.waitForURL((url) => url.searchParams.get('chrono_week') === '2019-12-30', { waitUntil: 'load' }),
    week.click(),
  ]);
  await page.waitForLoadState('load');
  const weekText = await page.locator('body').innerText();
  assert.ok(weekText.includes('CA HTTP Boundary Dec'),
    'Week includes December 2019; URL=' + page.url() + ', text=' + weekText.slice(0, 1200));
  assert.ok(weekText.includes('CA HTTP Boundary Jan'),
    'Week includes January 2020; URL=' + page.url() + ', text=' + weekText.slice(0, 1200));
  pass('Clicking archive link navigates to both years in one ISO week');

  await openQa();
  const decade = page.locator('a[href*="decade=2010"]').first();
  assert.equal(await decade.count(), 1, 'Decade link generated');
  await Promise.all([
    page.waitForURL((url) => url.searchParams.get('decade') === '2010', { waitUntil: 'load' }),
    decade.click(),
  ]);
  await page.waitForLoadState('load');
  const decadeText = await page.locator('body').innerText();
  assert.ok(decadeText.includes('CA HTTP Boundary Dec'));
  assert.ok(!decadeText.includes('CA HTTP Boundary Jan'), '2020 excluded from 2010 decade');
  pass('Clicking decade link excludes next-decade posts');

  await openQa();
  const selectYear = page.locator('select.chronoarchive-select').first();
  const selectedValue = await selectYear.evaluate((node) => {
    const match = [...node.options].find((option) => /\b2019\b/.test(option.textContent));
    return match?.value ?? null;
  });
  assert.ok(selectedValue, '2019 year option available');
  const selectedURL = new URL(selectedValue, qaURL);
  assert.equal(selectedURL.origin, new URL(qaURL).origin);
  await Promise.all([
    page.waitForURL((url) => url.href === selectedURL.href, { waitUntil: 'load' }),
    selectYear.selectOption(selectedValue),
  ]);
  await page.waitForLoadState('load');
  assert.ok((await page.locator('body').innerText()).includes('CA HTTP Boundary Dec'));
  pass('Real change event loads same-origin yearly archive');

  // Both legacy shortcode aliases must navigate using their own dropdowns,
  // not merely render labels or inherit another select's event handling.
  for (const scenario of [
    { index: 1, optionText: /December 2019/i, fixture: 'CA HTTP Boundary Dec', name: '[archives] monthly dropdown' },
    { index: 2, optionText: /\b2019\b/, fixture: 'CA HTTP Boundary Dec', name: '[arcpromat] yearly dropdown' },
  ]) {
    await openQa();
    const aliasSelect = page.locator('select.chronoarchive-select').nth(scenario.index);
    const aliasOption = await aliasSelect.evaluate((node, regexSource) => {
      const pattern = new RegExp(regexSource.source, regexSource.flags);
      const match = [...node.options].find((option) => pattern.test(option.textContent));
      return match?.value ?? null;
    }, { source: scenario.optionText.source, flags: scenario.optionText.flags });
    assert.ok(aliasOption, scenario.name + ' has a matching option');
    const destination = new URL(aliasOption, qaURL);
    assert.equal(destination.origin, new URL(qaURL).origin, scenario.name + ' remains same-origin');
    await Promise.all([
      page.waitForURL((url) => url.href === destination.href, { waitUntil: 'load' }),
      aliasSelect.selectOption(aliasOption),
    ]);
    assert.ok((await page.locator('body').innerText()).includes(scenario.fixture),
      scenario.name + ' resolves the expected published post');
    pass(scenario.name + ' navigates in real Chromium');
  }

  for (const [url, kind] of [
    ['/?year=2024&chrono_week=2024-02-31', 'malformed ISO week'],
    ['/?year=2010&decade=2015', 'non-boundary decade'],
  ]) {
    const response = await page.goto(new URL(url, base).href);
    assert.equal(response?.status(), 404, kind + ' must return HTTP 404');
    pass(kind + ': HTTP 404');
  }

  assert.deepEqual(javascriptErrors, [], 'No uncaught browser JavaScript errors');
  pass('Chromium runtime generated no uncaught errors');
  console.log('BROWSER RESULT: Chromium archive QA passed');
} finally {
  await browser.close();
}
