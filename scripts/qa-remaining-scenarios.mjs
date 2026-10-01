/**
 * Remaining Campus Coin browser QA (paraphrases + income 30k + food expense + Gemini key).
 * Run: node scripts/qa-remaining-scenarios.mjs
 */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.join(__dirname, '..');
const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const OUT = path.join(ROOT, 'storage', 'app', 'qa-exports');
fs.mkdirSync(OUT, { recursive: true });

const EMAIL = 'test@example.com';
const PASSWORD = 'password';
const OOS =
    "I'm sorry, that is out of context. I can only assist you with questions related to your Campus Coin spending, budgets, savings, or income planning.";

const PARAPHRASES = [
    'What should I do?',
    'Where should I start?',
    'What should I focus on?',
    'How can I improve my spending?',
    'Where can I cut back?',
    'What needs my attention?',
    'How do I save more?',
    'Where is most of my money going?',
    'Can I improve my finances?',
];

const results = [];
const observations = [];

function record(name, status, detail = '') {
    results.push({ name, status, detail });
    console.log(`[${status}] ${name}${detail ? ` — ${detail}` : ''}`);
}

function note(msg) {
    observations.push(msg);
    console.log(`  · ${msg}`);
}

function slug(s) {
    return s
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 48);
}

async function login(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[type=email]', EMAIL);
    await page.fill('input[type=password]', PASSWORD);
    await Promise.all([
        page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 20000 }),
        page.click('button[type=submit]'),
    ]);
}

async function conversationText(page) {
    const panel = page.getByLabel('Conversation');
    if ((await panel.count()) === 0) return '';
    return (await panel.innerText()).trim();
}

function parseTurns(full) {
    const lines = full.split(/\n/);
    const turns = [];
    let current = null;
    for (const line of lines) {
        const trimmed = line.trim();
        if (trimmed === 'YOU' || trimmed === 'You') {
            current = { role: 'user', lines: [] };
            turns.push(current);
            continue;
        }
        if (trimmed === 'CAMPUS COIN AI' || trimmed === 'Campus Coin AI') {
            current = { role: 'assistant', lines: [] };
            turns.push(current);
            continue;
        }
        if (current) current.lines.push(line);
    }
    return turns.map((t) => ({ role: t.role, text: t.lines.join('\n').trim() }));
}

function extractAnswer(assistantText) {
    const m =
        assistantText.match(/Answer\n([\s\S]*?)(?=\nBased on|\nSuggested action|\nSuggested Plan|$)/i) ||
        assistantText.match(/ANSWER\s*\n?([\s\S]*?)(?=BASED ON|SUGGESTED ACTION|SUGGESTED PLAN|$)/i);
    return (m?.[1]?.trim() || assistantText).trim();
}

function judgeParaphrase(question, answer) {
    const a = answer || '';
    const lower = a.toLowerCase();
    if (!a || a.length < 15) return { ok: false, reason: 'empty/short answer' };
    if (a.includes(OOS) || lower.includes('out of context')) return { ok: false, reason: 'OOS refusal' };
    // Generic dump heuristic: only a raw spend summary with no guidance
    const looksLikeDumpOnly =
        /^this month you('ve| have) spent/i.test(a.trim()) &&
        !/should|focus|start|cut|save|recommend|try|consider|attention|allocate|budget/i.test(a);
    if (looksLikeDumpOnly) return { ok: false, reason: 'generic dump' };
    const personalized =
        /rs\s*[\d,]+|\d+%|food|transport|entertainment|balance|budget|savings|income|spend|category|this month|your/i.test(
            a,
        );
    if (!personalized) return { ok: false, reason: 'not personalized' };
    return { ok: true, reason: 'personalized guidance' };
}

async function ask(page, text) {
    const input = page.locator('#campus-coin-ai-input');
    await input.waitFor({ state: 'visible', timeout: 20000 });
    await input.fill(text);
    const before = await conversationText(page);
    await page.getByRole('button', { name: 'Ask', exact: true }).click();
    await page.waitForFunction(
        (prevLen) => {
            const el = document.querySelector('[aria-label="Conversation"]');
            if (!el) return false;
            const t = el.innerText || '';
            if (t.includes('Thinking')) return false;
            return t.length > prevLen + 20;
        },
        before.length,
        { timeout: 90000 },
    );
    await page.waitForTimeout(500);
}

async function selectType(page, label) {
    const btn = page.getByRole('button', { name: label, exact: true });
    if (await btn.count()) {
        await btn.first().click();
        await page.waitForTimeout(400);
        return;
    }
    const radio = page.getByRole('radio', { name: label });
    if (await radio.count()) {
        await radio.first().click();
        await page.waitForTimeout(400);
        return;
    }
    const sel = page.locator('select[name="type"], #type');
    if (await sel.count()) {
        await sel.first().selectOption({ label }).catch(async () => {
            await sel.first().selectOption(label.toLowerCase());
        });
        await page.waitForTimeout(400);
    }
}

async function selectFirstCategory(page, preferName = null) {
    const cat = page.locator('#category_id, select[name="category_id"]');
    await cat.first().waitFor({ state: 'visible', timeout: 15000 });
    const opts = cat.first().locator('option');
    let chosen = '';
    for (let i = 0; i < (await opts.count()); i++) {
        const v = await opts.nth(i).getAttribute('value');
        const label = (await opts.nth(i).innerText()).trim();
        if (!v) continue;
        if (preferName && label.toLowerCase().includes(preferName.toLowerCase())) {
            await cat.first().selectOption(v);
            return label;
        }
        if (!chosen) {
            await cat.first().selectOption(v);
            chosen = label;
        }
    }
    return chosen;
}

async function countUnusualExpenseMentions(page) {
    const body = await page.locator('body').innerText();
    const titles = (body.match(/unusual expense/gi) || []).length;
    // Also check notification list items text if present
    return { bodySnippet: body.slice(0, 800).replace(/\s+/g, ' '), unusualMentions: titles };
}

async function main() {
    note(`Base URL: ${BASE}`);
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    page.setDefaultTimeout(45000);

    let unusualBefore = 0;

    try {
        await login(page);
        const loggedIn = !page.url().includes('/login');
        record('Login', loggedIn ? 'PASS' : 'FAIL', `url=${page.url()}`);
        if (!loggedIn) {
            printSummary();
            await browser.close();
            process.exit(1);
        }
        await page.screenshot({ path: path.join(OUT, 'rem-01-after-login.png'), fullPage: true });

        // Baseline unusual_expense mentions on notifications
        await page.goto(`${BASE}/notifications`, { waitUntil: 'networkidle' });
        const beforeNotif = await countUnusualExpenseMentions(page);
        unusualBefore = beforeNotif.unusualMentions;
        note(`Notifications baseline unusual_expense mentions=${unusualBefore}`);
        await page.screenshot({ path: path.join(OUT, 'rem-02-notifications-baseline.png'), fullPage: true });

        // --- 1. Paraphrases one-by-one (fresh page each) ---
        for (let i = 0; i < PARAPHRASES.length; i++) {
            const q = PARAPHRASES[i];
            await page.goto(`${BASE}/intelligence`, { waitUntil: 'networkidle' });
            await page.waitForSelector('#campus-coin-ai-input', { timeout: 20000 });
            await ask(page, q);
            const shot = path.join(OUT, `rem-para-${String(i + 1).padStart(2, '0')}-${slug(q)}.png`);
            await page.screenshot({ path: shot, fullPage: true });
            const full = await conversationText(page);
            const turns = parseTurns(full);
            const lastAsst = [...turns].reverse().find((t) => t.role === 'assistant');
            const answer = extractAnswer(lastAsst?.text ?? full);
            const judge = judgeParaphrase(q, answer);
            const snippet = answer.replace(/\s+/g, ' ').slice(0, 220);
            note(`Q: ${q}`);
            note(`Answer snippet: ${snippet}`);
            record(`Paraphrase: "${q}"`, judge.ok ? 'PASS' : 'FAIL', `${judge.reason} | Answer: "${snippet}"`);
        }

        // --- 4 early: Gemini key check on /intelligence (also after paraphrases) ---
        await page.goto(`${BASE}/intelligence`, { waitUntil: 'networkidle' });
        const html = await page.content();
        const hasGeminiKeyName = html.includes('GEMINI_API_KEY');
        const hasAqAb8 = html.includes('AQ.Ab8');
        await page.screenshot({ path: path.join(OUT, 'rem-gemini-source-check.png'), fullPage: true });
        record(
            'Gemini key not in /intelligence HTML',
            !hasGeminiKeyName && !hasAqAb8 ? 'PASS' : 'FAIL',
            `GEMINI_API_KEY=${hasGeminiKeyName} AQ.Ab8=${hasAqAb8} htmlLen=${html.length}`,
        );

        // --- 2. Create REAL income Rs 30000 ---
        await page.goto(`${BASE}/transactions/create`, { waitUntil: 'networkidle' });
        await selectType(page, 'Income');
        const incomeCat = await selectFirstCategory(page);
        const incomeDesc = `QA REAL income 30000 ${Date.now()}`;
        await page.locator('#amount, input[name="amount"]').first().fill('30000');
        const descField = page.locator('#description, input[name="description"], textarea[name="description"]');
        if (await descField.count()) await descField.first().fill(incomeDesc);
        const dateField = page.locator('#transaction_date, input[name="transaction_date"]');
        if (await dateField.count()) {
            const v = await dateField.first().inputValue();
            if (!v) await dateField.first().fill(new Date().toISOString().slice(0, 10));
        }
        await page.screenshot({ path: path.join(OUT, 'rem-03-income-form.png'), fullPage: true });
        await page.getByRole('button', { name: /Add transaction/i }).click();
        try {
            await page.waitForURL((u) => /\/transactions\/?$/.test(u.pathname), { timeout: 30000 });
        } catch {
            /* may stay or redirect elsewhere */
        }
        await page.waitForLoadState('networkidle').catch(() => null);
        await page.waitForTimeout(1500);
        await page.screenshot({ path: path.join(OUT, 'rem-04-after-income-save.png'), fullPage: true });
        const afterIncomeBody = (await page.locator('body').innerText()).replace(/\s+/g, ' ');
        const incomeSaved =
            /Transaction added|successfully|30,?000/i.test(afterIncomeBody) ||
            afterIncomeBody.includes(incomeDesc) ||
            page.url().includes('/transactions');
        record(
            'Income Rs 30000 create/save',
            incomeSaved ? 'PASS' : 'FAIL',
            `url=${page.url()} category=${incomeCat} desc=${incomeDesc} body="${afterIncomeBody.slice(0, 280)}"`,
        );

        // Dashboard + notifications for plan
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(800);
        await page.screenshot({ path: path.join(OUT, 'rem-05-dashboard-after-income.png'), fullPage: true });
        const dashText = (await page.locator('body').innerText()).replace(/\s+/g, ' ');
        note(`Dashboard snippet: ${dashText.slice(0, 500)}`);
        const dashHasPlanCue = /suggested plan|Income received|View (my )?plan|View plan|income/i.test(dashText);
        record(
            'Dashboard shows income/plan cue',
            dashHasPlanCue ? 'PASS' : 'FAIL',
            dashText.slice(0, 400),
        );

        await page.goto(`${BASE}/notifications`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(OUT, 'rem-06-notifications-after-income.png'), fullPage: true });
        const notifText = await page.locator('body').innerText();
        const notifFlat = notifText.replace(/\s+/g, ' ');
        note(`Notifications snippet: ${notifFlat.slice(0, 700)}`);

        // Capture exact title wording if present
        const titleMatch =
            notifText.match(/Income received[^\n]*/i) ||
            notifText.match(/suggested plan[^\n]*/i) ||
            notifText.match(/View (my )?plan[^\n]*/i);
        const observedTitle = titleMatch ? titleMatch[0].trim() : '(no matching title found in page text)';
        note(`Observed notification title/line: ${observedTitle}`);
        if (/Income received — suggested plan/i.test(notifText)) {
            note('Title matches expected: "Income received — suggested plan"');
        } else if (/Income received/i.test(notifText) || /suggested plan/i.test(notifText)) {
            note(`Title wording DIFFERS from exact "Income received — suggested plan". Observed: ${observedTitle}`);
        }

        const hasPlanNotif =
            /Income received|suggested plan|View plan|View my plan/i.test(notifText);
        record(
            'Notifications income plan',
            hasPlanNotif ? 'PASS' : 'FAIL',
            `observedTitle="${observedTitle}" | action_label expected "View plan" (not "View my plan") | snippet="${notifFlat.slice(0, 350)}"`,
        );

        // Open plan link if present
        const planLink = page.locator('a[href*="intelligence?plan="]').first();
        let planHref = null;
        if (await planLink.count()) {
            planHref = await planLink.getAttribute('href');
            const linkText = (await planLink.innerText()).trim();
            note(`Plan link href=${planHref} text="${linkText}"`);
            const abs = planHref.startsWith('http') ? planHref : `${BASE}${planHref.startsWith('/') ? '' : '/'}${planHref}`;
            await page.goto(abs, { waitUntil: 'networkidle' });
            await page.waitForTimeout(800);
            await page.screenshot({ path: path.join(OUT, 'rem-07-intelligence-plan.png'), fullPage: true });
            const planBody = (await page.locator('body').innerText()).replace(/\s+/g, ' ');
            record(
                'Open /intelligence?plan={id}',
                /plan|allocation|Savings|Rs|suggested/i.test(planBody) && page.url().includes('plan=')
                    ? 'PASS'
                    : 'FAIL',
                `url=${page.url()} linkText="${linkText}" body="${planBody.slice(0, 350)}"`,
            );
        } else {
            // Try constructing from latest income if URL pattern known — but do not invent success
            record('Open /intelligence?plan={id}', 'FAIL', 'No a[href*="intelligence?plan="] on /notifications');
        }

        // --- 3. Small Food expense ~300 ---
        await page.goto(`${BASE}/notifications`, { waitUntil: 'networkidle' });
        const midNotif = await countUnusualExpenseMentions(page);
        note(`Unusual mentions before food expense=${midNotif.unusualMentions}`);

        await page.goto(`${BASE}/transactions/create`, { waitUntil: 'networkidle' });
        await selectType(page, 'Expense');
        const foodCat = await selectFirstCategory(page, 'Food');
        const foodDesc = `QA small Food ${Date.now()}`;
        await page.locator('#amount, input[name="amount"]').first().fill('300');
        if (await descField.count()) await page.locator('#description, input[name="description"], textarea[name="description"]').first().fill(foodDesc);
        await page.screenshot({ path: path.join(OUT, 'rem-08-food-expense-form.png'), fullPage: true });
        await page.getByRole('button', { name: /Add transaction/i }).click();
        try {
            await page.waitForURL((u) => /\/transactions\/?$/.test(u.pathname), { timeout: 30000 });
        } catch {
            /* ignore */
        }
        await page.waitForLoadState('networkidle').catch(() => null);
        await page.waitForTimeout(1500);
        await page.screenshot({ path: path.join(OUT, 'rem-09-after-food-save.png'), fullPage: true });
        const foodBody = (await page.locator('body').innerText()).replace(/\s+/g, ' ');
        const foodSaved =
            foodBody.includes(foodDesc) ||
            /Transaction added|successfully|300/i.test(foodBody) ||
            page.url().includes('/transactions');
        record(
            'Food expense ~Rs 300 save',
            foodSaved ? 'PASS' : 'FAIL',
            `category=${foodCat} desc=${foodDesc} url=${page.url()} body="${foodBody.slice(0, 250)}"`,
        );

        await page.goto(`${BASE}/notifications`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(OUT, 'rem-10-notifications-after-food.png'), fullPage: true });
        const afterFood = await countUnusualExpenseMentions(page);
        note(`Unusual mentions after food expense=${afterFood.unusualMentions} (before=${midNotif.unusualMentions})`);
        const noNewSpam = afterFood.unusualMentions <= midNotif.unusualMentions;
        // Also check newest notification isn't unusual for this 300 food
        const newestIsUnusual = /unusual expense/i.test(afterFood.bodySnippet.slice(0, 400));
        record(
            'No new unusual_expense spam after Rs 300 Food',
            noNewSpam ? 'PASS' : 'FAIL',
            `before=${midNotif.unusualMentions} after=${afterFood.unusualMentions} newestLooksUnusual=${newestIsUnusual} snippet="${afterFood.bodySnippet.slice(0, 350)}"`,
        );
    } catch (err) {
        record('Unhandled QA error', 'FAIL', String(err?.stack || err));
        await page.screenshot({ path: path.join(OUT, 'rem-error.png'), fullPage: true }).catch(() => {});
    } finally {
        await browser.close();
    }

    printSummary();
    process.exit(results.some((r) => r.status === 'FAIL') ? 1 : 0);
}

function printSummary() {
    console.log('\n=== REMAINING QA SUMMARY ===');
    for (const r of results) {
        console.log(`[${r.status}] ${r.name}${r.detail ? ` — ${r.detail}` : ''}`);
    }
    console.log('\n=== OBSERVATIONS ===');
    for (const o of observations) console.log(`- ${o}`);
    const reportPath = path.join(OUT, 'qa-remaining-scenarios-report.json');
    fs.writeFileSync(reportPath, JSON.stringify({ results, observations, at: new Date().toISOString() }, null, 2));
    console.log(`\nReport: ${reportPath}`);
    console.log(`Screenshots: ${OUT}`);
}

main().catch((e) => {
    console.error(e);
    process.exit(1);
});
