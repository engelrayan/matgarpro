<script setup lang="ts">
import { computed } from 'vue';

interface Stage {
    label: string;
    value: number;
}

const props = defineProps<{ stages: Stage[] }>();

const top = computed(() => Math.max(1, props.stages[0]?.value ?? 0));

/**
 * Two different percentages, because merchants confuse them constantly:
 * `share` is of everyone who arrived, `step` is of the previous stage only.
 * The second is what tells you which step is leaking.
 */
const rows = computed(() =>
    props.stages.map((stage, i) => {
        const previous = i === 0 ? stage.value : props.stages[i - 1].value;

        return {
            ...stage,
            share: Math.round((stage.value / top.value) * 100),
            step: i === 0 ? 100 : previous > 0 ? Math.round((stage.value / previous) * 100) : 0,
            isFirst: i === 0,
        };
    }),
);
</script>

<template>
    <div class="space-y-3">
        <div v-for="row in rows" :key="row.label">
            <div class="mb-1.5 flex items-baseline justify-between text-sm">
                <span class="text-muted-foreground">{{ row.label }}</span>
                <span class="flex items-baseline gap-2">
                    <span class="tabular font-semibold">{{ row.value.toLocaleString('en-US') }}</span>
                    <span v-if="!row.isFirst" class="tabular text-xs text-muted-foreground">
                        {{ row.step }}%
                    </span>
                </span>
            </div>

            <div class="h-2.5 overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full bg-primary transition-[width] duration-500"
                    :style="{ width: `${Math.max(row.share, row.value > 0 ? 2 : 0)}%` }"
                />
            </div>
        </div>
    </div>
</template>
