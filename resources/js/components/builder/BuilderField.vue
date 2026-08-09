<script setup lang="ts">
import ProductPicker from '@/components/builder/ProductPicker.vue';
import RichTextEditor from '@/components/products/RichTextEditor.vue';
import { ChevronDown, Plus, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/*
 * One setting, rendered from its schema.
 *
 * Recursive: a `repeater` renders this same component for each of its own
 * fields, which is what lets a footer column contain a list of links without a
 * second, near-identical component that drifts from this one.
 */

interface FieldSchema {
    key: string;
    type: string;
    label: string;
    hint?: string;
    default?: unknown;
    min?: number;
    max?: number;
    options?: { value: string; label: string }[];
    fields?: FieldSchema[];
    item_label?: string;
    when?: Record<string, unknown>;
}

const props = defineProps<{
    field: FieldSchema;
    modelValue: unknown;
    categories: { id: number; name: string }[];
}>();

const emit = defineEmits<{ 'update:modelValue': [unknown] }>();

const set = (value: unknown) => emit('update:modelValue', value);

// ── Repeater ─────────────────────────────────────────────────────────────
const items = computed(() => (Array.isArray(props.modelValue) ? (props.modelValue as Record<string, unknown>[]) : []));
const openItem = ref<number | null>(null);

const blankItem = () =>
    Object.fromEntries((props.field.fields ?? []).map((f) => [f.key, f.default ?? null]));

const addItem = () => {
    const next = [...items.value, blankItem()];
    set(next);
    openItem.value = next.length - 1;
};

const removeItem = (index: number) => {
    set(items.value.filter((_, i) => i !== index));
    openItem.value = null;
};

const patchItem = (index: number, key: string, value: unknown) => {
    set(items.value.map((item, i) => (i === index ? { ...item, [key]: value } : item)));
};

const moveItem = (index: number, delta: number) => {
    const target = index + delta;
    if (target < 0 || target >= items.value.length) return;

    const next = [...items.value];
    [next[index], next[target]] = [next[target], next[index]];
    set(next);
    openItem.value = target;
};

const itemTitle = (item: Record<string, unknown>, index: number) => {
    const key = props.field.item_label;
    const value = key ? item[key] : null;
    return (typeof value === 'string' && value.trim()) || `عنصر ${index + 1}`;
};

// ── Image ────────────────────────────────────────────────────────────────
const uploading = ref(false);
const uploadError = ref('');
const imageUrl = ref<string | null>(null);

const upload = async (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    uploading.value = true;
    uploadError.value = '';

    const body = new FormData();
    body.append('image', file);

    try {
        const response = await fetch('/builder/upload', {
            method: 'POST',
            body,
            headers: {
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        });

        const payload = await response.json();

        if (!response.ok) {
            // The server's own message, not a generic one: "الصورة كبيرة أوي"
            // tells the merchant what to do next, "فشل الرفع" does not.
            uploadError.value = payload?.message ?? payload?.errors?.image?.[0] ?? 'الرفع فشل.';
            return;
        }

        imageUrl.value = payload.url;
        set(payload.path);
    } catch {
        uploadError.value = 'الرفع فشل. جرّب تاني.';
    } finally {
        uploading.value = false;
    }
};

// A path saved earlier has no URL in the payload; the public disk's layout is
// stable, so it can be derived rather than round-tripped to the server.
const preview = computed(() => imageUrl.value ?? (props.modelValue ? `/storage/${props.modelValue}` : null));
</script>

<template>
    <div>
        <!-- ── Toggle ─────────────────────────────────────────────────── -->
        <label v-if="field.type === 'toggle'" class="flex cursor-pointer items-start gap-3">
            <input
                type="checkbox"
                class="mt-0.5 size-4 rounded border-input"
                :checked="!!modelValue"
                @change="set(($event.target as HTMLInputElement).checked)"
            />
            <span>
                <span class="text-sm font-medium">{{ field.label }}</span>
                <span v-if="field.hint" class="mt-0.5 block text-xs text-muted-foreground">{{ field.hint }}</span>
            </span>
        </label>

        <template v-else>
            <label class="field-label">{{ field.label }}</label>

            <!-- ── Text ───────────────────────────────────────────────── -->
            <input
                v-if="field.type === 'text' || field.type === 'link'"
                class="field"
                :value="modelValue ?? ''"
                :dir="field.type === 'link' ? 'ltr' : undefined"
                :placeholder="field.type === 'link' ? '/c/men أو https://…' : ''"
                @input="set(($event.target as HTMLInputElement).value)"
            />

            <textarea
                v-else-if="field.type === 'textarea'"
                class="field"
                rows="3"
                :value="(modelValue as string) ?? ''"
                @input="set(($event.target as HTMLTextAreaElement).value)"
            />

            <RichTextEditor
                v-else-if="field.type === 'richtext'"
                :model-value="(modelValue as string) ?? ''"
                @update:model-value="set($event)"
            />

            <input
                v-else-if="field.type === 'number'"
                type="number"
                class="field"
                :min="field.min"
                :max="field.max"
                :value="modelValue ?? field.default"
                @input="set(Number(($event.target as HTMLInputElement).value))"
            />

            <input
                v-else-if="field.type === 'datetime'"
                type="datetime-local"
                class="field"
                dir="ltr"
                :value="(modelValue as string)?.slice(0, 16) ?? ''"
                @input="set(($event.target as HTMLInputElement).value)"
            />

            <select
                v-else-if="field.type === 'select'"
                class="field"
                :value="modelValue"
                @change="set(($event.target as HTMLSelectElement).value)"
            >
                <option v-for="option in field.options" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>

            <select
                v-else-if="field.type === 'categories'"
                class="field"
                :value="(modelValue as number[])?.[0] ?? ''"
                @change="set(($event.target as HTMLSelectElement).value ? [Number(($event.target as HTMLSelectElement).value)] : [])"
            >
                <option value="">اختار قسم</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                    {{ category.name }}
                </option>
            </select>

            <!-- ── Products ───────────────────────────────────────────── -->
            <ProductPicker
                v-else-if="field.type === 'products'"
                :model-value="(modelValue as number[]) ?? []"
                :max="field.max ?? 12"
                @update:model-value="set($event)"
            />

            <!-- ── Image ──────────────────────────────────────────────── -->
            <div v-else-if="field.type === 'image'" class="space-y-2">
                <div v-if="preview" class="relative overflow-hidden rounded-xl border border-border">
                    <img :src="preview" alt="" class="h-28 w-full object-cover" />
                    <button
                        type="button"
                        class="absolute left-2 top-2 rounded-lg bg-black/60 p-1.5 text-white"
                        @click="set(null); imageUrl = null"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>

                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-border py-4 text-sm text-muted-foreground hover:bg-muted">
                    <Upload class="size-4" />
                    {{ uploading ? 'بيرفع…' : preview ? 'غيّر الصورة' : 'ارفع صورة' }}
                    <input type="file" accept="image/*" class="hidden" @change="upload" />
                </label>

                <p v-if="uploadError" class="field-error">{{ uploadError }}</p>
            </div>

            <!-- ── Repeater ───────────────────────────────────────────── -->
            <div v-else-if="field.type === 'repeater'" class="space-y-2">
                <div
                    v-for="(item, index) in items"
                    :key="index"
                    class="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <div class="flex items-center gap-1 px-2 py-2">
                        <!-- Up/down rather than drag: this list lives inside a
                             scrolling panel, where a drag that starts near the
                             edge fights the scroll on every attempt. -->
                        <div class="flex flex-col">
                            <button type="button" class="px-1 text-xs text-muted-foreground hover:text-foreground" @click="moveItem(index, -1)">▲</button>
                            <button type="button" class="px-1 text-xs text-muted-foreground hover:text-foreground" @click="moveItem(index, 1)">▼</button>
                        </div>

                        <button
                            type="button"
                            class="flex-1 truncate px-1 text-right text-sm font-medium"
                            @click="openItem = openItem === index ? null : index"
                        >
                            {{ itemTitle(item, index) }}
                        </button>

                        <ChevronDown class="size-4 shrink-0 text-muted-foreground transition-transform" :class="openItem === index ? 'rotate-180' : ''" />

                        <button type="button" class="p-1.5 text-destructive" @click="removeItem(index)">
                            <Trash2 class="size-4" />
                        </button>
                    </div>

                    <div v-if="openItem === index" class="space-y-3 border-t border-border p-3">
                        <BuilderField
                            v-for="sub in field.fields"
                            :key="sub.key"
                            :field="sub"
                            :categories="categories"
                            :model-value="item[sub.key]"
                            @update:model-value="patchItem(index, sub.key, $event)"
                        />
                    </div>
                </div>

                <button
                    v-if="items.length < (field.max ?? 10)"
                    type="button"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-border py-2.5 text-sm text-muted-foreground hover:bg-muted"
                    @click="addItem"
                >
                    <Plus class="size-4" />
                    أضف
                </button>
            </div>

            <p v-if="field.hint" class="field-hint">{{ field.hint }}</p>
        </template>
    </div>
</template>
