export function initWorkflow() {
    const text = JSON.parse(document.getElementById('iyawe-messages')?.textContent || '{}');
    const toast = message => {
        const element = document.querySelector('[data-toast]');
        if (!element) return;
        element.textContent = message; element.hidden = false;
        clearTimeout(element.dismissTimer);
        element.dismissTimer = setTimeout(() => { element.hidden = true; }, 6000);
    };
    window.iyaweToast = toast;
    const network = () => document.querySelectorAll('[data-network-banner]').forEach(element => { element.hidden = navigator.onLine; });
    window.addEventListener('online', network); window.addEventListener('offline', network); network();
    const header = document.querySelector('.topbar');
    if (header) new ResizeObserver(() => document.documentElement.style.setProperty('--topbar-height', header.getBoundingClientRect().height + 'px')).observe(header);
    for (const form of document.querySelectorAll('[data-workflow]')) {
        const locationSelect = form.querySelector('#location_id');
        if (locationSelect) {
            const error = form.querySelector('#location_id_error');
            const validateLocation = (show = false) => {
                const message = locationSelect.value ? '' : locationSelect.dataset.requiredMessage;
                locationSelect.setCustomValidity(message);
                if (show) {
                    error.textContent = message; error.hidden = !message;
                    locationSelect.setAttribute('aria-invalid', String(!!message));
                }
            };
            locationSelect.addEventListener('change', () => validateLocation(true));
            locationSelect.addEventListener('invalid', () => validateLocation(true));
            validateLocation();
        }
        let dirty = false, timer, saving = null, busy = false, conflict = false, generation = 0, navigating = false;
        const auto = form.dataset.autosave === 'true';
        const state = form.querySelector('[data-save-state]');
        const errors = form.querySelector('[data-workflow-errors]');
        const setState = message => { if (state) state.textContent = message; };
        const revision = value => document.querySelectorAll('input[name="revision"]').forEach(input => { input.value = value; });
        const explain = async response => {
            if (response.status === 401 || response.status === 419 || response.redirected) throw new Error(text.session);
            if (response.status === 409) { conflict = true; throw new Error(text.conflict); }
            const data = await response.json().catch(() => ({}));
            if (response.status >= 500) throw new Error(text.failed);
            if (!response.ok) {
                if (data.errors) {
                    for (const [key, messages] of Object.entries(data.errors)) {
                        const input = form.elements.namedItem(key);
                        if (input?.setAttribute) {
                            input.setAttribute('aria-invalid', 'true');
                            const error = document.createElement('p'); error.className = 'field-error'; error.dataset.serverError = 'true'; error.textContent = messages[0];
                            input.insertAdjacentElement('afterend', error);
                        }
                    }
                    throw new Error(Object.values(data.errors).flat().join(' '));
                }
                throw new Error(data.message || text.failed);
            }
            return data;
        };
        const clearErrors = () => {
            form.querySelectorAll('[data-server-error]').forEach(element => element.remove());
            form.querySelectorAll('[aria-invalid]').forEach(element => element.removeAttribute('aria-invalid'));
            if (errors) errors.hidden = true;
        };
        const failure = error => { setState(error.message); if(errors){errors.textContent=error.message;errors.hidden=false;} toast(error.message); };
        const save = async () => {
            clearTimeout(timer);
            if (saving) { const result = await saving; if (!result) return false; return save(); }
            if (!auto || !dirty) return true;
            if (conflict) { failure(new Error(text.conflict)); return false; }
            if (!navigator.onLine) { failure(new Error(text.offline)); return false; }
            const atGeneration = generation;
            const body = new FormData(form);
            body.delete('signature'); body.delete('acknowledged'); body.delete('receipt_acknowledged');
            body.append('_method', 'PATCH');
            setState(text.saving); clearErrors();
            saving = (async () => {
                try {
                    const response = await fetch(form.dataset.draftUrl,{method:'POST',body,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},cache:'no-store',credentials:'same-origin'});
                    const data = await explain(response);
                    revision(data.revision);
                    if (generation === atGeneration) dirty = false;
                    setState(dirty ? text.changed : text.saved + ' · ' + new Date(data.saved_at).toLocaleTimeString(document.documentElement.lang, {hour:'2-digit',minute:'2-digit'}));
                    return true;
                } catch(error) { if (error instanceof TypeError) { conflict=true; error=new Error(text.failed); } failure(error); return false; }
            })();
            const success = await saving; saving = null;
            return success;
        };
        form.addEventListener('input', event => {
            if (event.target.name === 'revision' || event.target.type === 'hidden') return;
            dirty = true; generation++; setState(text.changed);
            if(auto && !busy){clearTimeout(timer);timer=setTimeout(save,1500);}
        });
        form.addEventListener('signature-change', () => { dirty=true;generation++;setState(text.changed); });
        form.addEventListener('change', () => {
            if(auto && !busy){dirty=true;generation++;clearTimeout(timer);timer=setTimeout(save,1500);}
        });
        form.querySelector('[data-save-draft]')?.addEventListener('click', async () => { if(await save()) toast(dirty ? text.changed : text.saved); });
        const backForm=document.querySelector('.back-form');
        backForm?.addEventListener('submit', async event => {
            if(!auto) return;
            event.preventDefault(); if(busy) return;
            busy=true; try { if(await save()){navigating=true;backForm.submit();} } finally {busy=false;}
        });
        document.querySelector('[data-save-exit]')?.addEventListener('click', async event => {
            event.preventDefault(); if(busy) return;
            const href=event.currentTarget.href;
            if(!auto && dirty){location.assign(href);return;}
            if(await save()){navigating=true;location.assign(href);}
        });
        form.addEventListener('submit', async event => {
            if(event.defaultPrevented) return;
            event.preventDefault();
            if(busy) return;
            busy=true; form.inert=true; clearTimeout(timer);
            const buttons=Array.from(form.querySelectorAll('button'));
            buttons.forEach(button=>button.disabled=true);form.setAttribute('aria-busy','true');
            try {
                if(saving) await saving;
                if(auto && dirty && !(await save())) return;
                if(conflict) throw new Error(text.conflict);
                if(!navigator.onLine) throw new Error(text.offline);
                clearErrors();setState(text.saving);
                const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},cache:'no-store',credentials:'same-origin'});
                const data=await explain(response);
                if(!data.saved || !data.redirect) throw new Error(text.failed);
                dirty=false;navigating=true;location.assign(data.redirect);
            } catch(error) {if(error instanceof TypeError){conflict=true;error=new Error(text.failed);}failure(error);}
            finally {busy=false;form.inert=false;buttons.forEach(button=>button.disabled=false);form.removeAttribute('aria-busy');}
        });
        window.addEventListener('beforeunload', event => {if(dirty && !navigating){event.preventDefault();event.returnValue='';}});
    }
    window.addEventListener('pageshow', event => { if(event.persisted) location.reload(); });
}
