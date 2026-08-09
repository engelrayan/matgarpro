<script setup lang="ts">
import Pagination from '@/components/admin/Pagination.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { num } from '@/composables/useFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface MerchantRow {
    id: number;
    name: string;
    email: string;
    verified: boolean;
    stores_count: number;
    created_at: string;
}

const props = defineProps<{
    merchants: { data: MerchantRow[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null; to: number | null };
    filters: { q: string; sort: string };
}>();

const form = ref({ ...props.filters });

let timer: ReturnType<typeof setTimeout>;
watch(
    form,
    (value) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get('/admin/merchants', { ...value }, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);
    },
    { deep: true },
);
</script>

<template>
    <AdminLayout title="التجار" :subtitle="`${num(merchants.total)} تاجر`">
        <Head title="التجار" />

        <div class="mx-auto w-full max-w-5xl space-y-4">
            <div class="surface grid gap-3 p-4 sm:grid-cols-3">
                <div class="relative sm:col-span-2">
                    <Search class="absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input v-model="form.q" class="field pr-9" placeholder="الاسم أو الإيميل" />
                </div>
                <select v-model="form.sort" class="field">
                    <option value="newest">الأحدث</option>
                    <option value="stores">الأكتر متاجر</option>
                    <option value="name">بالاسم</option>
                </select>
            </div>

            <div class="surface overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-sm">
                        <thead class="text-xs text-muted-foreground">
                            <tr class="border-b border-border">
                                <th class="px-4 py-3 text-right font-medium">التاجر</th>
                                <th class="px-4 py-3 text-right font-medium">الإيميل</th>
                                <th class="px-4 py-3 text-left font-medium">متاجر</th>
                                <th class="px-4 py-3 text-right font-medium">اشترك</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in merchants.data" :key="m.id" class="border-b border-border/60 last:border-0 hover:bg-muted/40">
                                <td class="px-4 py-3">
                                    <Link :href="`/admin/merchants/${m.id}`" class="font-medium hover:underline">
                                        {{ m.name }}
                                    </Link>
                                    <!-- Unverified is worth seeing: those accounts never finished signup. -->
                                    <span v-if="!m.verified" class="badge-warning mr-2">إيميل مش متأكد</span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground" dir="ltr">{{ m.email }}</td>
                                <td class="tabular px-4 py-3 text-left">{{ num(m.stores_count) }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ m.created_at }}</td>
                            </tr>
                            <tr v-if="!merchants.data.length">
                                <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">مفيش نتايج.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-4 pb-4">
                    <Pagination :links="merchants.links" :total="merchants.total" :from="merchants.from" :to="merchants.to" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
