<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { money, num } from '@/composables/useFormat';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    merchant: { id: number; name: string; email: string; verified_at: string | null; created_at: string };
    stores: { id: number; name: string; slug: string; status: string; balance: number; orders_count: number; gmv: number; created_at: string }[];
    currency: string;
}>();

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
</script>

<template>
    <AdminLayout :title="merchant.name" subtitle="بيانات التاجر ومتاجره">
        <Head :title="merchant.name" />

        <div class="mx-auto w-full max-w-4xl space-y-5">
            <section class="surface p-5">
                <dl class="grid gap-4 sm:grid-cols-3 text-sm">
                    <div>
                        <dt class="text-muted-foreground">الإيميل</dt>
                        <dd class="mt-0.5 truncate" dir="ltr">{{ merchant.email }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">تأكيد الإيميل</dt>
                        <dd class="mt-0.5">
                            <span v-if="merchant.verified_at" class="badge-success">{{ merchant.verified_at }}</span>
                            <span v-else class="badge-warning">مش متأكد</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">اشترك</dt>
                        <dd class="mt-0.5">{{ merchant.created_at }}</dd>
                    </div>
                </dl>

                <!--
                    Nothing here is editable, and that is the design: the panel
                    operates stores, not merchant logins. See MerchantController.
                -->
                <p class="mt-4 border-t border-border pt-3 text-xs text-muted-foreground">
                    الإجراءات كلها بتتعمل على المتجر نفسه — افتح المتجر من تحت.
                </p>
            </section>

            <section class="surface overflow-hidden">
                <p class="border-b border-border p-5 text-sm font-medium">متاجره</p>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="text-xs text-muted-foreground">
                            <tr class="border-b border-border">
                                <th class="px-5 py-3 text-right font-medium">المتجر</th>
                                <th class="px-5 py-3 text-right font-medium">الحالة</th>
                                <th class="px-5 py-3 text-left font-medium">طلبات</th>
                                <th class="px-5 py-3 text-left font-medium">مبيعات</th>
                                <th class="px-5 py-3 text-left font-medium">الرصيد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in stores" :key="s.id" class="border-b border-border/60 last:border-0 hover:bg-muted/40">
                                <td class="px-5 py-3">
                                    <Link :href="`/admin/stores/${s.id}`" class="font-medium hover:underline">{{ s.name }}</Link>
                                    <p class="font-mono text-xs text-muted-foreground" dir="ltr">{{ s.slug }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <span :class="STATUS_BADGE[s.status]">{{ STATUS_LABEL[s.status] }}</span>
                                </td>
                                <td class="tabular px-5 py-3 text-left">{{ num(s.orders_count) }}</td>
                                <td class="tabular px-5 py-3 text-left">{{ money(s.gmv) }}</td>
                                <td class="tabular px-5 py-3 text-left" :class="s.balance < 0 ? 'text-destructive' : ''">
                                    {{ money(s.balance) }}
                                </td>
                            </tr>
                            <tr v-if="!stores.length">
                                <td colspan="5" class="px-5 py-10 text-center text-muted-foreground">
                                    التاجر ده لسه مفتحش متجر.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-border px-5 py-3 text-xs text-muted-foreground">
                    المبيعات بالـ {{ currency }} والموصّل فقط.
                </p>
            </section>
        </div>
    </AdminLayout>
</template>
