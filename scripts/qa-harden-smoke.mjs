/**
 * Quick Playwright harden smoke against http://127.0.0.1:8000
 * Screenshots: storage/app/qa-exports/harden-*.png
 * Run: node scripts/qa-harden-smoke.mjs
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

const results = [];

function record(name, pass, detail = '') {
    const snippet = String(detail).replace(/\s+/g, ' ').trim().slice(0, 220);
    results.push({ name, pass, detail: snippet });
    console.log(`${pass ? 'PASS' : 'FAIL'} | ${name} | ${snippet}`);
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

function lastAssistant(full) {
    const turns = parseTurns(full);
    for (let i = turns.length - 1; i >= 0; i--) {
        if (turns[i].role === 'assistant') return turns[i].text;
    }
    return '';
}

function extractAnswer(assistantText) {
    const m =
        assistantText.match(/Answer\n([\s\S]*?)(?=\nBased on|\nSuggested action|\nSuggested Plan|$)/i) ||
        assistantText.match(/ANSWER\s*\n?([\s\S]*?)(?=BASED ON|SUGGESTED ACTION|SUGGESTED PLAN|$)/i);
    return (m?.[1]?.trim() || assistantText).trim();
}

async function ask(page, text) {
    const input = page.locator('#campus-coin-ai-input');
    await input.waitFor({ state: 'visible', timeout: 20000 });
    await input.fill(text);
    const before = await conversationText(page);
    await page.click('button:has-text("Ask")');
    await page.waitForSelector('text=Thinking', { state: 'detached', timeout: 90000 }).catch(() => null);
    // Wait until conversation grows / new YOU turn appears
    await page.waitForFunction(
        ({ prev, q }) => {
            const el = document.querySelector('[aria-label="Conversation"]');
            if (!el) return false;
            const t = el.innerText || '';
            return t.includes(q) && t.length > prev.length + 10;
        },
        { prev: before, q: text },
        { timeout: 90000 },
    ).catch(() => null);
    await page.waitForTimeout(400);
    return conversationText(page);
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    page.setDefaultTimeout(45000);

    try {
        await login(page);
        record('Login', !page.url().includes('/login'), page.url());

        await page.goto(`${BASE}/intelligence`, { waitUntil: 'networkidle' });
        await page.waitForSelector('#campus-coin-ai-input', { timeout: 20000 });

        // 1) Focus → Why → And Transport?
        let thread = await ask(page, 'What should I focus on?');
        const focusAns = extractAnswer(lastAssistant(thread));
        const focusOk =
            focusAns.length > 20 &&
            !focusAns.includes(OOS) &&
            /rs\s*[\d,]+|food|transport|budget|savings|income|spend|balance|category|this month|your/i.test(focusAns);
        record('Q1 What should I focus on? personalized', focusOk, focusAns);

        thread = await ask(page, 'Why?');
        const whyAns = extractAnswer(lastAssistant(thread));
        const whyOk =
            whyAns.length > 15 &&
            !whyAns.includes(OOS) &&
            /because|since|largest|food|transport|spend|budget|savings|rs\s*[\d,]+|your|recommend|impact/i.test(whyAns);
        record('Q2 Why? personalized follow-up', whyOk, whyAns);

        thread = await ask(page, 'And Transport?');
        const transportAns = extractAnswer(lastAssistant(thread));
        const transportOk =
            transportAns.length > 15 &&
            !transportAns.includes(OOS) &&
            /transport/i.test(transportAns);
        record('Q3 And Transport? personalized', transportOk, transportAns);

        const youCount = (thread.match(/\bYOU\b/g) || []).length;
        const aiCount = (thread.match(/CAMPUS COIN AI/gi) || []).length;
        const labelsOk = youCount >= 3 && aiCount >= 3;
        record(
            'Thread labels YOU / CAMPUS COIN AI',
            labelsOk,
            `YOU=${youCount} AI=${aiCount}; has Qs=${thread.includes('What should I focus on?') && thread.includes('Why?') && thread.includes('And Transport?')}`,
        );

        await page.screenshot({ path: path.join(OUT, 'harden-01-focus-why-transport.png'), fullPage: true });

        // 2) OOS Hi
        thread = await ask(page, 'Hi');
        const hiAns = extractAnswer(lastAssistant(thread));
        const oosOk = hiAns.includes(OOS) || thread.includes(OOS);
        record('Q4 Hi exact OOS', oosOk, oosOk ? OOS : hiAns);

        await page.screenshot({ path: path.join(OUT, 'harden-02-oos-hi.png'), fullPage: true });

        // 3) Where should I cut down? → save/Food personalized
        thread = await ask(page, 'Where should I cut down?');
        const cutAns = extractAnswer(lastAssistant(thread));
        const cutOk =
            cutAns.length > 20 &&
            !cutAns.includes(OOS) &&
            /food/i.test(cutAns) &&
            /save|cut|reduc|largest|opportunity|spend/i.test(cutAns);
        record('Q5 Where should I cut down? Food/save', cutOk, cutAns);

        await page.screenshot({ path: path.join(OUT, 'harden-03-cut-down.png'), fullPage: true });

        // Explicitly skip income creation
        record('Skip income 30000', true, 'not created (per instructions)');
    } catch (e) {
        record('Runner exception', false, String(e));
        await page.screenshot({ path: path.join(OUT, 'harden-99-error.png'), fullPage: true }).catch(() => null);
    } finally {
        fs.writeFileSync(path.join(OUT, 'harden-results.json'), JSON.stringify(results, null, 2));
        await browser.close();
        const failed = results.filter((r) => !r.pass).length;
        console.log('\n=== PASS/FAIL TABLE ===');
        for (const r of results) {
            console.log(`| ${r.pass ? 'PASS' : 'FAIL'} | ${r.name} | ${r.detail} |`);
        }
        console.log(`\nSUMMARY ${results.length - failed}/${results.length} passed`);
        console.log(`Screenshots: ${OUT}${path.sep}harden-*.png`);
        process.exit(failed ? 1 : 0);
    }
})();
