<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <main
        :class="
            cn(
                // `min-w-0`: a flex child otherwise grows to its widest nowrap line,
                // and one long product name made the whole page scroll sideways.
                'relative flex min-h-svh min-w-0 flex-1 flex-col bg-grouped',
                // Clear of the phone tab bar, and of the home indicator under it.
                'pb-[calc(env(safe-area-inset-bottom)+4.75rem)] md:pb-0',
                // `mr-0`, not the upstream `ml-0`: the sidebar sits on the right
                // in this RTL app, so the inset panel must close the gap on the
                // right. Left margins here leave the content underneath the rail.
                'peer-data-[variant=inset]:min-h-[calc(100svh-theme(spacing.4))] md:peer-data-[variant=inset]:m-2 md:peer-data-[state=collapsed]:peer-data-[variant=inset]:mr-2 md:peer-data-[variant=inset]:mr-0 md:peer-data-[variant=inset]:rounded-[14px] md:peer-data-[variant=inset]:shadow-[0_0_0_0.5px_hsl(var(--foreground)/0.08),0_2px_8px_-2px_hsl(var(--foreground)/0.06)]',
                props.class,
            )
        "
    >
        <slot />
    </main>
</template>
