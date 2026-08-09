<script setup lang="ts">
/*
 * Settings pages are reached from the app sidebar, which lists every one of
 * them by name. This layout used to carry a second nav repeating those links —
 * two navigations for one app, and it squeezed every settings page into a
 * narrow column beside a list nobody needed.
 *
 * `width="wide"` opts a page out of the reading-width cap: forms want a narrow
 * column, but a gallery of theme cards wants the room.
 */
withDefaults(defineProps<{ width?: 'narrow' | 'wide' }>(), {
    width: 'narrow',
});
</script>

<template>
    <div class="mx-auto p-4 md:p-6" :class="width === 'wide' ? 'max-w-6xl' : 'max-w-2xl'">
        <slot />
    </div>
</template>
