<script setup lang="ts">
import { Bold, Italic, Link2, List, ListOrdered, Type, Underline } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

const model = defineModel<string>({ required: true });

/*
 * A small contenteditable editor, not a library.
 *
 * The alternative is ~100KB of JS on the dashboard for bold, italic and a
 * bullet list. The description is a sales pitch, not a document — this covers
 * what merchants actually write and nothing else.
 *
 * Whatever lands here is sanitised again on the server. `contenteditable`
 * accepts pasted HTML, and the browser is not a trust boundary.
 */
const editor = ref<HTMLElement>();
const focused = ref(false);

// Guards the watcher below: writing model back into the element while the
// merchant is typing would reset the caret to the start on every keystroke.
let internalChange = false;

const sync = () => {
    internalChange = true;
    model.value = editor.value?.innerHTML ?? '';
};

watch(model, (value) => {
    if (internalChange) {
        internalChange = false;
        return;
    }

    if (editor.value && editor.value.innerHTML !== value) {
        editor.value.innerHTML = value ?? '';
    }
});

onMounted(() => {
    if (editor.value) {
        editor.value.innerHTML = model.value ?? '';
    }
});

const exec = (command: string, value?: string) => {
    editor.value?.focus();
    document.execCommand(command, false, value);
    sync();
};

const addLink = () => {
    const url = window.prompt('اكتب الرابط:');
    if (!url) return;

    // Only http(s). `javascript:` in an href is a script the customer runs.
    if (!/^https?:\/\//i.test(url)) {
        window.alert('الرابط لازم يبدأ بـ http:// أو https://');
        return;
    }

    exec('createLink', url);
};

/*
 * Paste as plain text.
 *
 * Merchants paste from Word and supplier sites, which carries in fonts,
 * colours and background images that fight the store's theme — and the store
 * ends up looking like the page it was copied from.
 */
const onPaste = (event: ClipboardEvent) => {
    event.preventDefault();
    const text = event.clipboardData?.getData('text/plain') ?? '';
    document.execCommand('insertText', false, text);
    sync();
};

const tools = [
    { icon: Bold, title: 'عريض', run: () => exec('bold') },
    { icon: Italic, title: 'مائل', run: () => exec('italic') },
    { icon: Underline, title: 'تحته خط', run: () => exec('underline') },
    { icon: Type, title: 'عنوان', run: () => exec('formatBlock', '<h3>') },
    { icon: List, title: 'نقط', run: () => exec('insertUnorderedList') },
    { icon: ListOrdered, title: 'أرقام', run: () => exec('insertOrderedList') },
    { icon: Link2, title: 'رابط', run: addLink },
];
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border bg-card transition-colors"
        :class="focused ? 'border-ring ring-2 ring-ring/25' : 'border-input'"
    >
        <div class="flex flex-wrap items-center gap-0.5 border-b border-border bg-muted/40 p-1.5">
            <button
                v-for="tool in tools"
                :key="tool.title"
                type="button"
                class="btn-ghost px-2 py-1.5"
                :title="tool.title"
                @mousedown.prevent
                @click="tool.run"
            >
                <component :is="tool.icon" class="size-4" />
            </button>
        </div>

        <div
            ref="editor"
            contenteditable="true"
            dir="rtl"
            class="prose-storefront min-h-40 px-3.5 py-3 text-sm focus:outline-none"
            @input="sync"
            @blur="focused = false"
            @focus="focused = true"
            @paste="onPaste"
        />
    </div>
</template>

<style scoped>
.prose-storefront :deep(h3) {
    @apply mb-1.5 mt-3 text-base font-bold;
}

.prose-storefront :deep(ul) {
    @apply my-2 list-disc space-y-1 pr-5;
}

.prose-storefront :deep(ol) {
    @apply my-2 list-decimal space-y-1 pr-5;
}

.prose-storefront :deep(a) {
    @apply text-primary underline;
}

.prose-storefront:empty::before {
    content: 'اكتب وصف المنتج — إيه اللي يخلّي العميل يشتريه؟';
    @apply text-muted-foreground/70;
}
</style>
