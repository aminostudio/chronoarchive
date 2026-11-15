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
page.on('pageerror', (error) => javascriptErrors.push(error.message));
page.on('request', (request) => {
  if (request.url().includes('blocked.example.invalid')) {
    offsiteRequests.push(request.url());
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
  pass('Three labelled, unique dropdowns and one script present');

  const yearSelect = dropdowns.first();
  await yearSelect.focus();
  assert.equal(await yearSelect.evaluate((node) => document.activeElement === node), true);
  await yearSelect.press('Enter'); // Empty placeholder is inert.
  assert.equal(page.url(), qaURL, 'Empty dropdown selection must not navigate');
  pass('Dropdown accepts keyboard focus and empty selection is inert');

  // Synthetic options are inserted by the test, not source-site data.
  for (const target of [
    'https://blocked.example.invalid/unsafe',
    'javascript:alert(document.domain)',
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
  assert.deepEqual(offsiteRequests, []);
  pass('Off-origin and javascript: dropdown destinations refused');

  const week = page.locator('a[href*="chrono_week=2019-12-30"]').first();
  assert.equal(await week.count(), 1, 'Cross-year week link generated');
  await Promise.all([
    page.waitForURL((url) => url.searchParams.get('chrono_week') === '2019-12-30'),
    week.click(),
  ]);
  const weekText = await page.locator('body').innerText();
  assert.ok(weekText.includes('CA HTTP Boundary Dec'), 'Week includes December 2019');
  assert.ok(weekText.includes('CA HTTP Boundary Jan'), 'Week includes January 2020');
  pass('Clicking archive link navigates to both years in one ISO week');

  await openQa();
  const decade = page.locator('a[href*="decade=2010"]').first();
  assert.equal(await decade.count(), 1, 'Decade link generated');
  await Promise.all([
    page.waitForURL((url) => url.searchParams.get('decade') === '2010'),
    decade.click(),
  ]);
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
    page.waitForURL((url) => url.href === selectedURL.href),
    selectYear.selectOption(selectedValue),
  ]);
  assert.ok((await page.locator('body').innerText()).includes('CA HTTP Boundary Dec'));
  pass('Real change event loads same-origin yearly archive');

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
