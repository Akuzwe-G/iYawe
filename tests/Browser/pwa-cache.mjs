
import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import assert from 'node:assert/strict';
import {chromium} from 'playwright';
const root=path.resolve('public'),origin='http://127.0.0.1:8793';
let generation=1;
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,origin);
 if(url.pathname==='/__next'){generation=2;res.writeHead(200);res.end('ok');return;}
 const file=path.resolve(root,'.'+url.pathname);
 if(file.startsWith(root+path.sep)&&fs.existsSync(file)&&fs.statSync(file).isFile()){
 const mime={'.js':'text/javascript','.html':'text/html','.css':'text/css','.png':'image/png','.svg':'image/svg+xml','.woff':'font/woff','.woff2':'font/woff2','.webmanifest':'application/manifest+json','.json':'application/json'}[path.extname(file)]||'application/octet-stream';
 res.writeHead(200,{'Content-Type':mime});let bytes=fs.readFileSync(file);if(url.pathname==='/sw.js' && generation===2){const version=JSON.parse(fs.readFileSync(root+'/pwa/version.json','utf8')).version;bytes=Buffer.from(bytes.toString().replaceAll(version,version+'-acceptance-update'));}res.end(bytes);return;
 }
 if(req.method==='GET'){res.writeHead(200,{'Content-Type':'text/html','Cache-Control':'no-store, private'});res.end('<html><body>PRIVATE_TEST_MARKER</body></html>');return;}
 res.writeHead(200,{'Content-Type':'application/json','Cache-Control':'no-store, private'});res.end('{"saved":true}');
});
await new Promise(resolve=>server.listen(8793,'127.0.0.1',resolve));
let browser;
try{
 browser=await chromium.launch({channel:'msedge',headless:true});
 const context=await browser.newContext(),page=await context.newPage();
 await page.goto(origin+'/dashboard');
 await page.evaluate(async()=>{await navigator.serviceWorker.register('/sw.js');await navigator.serviceWorker.ready;});
 await page.waitForFunction(()=>!!navigator.serviceWorker.controller);
 await page.goto(origin+'/records');
 await page.evaluate(async()=>{await fetch('/swaps/1/signatures/1');await fetch('/swaps/1/pdf');await fetch('/swaps/1/draft',{method:'PATCH',body:'test'});});
 const caches=await page.evaluate(async()=>{const result=[];for(const name of await window.caches.keys()){const cache=await window.caches.open(name);for(const req of await cache.keys())result.push(new URL(req.url).pathname);}return result;});
 assert(caches.length>10);
 assert(caches.every(p=>p.startsWith('/build/assets/')||p.startsWith('/pwa/icons/')||p==='/offline.html'));
 const manifest=await page.evaluate(async()=>await(await fetch('/manifest.webmanifest')).json());
 assert.equal(manifest.display,'standalone');assert.equal(manifest.start_url,'/dashboard');
 for(const size of [192,512])assert(manifest.icons.some(i=>i.sizes===size+'x'+size));
 await page.evaluate(async()=>{
  await fetch('/__next');
  const registration=await navigator.serviceWorker.getRegistration();
  await registration.update();
  for(let i=0;i<100 && !registration.waiting;i++) await new Promise(resolve=>setTimeout(resolve,50));
  if(!registration.waiting)throw new Error('Updated worker did not become ready.');
  registration.waiting.postMessage({type:'ACTIVATE_UPDATE'});
 });
 await page.waitForFunction(async()=>(await window.caches.keys()).length===1 && (await window.caches.keys())[0].endsWith('-acceptance-update'));
 await context.setOffline(true);
 await page.goto(origin+'/records');
 assert((await page.textContent('body')).includes('interineti'));
 assert(!(await page.textContent('body')).includes('PRIVATE_TEST_MARKER'));
 await page.screenshot({path:'storage/app/milestone4-offline.png',fullPage:true});
 const result={cachedStaticAssets:caches.length,privateUrlsCached:false,offlineBilingual:true,manifestIcons:true,cacheUpdateAndCleanup:true};
 fs.writeFileSync('storage/app/milestone4-pwa-review.json',JSON.stringify(result,null,2));
 console.log(JSON.stringify(result));
 await context.close();
}finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
