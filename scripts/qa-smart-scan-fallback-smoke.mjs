/**
 * Browser smoke: Gemini 429 → Tesseract fallback → review (no unavailable banner).
 */
import { chromium } from 'playwright';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const fixture = path.join(__dirname, 'fixtures', 'qa-receipt-ocr.png');

const notes = [];
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });

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
        { timeout: 120000 },
    );

    await page.locator('input[type=file]').first().setInputFiles(fixture);
    const response = await responsePromise;
    const json = await response.json().catch(() => null);
    notes.push(`status:${response.status()}`);
    notes.push(`ok:${json?.ok}`);
    notes.push(`error_code:${json?.error_code ?? 'none'}`);
    notes.push(`merchant:${json?.extraction?.merchant ?? 'none'}`);
    notes.push(`amount:${json?.extraction?.amount ?? 'none'}`);

    await page.waitForTimeout(1000);
    const bodyText = await page.locator('[role="dialog"]').innerText();
    notes.push(`dialog:${bodyText.replace(/\s+/g, ' ').slice(0, 320)}`);

    const unavailable = /temporarily unavailable/i.test(bodyText);
    const generic = /We couldn't process this document/i.test(bodyText);
    const review = json?.ok === true && Boolean(json?.scan_token);

    if (!review) {
        throw new Error(`Expected review payload, got: ${JSON.stringify(json)}`);
    }
    if (unavailable || generic) {
        throw new Error('Unavailable/generic error shown despite successful fallback');
    }

    console.log(JSON.stringify({ ok: true, notes, review: true }, null, 2));
} catch (e) {
    console.log(JSON.stringify({ ok: false, error: String(e), notes }, null, 2));
    process.exitCode = 1;
} finally {
    await browser.close();
}
