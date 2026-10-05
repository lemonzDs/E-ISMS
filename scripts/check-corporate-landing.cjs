const { chromium } = require('C:/Users/Firdaus/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
(async()=>{
 const browser = await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const output='docs/review/corporate-landing'; fs.mkdirSync(output,{recursive:true});
 const page=await browser.newPage(); const errors=[]; page.on('pageerror',e=>errors.push(e.message));
 for(const [name,width,height] of [['desktop',1440,1000],['mobile',390,844],['small-mobile',320,740]]){
  await page.setViewportSize({width,height}); await page.goto('http://127.0.0.1:8099/',{waitUntil:'networkidle'});
  assert(await page.locator('.cp-hero-image').evaluate(i=>i.complete&&i.naturalWidth>0));
  assert(await page.locator('.cp-identity img').evaluate(i=>i.complete&&i.naturalWidth>0));
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'horizontal overflow '+name);
  await page.screenshot({path:output+'/'+name+'.png',fullPage:true});
 }
 await page.setViewportSize({width:1440,height:1000});await page.goto('http://127.0.0.1:8099/');
 await page.keyboard.press('Tab');assert.equal(await page.locator(':focus').textContent(),'Langkau ke kandungan');await page.keyboard.press('Enter');assert.equal(await page.locator(':focus').getAttribute('id'),'main');
 await page.getByRole('link',{name:'Mengenai sistem',exact:true}).click();assert(page.url().endsWith('#mengenai'));
 await page.getByRole('link',{name:'Log masuk ke e-ISMS'}).click();await page.waitForURL('**/login');assert(await page.getByLabel('E-mel',{exact:true}).isVisible());
 assert.deepEqual(errors,[]);fs.writeFileSync(output+'/checks.json',JSON.stringify({passed:['assets loaded','1440/390/320 widths no overflow','skip link keyboard focus','section navigation','login CTA'],errors},null,2));
 await browser.close();console.log('PASS: landing assets, three widths, keyboard skip link, section navigation and login CTA');
})().catch(e=>{console.error(e);process.exit(1)});
