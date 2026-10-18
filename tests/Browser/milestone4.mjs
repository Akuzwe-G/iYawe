
import fs from 'node:fs';
import path from 'node:path';
import {spawn,spawnSync} from 'node:child_process';
import {randomBytes,createHash} from 'node:crypto';
import assert from 'node:assert/strict';
import {chromium} from 'playwright';
const root=process.cwd(),origin='http://127.0.0.1:8795',database=process.env.IYAWE_ACCEPTANCE_DATABASE||'iyawe_m4_test_suite';
assert(database.startsWith('iyawe_m4_test_'),'An isolated acceptance database is mandatory.');
fs.mkdirSync('storage/framework/testing',{recursive:true});fs.mkdirSync('storage/app/milestone4-browser',{recursive:true});
const keyFile='storage/framework/testing/m4-browser-key';
const appKey=fs.existsSync(keyFile)?fs.readFileSync(keyFile,'utf8'):'base64:'+randomBytes(32).toString('base64');
if(!fs.existsSync(keyFile))fs.writeFileSync(keyFile,appKey,{mode:0o600});
const password=randomBytes(24).toString('base64url')+'Aa1!';
const env={...process.env,APP_ENV:'testing',APP_DEBUG:'false',APP_URL:origin,APP_KEY:appKey,DB_CONNECTION:'mysql',DB_DATABASE:database,DB_URL:'',SESSION_DRIVER:'database',SESSION_ENCRYPT:'true',SESSION_SECURE_COOKIE:'false',CACHE_STORE:'database',QUEUE_CONNECTION:'sync',MAIL_MAILER:'array',LOG_CHANNEL:'stderr',LOG_LEVEL:'warning',IYAWE_DOCUMENTS_DISK:'local',IYAWE_PRIVATE_LOCAL_ROOT:path.join(root,'storage/framework/testing/m4-browser-private'),IYAWE_ACCEPTANCE_PASSWORD:password};
const seed=spawnSync('php',['artisan','db:seed','--class=AcceptanceSeeder','--no-interaction'],{cwd:root,env,encoding:'utf8'});
if(seed.status!==0)throw new Error('Acceptance seeding failed: '+seed.stderr.slice(0,300));
const php=spawn('php',['-S','127.0.0.1:8795','-t',path.join(root,'public'),path.join(root,'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],{cwd:path.join(root,'public'),env,stdio:'ignore',windowsHide:true});
const results=[],pageErrors=[];let browser;
const planned=['Unified login and authentication','Company branding and bilingual publication','Server autosave and recovery','English three-signature workflow','Kinyarwanda three-signature workflow','Historical PDF preservation','Cross-company private endpoints','Responsive major pages','PWA static-only cache and offline fallback','Logout and expired access'];
async function check(name,fn){try{await fn();results.push({name,result:'Passed',environment:'Local MySQL / headless Edge',details:''});}catch(error){results.push({name,result:'Failed',environment:'Local MySQL / headless Edge',details:error.message});throw error;}}
async function ready(){for(let i=0;i<80;i++){try{const r=await fetch(origin+'/up');if(r.ok)return;}catch{}await new Promise(r=>setTimeout(r,250));}throw new Error('Isolated PHP test server did not start.');}
try{
 await ready();browser=await chromium.launch({channel:'msedge',headless:true});
 const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true,deviceScaleFactor:2});
 const page=await context.newPage();page.on('pageerror',error=>pageErrors.push(error.message));page.on('dialog',dialog=>dialog.accept());
 const go=async url=>{await page.goto(origin+url);await page.waitForLoadState('networkidle');};
 const login=async email=>{await go('/login');await page.locator('#email').fill(email);await page.locator('#password').fill(password);await page.locator('.login-card button.primary').click();await page.waitForURL('**/dashboard');};
 await check(planned[0],async()=>{await login('admin-a@example.test');assert(await page.locator('h1').count());assert.equal(await page.locator('select[name=role]').count(),0);});
 await check(planned[1],async()=>{
  await go('/settings/branding');await page.locator('#brand_legal_name').fill('Fictional A Legal Ltd');await page.locator('#brand_report_title').fill('Fictional operational report');await page.locator('#brand_logo').setInputFiles('public/pwa/icons/icon-192.png');await page.locator('form button.primary').click();await page.waitForLoadState('networkidle');assert((await page.content()).includes('Fictional A Legal Ltd'));
  for(const language of ['en','rw']){await go('/consents');await page.locator('#language').selectOption(language);await page.locator('[name=approved]').check();await page.locator('[name=replace_current]').check();await page.locator('form button.primary').click();await page.waitForLoadState('networkidle');assert((await page.content()).includes('tapgo-'+language+'-1.0'));}
 });
 await page.locator('form[action$="/logout"] button').click();await page.waitForURL('**/login');await login('field-a@example.test');
 const swaps=[];
 async function sign(touch=false){
  const canvas=page.locator('[data-signature] canvas');await canvas.scrollIntoViewIfNeeded();const b=await canvas.boundingBox();
  const points=[[20,130],[65,40],[95,135],[150,45],[210,120]].map(([x,y])=>({x:Math.round(b.x+Math.min(x,b.width-15)),y:Math.round(b.y+y)}));
  if(touch){const cdp=await context.newCDPSession(page);await cdp.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{...points[0],radiusX:2,radiusY:2}]});for(const point of points.slice(1))await cdp.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{...point,radiusX:2,radiusY:2}]});await cdp.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});await cdp.detach();}
  else{await page.mouse.move(points[0].x,points[0].y);await page.mouse.down();for(const p of points.slice(1))await page.mouse.move(p.x,p.y,{steps:8});await page.mouse.up();}
 }
 const next=async()=>{await page.locator('[data-workflow] .form-actions button.primary').click();await page.waitForLoadState('networkidle');};
 const unlock=async()=>{await page.locator('.staff-unlock summary').click();await page.locator('#unlock_password').fill(password);await page.locator('.staff-unlock button').click();await page.waitForLoadState('networkidle');};
 for(const language of ['en','rw']){
  await go('/swaps/create');await page.locator('form[action$="/swaps"] button').click();await page.waitForURL('**/continue');
  const id=page.url().match(/swaps\/(\d+)/)[1],reference=await page.locator('.eyebrow').last().textContent();
  await page.locator('#location_id').selectOption({label:'Fictional service location'});
  await page.locator('[name=commuter_name]').fill('Fictional Browser '+language);await page.locator('[name=phone]').fill('+250788123456');await page.locator('[name=national_id]').fill(language==='en'?'0000000000001234':'0000000000005678');
  await check(language==='en'?planned[2]:'Kinyarwanda draft recovery',async()=>{
   await page.locator('[data-save-draft]').click();await page.waitForFunction(()=>document.querySelector('[data-save-state]').textContent.includes('Saved to server'));await page.reload();assert.equal(await page.locator('[name=commuter_name]').inputValue(),'Fictional Browser '+language);assert((await page.content()).includes(reference.trim()));
  });
  await check(language==='en'?planned[3]:planned[4],async()=>{
   await next();await page.locator('[name=old_card]').fill('00ab1234');await page.locator('[name=new_card]').fill('00cd5678');await page.locator('[name=old_balance]').fill('1000');await page.locator('[name=new_balance]').fill('1000');await page.locator('#reason').selectOption(language==='en'?'lost':'other');if(language==='rw')await page.locator('#reason_other').fill('Fictional other reason');await next();
   await page.locator('#customer_language').selectOption(language);await page.locator('form[action$="/customer"] button').click();await page.waitForLoadState('networkidle');
   assert.equal(await page.locator('html').getAttribute('lang'),language);
   const blocked=await context.request.get(origin+'/reports',{headers:{Accept:'application/json'}});assert.equal(blocked.status(),423);
   await page.locator('[name=acknowledged]').check();
   await page.locator('[data-workflow] .form-actions button').click();assert(await page.locator('.signature-error').isVisible());
   await sign(language==='rw');await page.locator('[data-clear]').click();await sign(language==='rw');
   await page.locator('[data-expand]').click();await page.setViewportSize({width:844,height:390});await page.waitForTimeout(200);await page.locator('[data-expand]').click();await page.setViewportSize({width:390,height:844});await page.waitForTimeout(200);
   await next();await page.waitForURL('**/customer/done');await unlock();
   await page.locator('#outcome').selectOption('completed');await page.locator('[name=physical_confirmation]').check();await sign();await next();
   await page.locator('form[action$="/customer"] button').click();await page.waitForLoadState('networkidle');
   await page.locator('[name=receipt_acknowledged]').check();await sign(language==='rw');await next();await page.waitForURL('**/customer/done');await unlock();assert(page.url().endsWith('/swaps/'+id));
   assert(await page.locator('.confirmation-summary').isVisible());
   const pdf=await context.request.get(origin+'/swaps/'+id+'/pdf');assert.equal(pdf.status(),200);const bytes=await pdf.body();assert(bytes.subarray(0,4).toString()==='%PDF');fs.writeFileSync('storage/app/milestone4-browser/'+language+'.pdf',bytes);
   const html=await page.content();const signature=html.match(/\/swaps\/\d+\/signatures\/\d+/)[0];swaps.push({id,signature,hash:createHash('sha256').update(bytes).digest('hex')});
  });
 }
 await page.locator('form[action$="/logout"] button').click();await page.waitForURL('**/login');await login('admin-a@example.test');
 await check(planned[5],async()=>{await go('/settings/branding');await page.locator('#brand_legal_name').fill('Fictional Later Branding');await page.locator('form button.primary').click();await page.waitForLoadState('networkidle');for(const swap of swaps){const pdf=await context.request.get(origin+'/swaps/'+swap.id+'/pdf');assert.equal(createHash('sha256').update(await pdf.body()).digest('hex'),swap.hash);}});
 await check(planned[7],async()=>{
  const screens=['/dashboard','/records','/drafts','/reports','/settings','/settings/branding','/consents','/staff','/roles','/locations','/swaps/create','/swaps/'+swaps[0].id];
  for(const width of [320,390,768,1280]){await page.setViewportSize({width,height:width<500?844:900});for(const screen of screens){await go(screen);const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);assert(!overflow,'Horizontal overflow '+width+' '+screen);}}
  await page.setViewportSize({width:390,height:844});await go('/dashboard');await page.screenshot({path:'storage/app/milestone4-browser/dashboard.png',fullPage:true});
 });
 await page.locator('form[action$="/logout"] button').click();await page.waitForURL('**/login');await login('admin-b@example.test');
 await check(planned[6],async()=>{for(const swap of swaps){for(const url of ['/swaps/'+swap.id,'/swaps/'+swap.id+'/pdf',swap.signature]){const response=await context.request.get(origin+url);assert.equal(response.status(),404);}}await go('/reports?company_id=1');assert(!(await page.content()).includes('Fictional Browser'));});
 await check(planned[8],async()=>{
  await page.evaluate(async()=>{await navigator.serviceWorker.ready;});await page.waitForFunction(()=>!!navigator.serviceWorker.controller);
  const urls=await page.evaluate(async()=>{const result=[];for(const name of await caches.keys()){for(const request of await(await caches.open(name)).keys())result.push(new URL(request.url).pathname);}return result;});
  assert(urls.length>10);assert(urls.every(url=>url.startsWith('/build/')||url.startsWith('/pwa/icons/')||url==='/offline.html'));
  await context.setOffline(true);await page.goto(origin+'/records');assert((await page.textContent('body')).includes('interineti'));assert(!(await page.content()).includes('Fictional Browser'));await context.setOffline(false);
 });
 await check(planned[9],async()=>{await go('/dashboard');await page.locator('form[action$="/logout"] button').click();await page.waitForURL('**/login');assert.equal((await context.request.get(origin+'/reports',{headers:{Accept:'application/json'}})).status(),401);assert.equal(pageErrors.length,0,pageErrors.join('; '));});
 await context.close();
}catch(error){if(!results.some(r=>r.result==='Failed'))results.push({name:'Browser setup',result:'Failed',environment:'Local MySQL / headless Edge',details:error.message});process.exitCode=1;}
finally{if(browser)await browser.close();php.kill();for(const name of planned)if(!results.some(r=>r.name===name))results.push({name,result:'Not Run',environment:'Local MySQL / headless Edge',details:'A dependent browser step failed.'});fs.writeFileSync('storage/app/milestone4-browser/results.json',JSON.stringify({results,pageErrors},null,2));console.log(JSON.stringify({passed:results.filter(r=>r.result==='Passed').length,failed:results.filter(r=>r.result==='Failed'),notRun:results.filter(r=>r.result==='Not Run').map(r=>r.name),pageErrors}));}
