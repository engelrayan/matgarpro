<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const props = defineProps<{
    orderId: number;
    field: string;
    modelValue: string | null;
    /** Rendered instead of the raw value when the cell is not being edited. */
    display?: string | null;
    type?: 'text' | 'tel';
    align?: 'start' | 'end';
    placeholder?: string;
}>();

/*
 * One spreadsheet cell.
 *
 * Saves on blur and on Enter, and rolls back on failure. Optimistic — the
 * merchant is editing dozens of rows in a pass, and a spinner per cell would
 * make the screen feel slower than the paper it replaced.
 */
const editing = ref(false);
const draft = ref(props.modelValue ?? '');
const shown = ref(props.modelValue ?? '');
const error = ref<string | null>(null);
const input = ref<HTMLInputElement>();

const start = async () => {
    if (editing.value) return;
    draft.value = shown.value;
    error.value = null;
    editing.value = true;
    await nextTick();
    input.value?.select();
};

const cancel = () => {
    draft.value = shown.value;
    editing.value = false;
    error.value = null;
};

const commit = () => {
    if (!editing.value) return;

    const value = draft.value.trim();
    editing.value = false;

    if (value === (shown.value ?? '')) return;

    const previous = shown.value;
    shown.value = value;

    router.patch(
        `/orders/${props.orderId}`,
        { [props.field]: value },
        {
            preserveScroll: true,
            preserveState: true,
            // The whole point of an inline edit is that the page does not move
            // underneath you; re-rendering the grid would lose the merchant's
            // place in a 50-row list.
            only: [],
            onError: (errors) => {
                shown.value = previous;
                error.value = Object.values(errors)[0] ?? 'مش قادر أحفظ.';
            },
        },
    );
};
</script>

<template>
    <td
        class="relative border-b border-border p-0"
        :class="align === 'end' ? 'text-left' : 'text-right'"
        @dblclick="start"
    >
        <input
            v-if="editing"
            ref="input"
            v-model="draft"
            :type="type === 'tel' ? 'tel' : 'text'"
            :dir="type === 'tel' ? 'ltr' : undefined"
            class="w-full border-2 border-primary bg-card px-3 py-2 text-sm focus:outline-none"
            @blur="commit"
            @keydown.enter.prevent="commit"
            @keydown.esc.prevent="cancel"
            @keydown.tab="commit"
        />

        <button
            v-else
            type="button"
            class="w-full cursor-text px-3 py-2 text-right text-sm transition-colors hover:bg-muted/60"
            :class="{ 'text-destructive': error }"
            :title="error ?? 'دوس مرتين للتعديل'"
            @click="start"
        >
            <span v-if="display ?? shown" :dir="type === 'tel' ? 'ltr' : undefined" class="block truncate">
                {{ display ?? shown }}
            </span>
            <span v-else class="block text-muted-foreground/50">{{ placeholder ?? '—' }}</span>
        </button>

        <span v-if="error" class="absolute -bottom-px right-0 h-0.5 w-full bg-destructive"></span>
    </td>
</template>
