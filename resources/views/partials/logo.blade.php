{{-- متجر برو mark, for Blade pages.

     Kept identical to resources/js/components/AppLogoIcon.vue on purpose: the
     marketing site and the dashboard must show the same shape, and an <img>
     would not inherit the surrounding colour the way the Vue one does.

     `$class` sets the size and the body colour; the gold stays gold. --}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" class="{{ $class ?? 'size-9 text-primary' }}" aria-hidden="true">
    <rect width="40" height="40" rx="11" fill="currentColor" />

    <path d="M6.5 13.4c0-2.5 1.7-4.6 3.7-4.6h19.6c2 0 3.7 2.1 3.7 4.6H6.5Z" fill="hsl(var(--gold-400))" />
    <path d="M6.5 13.4h27v1.1c0 .8-.6 1.4-1.4 1.4H7.9c-.8 0-1.4-.6-1.4-1.4v-1.1Z" fill="hsl(var(--gold-300))" />

    <path d="m20 18.6 4.1 3.5a1.5 1.5 0 0 1-1.9 2.3L20 22.6l-2.2 1.8a1.5 1.5 0 1 1-1.9-2.3l4.1-3.5Z" fill="hsl(var(--gold-300))" />

    <path d="M20 25.4c-3.4 0-6.1 2.7-6.1 6.1V34h12.2v-2.5c0-3.4-2.7-6.1-6.1-6.1Z" fill="hsl(var(--jade-950))" fill-opacity=".3" />
    <path d="M20 26.6a4.4 4.4 0 0 0-4.4 4.4V34h8.8v-3a4.4 4.4 0 0 0-4.4-4.4Z" fill="hsl(var(--gold-100))" />
</svg>
