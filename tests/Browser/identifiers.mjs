import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import {spawn} from 'node:child_process';
import assert from 'node:assert/strict';

const root=process.cwd(),origin='http://127.0.0.1:8796',publicRoot=path.join(root,'public');
const manifest=JSON.parse(fs.readFileSync(publicRoot+'/build/manifest.json','utf8'));
const server=http.createServer((request,response)=>{
 const pathname=new URL(request.url,origin).pathname;
 if(pathname.startsWith('/build/')){
  const file=path.resolve(publicRoot,'.'+pathname);
  if(!file.startsWith(publicRoot+path.sep)||!fs.existsSync(file)){response.writeHead(404);response.end();return;}
  response.writeHead(200,{'Content-Type':{'.js':'text/javascript','.css':'text/css','.woff2':'font/woff2','.woff':'font/woff'}[path.extname(file)]||'application/octet-stream'});response.end(fs.readFileSync(file));return;
 }
 const name=pathname.slice(1);
 if(request.method!=='GET'||!['identification','cards','identification-rw','cards-rw'].includes(name)){response.writeHead(404);response.end();return;}
 let html=fs.readFileSync('storage/app/identifier-fixtures/'+name+'.html','utf8').replaceAll('http://iyawe.test',origin);
 html=html.replace('</head>','<link rel="stylesheet" href="/build/'+manifest['resources/css/app.css'].file+'"><script type="module" src="/build/'+manifest['resources/js/app.js'].file+'"></script></head>');
 response.writeHead(200,{'Content-Type':'text/html; charset=utf-8'});response.end(html);
});
await new Promise(resolve=>server.listen(8796,'127.0.0.1',resolve));
const profile=fs.mkdtempSync(path.join(root,'storage/framework/testing/identifier-edge-'));
const executable=process.env.IYAWE_EDGE_PATH||'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const edge=spawn(executable,['--headless=new','--remote-debugging-port=18997','--remote-debugging-address=127.0.0.1','--user-data-dir='+profile,'--no-first-run','--no-default-browser-check','about:blank'],{stdio:'ignore',windowsHide:true});
let ws,sequence=0;const pending=new Map(),results=[];
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function connect(){
 for(let n=0;n<80;n++){
  try{const pages=await(await fetch('http://127.0.0.1:18997/json/list')).json();const page=pages.find(p=>p.type==='page');if(page){ws=new WebSocket(page.webSocketDebuggerUrl);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});break;}}catch{}await delay(100);
 }
 assert(ws,'Headless Edge did not start.');
 ws.onmessage=event=>{if(process.env.IYAWE_DEBUG_CDP)console.log(String(event.data).slice(0,250));const data=JSON.parse(event.data);const request=pending.get(data.id);if(request){pending.delete(data.id);clearTimeout(request.timer);if(data.error)request.reject(new Error(data.error.message));else request.resolve(data.result);}};
}
function command(method,params={}){
 const id=++sequence;return new Promise((resolve,reject)=>{const timer=setTimeout(()=>{pending.delete(id);reject(new Error('Timed out: '+method));},15000);pending.set(id,{resolve,reject,timer});ws.send(JSON.stringify({id,method,params}));});
}
async function evaluate(expression){const data=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(data.exceptionDetails)throw new Error(data.exceptionDetails.text+': '+data.exceptionDetails.exception?.description);return data.result.value;}
async function navigate(name){await command('Page.navigate',{url:origin+'/'+name});for(let n=0;n<100;n++){if(await evaluate("document.readyState === 'complete' && !!window.Alpine"))return;await delay(100);}throw new Error('Module not ready: '+name);}
async function check(name,fn){await fn();results.push({name,result:'Passed',environment:'Headless Microsoft Edge / generated Laravel fixtures'});}
try{
 await connect();await command('Page.enable');await command('Runtime.enable');await command('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:2,mobile:true});
 await check('Location is required with bilingual feedback and keeps the selected value',async()=>{
  for(const language of ['en','rw']){
   await navigate(language==='en'?'identification':'identification-rw');
   const state=await evaluate(`(()=>{const select=document.querySelector('#location_id');select.value='';select.dispatchEvent(new Event('change',{bubbles:true}));const empty={required:select.required,valid:select.checkValidity(),message:select.validationMessage};select.selectedIndex=1;select.dispatchEvent(new Event('change',{bubbles:true}));return {empty,selected:select.value,valid:select.checkValidity()};})()`);
   assert.equal(state.empty.required,true);assert.equal(state.empty.valid,false);assert(state.empty.message.startsWith(language==='en'?'Choose':'Hitamo'));assert(state.selected);assert.equal(state.valid,true);
  }
 });
 await navigate('identification');
 await check('Rwandan ID native constraint validation rejects 15 and 17 digits and keeps the complete value',async()=>{
  const states=await evaluate(`['123456789012345','0000000000000000','12345678901234567'].map(value=>{const input=document.querySelector('#national_id');input.value=value;input.dispatchEvent(new Event('input',{bubbles:true}));return {valid:input.checkValidity(),value:input.value,message:input.validationMessage,inputMode:input.inputMode};})`);
  assert.deepEqual(states.map(s=>s.valid),[false,true,false]);assert.equal(states[2].value.length,17);assert.equal(states[0].inputMode,'numeric');assert(states[0].message.includes('16'));
 });
 await check('Passport and foreign ID dynamic patterns match the proposed formats',async()=>{
  const states=await evaluate(`[['passport','Ab-01'],['passport','AB 12'],['foreign_id','AB-12 34'],['foreign_id','AB_123']].map(([type,value])=>{const select=document.querySelector('#identification_type'),input=document.querySelector('#national_id');select.value=type;select.dispatchEvent(new Event('change',{bubbles:true}));input.value=value;input.dispatchEvent(new Event('input',{bubbles:true}));return input.checkValidity();})`);
  assert.deepEqual(states,[true,false,true,false]);
 });
 await check('Changing identification type preserves an invalid existing value',async()=>{
  const state=await evaluate(`(()=>{const select=document.querySelector('#identification_type'),input=document.querySelector('#national_id');select.value='foreign_id';select.dispatchEvent(new Event('change'));input.value='A'.repeat(30);input.dispatchEvent(new Event('input'));select.value='rwandan_national_id';select.dispatchEvent(new Event('change'));return {length:input.value.length,valid:input.checkValidity()};})()`);
  assert.equal(state.length,30);assert.equal(state.valid,false);
 });
 await navigate('cards');
 await check('Card inputs uppercase lowercase letters while preserving zeroes and rejecting short/long/invalid input',async()=>{
  const states=await evaluate(`['00ab12cd','1234567','123456789','00GG1234'].map(value=>{const input=document.querySelector('#old_card');input.value=value;input.dispatchEvent(new Event('input',{bubbles:true}));return {valid:input.checkValidity(),value:input.value};})`);
  assert.deepEqual(states.map(s=>s.valid),[true,false,false,false]);assert.equal(states[0].value,'00AB12CD');assert.equal(states[2].value,'123456789');
 });
 await check('Identical cards are rejected case-insensitively without erasing input',async()=>{
  const state=await evaluate(`(()=>{for(const [name,value]of [['old_card','00ab1234'],['new_card','00AB1234']]){const input=document.getElementById(name);input.value=value;input.dispatchEvent(new Event('input',{bubbles:true}));}return {valid:document.querySelector('#new_card').checkValidity(),value:document.querySelector('#new_card').value,error:document.querySelector('#new_card').validationMessage};})()`);
  assert.equal(state.valid,false);assert.equal(state.value,'00AB1234');assert(state.error.includes('different'));
 });
 await navigate('identification-rw');
 await check('Kinyarwanda validation uses friendly translated messages',async()=>{
  const state=await evaluate(`(()=>{const input=document.querySelector('#national_id');input.value='123';input.dispatchEvent(new Event('input',{bubbles:true}));return {language:document.documentElement.lang,message:input.validationMessage};})()`);
  assert.equal(state.language,'rw');assert(state.message.startsWith('Andika'));
 });
 await check('Both balances enforce inclusive whole-RWF limits without erasing invalid input',async()=>{
  await navigate('cards');
  const states=await evaluate(`['0','50','99','49999','50000','-1','100.25','100.00','abc'].map(value=>{const old=document.querySelector('#old_balance'),replacement=document.querySelector('#new_balance');old.value=value;replacement.value=value;for(const input of [old,replacement])input.dispatchEvent(new Event('input',{bubbles:true}));return {oldValid:old.checkValidity(),newValid:replacement.checkValidity(),value:old.value,inputMode:old.inputMode};})`);
  assert.deepEqual(states.map(s=>s.oldValid),[true,true,true,true,false,false,false,false,false]);assert.deepEqual(states.map(s=>s.newValid),[true,true,true,true,false,false,false,false,false]);assert.equal(states[6].value,'100.25');assert.equal(states[0].inputMode,'numeric');
 });
 await check('Mismatched balances are rejected and corrected values clear the error',async()=>{
  const state=await evaluate(`(()=>{const old=document.querySelector('#old_balance'),replacement=document.querySelector('#new_balance');old.value='100';replacement.value='101';replacement.dispatchEvent(new Event('input',{bubbles:true}));const mismatch={valid:replacement.checkValidity(),message:replacement.validationMessage,value:replacement.value};replacement.value='100';replacement.dispatchEvent(new Event('input',{bubbles:true}));return {mismatch,corrected:replacement.checkValidity(),old:old.value};})()`);
  assert.equal(state.mismatch.valid,false);assert(state.mismatch.message.includes('equal'));assert.equal(state.mismatch.value,'101');assert.equal(state.corrected,true);assert.equal(state.old,'100');
 });
 await check('Kinyarwanda balance format and equality messages are translated',async()=>{
  await navigate('cards-rw');
  const state=await evaluate(`(()=>{const old=document.querySelector('#old_balance'),replacement=document.querySelector('#new_balance');old.value='-1';replacement.value='100';old.dispatchEvent(new Event('input',{bubbles:true}));const format=old.validationMessage;old.value='101';old.dispatchEvent(new Event('input',{bubbles:true}));return {format,equal:replacement.validationMessage};})()`);
  assert(state.format.startsWith('Andika'));assert(state.equal.startsWith('Amafaranga'));
 });

 await check('Both bilingual identifier/card forms fit a narrow mobile screen',async()=>{
  for(const name of ['identification','cards','identification-rw','cards-rw']){
   await navigate(name);await command('Emulation.setDeviceMetricsOverride',{width:320,height:568,deviceScaleFactor:2,mobile:true});assert(await evaluate('document.documentElement.scrollWidth <= innerWidth + 1'),name);
  }
 });
 console.log(JSON.stringify({checks:results.length,errors:[]}));
}catch(error){results.push({name:'Browser validation failure',result:'Failed',details:error.message});console.log(JSON.stringify({error:error.message}));process.exitCode=1;}
finally{
 if(ws){for(const request of pending.values()){clearTimeout(request.timer);request.reject(new Error('Browser closed'));}ws.close();}
 edge.kill();await new Promise(resolve=>server.close(resolve));fs.writeFileSync('storage/app/identifier-browser-tests.json',JSON.stringify(results,null,2));
}

