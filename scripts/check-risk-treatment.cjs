const { chromium } = require(process.env.ISMS_PLAYWRIGHT_PATH || 'C:/Users/Firdaus/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const base = process.env.ISMS_URL || 'http://127.0.0.1:8099';

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.ISMS_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const password = fs.readFileSync(path.join(root, 'application/.env'), 'utf8').match(/^ISMS_DEMO_PASSWORD=(.+)$/m)?.[1].trim();
    assert(password, 'Demo password must be configured');
    const output = path.join(root, 'docs/review/treatment');
    fs.mkdirSync(output, { recursive: true });
    async function login(name) {
      await page.goto(base + '/login');
      await page.getByLabel('E-mel', { exact: true }).fill(name + '@example.test');
      await page.getByLabel('Kata laluan', { exact: true }).fill(password);
      await page.getByRole('button', { name: 'Log masuk', exact: true }).click();
      await page.waitForURL('**/documents');
    }
    async function logout() {
      await page.getByRole('button', { name: 'Log keluar', exact: true }).click();
      await page.waitForURL(base + '/');
    }
    await login('pegawai');
    await page.goto(base + '/risks/create');
    const title = '[DEMO E2E] Rawatan akses ' + Date.now();
    for (const [id, value] of Object.entries({title, asset_process:'Aplikasi contoh', threat:'Akses tanpa kebenaran', vulnerability:'Akaun lama masih aktif', consequence:'Maklumat contoh terdedah', existing_controls:'Semakan manual', rationale:'Penilaian sintetik untuk ujian'})) {
      await page.locator('#' + id).fill(value);
    }
    await page.locator('#likelihood').selectOption('3');
    await page.locator('#impact').selectOption('5');
    await page.getByRole('button', {name:'Simpan draf',exact:true}).click();
    await page.waitForURL(/\/risks\/\d+$/);
    const riskUrl = page.url();
    await page.getByRole('button', {name:'Hantar untuk semakan',exact:true}).click();
    await logout(); await login('penyelaras'); await page.goto(riskUrl);
    await page.getByRole('button', {name:'Sahkan semakan',exact:true}).click();
    await logout(); await login('pegawai'); await page.goto(riskUrl + '/treatment');
    await page.getByText('Tambah tindakan rawatan', {exact:true}).click();
    await page.locator('#new-title').fill('Semak dan batalkan akses lama');
    await page.locator('#new-description').fill('Semak senarai akaun contoh dan batalkan akses lama.');
    await page.locator('#new-assignee').selectOption({label:'Pegawai ICT Demo'});
    await page.locator('#new-due').fill('2026-12-31');
    await page.getByRole('button', {name:'Tambah tindakan',exact:true}).click();
    await page.getByLabel('Ringkasan pelaksanaan', {exact:true}).fill('Semakan sintetik selesai; bukti contoh dilampirkan.');
    await page.locator('input[type=file]').setInputFiles({name:'bukti-contoh.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF')});
    await page.getByRole('button', {name:'Hantar bukti untuk pengesahan',exact:true}).click();
    await page.getByText('Bukti dihantar untuk pengesahan.', {exact:true}).waitFor();
    await logout(); await login('penyelaras'); await page.goto(riskUrl + '/treatment');
    const [download] = await Promise.all([page.waitForEvent('download'), page.getByRole('link', {name:'bukti-contoh.pdf',exact:true}).click()]);
    assert.equal(download.suggestedFilename(),'bukti-contoh.pdf');
    await page.getByLabel('Ulasan keputusan (wajib)', {exact:true}).fill('Bukti sintetik mencukupi untuk ujian.');
    await page.getByRole('button', {name:'Sahkan tindakan',exact:true}).click();
    await logout(); await login('pegawai'); await page.goto(riskUrl + '/treatment');
    await page.getByText('Rekod penilaian risiko baki', {exact:true}).click();
    await page.locator('#residual-likelihood').selectOption('1');
    await page.locator('#residual-impact').selectOption('2');
    await page.locator('#residual-controls').fill('Semakan akses contoh selesai.');
    await page.locator('#residual-rationale').fill('Skor contoh selepas rawatan: 2.');
    await page.getByRole('button', {name:'Simpan penilaian baki',exact:true}).click();
    await page.getByText(/2\/25 · Rendah/).waitFor();
    await page.screenshot({path:path.join(output,'desktop.png'),fullPage:true});
    await page.setViewportSize({width:390,height:844});
    assert(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth));
    await page.screenshot({path:path.join(output,'mobile.png'),fullPage:true});
    assert.deepEqual(errors,[]);
    console.log('PASS: risk assessment → assigned action → PDF evidence/download → independent verification → residual score; mobile overflow and JS checks');
    fs.writeFileSync(path.join(output,'checks.json'),JSON.stringify({passed:true,riskUrl,checks:['assessment reviewed','action assigned','private PDF uploaded/downloaded','independent verification','residual score 2','mobile no overflow','no JS errors']},null,2));
  } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exit(1); });
