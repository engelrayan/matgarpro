<script setup lang="ts">
import Pagination from '@/components/admin/Pagination.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { num } from '@/composables/useFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { RefreshCw, Search, Star, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface DomainRow {
    id: number;
    domain: string;
    status: string;
    is_primary: boolean;
    is_apex: boolean;
    store: { id: number; name: string };
    ssl_issued_at: string | null;
    last_error: string | null;
    last_checked_at: string | null;
    check_attempts: number;
    created_at: string;
}

const props = defineProps<{
    domains: { data: DomainRow[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null; to: number | null };
    filters: { q: string; status: string };
    counts: { total: number; active: number; pending: number; failed: number };
    targets: { a: string[]; cname: string };
}>();

const form = ref({ ...props.filters });

let timer: ReturnType<typeof setTimeout>;
watch(
    form,
    (value) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get('/admin/domains', { ...value }, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);
    },
    { deep: true },
);

const recheck = (id: number) => router.post(`/admin/domains/${id}/verify`, {}, { preserveScroll: true });
const makePrimary = (id: number) => router.post(`/admin/domains/${id}/primary`, {}, { preserveScroll: true });

const remove = (row: DomainRow) => {
    // Detaching kills a live storefront hostname the second it goes through,
    // so the confirmation names the domain rather than asking "are you sure".
    if (confirm(`هتشيل ${row.domain} من متجر «${row.store.name}». المتجر هيرجع على الدومين المجاني. تمام؟`)) {
        router.delete(`/admin/domains/${row.id}`, { preserveScroll: true });
    }
};

const BADGE: Record<string, string> = {
    active: 'badge-success',
    pending: 'badge-warning',
    failed: 'badge-danger',
};

const LABEL: Record<string, string> = {
    active: 'شغّال',
    pending: 'مستني DNS',
    failed: 'فشل',
};
</script>

<template>
    <AdminLayout title="الدومينات" :subtitle="`${num(counts.total)} دومين`">
        <Head title="الدومينات" />

        <div class="mx-auto w-full max-w-6xl space-y-4">
            <!--
                Our own DNS targets, on this screen on purpose: a sudden wave of
                failures is far more often our records moving than fifty
                merchants making the same mistake on the same morning.
            -->
            <div class="surface flex flex-wrap items-center gap-x-6 gap-y-2 p-4 text-sm">
                <span class="text-muted-foreground">اللي بنطلبه من التجار:</span>
                <span class="font-mono text-xs" dir="ltr">A → {{ targets.a.join(', ') || '—' }}</span>
                <span class="font-mono text-xs" dir="ltr">CNAME → {{ targets.cname }}</span>
            </div>

            <div class="grid gap-3 sm:grid-cols-4">
                <button
                    v-for="s in [
                        { key: '', label: 'الكل', value: counts.total },
                        { key: 'failed', label: 'فشل', value: counts.failed },
                        { key: 'pending', label: 'مستني DNS', value: counts.pending },
                        { key: 'active', label: 'شغّال', value: counts.active },
                    ]"
                    :key="s.key"
                    class="surface p-4 text-right transition-shadow hover:shadow-e2"
                    :class="form.status === s.key ? 'ring-2 ring-primary' : ''"
                    @click="form.status = s.key"
                >
                    <p class="text-sm text-muted-foreground">{{ s.label }}</p>
                    <p class="tabular text-2xl font-semibold">{{ num(s.value) }}</p>
                </button>
            </div>

            <div class="relative">
                <Search class="absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input v-model="form.q" class="field pr-9" placeholder="ابحث بالدومين" dir="ltr" />
            </div>

            <div class="surface overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="text-xs text-muted-foreground">
                            <tr class="border-b border-border">
                                <th class="px-4 py-3 text-right font-medium">الدومين</th>
                                <th class="px-4 py-3 text-right font-medium">المتجر</th>
                                <th class="px-4 py-3 text-right font-medium">الحالة</th>
                                <th class="px-4 py-3 text-right font-medium">آخر فحص</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="d in domains.data" :key="d.id" class="border-b border-border/60 last:border-0 hover:bg-muted/40">
                                <td class="px-4 py-3">
                                    <span class="font-mono text-xs" dir="ltr">{{ d.domain }}</span>
                                    <span v-if="d.is_primary" class="badge-gold mr-2">أساسي</span>
                                    <p v-if="d.last_error" class="mt-1 max-w-md text-xs text-muted-foreground">
                                        {{ d.last_error }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <Link :href="`/admin/stores/${d.store.id}`" class="hover:underline">
                                        {{ d.store.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="BADGE[d.status]">{{ LABEL[d.status] }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">
                                    {{ d.last_checked_at ?? 'لسه' }}
                                    <span v-if="d.check_attempts"> · {{ num(d.check_attempts) }} محاولة</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button class="btn-ghost px-2 py-1.5" title="أعد الفحص" @click="recheck(d.id)">
                                            <RefreshCw class="size-4" />
                                        </button>
                                        <button
                                            v-if="!d.is_primary && d.status === 'active'"
                                            class="btn-ghost px-2 py-1.5"
                                            title="اجعله أساسي"
                                            @click="makePrimary(d.id)"
                                        >
                                            <Star class="size-4" />
                                        </button>
                                        <button class="btn-ghost px-2 py-1.5 text-destructive" title="شيله" @click="remove(d)">
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!domains.data.length">
                                <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">مفيش دومينات هنا.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-4 pb-4">
                    <Pagination :links="domains.links" :total="domains.total" :from="domains.from" :to="domains.to" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
