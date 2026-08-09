<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<!--
    متجر برو mark — a shopfront: a gold awning over an arched doorway, with a
    gold chevron above the arch for the "Pro".

    The body inherits `currentColor` so the mark picks up whatever it sits in,
    while the awning and chevron are pinned to the gold tokens. Gold is the
    brand's accent for value, and it has to read as gold on a deep jade panel
    and on ivory alike.

    Drawn rather than lettered: a mark built from the word "م" cannot shrink to
    a favicon or sit on a courier's waybill without turning to mush.
-->
<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" :class="className" v-bind="$attrs">
        <rect width="40" height="40" rx="11" fill="currentColor" />

        <!-- Awning -->
        <path d="M6.5 13.4c0-2.5 1.7-4.6 3.7-4.6h19.6c2 0 3.7 2.1 3.7 4.6H6.5Z" fill="hsl(var(--gold-400))" />
        <path d="M6.5 13.4h27v1.1c0 .8-.6 1.4-1.4 1.4H7.9c-.8 0-1.4-.6-1.4-1.4v-1.1Z" fill="hsl(var(--gold-300))" />

        <!-- The "Pro" chevron, sitting in the shopfront's upper wall. -->
        <path
            d="m20 18.6 4.1 3.5a1.5 1.5 0 0 1-1.9 2.3L20 22.6l-2.2 1.8a1.5 1.5 0 1 1-1.9-2.3l4.1-3.5Z"
            fill="hsl(var(--gold-300))"
        />

        <!-- Arched doorway -->
        <path d="M20 25.4c-3.4 0-6.1 2.7-6.1 6.1V34h12.2v-2.5c0-3.4-2.7-6.1-6.1-6.1Z" fill="hsl(var(--jade-950))" fill-opacity=".3" />
        <path d="M20 26.6a4.4 4.4 0 0 0-4.4 4.4V34h8.8v-3a4.4 4.4 0 0 0-4.4-4.4Z" fill="hsl(var(--gold-100))" />
    </svg>
</template>
