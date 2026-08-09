{{-- One timer script for every countdown on the page.

     Previously this lived inside the deals partial and grabbed `[data-countdown]`
     with querySelector — singular. The moment a page can carry both a deals
     block and a countdown section, that version animates the first one and
     leaves the second showing dashes forever. --}}
@once
@push('scripts')
<script>
(function () {
    const pad = (n) => String(n).padStart(2, '0');

    document.querySelectorAll('[data-countdown]').forEach((box) => {
        const target = new Date(box.dataset.countdown).getTime();
        if (Number.isNaN(target)) return;

        const cells = {
            d: box.querySelector('[data-countdown-d]'),
            h: box.querySelector('[data-countdown-h]'),
            m: box.querySelector('[data-countdown-m]'),
            s: box.querySelector('[data-countdown-s]'),
        };

        function tick() {
            const left = target - Date.now();

            if (left <= 0) {
                /*
                 * The offer is over. The timer is removed rather than frozen on
                 * zeros — a countdown that has finished and stayed on screen is
                 * how customers learn that a store's timers mean nothing. The
                 * server already stops listing expired deals, so a reload drops
                 * the whole block.
                 */
                box.remove();
                clearInterval(timer);
                return;
            }

            const s = Math.floor(left / 1000);
            if (cells.d) cells.d.textContent = pad(Math.floor(s / 86400));
            if (cells.h) cells.h.textContent = pad(Math.floor((s % 86400) / 3600));
            if (cells.m) cells.m.textContent = pad(Math.floor((s % 3600) / 60));
            if (cells.s) cells.s.textContent = pad(s % 60);
        }

        tick();
        const timer = setInterval(tick, 1000);
    });
})();
</script>
@endpush
@endonce
