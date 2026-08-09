<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Copy, ExternalLink, Package, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Product {
    id: number;
    name: string;
    price: string;
    compare_at_price: string | null;
    stock: number;
    variants_count: number;
    track_stock: boolean;
    status: 'draft' | 'active';
    image: string | null;
    url: string;
    created_at: string;
}

const props = defineProps<{
    products: { data: Product[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    filters: { q: string };
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'المنتجات', href: '/products' }];

const q = ref(props.filters.q ?? '');
let debounce: ReturnType<typeof setTimeout>;

watch(q, (value) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/products', { q: value || undefined }, { preserveState: true, replace: true });
    }, 300);
});

const busyId = ref<number | null>(null);

const duplicate = (product: Product) => {
    busyId.value = product.id;
    router.post(`/products/${product.id}/duplicate`, {}, { onFinish: () => (busyId.value = null) });
};

const destroy = (product: Product) => {
    if (!confirm(`تحذف «${product.name}»؟ الطلبات القديمة هتفضل زي ما هي.`)) return;
    busyId.value = product.id;
    router.delete(`/products/${product.id}`, { onFinish: () => (busyId.value = null) });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="المنتجات" />

        <div class="px-4 py-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">المنتجات</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ products.total }} منتج</p>
                </div>

                <Link href="/products/create" class="btn-primary">
                    <Plus class="h-4 w-4" />
                    أضف منتج
                </Link>
            </div>

            <div class="relative mb-5 max-w-sm">
                <Search class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <input v-model="q" class="field pr-10" placeholder="دوّر باسم المنتج أو الـ SKU" />
            </div>

            <!-- Empty state does the teaching: a merchant with no products needs
                 a next step, not an apology. -->
            <div v-if="!products.data.length" class="surface p-12 text-center">
                <Package class="mx-auto h-10 w-10 text-muted-foreground/50" />
                <h2 class="mt-4 font-semibold">{{ q ? 'مفيش نتايج' : 'لسه مفيش منتجات' }}</h2>
                <p class="mx-auto mt-2 max-w-sm text-sm text-muted-foreground">
                    {{ q ? 'جرّب كلمة تانية.' : 'ضيف أول منتج — اسم وسعر وصورة وخلاص. الباقي اختياري.' }}
                </p>
                <Link v-if="!q" href="/products/create" class="btn-primary mt-6">
                    <Plus class="h-4 w-4" />
                    أضف أول منتج
                </Link>
            </div>

            <div v-else class="surface divide-y divide-border">
                <div v-for="product in products.data" :key="product.id" class="flex items-center gap-4 p-4">
                    <img v-if="product.image" :src="product.image" alt="" class="h-14 w-14 shrink-0 rounded-xl object-cover" />
                    <div v-else class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-muted">
                        <Package class="h-5 w-5 text-muted-foreground/50" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate font-medium">{{ product.name }}</span>
                            <span v-if="product.status === 'draft'" class="badge-neutral">مسودّة</span>
                            <span v-else-if="product.track_stock && product.stock <= 0" class="badge-danger">خلص</span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-baseline gap-2 text-sm">
                            <span class="tabular font-semibold text-primary">{{ product.price }}</span>
                            <span v-if="product.compare_at_price" class="tabular text-xs text-muted-foreground line-through">
                                {{ product.compare_at_price }}
                            </span>
                            <span v-if="product.track_stock" class="text-xs text-muted-foreground">
                                · المخزون {{ product.stock }}
                                <template v-if="product.variants_count"> في {{ product.variants_count }} نوع</template>
                            </span>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <a :href="product.url" target="_blank" class="btn-ghost px-2" title="شوفه في المتجر">
                            <ExternalLink class="h-4 w-4" />
                        </a>
                        <button class="btn-ghost px-2" title="استنسخ" :disabled="busyId === product.id" @click="duplicate(product)">
                            <Copy class="h-4 w-4" />
                        </button>
                        <Link :href="`/products/${product.id}/edit`" class="btn-ghost px-2" title="عدّل">
                            <Pencil class="h-4 w-4" />
                        </Link>
                        <button class="btn-ghost px-2 text-destructive" title="احذف" :disabled="busyId === product.id" @click="destroy(product)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="products.links.length > 3" class="mt-6 flex flex-wrap justify-center gap-1">
                <Link
                    v-for="link in products.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    :class="['btn-ghost min-w-9 px-3 py-1.5 text-sm', { 'bg-muted font-semibold': link.active, 'pointer-events-none opacity-40': !link.url }]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
