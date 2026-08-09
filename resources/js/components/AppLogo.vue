<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <!-- The drawn mark, not a letter in a box: one shape used everywhere means
         the sidebar, the auth screen and the marketing site cannot drift apart. -->
    <AppLogoIcon class="size-8 shrink-0 text-sidebar-accent" />

    <div class="grid flex-1 text-right text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none">متجر برو</span>
    </div>
</template>
