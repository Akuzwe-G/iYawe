export function initPwa() {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return;
    const text=JSON.parse(document.getElementById('iyawe-messages')?.textContent || '{}');
    const banner=document.querySelector('[data-install-banner]');
    const installButton=document.querySelector('[data-install]');
    const manual=document.querySelector('[data-install-manual]');
    const description=document.querySelector('[data-install-description]');
    const standalone=matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const ios=/iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform==='MacIntel' && navigator.maxTouchPoints>1);
    let deferred=null, registration=null, requestedReload=false;
    const dismissed=()=>{try{return Number(localStorage.getItem('iyawe-install-dismissed-until')||0)>Date.now();}catch{return false;}};
    const guidance=()=>{
        if(!banner || standalone || dismissed()) return;
        if(deferred){banner.hidden=false;if(installButton)installButton.hidden=false;if(manual)manual.hidden=false;}
        else if(ios){
            banner.hidden=false;
            if(description)description.textContent=text.ios;
        }
    };
    window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();deferred=event;guidance();});
    const install=async()=>{if(!deferred)return;await deferred.prompt();await deferred.userChoice;deferred=null;if(banner)banner.hidden=true;if(manual)manual.hidden=true;};
    installButton?.addEventListener('click',install);manual?.addEventListener('click',install);
    document.querySelector('[data-install-dismiss]')?.addEventListener('click',()=>{
        if(banner)banner.hidden=true;
        try{localStorage.setItem('iyawe-install-dismissed-until',String(Date.now()+7*86400000));}catch{}
    });
    window.addEventListener('appinstalled',()=>{if(banner)banner.hidden=true;if(manual)manual.hidden=true;});
    const updateBanner=document.querySelector('[data-update-banner]');
    const pendingUpdate=()=>{if(registration?.waiting && navigator.serviceWorker.controller && updateBanner)updateBanner.hidden=false;};
    document.querySelector('[data-apply-update]')?.addEventListener('click',()=>{
        if(!registration?.waiting)return;
        const message=text.update;
        if(!confirm(message))return;
        requestedReload=true;registration.waiting.postMessage({type:'ACTIVATE_UPDATE'});
    });
    navigator.serviceWorker.addEventListener('controllerchange',()=>{if(requestedReload)location.reload();});
    window.addEventListener('load',async()=>{
        try{
            registration=await navigator.serviceWorker.register('/sw.js',{scope:'/',updateViaCache:'none'});
            pendingUpdate();
            registration.addEventListener('updatefound',()=>{
                registration.installing?.addEventListener('statechange',pendingUpdate);
            });
            await registration.update();
        }catch{
            const manual=document.querySelector('[data-install-manual]');if(manual)manual.hidden=true;
        }
        guidance();
    });
}
