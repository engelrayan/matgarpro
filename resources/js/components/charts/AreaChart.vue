<script setup lang="ts">
import { computed, ref } from 'vue';

interface Point {
    label: string;
    value: number;
}

const props = withDefaults(
    defineProps<{
        points: Point[];
        /** Formats the tooltip value; defaults to a plain thousands-separated number. */
        format?: (value: number) => string;
        /** CSS colour for the line and fill. Pass a token, e.g. `hsl(var(--primary))`. */
        color?: string;
        height?: number;
    }>(),
    {
        color: 'hsl(var(--primary))',
        height: 160,
    },
);

/*
 * Hand-drawn SVG rather than a charting library.
 *
 * A chart library is ~50KB of JS on a page that shows at most a few dozen
 * points, and every one of them ships its own opinions about fonts and colours
 * that then have to be fought back into the design system.
 */
const W = 600;
const PAD = { top: 8, right: 4, bottom: 4, left: 4 };

const max = computed(() => Math.max(1, ...props.points.map((p) => p.value)));

const coords = computed(() => {
    const n = props.points.length;
    const innerW = W - PAD.left - PAD.right;
    const innerH = props.height - PAD.top - PAD.bottom;

    return props.points.map((p, i) => ({
        ...p,
        // A single point has no span to divide; park it in the middle so it
        // does not collapse onto the left edge as a stray dot.
        x: n === 1 ? W / 2 : PAD.left + (i / (n - 1)) * innerW,
        y: PAD.top + innerH - (p.value / max.value) * innerH,
    }));
});

const linePath = computed(() =>
    coords.value.map((c, i) => `${i === 0 ? 'M' : 'L'}${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(' '),
);

const areaPath = computed(() => {
    if (!coords.value.length) return '';
    const first = coords.value[0];
    const last = coords.value[coords.value.length - 1];
    return `${linePath.value} L${last.x.toFixed(1)},${props.height} L${first.x.toFixed(1)},${props.height} Z`;
});

const hovered = ref<number | null>(null);

const fmt = (n: number) => (props.format ? props.format(n) : n.toLocaleString('en-US'));

// Unique gradient id per instance: two charts on one page would otherwise
// share a <defs> id and the second would silently reuse the first's colour.
const gradientId = `area-${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
    <div class="relative">
        <svg
            :viewBox="`0 0 ${W} ${height}`"
            preserveAspectRatio="none"
            class="w-full"
            :style="{ height: `${height}px` }"
            role="img"
        >
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="color" stop-opacity="0.22" />
                    <stop offset="100%" :stop-color="color" stop-opacity="0" />
                </linearGradient>
            </defs>

            <path :d="areaPath" :fill="`url(#${gradientId})`" />
            <path
                :d="linePath"
                fill="none"
                :stroke="color"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
                vector-effect="non-scaling-stroke"
            />

            <circle
                v-if="hovered !== null"
                :cx="coords[hovered].x"
                :cy="coords[hovered].y"
                r="4"
                :fill="color"
                vector-effect="non-scaling-stroke"
            />

            <!-- Invisible full-height hit areas: aiming at a 2px line is not
                 something anyone should have to do. -->
            <rect
                v-for="(c, i) in coords"
                :key="i"
                :x="c.x - W / Math.max(coords.length, 1) / 2"
                y="0"
                :width="W / Math.max(coords.length, 1)"
                :height="height"
                fill="transparent"
                @mouseenter="hovered = i"
                @mouseleave="hovered = null"
            />
        </svg>

        <div
            v-if="hovered !== null"
            class="pointer-events-none absolute -top-1 z-10 -translate-x-1/2 whitespace-nowrap rounded-lg bg-foreground px-2.5 py-1.5 text-xs font-medium text-background shadow-e2"
            :style="{ left: `${(coords[hovered].x / W) * 100}%` }"
        >
            <span class="tabular">{{ fmt(coords[hovered].value) }}</span>
            <span class="opacity-60"> · {{ coords[hovered].label }}</span>
        </div>

        <div class="mt-2 flex justify-between text-[10px] text-muted-foreground">
            <span>{{ points[0]?.label }}</span>
            <span>{{ points[points.length - 1]?.label }}</span>
        </div>
    </div>
</template>
