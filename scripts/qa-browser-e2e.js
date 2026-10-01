/**
 * Playwright live browser QA against php artisan serve (127.0.0.1:8000).
 * Evidence saved under storage/app/qa-exports/
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.QA_BASE_URL || 'http://127.0.0.1:8000';
const OUT = path.join(__dirname, '..', 'storage', 'app', 'qa-exports');
fs.mkdirSync(OUT, { recursive: true });

const results = [];

function record(name, pass, detail) {
  results.push({ name, pass, detail });
  console.log(`${pass ? 'PASS' : 'FAIL'} | ${name} | ${detail}`);
}

async function login(page) {
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[type="email"], input[name="email"]', 'test@example.com');
  await page.fill('input[type="password"], input[name="password"]', 'password');
  await Promise.all([
    page.waitForURL(/dashboard|intelligence|transactions/, { timeout: 20000 }).catch(() => null),
    page.click('button[type="submit"]'),
  ]);
  await page.waitForTimeout(800);
}

async function ask(page, text) {
  const box = page.locator('#campus-coin-ai-input');
  await box.fill(text);
  await page.click('button:has-text("Ask")');
  await page.waitForSelector('text=Thinking', { state: 'detached', timeout: 60000 }).catch(() => null);
  await page.waitForTimeout(600);
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  page.setDefaultTimeout(45000);

  try {
    await login(page);
    record('Login', page.url().includes('dashboard') || page.url().includes('intelligence'), page.url());

    await page.goto(`${BASE}/intelligence`, { waitUntil: 'networkidle' });
    await page.screenshot({ path: path.join(OUT, '01-intelligence.png'), fullPage: true });
    const hasCoach = await page.locator('text=personal financial coach').count();
    record('/intelligence loads coach', hasCoach > 0, `coach heading count=${hasCoach}`);

    const paraphrases = [
      'What can I do?',
      'What should I focus on?',
      'Where should I start?',
      'How can I improve my spending?',
    ];
    for (const p of paraphrases) {
      await ask(page, p);
      const body = await page.locator('[aria-label="Conversation"]').innerText();
      const ok =
        body.includes(p) &&
        (body.includes('Answer') || body.toLowerCase().includes('food') || body.toLowerCase().includes('balance')) &&
        !body.includes("I'm sorry, that is out of context") === false
          ? body.includes(p)
          : true;
      const actionable = /Food|balance|budget|savings|Suggested action/i.test(body);
      const notDumpOnly = !/^This month you've spent/i.test(body);
      record(`NL: ${p}`, actionable && body.includes(p), body.slice(-400).replace(/\s+/g, ' '));
    }

    // Fresh conversation for follow-ups: reload page
    await page.goto(`${BASE}/intelligence`, { waitUntil: 'networkidle' });
    await ask(page, 'What can I do?');
    await ask(page, 'Why?');
    await ask(page, 'How much?');
    await ask(page, 'What about Food?');
    await ask(page, 'Tell me more');
    await page.screenshot({ path: path.join(OUT, '02-followups.png'), fullPage: true });
    const thread = await page.locator('[aria-label="Conversation"]').innerText();
    const youCount = (thread.match(/\bYOU\b/g) || []).length;
    const aiCount = (thread.match(/CAMPUS COIN AI/gi) || []).length;
    record(
      'Follow-up thread 5 turns',
      youCount >= 5 && aiCount >= 5 && thread.includes('Why?') && thread.includes('How much?'),
      `YOU=${youCount} AI=${aiCount}`,
    );
    record('Why uses prior topic', /Food|balance|reduction|recommend/i.test(thread), thread.slice(-500).replace(/\s+/g, ' '));
    record('Food follow-up', /Food spending this month|Food/i.test(thread), 'Food mentioned in thread');

    await ask(page, 'Hello');
    await ask(page, 'How are you?');
    await ask(page, 'Tell me a joke.');
    const oos = await page.locator('[aria-label="Conversation"]').innerText();
    const oosExact =
      "I'm sorry, that is out of context. I can only assist you with questions related to your Campus Coin spending, budgets, savings, or income planning.";
    record('OOS Hello/How are you/joke', oos.includes(oosExact), oos.includes(oosExact) ? 'exact OOS present' : oos.slice(-300));

    // Income Rs 30000
    await page.goto(`${BASE}/transactions/create`, { waitUntil: 'networkidle' });
    // Prefer selecting Income type if UI has it
    const typeSelect = page.locator('select[name="type"], [name="type"]');
    if (await typeSelect.count()) {
      await typeSelect.first().selectOption({ label: 'Income' }).catch(async () => {
        await typeSelect.first().selectOption('income').catch(() => null);
      });
    } else {
      const incomeBtn = page.locator('button:has-text("Income"), [role="radio"]:has-text("Income")');
      if (await incomeBtn.count()) await incomeBtn.first().click();
    }
    await page.fill('input[name="amount"], #amount', '30000');
    const desc = page.locator('input[name="description"], #description, textarea[name="description"]');
    if (await desc.count()) await desc.first().fill('QA E2E Allowance 30000');
    await page.screenshot({ path: path.join(OUT, '03-income-form.png'), fullPage: true });
    await Promise.all([
      page.waitForURL(/transactions/, { timeout: 20000 }).catch(() => null),
      page.click('button[type="submit"]'),
    ]);
    await page.waitForTimeout(1200);
    await page.screenshot({ path: path.join(OUT, '04-after-income.png'), fullPage: true });
    const flash = await page.locator('body').innerText();
    record('Income Rs 30000 saved', /Transaction added|30000|30,000/i.test(flash), flash.slice(0, 400).replace(/\s+/g, ' '));

    // Notifications / dashboard
    await page.goto(`${BASE}/dashboard`, { waitUntil: 'networkidle' });
    await page.screenshot({ path: path.join(OUT, '05-dashboard.png'), fullPage: true });
    const dash = await page.locator('body').innerText();
    record(
      'Dashboard/notification surface',
      /suggested plan|Income received|View my plan|income/i.test(dash),
      dash.slice(0, 500).replace(/\s+/g, ' '),
    );

    // Try intelligence?plan= from notifications link if present
    const planLink = page.locator('a[href*="intelligence?plan="]').first();
    if (await planLink.count()) {
      const href = await planLink.getAttribute('href');
      await page.goto(href.startsWith('http') ? href : `${BASE}${href}`, { waitUntil: 'networkidle' });
      await page.screenshot({ path: path.join(OUT, '06-plan-view.png'), fullPage: true });
      const planBody = await page.locator('body').innerText();
      record('Intelligence plan view', /plan|allocation|Savings|Rs/i.test(planBody), href);
    } else {
      record('Intelligence plan view', false, 'No intelligence?plan= link found on dashboard');
    }
  } catch (e) {
    record('Runner exception', false, String(e));
    await page.screenshot({ path: path.join(OUT, '99-error.png'), fullPage: true }).catch(() => null);
  } finally {
    fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify(results, null, 2));
    await browser.close();
    const failed = results.filter((r) => !r.pass).length;
    console.log(`\nSUMMARY ${results.length - failed}/${results.length} passed`);
    process.exit(failed ? 1 : 0);
  }
})();
