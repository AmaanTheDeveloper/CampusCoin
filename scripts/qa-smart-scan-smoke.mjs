/**
 * One-off smoke: document upload on /transactions after Smart Scan fix.
 */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
);
const fixture = path.join(__dirname, 'fixtures', 'qa-receipt-smoke.png');
fs.mkdirSync(path.dirname(fixture), { recursive: true });
fs.writeFileSync(fixture, png);

const notes = [];
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
page.on('console', (msg) => {
    if (msg.type() === 'error') {
        notes.push(`console:${msg.text()}`);
    }
});

try {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[type=email]', 'test@example.com');
    await page.fill('input[type=password]', 'password');
    await Promise.all([
        page.waitForURL((u) => !u.pathname.includes('/login')),
        page.click('button[type=submit]'),
    ]);

    await page.goto(`${BASE}/transactions`, { waitUntil: 'networkidle' });
    notes.push('transactions_loaded');

    const addBtn = page.getByRole('link', { name: /add transaction/i }).or(page.getByRole('button', { name: /add transaction/i }));
    await addBtn.first().waitFor({ timeout: 8000 });
    notes.push('manual_add_visible');

    await page.getByTestId('smart-scan-trigger').first().click();
    await page.getByRole('heading', { name: 'Smart Scan' }).waitFor({ timeout: 8000 });
    await page.getByTestId('smart-scan-mode-document').click();

    const responsePromise = page.waitForResponse(
        (r) => r.url().includes('/document-scan/process') && r.request().method() === 'POST',
        { timeout: 90000 },
    );

    const input = page.locator('input[type=file]').first();
    await input.setInputFiles(fixture);
    const response = await responsePromise;
    const json = await response.json().catch(() => null);
    notes.push(`status:${response.status()}`);
    notes.push(`error_code:${json?.error_code ?? 'none'}`);
    notes.push(`message:${json?.message ?? 'none'}`);

    await page.waitForTimeout(800);
    const bodyText = await page.locator('[role="dialog"]').innerText();
    notes.push(`dialog:${bodyText.replace(/\s+/g, ' ').slice(0, 280)}`);

    const hasGeneric = /We couldn't process this document/i.test(bodyText);
    const hasRateLimit = /temporarily unavailable/i.test(bodyText) || json?.error_code === 'OCR_RATE_LIMITED';
    const hasReview = /merchant|amount|confirm/i.test(bodyText) && json?.ok === true;

    if (hasGeneric) {
        throw new Error('Generic process-this-document message still shown');
    }
    if (!hasRateLimit && !hasReview) {
        throw new Error(`Unexpected Smart Scan outcome: ${JSON.stringify(json)}`);
    }

    console.log(JSON.stringify({ ok: true, notes, hasRateLimit, hasReview }, null, 2));
} catch (e) {
    console.log(JSON.stringify({ ok: false, error: String(e), notes }, null, 2));
    process.exitCode = 1;
} finally {
    await browser.close();
}
