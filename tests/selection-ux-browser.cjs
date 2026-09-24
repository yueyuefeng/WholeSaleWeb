/* Disposable seeded environment. npm install --no-save playwright; npx playwright install chromium.
   node tests/selection-ux-browser.cjs http://localhost:8080
   Created demo plans follow the 30-day expiry; no personal data or live orders are submitted. */
const {chromium}=require(process.env.SW_PW_MODULE || 'playwright');
const assert=require('node:assert/strict');
const path=require('node:path');
const base=(process.argv[2] || 'http://localhost:8080').replace(/\/$/,'');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.SW_BROWSER_CHANNEL?{channel:process.env.SW_BROWSER_CHANNEL}:{})});
 const context=await browser.newContext({viewport:{width:1440,height:1000},permissions:['clipboard-read','clipboard-write']});
 const page=await context.newPage(), errors=[]; page.on('pageerror',error=>errors.push(error.message));
 const shot=async name=>{if(process.env.SW_SCREENSHOT_DIR) await page.screenshot({path:path.join(process.env.SW_SCREENSHOT_DIR,name+'.png'),fullPage:true});};
 try {
  await page.goto(base+'/product/trail-electric-golf-cart/',{waitUntil:'networkidle'});
  const build=await page.locator('.sw-selection-actions a').getAttribute('href'); await page.goto(build,{waitUntil:'networkidle'});
  const seat=page.locator('[name="choices[seats]"]'), battery=page.locator('[name="choices[battery]"]'), canopy=page.locator('[name="choices[canopy]"]');
  const submit=page.locator('.sw-selection-form [type=submit]');
  await seat.selectOption('six'); await battery.selectOption('standard'); await canopy.selectOption('roof');
  assert.equal(await seat.getAttribute('aria-invalid'),'true');
  assert.equal(await page.locator('.sw-selection-form').evaluate(f=>f.checkValidity()),false);
  await battery.selectOption('extended'); await page.fill('[name=quantity]','3');
  assert.equal(await seat.getAttribute('aria-invalid'),'false');
  await page.reload({waitUntil:'networkidle'}); assert.equal(await seat.inputValue(),'six'); assert.equal(await page.locator('[name=quantity]').inputValue(),'3');
  await page.locator('[data-clear-draft]').click(); assert.equal(await seat.inputValue(),'');
  await seat.selectOption('four');
  await page.evaluate(()=>{const key=Object.keys(sessionStorage).find(k=>k.startsWith('sw-selection-draft-v1:')); const d=JSON.parse(sessionStorage.getItem(key));d.time=Date.now()-86400001;sessionStorage.setItem(key,JSON.stringify(d));});
  await page.reload({waitUntil:'networkidle'}); assert.equal(await seat.inputValue(),'');
  await seat.selectOption('four');
  await page.evaluate(()=>{const key=Object.keys(sessionStorage).find(k=>k.startsWith('sw-selection-draft-v1:')); const d=JSON.parse(sessionStorage.getItem(key));d.revision='outdated';sessionStorage.setItem(key,JSON.stringify(d));});
  await page.reload({waitUntil:'networkidle'}); assert.equal(await seat.inputValue(),'');
  await seat.selectOption('six'); await battery.selectOption('extended'); await canopy.selectOption('roof');
  await page.route('**/selection/plan',async route=>{await new Promise(r=>setTimeout(r,400));await route.continue();},{times:1});
  const requested=page.waitForRequest(r=>r.method()==='POST'&&r.url().includes('/selection/plan')); await submit.click(); await requested;
  assert.equal(await seat.isDisabled(),true);
  await page.locator('.sw-plan-result:not([hidden])').waitFor(); assert.equal(await seat.isDisabled(),false);
  const saved=await page.locator('.sw-plan-result a').nth(1).getAttribute('href');
  await page.locator('[data-copy-result]').click(); await page.waitForFunction(()=>document.querySelector('.sw-copy-status').textContent===window.swSelection.strings.copied);
  assert.equal(await page.evaluate(()=>navigator.clipboard.readText()),saved);
  await page.fill('[name=quantity]','4'); await page.locator('[name=quantity]').blur();
  assert.equal(await page.locator('.sw-plan-result').isHidden(),true);
  assert.equal(await page.locator('.sw-selection-status').innerText(),await page.evaluate(()=>window.swSelection.strings.changed));
  await shot('shadowalker-ux-build');
  await page.goto(saved,{waitUntil:'networkidle'});
  await page.evaluate(()=>Object.defineProperty(navigator.clipboard,'writeText',{value:()=>Promise.reject(new Error('denied')),configurable:true}));
  await page.locator('[data-copy-plan]').click(); await page.locator('.sw-copy-status input').waitFor(); assert.equal(await page.locator('.sw-copy-status input').inputValue(),saved);
  await page.goto(base+'/compare/',{waitUntil:'networkidle'});
  const selects=page.locator('.sw-compare-picker select');
  const kitIds=await selects.first().locator('option[data-kind=kit]').evaluateAll(options=>options.map(o=>o.value)); assert.ok(kitIds.length>=3);
  const search=page.locator('.sw-searchable-picker input[type=search]').first(); await search.fill('Spark');
  assert.ok(await selects.first().locator('option[hidden]').count()>0); await search.fill('');
  await selects.nth(0).selectOption(kitIds[0]);
  assert.equal(await selects.nth(1).locator(`option[value="${kitIds[0]}"]`).isDisabled(),true);
  assert.equal(await selects.nth(1).locator('option[data-kind=cart]').first().isDisabled(),true);
  await selects.nth(1).selectOption(kitIds[1]); await selects.nth(2).selectOption(kitIds[2]);
  await page.locator('.sw-compare-picker [type=submit]').click(); await page.waitForLoadState('networkidle');
  assert.equal(await page.locator('.sw-compare-table thead th').count(),4);
  await page.locator('[data-differences-only]').check();
  assert.ok(await page.locator('.sw-compare-table tbody tr[hidden]').count()>0);
  assert.equal(await page.locator('[data-always-show]').isVisible(),true); assert.equal(await page.locator('.sw-compare-table tfoot a').count(),3);
  for(const width of [1440,390,320]) {await page.setViewportSize({width,height:950}); assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);if(width!==320)await shot('shadowalker-ux-compare-'+width);}
  await page.locator('[data-differences-only]').uncheck(); assert.equal(await page.locator('.sw-compare-table tbody tr[hidden]').count(),0);
  assert.deepEqual(errors,[]);
  console.log('PASS browser UX: conflict feedback, draft restore/reset/expiry/revision, pending-save lock, stale-result hiding, clipboard/fallback, picker search, duplicate/category guard, difference filter, source visibility, consultation links, 1440/390/320px');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
