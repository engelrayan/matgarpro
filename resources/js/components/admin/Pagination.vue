<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * Laravel's paginator links, rendered.
 *
 * `preserve-scroll` on purpose: an operator paging through a long store list
 * is reading the table, not the page header, and being thrown back to the top
 * on every page turn loses their place.
 */
defineProps<{ links: PageLink[]; total: number; from: number | null; to: number | null }>();
</script>

<template>
    <div v-if="links.length > 3" class="flex flex-wrap items-center justify-between gap-3 pt-4">
        <p class="text-sm text-muted-foreground">
            <span class="tabular">{{ from ?? 0 }}–{{ to ?? 0 }}</span> من
            <span class="tabular">{{ total }}</span>
        </p>

        <div class="flex flex-wrap items-center gap-1">
            <template v-for="(link, i) in links" :key="i">
                <span
                    v-if="!link.url"
                    class="rounded-lg px-3 py-1.5 text-sm text-muted-foreground/50"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    class="rounded-lg px-3 py-1.5 text-sm transition-colors"
                    :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
