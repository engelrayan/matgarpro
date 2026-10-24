<script lang="ts" setup>
import { cn } from '@/lib/utils';
import { ChevronLeft } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <li role="presentation" aria-hidden="true" :class="cn('[&>svg]:h-3.5 [&>svg]:w-3.5', props.class)">
        <slot>
            <!-- Left, not right: in an RTL trail the next crumb sits to the left. -->
            <ChevronLeft />
        </slot>
    </li>
</template>
