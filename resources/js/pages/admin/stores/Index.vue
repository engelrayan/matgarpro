<script setup lang="ts">
import Pagination from '@/components/admin/Pagination.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { money, num } from '@/composables/useFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface StoreRow {
    id: number;
    name: string;
    slug: string;
    url: string;
    status: string;
    billing_status: string;
    balance: number;
    price_per_order: number;
    price_source: string;
    plan: string | null;
    theme: string;
    orders_count: number;
    products_count: number;
    merchant: { id: number; name: string; email: string };
    created_at: string;
}

const props = defineProps<{
    stores: { data: StoreRow[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null; to: number | null };
    filters: { q: string; status: string; billing_status: string; plan: string; theme: string; sort: string };
    plans: { id: number; name: string }[];
    themeOptions: { key: string; name: string }[];
    currency: string;
}>();

const form = ref({ ...props.filters });

/*
 * Debounced so typing a shop name is one request at the end rather than one
 * per keystroke — this query joins users and counts two relations, and the
 * store list is the screen operators leave open all day.
 */
let timer: ReturnType<typeof setTimeout>;
watch(
    form,
    (value) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get('/admin/stores', { ...value }, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);
    },
    { deep: true },
);

const STATUS_BADGE: Record<string, string> = {
    active: 'badge-success',
    draft: 'badge-neutral',
    suspended: 'badge-danger',
};

const STATUS_LABEL: Record<string, string> = {
    active: 'شغّال',
    draft: 'مسودة',
    suspended: 'موقوف',
};

const PRICE_SOURCE: Record<string, string> = {
    override: 'سعر خاص',
    plan: 'من الخطة',
    default: 'الافتراضي',
};
</script>

<template>
    <AdminLayout title="المتاجر" :subtitle="`${num(stores.total)} متجر`">
        <Head title="المتاجر" />

        <div class="mx-auto max-w-7xl space-y-4">
            <!-- ── Filters ───────────────────────────────────────────────── -->
            <div class="surface grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
                <div class="relative lg:col-span-2">
                    <Search class="absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input v-model="form.q" class="field pr-9" placeholder="اسم المتجر، السلاج، أو التاجر" />
                </div>

                <select v-model="form.status" class="field">
                    <option value="">كل الحالات</option>
                    <option value="active">شغّال</option>
                    <option value="draft">مسودة</option>
                    <option value="suspended">موقوف</option>
                    <option value="demo">متاجر العرض</option>
                </select>

                <select v-model="form.billing_status" class="field">
                    <option value="">كل الحالات المالية</option>
                    <option value="active">مالياً سليم</option>
                    <option value="grace">فترة سماح</option>
                    <option value="suspended">موقوف مالياً</option>
                </select>

                <select v-model="form.plan" class="field">
                    <option value="">كل الخطط</option>
                    <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                </select>

                <select v-model="form.theme" class="field">
                    <option value="">كل الثيمات</option>
                    <option v-for="t in themeOptions" :key="t.key" :value="t.key">{{ t.name }}</option>
                </select>
            </div>

            <!-- ── Table ─────────────────────────────────────────────────── -->
            <div class="surface overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="text-xs text-muted-foreground">
                            <tr class="border-b border-border">
                                <th class="px-4 py-3 text-right font-medium">المتجر</th>
                                <th class="px-4 py-3 text-right font-medium">التاجر</th>
                                <th class="px-4 py-3 text-right font-medium">الحالة</th>
                                <th class="px-4 py-3 text-left font-medium">
                                    <button class="hover:text-foreground" @click="form.sort = 'orders'">طلبات</button>
                                </th>
                                <th class="px-4 py-3 text-left font-medium">منتجات</th>
                                <th class="px-4 py-3 text-left font-medium">
                                    <button class="hover:text-foreground" @click="form.sort = 'balance'">الرصيد</button>
                                </th>
                                <th class="px-4 py-3 text-right font-medium">التسعير</th>
                                <th class="px-4 py-3 text-right font-medium">الثيم</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="store in stores.data" :key="store.id" class="border-b border-border/60 last:border-0 hover:bg-muted/40">
                                <td class="px-4 py-3">
                                    <Link :href="`/admin/stores/${store.id}`" class="font-medium hover:underline">
                                        {{ store.name }}
                                    </Link>
                                    <p class="font-mono text-xs text-muted-foreground" dir="ltr">{{ store.slug }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <Link :href="`/admin/merchants/${store.merchant.id}`" class="hover:underline">
                                        {{ store.merchant.name }}
                                    </Link>
                                    <p class="truncate text-xs text-muted-foreground" dir="ltr">{{ store.merchant.email }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="STATUS_BADGE[store.status]">{{ STATUS_LABEL[store.status] }}</span>
                                    <span v-if="store.billing_status === 'suspended'" class="badge-danger mt-1 block w-fit">
                                        موقوف مالياً
                                    </span>
                                </td>
                                <td class="tabular px-4 py-3 text-left">{{ num(store.orders_count) }}</td>
                                <td class="tabular px-4 py-3 text-left">{{ num(store.products_count) }}</td>
                                <td class="tabular px-4 py-3 text-left" :class="store.balance < 0 ? 'text-destructive' : ''">
                                    {{ money(store.balance) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="tabular">{{ money(store.price_per_order) }}</span>
                                    <p class="text-xs text-muted-foreground">
                                        {{ store.plan ?? '—' }} · {{ PRICE_SOURCE[store.price_source] }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ store.theme }}</td>
                                <td class="px-4 py-3">
                                    <a
                                        :href="store.url"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-muted-foreground hover:text-foreground"
                                        title="افتح المتجر"
                                    >
                                        <ExternalLink class="size-4" />
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="!stores.data.length">
                                <td colspan="9" class="px-4 py-10 text-center text-muted-foreground">
                                    مفيش متاجر بالفلاتر دي.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-4 pb-4">
                    <Pagination :links="stores.links" :total="stores.total" :from="stores.from" :to="stores.to" />
                </div>
            </div>

            <p class="text-xs text-muted-foreground">الأسعار والأرصدة بالـ {{ currency }}.</p>
        </div>
    </AdminLayout>
</template>
