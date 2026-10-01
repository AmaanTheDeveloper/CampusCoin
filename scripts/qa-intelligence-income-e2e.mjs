/**
 * E2E browser QA: Campus Coin AI (/intelligence) + income (/transactions)
 * Run: node scripts/qa-intelligence-income-e2e.mjs
 */
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
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
const OUT_OF_SCOPE =
    "I'm sorry, that is out of context. I can only assist you with questions related to your Campus Coin spending, budgets, savings, or income planning.";

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

function php(code) {
    return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: ROOT,
        encoding: 'utf8',
        timeout: 60000,
    }).trim();
}

function ensureCoachData() {
    const out = php(`
$user = App\\Models\\User::where('email', 'test@example.com')->firstOrFail();
$food = App\\Models\\Category::where('type', 'expense')->where('name', 'Food')->where(function ($q) use ($user) {
    $q->whereNull('user_id')->orWhere('user_id', $user->id);
})->orderByRaw('user_id is null desc')->first();
$transport = App\\Models\\Category::where('type', 'expense')->where('name', 'Transport')->where(function ($q) use ($user) {
    $q->whereNull('user_id')->orWhere('user_id', $user->id);
})->orderByRaw('user_id is null desc')->first();
if ($food && !App\\Models\\Transaction::where('user_id', $user->id)->where('category_id', $food->id)->whereMonth('transaction_date', now()->month)->whereYear('transaction_date', now()->year)->exists()) {
    App\\Models\\Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food->id,
        'amount' => '8000.00',
        'transaction_date' => now()->toDateString(),
        'description' => 'QA Food spend',
    ]);
}
if ($transport && !App\\Models\\Transaction::where('user_id', $user->id)->where('category_id', $transport->id)->whereMonth('transaction_date', now()->month)->whereYear('transaction_date', now()->year)->exists()) {
    App\\Models\\Transaction::factory()->for($user)->expense()->create([
        'category_id' => $transport->id,
        'amount' => '2000.00',
        'transaction_date' => now()->toDateString(),
        'description' => 'QA Transport spend',
    ]);
}
echo 'ok income=' . App\\Models\\Transaction::where('user_id', $user->id)->where('type', 'income')->count()
  . ' expense=' . App\\Models\\Transaction::where('user_id', $user->id)->where('type', 'expense')->count()
  . ' ai=' . (config('ai.enabled') ? '1' : '0')
  . ' provider=' . config('ai.provider');
`);
    note(`DB prep: ${out}`);
}

async function login(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.screenshot({ path: path.join(OUT, 'qa-e2e-01-login.png'), fullPage: true });
    await page.fill('input[type=email]', EMAIL);
    await page.fill('input[type=password]', PASSWORD);
    await Promise.all([
        page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 20000 }),
        page.click('button[type=submit]'),
    ]);
}

async function ask(page, text) {
    const input = page.locator('#campus-coin-ai-input');
    await input.fill(text);
    const before = await conversationText(page);
    await page.getByRole('button', { name: 'Ask', exact: true }).click();
    // Wait for Thinking… then for Answer content to settle
    await page.waitForFunction(
        (prevLen) => {
            const el = document.querySelector('[aria-label="Conversation"]');
            if (!el) return false;
            const t = el.innerText || '';
            if (t.includes('Thinking')) return false;
            return t.length > prevLen + 20;
        },
        before.length,
        { timeout: 60000 },
    );
    await page.waitForTimeout(400);
}

async function conversationText(page) {
    const panel = page.getByLabel('Conversation');
    if ((await panel.count()) === 0) return '';
    return (await panel.innerText()).trim();
}

/** Split conversation into role turns using YOU / CAMPUS COIN AI markers (CSS uppercase). */
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

function extractStructured(assistantText) {
    const answer =
        assistantText.match(/Answer\n([\s\S]*?)(?=\nBased on|\nSuggested action|\nSuggested Plan|$)/i)?.[1]?.trim() ??
        '';
    const basedOn =
        assistantText.match(/Based on\n([\s\S]*?)(?=\nSuggested action|\nSuggested Plan|\nAnswer|$)/i)?.[1]?.trim() ??
        '';
    const suggested =
        assistantText.match(/Suggested action\n([\s\S]*?)(?=\nAnswer|\nBased on|\nSuggested Plan|$)/i)?.[1]?.trim() ??
        '';
    // Fallback: if labels are inline without newlines after CSS uppercase transform
    const answerAlt =
        answer ||
        assistantText.match(/ANSWER\s*\n?([\s\S]*?)(?=BASED ON|SUGGESTED ACTION|$)/i)?.[1]?.trim() ||
        '';
    const basedAlt =
        basedOn ||
        assistantText.match(/BASED ON\s*\n?([\s\S]*?)(?=SUGGESTED ACTION|ANSWER|$)/i)?.[1]?.trim() ||
        '';
    const suggestedAlt =
        suggested ||
        assistantText.match(/SUGGESTED ACTION\s*\n?([\s\S]*?)(?=ANSWER|BASED ON|$)/i)?.[1]?.trim() ||
        '';
    return {
        answer: (answerAlt || assistantText).slice(0, 500),
        basedOn: basedAlt.slice(0, 400),
        suggested: suggestedAlt.slice(0, 400),
    };
}

async function main() {
    note(`Base URL: ${BASE}`);
    note(`Demo credentials: ${EMAIL} / ${PASSWORD} (DatabaseSeeder + UserFactory default password)`);
    ensureCoachData();

    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

    try {
        await login(page);
        const loggedIn = !page.url().includes('/login');
        record('Login with demo user', loggedIn ? 'PASS' : 'FAIL', `url=${page.url()}`);
        if (!loggedIn) {
            printSummary();
            await browser.close();
            process.exit(1);
        }
        await page.screenshot({ path: path.join(OUT, 'qa-e2e-02-after-login.png'), fullPage: true });

        await page.goto(`${BASE}/intelligence`, { waitUntil: 'networkidle' });
        await page.waitForSelector('#campus-coin-ai-input', { timeout: 20000 });
        await page.screenshot({ path: path.join(OUT, 'qa-e2e-03-intelligence.png'), fullPage: true });
        record(
            'Intelligence page loads Campus Coin AI',
            (await page.getByRole('heading', { name: 'Your personal financial coach' }).isVisible()) ? 'PASS' : 'FAIL',
        );

        // --- What can I do? ---
        await ask(page, 'What can I do?');
        await page.screenshot({ path: path.join(OUT, 'qa-e2e-04-what-can-i-do.png'), fullPage: true });
        let full = await conversationText(page);
        let turns = parseTurns(full);
        let lastAsst = [...turns].reverse().find((t) => t.role === 'assistant');
        let structured = extractStructured(lastAsst?.text ?? full);
        const userBubble = turns.some((t) => t.role === 'user' && t.text.includes('What can I do?'));
        const asstBubble = turns.some((t) => t.role === 'assistant');
        note(`Bubble roles observed: ${turns.map((t) => t.role).join(', ')} (UI labels: YOU / CAMPUS COIN AI — not USER/ASSISTANT)`);
        note(`Answer: ${structured.answer}`);
        note(`Based on: ${structured.basedOn}`);
        note(`Suggested action: ${structured.suggested}`);
        record(
            'AI: "What can I do?" Answer/Based on/Suggested + You/AI bubbles',
            userBubble && asstBubble && structured.answer.length > 20 && (structured.basedOn.length > 0 || structured.suggested.length > 0)
                ? 'PASS'
                : 'FAIL',
            `answer="${structured.answer.slice(0, 160)}" | based="${structured.basedOn.slice(0, 100)}" | action="${structured.suggested.slice(0, 100)}"`,
        );

        // --- Follow-ups ---
        const followUps = ['Why?', 'How much?', 'What about Food?', 'Tell me more'];
        for (const [idx, q] of followUps.entries()) {
            await ask(page, q);
            await page.screenshot({ path: path.join(OUT, `qa-e2e-05-followup-${idx + 1}.png`), fullPage: true });
            full = await conversationText(page);
            turns = parseTurns(full);
            lastAsst = [...turns].reverse().find((t) => t.role === 'assistant');
            structured = extractStructured(lastAsst?.text ?? '');
            note(`Follow-up "${q}" → ${structured.answer.slice(0, 220)}`);
            const hasUser = turns.some((t) => t.role === 'user' && t.text.includes(q));
            record(
                `AI follow-up: "${q}"`,
                hasUser && structured.answer.length > 10 ? 'PASS' : 'FAIL',
                `answer="${structured.answer.slice(0, 180)}"`,
            );
        }

        full = await conversationText(page);
        turns = parseTurns(full);
        const userCount = turns.filter((t) => t.role === 'user').length;
        const asstCount = turns.filter((t) => t.role === 'assistant').length;
        const preserved = followUps.every((q) => full.includes(q)) && full.includes('What can I do?');
        note(`Thread: ${userCount} YOU turns, ${asstCount} CAMPUS COIN AI turns; preserved=${preserved}`);
        record(
            'AI conversation thread preserves all turns',
            preserved && userCount >= 5 && asstCount >= 5 ? 'PASS' : 'FAIL',
            `you=${userCount} ai=${asstCount}`,
        );
        await page.screenshot({ path: path.join(OUT, 'qa-e2e-06-thread.png'), fullPage: true });

        // --- Out of context ---
        for (const q of ['Hello', 'How are you?']) {
            await ask(page, q);
            full = await conversationText(page);
            turns = parseTurns(full);
            lastAsst = [...turns].reverse().find((t) => t.role === 'assistant');
            structured = extractStructured(lastAsst?.text ?? '');
            const exact = structured.answer.includes(OUT_OF_SCOPE) || full.includes(OUT_OF_SCOPE);
            note(`"${q}" → ${structured.answer.slice(0, 220)}`);
            record(
                `AI out-of-context refusal: "${q}"`,
                exact ? 'PASS' : 'FAIL',
                `observed="${structured.answer.slice(0, 200)}"`,
            );
        }
        await page.screenshot({ path: path.join(OUT, 'qa-e2e-07-oob.png'), fullPage: true });

        // --- Income on /transactions ---
        await page.goto(`${BASE}/transactions/create?type=income`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(OUT, 'qa-e2e-08-income-create.png'), fullPage: true });
        const incomeBtn = page.getByRole('button', { name: 'Income', exact: true });
        if (await incomeBtn.count()) await incomeBtn.click();
        await page.waitForTimeout(300);

        const cat = page.locator('#category_id');
        await cat.waitFor({ state: 'visible' });
        const opts = cat.locator('option');
        let categoryLabel = '';
        for (let i = 0; i < (await opts.count()); i++) {
            const v = await opts.nth(i).getAttribute('value');
            const label = (await opts.nth(i).innerText()).trim();
            if (v) {
                await cat.selectOption(v);
                categoryLabel = label;
                break;
            }
        }
        const stamp = Date.now();
        const desc = `QA E2E income ${stamp}`;
        await page.locator('#amount').fill('3500.00');
        await page.locator('#description').fill(desc);
        // Ensure date is today ISO if empty
        const dateVal = await page.locator('#transaction_date').inputValue();
        if (!dateVal) {
            const today = new Date().toISOString().slice(0, 10);
            await page.locator('#transaction_date').fill(today);
        }

        await page.getByRole('button', { name: /Add transaction/i }).click();
        try {
            await page.waitForURL((u) => u.pathname === '/transactions' || u.pathname === '/transactions/', {
                timeout: 25000,
            });
        } catch {
            const errText = await page.locator('[role=alert], .text-red-500, .text-destructive').allInnerTexts().catch(() => []);
            note(`Income save did not navigate. URL=${page.url()} errors=${JSON.stringify(errText)}`);
            await page.screenshot({ path: path.join(OUT, 'qa-e2e-09-transactions-after-income.png'), fullPage: true });
            record('Income create on /transactions', 'FAIL', `stuck on ${page.url()} category=${categoryLabel}`);
            printSummary();
            await browser.close();
            process.exit(1);
        }

        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: path.join(OUT, 'qa-e2e-09-transactions-after-income.png'), fullPage: true });
        const body = await page.locator('body').innerText();
        const descVisible = await page.getByText(desc).first().isVisible().catch(() => false);
        const amountVisible =
            (await page.getByText(/3,500\.00|3500\.00/).first().isVisible().catch(() => false)) ||
            body.includes('3,500.00') ||
            body.includes('3500');
        // Confirm in DB as well
        const dbCheck = php(`
$user = App\\Models\\User::where('email', 'test@example.com')->firstOrFail();
$tx = App\\Models\\Transaction::where('user_id', $user->id)->where('description', '${desc}')->first();
echo $tx ? ('found type=' . $tx->type . ' amount=' . $tx->amount) : 'missing';
`);
        note(`Income DB: ${dbCheck}; UI descVisible=${descVisible}; category=${categoryLabel}`);
        record(
            'Income create on /transactions',
            dbCheck.includes('type=income') && (descVisible || amountVisible) ? 'PASS' : 'FAIL',
            `db=${dbCheck} uiDesc=${descVisible} uiAmount=${amountVisible}`,
        );

        // List shows Income type badge if present
        const incomeBadge = (await page.getByText('Income', { exact: false }).count()) > 0;
        record(
            'Income visible on /transactions list',
            descVisible || (dbCheck.includes('found') && amountVisible) ? 'PASS' : 'FAIL',
            `badgeOrLabelPresent=${incomeBadge}`,
        );
    } catch (err) {
        record('Unhandled QA error', 'FAIL', String(err));
        await page.screenshot({ path: path.join(OUT, 'qa-e2e-error.png'), fullPage: true }).catch(() => {});
    } finally {
        await browser.close();
    }

    printSummary();
    process.exit(results.some((r) => r.status === 'FAIL') ? 1 : 0);
}

function printSummary() {
    console.log('\n=== QA SUMMARY ===');
    for (const r of results) {
        console.log(`[${r.status}] ${r.name}${r.detail ? ` — ${r.detail}` : ''}`);
    }
    console.log('\n=== UI OBSERVATIONS ===');
    for (const o of observations) console.log(`- ${o}`);
    const reportPath = path.join(OUT, 'qa-intelligence-income-e2e-report.json');
    fs.writeFileSync(reportPath, JSON.stringify({ results, observations, at: new Date().toISOString() }, null, 2));
    console.log(`\nReport: ${reportPath}`);
    console.log(`Screenshots: ${OUT}`);
}

main().catch((e) => {
    console.error(e);
    process.exit(1);
});
