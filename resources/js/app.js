import './bootstrap';
import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;
Alpine.start();

let observer = null;

// createIcons() copies data-lucide onto the <svg> it generates, so an unqualified
// [data-lucide] check would keep matching icons that are already rendered. Match
// only elements that haven't been converted yet, and stop observing while we
// convert, so our own DOM writes can't retrigger this.
const renderIcons = () => {
    if (!document.querySelector('[data-lucide]:not(svg)')) return;

    observer?.disconnect();
    createIcons({ icons });
    observer?.observe(document.body, { childList: true, subtree: true });
};

window.lucide = { createIcons: renderIcons };

document.addEventListener('DOMContentLoaded', () => {
    let queued = false;

    observer = new MutationObserver(() => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            renderIcons();
        });
    });

    // Alpine's x-if/x-for rebuild nodes, restoring raw <i data-lucide> markup that
    // the initial pass already converted; re-render so those icons don't vanish.
    renderIcons();
    observer.observe(document.body, { childList: true, subtree: true });
});
