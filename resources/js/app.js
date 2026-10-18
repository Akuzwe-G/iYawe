import {initIdentifierValidation} from './identifiers';
import {initBalanceValidation} from './balances';
import {initWorkflow} from './workflow';
import {initPwa} from './pwa';
import Alpine from 'alpinejs';
import SignaturePad from 'signature_pad';
import Chart from 'chart.js/auto';

const messages = JSON.parse(document.getElementById('iyawe-messages')?.textContent || '{}');
window.Alpine = Alpine;
Alpine.start();

Chart.defaults.font.family = 'Montserrat, ui-sans-serif, system-ui, sans-serif';
Chart.defaults.color = '#63758e';

for (const canvas of document.querySelectorAll('canvas[data-chart]')) {
    try {
        const config = JSON.parse(document.getElementById(canvas.dataset.chart).textContent);
        const colors = ['#1747A6', '#16B78C', '#1689B9', '#d59a20', '#bd5555'];
        const datasets = config.datasets.map((set, index) => ({
            ...set,
            borderColor: config.type === 'doughnut' ? '#ffffff' : colors[index % colors.length],
            backgroundColor: set.backgroundColor ?? (config.type === 'doughnut' ? ['#16B78C', '#d59a20', '#1689B9', '#94a3b8', '#bd5555'] : colors[index % colors.length]),
            borderWidth: config.type === 'doughnut' ? 3 : 2,
            borderRadius: config.type === 'bar' ? 5 : 0,
            tension: 0.32,
            pointRadius: config.labels.length > 45 ? 0 : 2,
            pointHitRadius: 12,
        }));
        const isDoughnut = config.type === 'doughnut';
        canvas.parentElement.querySelector('[data-chart-loading]')?.remove();
        new Chart(canvas, {
            type: config.type,
            data: { labels: config.labels, datasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                indexAxis: config.horizontal ? 'y' : 'x',
                animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 450 },
                plugins: {
                    legend: { display: isDoughnut || datasets.length > 1, position: 'bottom', labels: { boxWidth: 10, padding: 14, font: { size: 10 } } },
                    tooltip: { backgroundColor: '#172B4D', padding: 12 },
                },
                ...(isDoughnut ? { cutout: '72%' } : { scales: {
                    x: { grid: { display: !!config.horizontal, color: '#edf2f7' }, ticks: { maxTicksLimit: 7, font: { size: 10 } } },
                    y: { beginAtZero: !config.horizontal, grid: { color: '#edf2f7' }, ticks: { precision: 0, font: { size: 10 } } },
                } }),
            },
        });
    } catch (error) {
        canvas.parentElement.querySelector('[data-chart-loading]')?.remove();
        const message = document.createElement('p');
        message.textContent = messages.chart;
        canvas.replaceWith(message);
    }
}

for (const box of document.querySelectorAll('[data-signature]')) {
    const canvas = box.querySelector('canvas');
    const pad = new SignaturePad(canvas, { minWidth: 1, maxWidth: 2.5, penColor: '#172B4D', backgroundColor: 'rgb(255,255,255)' });
    let oldWidth = 0;
    let oldHeight = 0;
    const resize = () => {
        const width = Math.floor(canvas.getBoundingClientRect().width);
        const height = Math.floor(canvas.getBoundingClientRect().height);
        if (!width || !height || (width === oldWidth && height === oldHeight)) return;
        const ratio = Math.min(window.devicePixelRatio || 1, 2, 2048 / width, 2048 / height);
        const strokes = pad.toData();
        const scaleX = oldWidth ? width / oldWidth : 1;
        const scaleY = oldHeight ? height / oldHeight : 1;
        for (const stroke of strokes) for (const point of stroke.points) { point.x *= scaleX; point.y *= scaleY; }
        canvas.width = Math.floor(width * ratio);
        canvas.height = Math.floor(height * ratio);
        canvas.getContext('2d').scale(ratio, ratio);
        pad.clear();
        if (strokes.length) pad.fromData(strokes);
        oldWidth = width; oldHeight = height;
    };
    new ResizeObserver(resize).observe(canvas.parentElement);
    resize();
    const guide = box.querySelector('.signing-guide');
    pad.addEventListener('beginStroke', () => { box.closest('form').dispatchEvent(new Event('signature-change')); guide.hidden = true; box.querySelector('.signature-error').hidden = true; });
    box.querySelector('[data-clear]').addEventListener('click', () => { pad.clear(); box.closest('form').dispatchEvent(new Event('signature-change')); box.querySelector('input[name="signature"]').value = ''; guide.hidden = false; });
    const expandButton = box.querySelector('[data-expand]');
    const originalLabel = expandButton.textContent;
    const collapse = () => { box.classList.remove('signature-expanded'); document.body.classList.remove('signing'); expandButton.textContent = originalLabel; };
    expandButton.addEventListener('click', () => {
        if (box.classList.contains('signature-expanded')) collapse();
        else {
            box.classList.add('signature-expanded'); document.body.classList.add('signing');
            expandButton.textContent = messages.returnForm;
        }
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') collapse(); });
    box.closest('form').addEventListener('submit', event => {
        if (pad.isEmpty()) {
            event.preventDefault();
            box.querySelector('.signature-error').hidden = false;
            canvas.focus();
        } else {
            box.querySelector('input[name="signature"]').value = pad.toDataURL('image/png');
        }
    });
}

initIdentifierValidation();
initBalanceValidation();
initWorkflow();
initPwa();
for (const form of document.querySelectorAll('form[data-once]:not([data-workflow])')) {
    form.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => { button.disabled = true; });
    });
}
window.addEventListener('pageshow', () => {
    for (const form of document.querySelectorAll('form[data-once]:not([data-workflow])')) {
        delete form.dataset.submitting; form.removeAttribute('aria-busy');
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => { button.disabled = false; });
    }
});
