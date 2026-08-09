<script setup lang="ts">
import { Plus, Search, X } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

/**
 * Picks products by hand, in an order the merchant controls.
 *
 * Two separate fetches on purpose: one resolves the ids already saved (so a
 * selection survives a reload even when those products would not match the
 * current search), and one searches. Trying to serve both from a single list
 * is how a picker ends up quietly dropping a selected product the moment
 * somebody types.
 */
interface Option {
    id: number;
    name: string;
    price: number;
    image: string | null;
}

const props = defineProps<{ modelValue: number[]; max: number }>();
const emit = defineEmits<{ 'update:modelValue': [number[]] }>();

const selected = ref<Option[]>([]);
const results = ref<Option[]>([]);
const term = ref('');
const open = ref(false);

const fetchProducts = async (params: URLSearchParams): Promise<Option[]> => {
    const response = await fetch(`/builder/products?${params}`, { headers: { Accept: 'application/json' } });
    return response.ok ? await response.json() : [];
};

const loadSelected = async () => {
    if (props.modelValue.length === 0) {
        selected.value = [];
        return;
    }

    const params = new URLSearchParams();
    props.modelValue.forEach((id) => params.append('ids[]', String(id)));

    const found = await fetchProducts(params);
    // Re-ordered to the merchant's sequence — the endpoint returns catalogue
    // order, and this list IS the order products appear on the storefront.
    selected.value = props.modelValue.map((id) => found.find((p) => p.id === id)).filter(Boolean) as Option[];
};

const search = async () => {
    results.value = await fetchProducts(new URLSearchParams({ q: term.value }));
};

let timer: ReturnType<typeof setTimeout>;
watch(term, () => {
    clearTimeout(timer);
    timer = setTimeout(search, 250);
});

watch(() => props.modelValue, loadSelected);
onMounted(loadSelected);

const add = (option: Option) => {
    if (props.modelValue.includes(option.id) || props.modelValue.length >= props.max) return;
    emit('update:modelValue', [...props.modelValue, option.id]);
};

const remove = (id: number) => emit('update:modelValue', props.modelValue.filter((v) => v !== id));

const move = (index: number, delta: number) => {
    const target = index + delta;
    if (target < 0 || target >= props.modelValue.length) return;

    const next = [...props.modelValue];
    [next[index], next[target]] = [next[target], next[index]];
    emit('update:modelValue', next);
};

const toggleSearch = () => {
    open.value = !open.value;
    if (open.value && results.value.length === 0) search();
};
</script>

<template>
    <div class="space-y-2">
        <div
            v-for="(product, index) in selected"
            :key="product.id"
            class="flex items-center gap-2 rounded-xl border border-border bg-card p-2"
        >
            <div class="flex flex-col">
                <button type="button" class="px-1 text-xs text-muted-foreground hover:text-foreground" @click="move(index, -1)">▲</button>
                <button type="button" class="px-1 text-xs text-muted-foreground hover:text-foreground" @click="move(index, 1)">▼</button>
            </div>

            <img v-if="product.image" :src="product.image" alt="" class="size-9 shrink-0 rounded-lg object-cover" />
            <div v-else class="size-9 shrink-0 rounded-lg bg-muted" />

            <p class="min-w-0 flex-1 truncate text-sm">{{ product.name }}</p>

            <button type="button" class="p-1.5 text-muted-foreground hover:text-destructive" @click="remove(product.id)">
                <X class="size-4" />
            </button>
        </div>

        <p v-if="selected.length === 0" class="rounded-xl bg-muted/60 px-3 py-2 text-xs text-muted-foreground">
            لسه ما اخترتش منتجات — الجزء ده مش هيظهر في متجرك.
        </p>

        <button
            v-if="selected.length < max"
            type="button"
            class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-border py-2.5 text-sm text-muted-foreground hover:bg-muted"
            @click="toggleSearch"
        >
            <Plus class="size-4" />
            اختار منتج
        </button>

        <div v-if="open" class="rounded-xl border border-border bg-card p-2">
            <div class="relative">
                <Search class="absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input v-model="term" class="field pr-9" placeholder="دوّر بالاسم" />
            </div>

            <ul class="mt-2 max-h-56 space-y-1 overflow-y-auto">
                <li v-for="option in results" :key="option.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 rounded-lg p-1.5 text-right transition-colors hover:bg-muted disabled:opacity-40"
                        :disabled="modelValue.includes(option.id)"
                        @click="add(option)"
                    >
                        <img v-if="option.image" :src="option.image" alt="" class="size-8 shrink-0 rounded-md object-cover" />
                        <div v-else class="size-8 shrink-0 rounded-md bg-muted" />
                        <span class="min-w-0 flex-1 truncate text-sm">{{ option.name }}</span>
                    </button>
                </li>
                <li v-if="results.length === 0" class="px-2 py-3 text-center text-xs text-muted-foreground">
                    مفيش نتايج.
                </li>
            </ul>
        </div>
    </div>
</template>
