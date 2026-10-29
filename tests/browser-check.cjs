// Optional QA tooling only. The website itself has no Node dependency.
const {chromium}=require(process.env.WIKI_PLAYWRIGHT || 'playwright');
const fs=require('fs');
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 fs.mkdirSync('tests/artifacts',{recursive:true});
 for(const width of [320,375,425,768,1024,1440,1920]){
  await page.setViewportSize({width,height:1000});await page.goto('http://127.0.0.1:8080',{waitUntil:'load',timeout:60000});
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);if(overflow)throw new Error('Homepage overflow at '+width);
  const broken=await page.locator('img').evaluateAll(imgs=>imgs.filter(i=>i.complete&&i.naturalWidth===0).length);if(broken)throw new Error('Broken images at '+width);
  if([375,1440].includes(width)){await page.locator('img').evaluateAll(images=>images.forEach(i=>i.loading='eager'));await page.waitForFunction(()=>[...document.images].every(i=>i.complete));await page.screenshot({path:`tests/artifacts/home-${width}.png`,fullPage:true});}
  console.log(`PASS: homepage width ${width}px`);
 }
 await page.setViewportSize({width:375,height:900});
 for(const path of ['/shop','/product/midnight-oud','/cart','/checkout','/login','/size-guide']){
  await page.goto('http://127.0.0.1:8080'+path,{waitUntil:'load',timeout:60000});if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw new Error('Overflow '+path);console.log('PASS: mobile '+path);
 }
 await page.goto('http://127.0.0.1:8080/product/midnight-oud',{waitUntil:'load',timeout:60000});
 await page.locator('.cookie button[value="no"]').click();
 if(!await page.locator('#add-to-cart').isDisabled())throw new Error('Variant required');
 await page.locator('#variant').selectOption({index:1});await page.locator('#add-to-cart').click();await page.waitForFunction(()=>document.querySelector('[data-cart-count]').textContent==='1');
 await page.locator('[data-zoom]').click();if(!await page.locator('#zoom-dialog').isVisible())throw new Error('Zoom');await page.keyboard.press('Escape');console.log('PASS: variant, AJAX cart, image zoom');
 await page.goto('http://127.0.0.1:8080/shop');await page.locator('[name="q"]').fill('Azure');await page.locator('#filters button').click();await page.waitForFunction(()=>document.querySelector('#results-note').textContent==='1 pieces in this edit');console.log('PASS: AJAX product filtering');
 await page.goto('http://127.0.0.1:8080/checkout');if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw new Error('Checkout overflow');await page.screenshot({path:'tests/artifacts/checkout-mobile.png',fullPage:true});
 if(errors.length)throw new Error(errors.join('\n'));console.log('PASS: no JavaScript runtime errors');await browser.close();
})().catch(e=>{console.error(e);process.exit(1);});

