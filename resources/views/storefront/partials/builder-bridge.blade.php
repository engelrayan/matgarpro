{{-- The preview's half of the conversation with the builder.

     Loaded only when the request carried a valid preview token, so nothing here
     ever reaches a customer. It does three things and nothing else:

       · outlines a section on hover and reports a click, so the merchant can
         point at the page instead of hunting the list for the right row;
       · restores the scroll position after a reload, because the builder
         reloads this frame on every change and losing your place after every
         keystroke makes the tool unusable;
       · swallows navigation, so clicking a product card inside the preview
         does not take the merchant out of the editor.

     `postMessage` targets are checked on both ends — the frame accepts
     instructions only from its own opener's origin. --}}
<style>
    [data-section-id] { position: relative; }

    [data-section-id].is-hovered::after,
    [data-section-id].is-selected::after {
        content: '';
        position: absolute;
        inset: 0;
        pointer-events: none;
        z-index: 30;
        border: 2px solid hsl(var(--primary));
        border-radius: 4px;
    }

    [data-section-id].is-hovered::after { border-style: dashed; opacity: .6; }
    [data-section-id].is-selected::after { box-shadow: 0 0 0 4px hsl(var(--primary) / .15); }
</style>

<script>
(function () {
    const parentOrigin = @json($builderOrigin);
    const send = (message) => window.parent?.postMessage({ source: 'matgar-preview', ...message }, parentOrigin);

    const sections = () => [...document.querySelectorAll('[data-section-id]')];

    // ── Scroll restore ──────────────────────────────────────────────────
    const KEY = 'matgar-preview-scroll';
    const saved = sessionStorage.getItem(KEY);
    if (saved) window.scrollTo(0, parseInt(saved, 10) || 0);

    window.addEventListener('scroll', () => {
        sessionStorage.setItem(KEY, String(window.scrollY));
    }, { passive: true });

    // ── Hover + click ───────────────────────────────────────────────────
    sections().forEach((el) => {
        el.addEventListener('mouseenter', () => el.classList.add('is-hovered'));
        el.addEventListener('mouseleave', () => el.classList.remove('is-hovered'));

        el.addEventListener('click', (event) => {
            // The merchant is arranging a page, not shopping it. Every link in
            // here would otherwise navigate the frame away from the layout
            // they are working on.
            event.preventDefault();
            event.stopPropagation();
            send({ type: 'select', id: el.dataset.sectionId });
        }, true);
    });

    // ── Instructions from the builder ───────────────────────────────────
    window.addEventListener('message', (event) => {
        if (event.origin !== parentOrigin || event.data?.source !== 'matgar-builder') return;

        if (event.data.type === 'select') {
            sections().forEach((el) => {
                const isTarget = el.dataset.sectionId === event.data.id;
                el.classList.toggle('is-selected', isTarget);
                if (isTarget) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        }
    });

    send({ type: 'ready', sections: sections().map((el) => el.dataset.sectionId) });
})();
</script>
