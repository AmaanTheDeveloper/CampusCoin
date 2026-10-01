/**
 * Browser QA — Smart Scan modes (card auto-detect UI + manual bill path).
 *
 * Requires DOCUMENT_SCAN_FAKE=true on the running app (non-production).
 */
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { writeQaCardPng } from './generate-qa-card-png.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const results = [];

function record(name, status, detail = '') {
    results.push({ name, status, detail });
    console.log(`[${status}] ${name}${detail ? ` — ${detail}` : ''}`);
}

function php(args) {
    return execSync(`php scripts/qa-smart-scan-setup.php ${args}`, { encoding: 'utf8' }).trim();
}

function tinyPngPath() {
    const out = path.join(__dirname, 'fixtures', 'qa-receipt.png');
    fs.mkdirSync(path.dirname(out), { recursive: true });
    const png = Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        'base64',
    );
    fs.writeFileSync(out, png);
    return out;
}

function cardPngPath() {
    const out = path.join(__dirname, 'fixtures', 'qa-card.png');
    writeQaCardPng(out);
    return out;
}

async function login(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[type=email]', 'test@example.com');
    await page.fill('input[type=password]', 'password');
    await Promise.all([page.waitForURL((u) => !u.pathname.includes('/login')), page.click('button[type=submit]')]);
}

async function openSmartScan(page) {
    await page.goto(`${BASE}/dashboard`, { waitUntil: 'networkidle' });
    const trigger = page.getByTestId('smart-scan-trigger').first();
    await trigger.waitFor({ timeout: 10000 });
    await trigger.click();
    await page.getByRole('heading', { name: 'Smart Scan' }).waitFor({ timeout: 8000 });
}

async function main() {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    const receiptFixture = tinyPngPath();
    const cardFixture = cardPngPath();

    try {
        php('teardown');
        console.log(php('setup'));

        await login(page);
        await openSmartScan(page);
        record('Dashboard Smart Scan CTA + dialog', 'PASS');

        const cardMode = page.getByTestId('smart-scan-mode-card');
        const docMode = page.getByTestId('smart-scan-mode-document');
        await cardMode.waitFor();
        await docMode.waitFor();
        record('Mode picker shows Card and Bill/Receipt', 'PASS');

        // --- Card path ---
        await cardMode.click();
        await page.getByTestId('smart-scan-card-status').waitFor({ timeout: 15000 });
        const statusText = (await page.getByTestId('smart-scan-card-status').innerText()).trim();
        const placeOrFail =
            /Place your card|Move|couldn't detect|Camera unavailable|Upload card|Hold steady|Improve lighting|Reduce glare|Adjust your card|Card detected|Card moved/i.test(
                statusText,
            );
        record('Card camera shows guidance (no premature OCR)', placeOrFail ? 'PASS' : 'FAIL', statusText);

        const frame = page.getByTestId('smart-scan-card-frame');
        const uiState = (await frame.getAttribute('data-ui-state').catch(() => null)) ?? 'missing';
        record(
            'Card frame not capturing immediately',
            uiState !== 'capturing' && uiState !== 'countdown' ? 'PASS' : 'FAIL',
            `uiState=${uiState}`,
        );

        const cardUpload = page.getByTestId('smart-scan-card-upload');

        // Random/tiny image must be rejected by shared client validator (no process).
        await cardUpload.setInputFiles(receiptFixture);
        const rejectAlert = page.getByTestId('smart-scan-card-error');
        let rejected = false;
        try {
            await rejectAlert.waitFor({ state: 'visible', timeout: 5000 });
            const rejectText = (await rejectAlert.innerText()).trim();
            rejected = /Invalid card/i.test(rejectText);
            record('Tiny/non-card upload rejected client-side', rejected ? 'PASS' : 'FAIL', rejectText);
        } catch {
            const jumpedToReview = await page.getByText(/Card detected successfully/i).isVisible().catch(() => false);
            record(
                'Tiny/non-card upload rejected client-side',
                'FAIL',
                jumpedToReview ? 'processed without client reject' : 'no error alert',
            );
            // Recover to card camera if we accidentally processed.
            if (jumpedToReview) {
                await page.getByRole('button', { name: 'Done' }).click();
                await page.waitForTimeout(400);
                await openSmartScan(page);
                await page.getByTestId('smart-scan-mode-card').click();
                await page.getByTestId('smart-scan-card-status').waitFor({ timeout: 15000 });
            }
        }

        // Card-like fixture → process OK via fake extractor
        const expensesBefore = Number(php('count'));
        const cardUpload2 = page.getByTestId('smart-scan-card-upload');
        await cardUpload2.setInputFiles(cardFixture);

        await page.getByText(/Card detected successfully/i).waitFor({ timeout: 20000 });
        const masked = await page.getByText(/•••• •••• •••• 4521/).isVisible();
        record('Card review shows masked last4', masked ? 'PASS' : 'FAIL');

        await page.getByRole('button', { name: 'Done' }).click();
        await page.waitForTimeout(400);
        const expensesAfterCard = Number(php('count'));
        record(
            'Card Done creates no transaction',
            expensesAfterCard === expensesBefore ? 'PASS' : 'FAIL',
            `before=${expensesBefore} after=${expensesAfterCard}`,
        );

        // --- Bill/Receipt manual upload path ---
        await openSmartScan(page);
        await page.getByTestId('smart-scan-mode-document').click();
        await page.getByRole('button', { name: /Upload image/i }).click();
        await page.locator('input[type=file]').setInputFiles(receiptFixture);

        await page.getByLabel('Amount').waitFor({ timeout: 20000 });
        const amount = await page.getByLabel('Amount').inputValue();
        const desc = await page.getByLabel('Description').inputValue();
        record(
            'Bill review prefilled from extraction',
            amount === '42.00' && /Campus Cafe/i.test(desc) ? 'PASS' : 'FAIL',
            `amount=${amount} desc=${desc}`,
        );

        const cat = page.locator('#smart-scan-category');
        if (!(await cat.inputValue())) {
            await cat.selectOption(php('food-id'));
        }

        await page.getByRole('button', { name: /Add transaction/i }).click();
        await page.getByText(/Transaction added successfully/i).waitFor({ timeout: 15000 });
        record('Bill confirm shows success', 'PASS');

        await page.waitForTimeout(1200);
        const count = php('count');
        record('Bill transaction persisted', Number(count) >= 1 ? 'PASS' : 'FAIL', `count=${count}`);

        await page.goto(`${BASE}/dashboard`, { waitUntil: 'networkidle' });
        const again = await page.getByTestId('smart-scan-trigger').first().isVisible();
        record('CTA still present after reload', again ? 'PASS' : 'FAIL');
    } catch (e) {
        record('Unhandled error', 'FAIL', String(e?.message || e));
    } finally {
        php('teardown');
        await browser.close();
        const failed = results.filter((r) => r.status === 'FAIL').length;
        const passed = results.filter((r) => r.status === 'PASS').length;
        console.log(`\nSmart Scan Modes QA: ${passed} passed, ${failed} failed, ${results.length} total`);
        process.exit(failed > 0 ? 1 : 0);
    }
}

main();
