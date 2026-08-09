<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';

/**
 * One headline number.
 *
 * `hint` exists because most platform figures are meaningless alone — "١٢٤
 * متجر" is a different fact depending on whether three of them opened this
 * week or a hundred did.
 */
withDefaults(
    defineProps<{
        label: string;
        value: string;
        hint?: string;
        icon?: LucideIcon;
        /** Money-bearing tiles get the gold hairline; nothing else does. */
        lux?: boolean;
        href?: string;
    }>(),
    { lux: false },
);
</script>

<template>
    <component
        :is="href ? 'a' : 'div'"
        :href="href"
        :class="[lux ? 'surface-lux' : 'surface', 'flex flex-col gap-1 p-5', href ? 'transition-shadow hover:shadow-e2' : '']"
    >
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm text-muted-foreground">{{ label }}</p>
            <component :is="icon" v-if="icon" class="size-4 shrink-0 text-muted-foreground" />
        </div>
        <p class="tabular text-2xl font-semibold tracking-tight">{{ value }}</p>
        <p v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>
    </component>
</template>
