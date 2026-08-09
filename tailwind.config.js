import defaultTheme from 'tailwindcss/defaultTheme';

/**
 * Colour tokens live in `resources/css/app.css` as raw `H S% L%` triplets.
 * This helper wires each one up so opacity modifiers keep working
 * (`bg-jade-600/40`, `text-primary/70`).
 */
const token = (name) => `hsl(var(--${name}) / <alpha-value>)`;

const scale = (name) =>
    Object.fromEntries(
        [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950].map((step) => [
            step,
            token(`${name}-${step}`),
        ]),
    );

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js,ts,jsx,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                // One family for Arabic and Latin keeps mixed strings ("12 طلب")
                // on a single baseline — the usual giveaway of a cheap RTL UI.
                /*
                 | `--font-sans` is set by the storefront theme, and defaults
                 | to IBM Plex Sans Arabic in app.css for the dashboard and
                 | every other surface that has no theme.
                 |
                 | The default MUST live in CSS, not as a second entry here: an
                 | undefined custom property makes the whole `font-family`
                 | declaration invalid, so the browser drops it entirely rather
                 | than falling through to the next name in the list.
                 */
                sans: ['var(--font-sans)', ...defaultTheme.fontFamily.sans],
                mono: ['IBM Plex Mono', ...defaultTheme.fontFamily.mono],
            },
            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
                '2xl': 'calc(var(--radius) + 6px)',
                '3xl': 'calc(var(--radius) + 14px)',
            },
            colors: {
                jade: scale('jade'),
                gold: scale('gold'),
                sand: scale('sand'),

                background: token('background'),
                foreground: token('foreground'),
                card: {
                    DEFAULT: token('card'),
                    foreground: token('card-foreground'),
                },
                popover: {
                    DEFAULT: token('popover'),
                    foreground: token('popover-foreground'),
                },
                primary: {
                    DEFAULT: token('primary'),
                    foreground: token('primary-foreground'),
                },
                secondary: {
                    DEFAULT: token('secondary'),
                    foreground: token('secondary-foreground'),
                },
                muted: {
                    DEFAULT: token('muted'),
                    foreground: token('muted-foreground'),
                },
                accent: {
                    DEFAULT: token('accent'),
                    foreground: token('accent-foreground'),
                },
                success: {
                    DEFAULT: token('success'),
                    foreground: token('success-foreground'),
                },
                warning: {
                    DEFAULT: token('warning'),
                    foreground: token('warning-foreground'),
                },
                destructive: {
                    DEFAULT: token('destructive'),
                    foreground: token('destructive-foreground'),
                },
                info: {
                    DEFAULT: token('info'),
                    foreground: token('info-foreground'),
                },
                border: token('border'),
                input: token('input'),
                ring: token('ring'),
                chart: {
                    1: token('chart-1'),
                    2: token('chart-2'),
                    3: token('chart-3'),
                    4: token('chart-4'),
                    5: token('chart-5'),
                    6: token('chart-6'),
                },
                sidebar: {
                    DEFAULT: token('sidebar-background'),
                    foreground: token('sidebar-foreground'),
                    primary: token('sidebar-primary'),
                    'primary-foreground': token('sidebar-primary-foreground'),
                    accent: token('sidebar-accent'),
                    'accent-foreground': token('sidebar-accent-foreground'),
                    border: token('sidebar-border'),
                    ring: token('sidebar-ring'),
                },
            },
            /**
             * A four-step elevation ramp. Shadows are tinted with the ink hue
             * instead of pure black — black shadows on a warm canvas read grey
             * and muddy. e1 cards, e2 raised panels, e3 popovers, e4 modals.
             * `glow` is brand emphasis only, never a substitute for depth.
             */
            boxShadow: {
                e1: '0 1px 2px hsl(160 18% 6% / 0.04), 0 1px 3px hsl(160 18% 6% / 0.06)',
                e2: '0 2px 4px hsl(160 18% 6% / 0.04), 0 4px 12px hsl(160 18% 6% / 0.08)',
                e3: '0 4px 8px hsl(160 18% 6% / 0.05), 0 12px 28px hsl(160 18% 6% / 0.10)',
                e4: '0 8px 16px hsl(160 18% 6% / 0.06), 0 24px 56px hsl(160 18% 6% / 0.14)',
                glow: '0 0 0 1px hsl(var(--jade-500) / 0.25), 0 8px 32px hsl(var(--jade-500) / 0.22)',
                'glow-gold':
                    '0 0 0 1px hsl(var(--gold-400) / 0.35), 0 8px 32px hsl(var(--gold-400) / 0.25)',
            },
            keyframes: {
                'fade-up': {
                    from: { opacity: '0', transform: 'translateY(8px)' },
                    to: { opacity: '1', transform: 'translateY(0)' },
                },
                'scale-in': {
                    from: { opacity: '0', transform: 'scale(0.96)' },
                    to: { opacity: '1', transform: 'scale(1)' },
                },
            },
            animation: {
                'fade-up': 'fade-up 0.4s cubic-bezier(0.16, 1, 0.3, 1) both',
                'scale-in': 'scale-in 0.2s cubic-bezier(0.16, 1, 0.3, 1) both',
            },
            transitionTimingFunction: {
                // A single easing across the product; mixed easings read sloppy.
                brand: 'cubic-bezier(0.16, 1, 0.3, 1)',
            },
        },
    },
    plugins: [require('tailwindcss-animate')],
};
