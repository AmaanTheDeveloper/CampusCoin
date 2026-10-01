/**
 * Final browser acceptance QA — dashboard-aware Campus Coin AI.
 * Run: node scripts/qa-dashboard-aware-acceptance.mjs
 * Requires: php artisan serve at APP_URL (default http://127.0.0.1:8000)
 * Screenshots/results: storage/app/qa-exports/dashboard-aware-*
 */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.join(__dirname, '..');
const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const OUT = path.join(ROOT, 'storage', 'app', 'qa-exports');
fs.mkdirSync(OUT, { recursive: true });

const EMAIL = 'test@example.com';
const PASSWORD = 'password';
const EMAIL_B = 'qa-isolation-b@example.com';
const OOS =
    "I'm sorry, that is out of context. I can only assist you with questions related to your Campus Coin spending, budgets, savings, or income planning.";

const ALLOWED_HREFS = new Set([
    '/dashboard',
    '/transactions',
    '/budgets',
    '/categories',
    '/recurring-transactions',
    '/reports',
    '/intelligence',
    '/insights',
    '/monthly-review',
    '/month-forecast',
    '/savings-goal',
    '/spending-alerts',
    '/duplicate-review',
    '/bookmarks',
    '/notifications',
    '/settings/profile',
    '/transactions/import',
]);

const results = [];
const observations = [];

function record(name, pass, detail = '') {
    const snippet = String(detail).replace(/\s+/g, ' ').trim().slice(0, 320);
    results.push({ name, pass, detail: snippet });
    console.log(`${pass ? 'PASS' : 'FAIL'} | ${name} | ${snippet}`);
}

function note(msg) {
    observations.push(msg);
    console.log(`  · ${msg}`);
}

function loadExpectedFacts() {
    const out = execFileSync('php', [path.join(ROOT, 'scripts', 'qa-dashboard-aware-setup.php')], {
        cwd: ROOT,
        encoding: 'utf8',
    });
    const jsonStart = out.indexOf('{');
    return JSON.parse(out.slice(jsonStart));
}

function moneyVariants(amount) {
    const n = Number(amount);
    const plain = n.toFixed(2);
    const noCents = Number.isInteger(n) || plain.endsWith('.00') ? String(Math.round(n)) : plain;
    const withComma = noCents.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const withCommaDec = Number(plain).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return [plain, noCents, withComma, withCommaDec, `Rs ${withComma}`, `Rs ${noCents}`, `Rs ${plain}`];
}

function mentionsAmount(text, amount) {
    const t = text.replace(/\s+/g, ' ');
    return moneyVariants(amount).some((v) => t.includes(v));
}

async function login(page, email = EMAIL, password = PASSWORD) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[type=email]', email);
    await page.fill('input[type=password]', password);
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
        assistantText.match(/Answer\n([\s\S]*?)(?=\nBased on|\nSuggested action|\nSuggested Plan|\nView in Campus Coin|$)/i) ||
        assistantText.match(/ANSWER\s*\n?([\s\S]*?)(?=BASED ON|SUGGESTED ACTION|SUGGESTED PLAN|VIEW IN CAMPUS COIN|$)/i);
    return (m?.[1]?.trim() || assistantText).trim();
}

async function ask(page, text) {
    const input = page.locator('#campus-coin-ai-input');
    await input.waitFor({ state: 'visible', timeout: 20000 });
    await input.fill(text);
    const before = await conversationText(page);
    await page.click('button:has-text("Ask")');
    await page.waitForSelector('text=Thinking', { state: 'detached', timeout: 90000 }).catch(() => null);
    await page
        .waitForFunction(
            ({ prev, q }) => {
                const el = document.querySelector('[aria-label="Conversation"]');
                if (!el) return false;
                const t = el.innerText || '';
                return t.includes(q) && t.length > prev.length + 10 && !t.includes('Thinking…') && !t.includes('Thinking...');
            },
            { prev: before, q: text },
            { timeout: 90000 },
        )
        .catch(() => null);
    await page.waitForTimeout(350);
    return conversationText(page);
}

async function openIntelligence(page) {
    await page.goto(`${BASE}/intelligence`, { waitUntil: 'networkidle' });
    await page.waitForSelector('#campus-coin-ai-input', { timeout: 20000 });
}

async function viewInCampusLinks(page) {
    const panel = page.getByLabel('Conversation');
    if ((await panel.count()) === 0) return [];
    // Links under the last assistant bubble that shows View in Campus Coin
    const links = panel.locator('a');
    const count = await links.count();
    const out = [];
    for (let i = 0; i < count; i++) {
        const href = await links.nth(i).getAttribute('href');
        const label = (await links.nth(i).innerText()).trim();
        if (href) out.push({ href, label });
    }
    return out;
}

function isAllowedHref(href) {
    if (!href) return false;
    if (/^https?:\/\//i.test(href) && !href.startsWith(BASE)) return false;
    try {
        const u = href.startsWith('http') ? new URL(href) : new URL(href, BASE);
        const pathOnly = u.pathname.replace(/\/$/, '') || '/';
        return [...ALLOWED_HREFS].some((p) => pathOnly === p || pathOnly.startsWith(p + '/'));
    } catch {
        return false;
    }
}

async function clickAndVerifyRoute(page, hrefFragment, label) {
    const panel = page.getByLabel('Conversation');
    const link = panel.locator(`a[href="${hrefFragment}"], a[href$="${hrefFragment}"]`).last();
    if ((await link.count()) === 0) {
        record(`Link click ${label}`, false, `No link for ${hrefFragment}`);
        return false;
    }
    const href = await link.getAttribute('href');
    if (!isAllowedHref(href)) {
        record(`Link click ${label}`, false, `Untrusted href=${href}`);
        return false;
    }
    await Promise.all([
        page.waitForURL((u) => u.pathname.includes(hrefFragment.replace(/^\//, '').split('/')[0]) || u.pathname === hrefFragment, {
            timeout: 20000,
        }).catch(() => null),
        link.click(),
    ]);
    await page.waitForTimeout(500);
    const ok = page.url().includes(hrefFragment.replace(/^\//, '')) || new URL(page.url()).pathname === hrefFragment;
    record(`Link click ${label}`, ok, `href=${href} landed=${page.url()}`);
    await openIntelligence(page);
    return ok;
}

(async () => {
    const facts = loadExpectedFacts();
    note(`Expected A balance=${facts.available_balance} expense=${facts.month_expense} food=${facts.food} transport=${facts.transport}`);
    note(`Isolation B balance=${facts.isolation_b_balance}`);

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();
    page.setDefaultTimeout(45000);

    /** @type {any[]} */
    const assistantPayloads = [];
    page.on('response', async (res) => {
        try {
            if (!res.url().includes('/intelligence/assistant') || res.request().method() !== 'POST') return;
            const json = await res.json().catch(() => null);
            if (json) assistantPayloads.push(json);
        } catch {
            /* ignore */
        }
    });

    try {
        await login(page);
        record('Login test@example.com', !page.url().includes('/login'), page.url());

        await openIntelligence(page);
        await page.screenshot({ path: path.join(OUT, 'dashboard-aware-01-intelligence.png'), fullPage: true });

        // ---------- 2. ChatGPT-style thread ----------
        let thread = await ask(page, 'What is my balance?');
        let turns = parseTurns(thread);
        const balanceAns = extractAnswer(lastAssistant(thread));
        record(
            'Thread: first YOU+AI visible',
            turns.some((t) => t.role === 'user' && t.text.includes('What is my balance?')) &&
                turns.some((t) => t.role === 'assistant') &&
                !thread.includes('Thinking'),
            thread.slice(0, 280),
        );

        thread = await ask(page, 'Where can I see it?');
        turns = parseTurns(thread);
        const youCount = turns.filter((t) => t.role === 'user').length;
        const aiCount = turns.filter((t) => t.role === 'assistant').length;
        const followNav = extractAnswer(lastAssistant(thread));
        record(
            'Thread: both turns remain ordered',
            youCount >= 2 &&
                aiCount >= 2 &&
                thread.indexOf('What is my balance?') < thread.indexOf('Where can I see it?') &&
                thread.includes('Where can I see it?'),
            `YOU=${youCount} AI=${aiCount}`,
        );
        record(
            'Thread: no duplicate ask / no stuck loading',
            (thread.match(/What is my balance\?/g) || []).length === 1 &&
                (thread.match(/Where can I see it\?/g) || []).length === 1 &&
                !thread.includes('Thinking'),
            'counts ok',
        );
        await page.screenshot({ path: path.join(OUT, 'dashboard-aware-02-thread.png'), fullPage: true });

        // ---------- 3. Factual ----------
        await openIntelligence(page);
        thread = await ask(page, 'What is my balance?');
        const bal = extractAnswer(lastAssistant(thread));
        record('Factual: balance matches user', mentionsAmount(bal, facts.available_balance) && !bal.includes(OOS), bal);

        thread = await ask(page, 'How much did I spend this month?');
        const spent = extractAnswer(lastAssistant(thread));
        record('Factual: month spend matches', mentionsAmount(spent, facts.month_expense) && !spent.includes(OOS), spent);

        thread = await ask(page, 'How much did I spend on Food?');
        const foodAns = extractAnswer(lastAssistant(thread));
        record('Factual: Food spend matches', mentionsAmount(foodAns, facts.food) && /food/i.test(foodAns), foodAns);

        // ---------- Navigation ----------
        await openIntelligence(page);
        const navCases = [
            { q: 'Where are my transactions?', path: '/transactions', label: 'Transactions' },
            { q: 'Where can I see my budgets?', path: '/budgets', label: 'Budgets' },
            { q: 'Where can I see my monthly review?', path: '/monthly-review', label: 'Monthly Review' },
            { q: 'Where can I see my financial intelligence?', path: '/intelligence', label: 'Intelligence' },
        ];
        for (const c of navCases) {
            await openIntelligence(page);
            thread = await ask(page, c.q);
            const ans = extractAnswer(lastAssistant(thread));
            const links = await viewInCampusLinks(page);
            const hasLink = links.some((l) => (l.href || '').includes(c.path));
            const concise = ans.split(/\s+/).length < 80 && !/all categories|entire dashboard/i.test(ans);
            record(`Nav: ${c.label}`, hasLink && concise && !ans.includes(OOS), `ans=${ans.slice(0, 160)} links=${JSON.stringify(links)}`);
            if (hasLink) {
                await clickAndVerifyRoute(page, c.path, c.label);
            }
        }

        // Extra link targets when asked
        const extraNav = [
            { q: 'Where can I see my reports?', path: '/reports', label: 'Reports' },
            { q: 'Where can I see my savings goal?', path: '/savings-goal', label: 'Savings Goal' },
            { q: 'Where is my dashboard?', path: '/dashboard', label: 'Dashboard' },
        ];
        for (const c of extraNav) {
            await openIntelligence(page);
            thread = await ask(page, c.q);
            const links = await viewInCampusLinks(page);
            const hasLink = links.some((l) => (l.href || '').includes(c.path));
            record(`Nav link present: ${c.label}`, hasLink, JSON.stringify(links));
            if (hasLink) await clickAndVerifyRoute(page, c.path, c.label);
        }

        // ---------- 4. Recommendations ----------
        await openIntelligence(page);
        thread = await ask(page, 'What should I focus on?');
        const focus = extractAnswer(lastAssistant(thread));
        const focusLinks = await viewInCampusLinks(page);
        record(
            'Coach: What should I focus on?',
            focus.length > 20 &&
                !focus.includes(OOS) &&
                !/^This month you'?ve spent/i.test(focus) &&
                /food|transport|budget|savings|balance|spend|category|improve|careful|priority/i.test(focus),
            focus,
        );
        record(
            'Coach: intelligence link present',
            focusLinks.some((l) => (l.href || '').includes('/intelligence')),
            JSON.stringify(focusLinks),
        );

        await openIntelligence(page);
        thread = await ask(page, 'Where should I cut down?');
        const cut = extractAnswer(lastAssistant(thread));
        record(
            'Coach: Where should I cut down?',
            cut.length > 20 && !cut.includes(OOS) && /food|transport|budget|cut|reduc|save|spend/i.test(cut),
            cut,
        );

        await openIntelligence(page);
        thread = await ask(page, 'How can I save more?');
        const save = extractAnswer(lastAssistant(thread));
        record(
            'Coach: How can I save more?',
            save.length > 20 && !save.includes(OOS) && !/50\/30\/20|fifty.?thirty/i.test(save) && /food|rs|spend|budget|save/i.test(save),
            save,
        );

        // ---------- 5 already covered by link clicks ----------
        const allLinksTrusted = assistantPayloads.every((p) => {
            const links = p?.response?.links || [];
            return links.every((l) => isAllowedHref(l.href));
        });
        record('Links: all API links trusted routes only', allLinksTrusted || assistantPayloads.length === 0, `payloads=${assistantPayloads.length}`);

        // ---------- 6. Follow-up context ----------
        await openIntelligence(page);
        thread = await ask(page, 'What should I focus on?');
        const focus1 = extractAnswer(lastAssistant(thread));
        thread = await ask(page, 'Why?');
        const why = extractAnswer(lastAssistant(thread));
        thread = await ask(page, 'How much?');
        const howMuch = extractAnswer(lastAssistant(thread));
        thread = await ask(page, 'What about Food?');
        const aboutFood = extractAnswer(lastAssistant(thread));
        thread = await ask(page, 'What about Transport?');
        const aboutTransport = extractAnswer(lastAssistant(thread));
        await page.screenshot({ path: path.join(OUT, 'dashboard-aware-03-followups.png'), fullPage: true });
        turns = parseTurns(thread);
        record(
            'Follow-up: Why? stays on recommendation',
            why.length > 10 && !why.includes(OOS) && !/^This month you'?ve spent/i.test(why),
            why,
        );
        record('Follow-up: How much? has amount', /rs\s*[\d,]+/i.test(howMuch) && !howMuch.includes(OOS), howMuch);
        record('Follow-up: What about Food?', /food/i.test(aboutFood) && mentionsAmount(aboutFood, facts.food), aboutFood);
        record(
            'Follow-up: What about Transport?',
            /transport/i.test(aboutTransport) && mentionsAmount(aboutTransport, facts.transport),
            aboutTransport,
        );
        record(
            'Follow-up: thread not reset',
            turns.filter((t) => t.role === 'user').length >= 5 && thread.includes('What should I focus on?') && thread.includes('What about Transport?'),
            `users=${turns.filter((t) => t.role === 'user').length}`,
        );

        // ---------- 7. OOS ----------
        await openIntelligence(page);
        const oosPrompts = [
            'Hi',
            'Hello',
            'How are you?',
            'What is your name?',
            'Tell me a joke.',
            "What's the weather?",
            'Who is Elon Musk?',
            'Explain quantum physics.',
            'Write me an email.',
        ];
        for (const q of oosPrompts) {
            await openIntelligence(page);
            thread = await ask(page, q);
            const ans = extractAnswer(lastAssistant(thread));
            const links = await viewInCampusLinks(page);
            const body = lastAssistant(thread);
            const noFinance = !/available balance|this month you|food spending|budget status/i.test(ans);
            const noLinksSection = !/View in Campus Coin/i.test(body);
            record(`OOS: ${q}`, ans.includes(OOS) && noFinance && noLinksSection, ans.slice(0, 120));
        }

        // ---------- 8. Ambiguous ----------
        await openIntelligence(page);
        thread = await ask(page, 'How am I doing?');
        const doing = extractAnswer(lastAssistant(thread));
        record(
            'Ambiguous: How am I doing?',
            doing.length > 5,
            doing.includes(OOS) ? `OOS (acceptable if unresolved): ${doing}` : doing,
        );

        thread = await ask(page, 'What needs attention?');
        const attention = extractAnswer(lastAssistant(thread));
        record(
            'Ambiguous: What needs attention?',
            attention.length > 20 && !attention.includes(OOS) && /food|budget|balance|spend|save|focus|careful|priority/i.test(attention),
            attention,
        );

        await openIntelligence(page);
        thread = await ask(page, 'Where should I look?');
        const look = extractAnswer(lastAssistant(thread));
        record(
            'Ambiguous: Where should I look?',
            look.length > 5,
            look.includes(OOS) ? `OOS preserved: ${look}` : look,
        );

        // ---------- 9. Informal NL ----------
        const informal = [
            { q: 'bhai paisay kahan ja rahay hain', expect: /food|spend|money|expense|rs/i },
            { q: 'mera balance kitna hai', expect: /balance|rs/i, amount: facts.available_balance },
            { q: 'food pe kitna laga', expect: /food|rs/i, amount: facts.food },
            { q: 'kahan cut karun', expect: /food|cut|reduc|save|budget|spend/i },
            { q: 'transactions kidhar hain', expect: /transaction|\/transactions/i },
            { q: 'budget kaisa chal raha hai', expect: /budget|limit|spent|remaining|no budgets/i },
            { q: 'kitna save hua', expect: /save|goal|balance|period|rs/i },
        ];
        for (const item of informal) {
            await openIntelligence(page);
            thread = await ask(page, item.q);
            const ans = extractAnswer(lastAssistant(thread));
            const amountOk = item.amount ? mentionsAmount(ans, item.amount) : true;
            const ok = !ans.includes(OOS) && item.expect.test(ans) && amountOk;
            record(`Informal NL: ${item.q}`, ok, ans);
        }

        // ---------- 10. Gemini role / API contract ----------
        const sample = assistantPayloads.find((p) => p?.response?.in_scope && Array.isArray(p.response.links));
        if (sample) {
            const linksOk = (sample.response.links || []).every((l) => isAllowedHref(l.href));
            const noRawError = !JSON.stringify(sample).match(/cURL error|GeminiException|stack trace/i);
            record('Gemini/API: links are app-verified routes', linksOk, JSON.stringify(sample.response.links));
            record('Gemini/API: no raw provider error in response', noRawError, 'ok');
            note(`Sample intent=${sample.response.intent} type=${sample.response.response_type}`);
        } else {
            record('Gemini/API: captured in-scope payload with links', false, 'No in-scope payload with links captured');
        }

        // ---------- 11. Gemini failure simulation ----------
        // Block Google Gemini hosts; assistant must still answer via deterministic paths.
        await context.route(/generativelanguage\.googleapis\.com|gemini/i, async (route) => {
            await route.fulfill({ status: 503, contentType: 'application/json', body: '{"error":"simulated"}' });
        });
        await openIntelligence(page);
        thread = await ask(page, 'What is my balance?');
        record(
            'Gemini-down: factual balance still works',
            mentionsAmount(extractAnswer(lastAssistant(thread)), facts.available_balance) &&
                !/cURL|503|timeout|malformed|API key/i.test(thread),
            extractAnswer(lastAssistant(thread)),
        );
        thread = await ask(page, 'What should I focus on?');
        record(
            'Gemini-down: coach still works',
            extractAnswer(lastAssistant(thread)).length > 20 && !extractAnswer(lastAssistant(thread)).includes(OOS),
            extractAnswer(lastAssistant(thread)),
        );
        await openIntelligence(page);
        thread = await ask(page, 'Where can I see my budgets?');
        const navLinks = await viewInCampusLinks(page);
        record(
            'Gemini-down: navigation still works',
            navLinks.some((l) => (l.href || '').includes('/budgets')),
            JSON.stringify(navLinks),
        );
        await openIntelligence(page);
        thread = await ask(page, 'Tell me a joke.');
        record('Gemini-down: OOS still exact', extractAnswer(lastAssistant(thread)).includes(OOS), extractAnswer(lastAssistant(thread)));

        // Also simulate abort/timeout style
        await context.unroute(/generativelanguage\.googleapis\.com|gemini/i);
        await context.route(/generativelanguage\.googleapis\.com/i, (route) => route.abort('timedout'));
        await openIntelligence(page);
        thread = await ask(page, 'Where are my transactions?');
        record(
            'Gemini-timeout: nav deterministic',
            (await viewInCampusLinks(page)).some((l) => (l.href || '').includes('/transactions')) &&
                !/timedout|cURL|API/i.test(thread),
            extractAnswer(lastAssistant(thread)),
        );
        await context.unroute(/generativelanguage\.googleapis\.com/i);

        // ---------- 12. User isolation ----------
        const ctxB = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        const pageB = await ctxB.newPage();
        await login(pageB, EMAIL_B, PASSWORD);
        await openIntelligence(pageB);
        const threadB = await ask(pageB, 'What is my balance?');
        const balB = extractAnswer(lastAssistant(threadB));
        const isolationOk =
            mentionsAmount(balB, facts.isolation_b_balance) &&
            !mentionsAmount(balB, facts.available_balance) &&
            !balB.includes(String(facts.food));
        record('Isolation: User B balance is not User A', isolationOk, balB);
        const foodB = extractAnswer(lastAssistant(await ask(pageB, 'How much did I spend on Food?')));
        record(
            'Isolation: User B Food is not User A Food',
            !mentionsAmount(foodB, facts.food) || mentionsAmount(foodB, '777.00'),
            foodB,
        );
        await pageB.screenshot({ path: path.join(OUT, 'dashboard-aware-04-isolation-b.png'), fullPage: true });
        await ctxB.close();

        // ---------- 13. Concise ----------
        await openIntelligence(page);
        thread = await ask(page, 'What is my balance?');
        const conciseBal = extractAnswer(lastAssistant(thread));
        const basedOnShown = /Based on/i.test(lastAssistant(thread));
        record(
            'Concise: balance not a dashboard dump',
            conciseBal.split(/\s+/).length <= 40 &&
                !/top categor|largest named|all of your budgets|transaction list/i.test(conciseBal),
            conciseBal,
        );
        record('Frontend: Based on collapsed for short balance', !basedOnShown, basedOnShown ? 'Based on visible' : 'Based on hidden');

        // ---------- 14/15 Frontend View in Campus Coin ----------
        const body = lastAssistant(thread);
        const hasView = /View in Campus Coin/i.test(body);
        const links = await viewInCampusLinks(page);
        record('Frontend: View in Campus Coin on balance', hasView && links.some((l) => (l.href || '').includes('/dashboard')), JSON.stringify(links));

        await openIntelligence(page);
        thread = await ask(page, 'Hi');
        record(
            'Frontend: OOS has no View in Campus Coin',
            !/View in Campus Coin/i.test(lastAssistant(thread)) && extractAnswer(lastAssistant(thread)).includes(OOS),
            lastAssistant(thread).slice(0, 160),
        );

        await page.screenshot({ path: path.join(OUT, 'dashboard-aware-05-final.png'), fullPage: true });
        note(`Captured assistant API payloads: ${assistantPayloads.length}`);
        note(`Focus sample: ${focus1.slice(0, 180)}`);
    } catch (e) {
        record('Runner exception', false, String(e));
        await page.screenshot({ path: path.join(OUT, 'dashboard-aware-99-error.png'), fullPage: true }).catch(() => null);
    } finally {
        const summary = {
            passed: results.filter((r) => r.pass).length,
            failed: results.filter((r) => !r.pass).length,
            total: results.length,
            facts,
            observations,
            results,
        };
        fs.writeFileSync(path.join(OUT, 'dashboard-aware-results.json'), JSON.stringify(summary, null, 2));
        await browser.close();
        console.log(`\nSUMMARY ${summary.passed}/${summary.total} passed (${summary.failed} failed)`);
        process.exit(summary.failed ? 1 : 0);
    }
})();
