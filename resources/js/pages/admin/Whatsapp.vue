<script setup lang="ts">
import StatTile from '@/components/admin/StatTile.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { money, num } from '@/composables/useFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, MessageCircle, Send, Wallet, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

/*
 * WhatsApp confirmations across the platform.
 *
 * Problems first — a dropped number is a merchant whose confirmations stopped
 * without them knowing — then how the confirmations are doing, then which
 * stores. Counts only: no customer numbers or message text, as everywhere in
 * the panel.
 */

interface StoreRow {
    store_id: number;
    store: string;
    channel: 'platform' | 'waha' | null;
    sent: number;
    confirmed: number;
    cancelled: number;
    awaiting: number;
    failed: number;
    answer_rate: number | null;
    error: string | null;
}

const props = defineProps<{
    range: string;
    currency: string;
    insights: {
        channels: { platform: number; waha: number; active: number };
        problems: { store: { id: number; name: string }; channel: string; status: string | null; error: string | null; since: string | null }[];
        funnel: { sent: number; confirmed: number; cancelled: number; awaiting: number; no_reply: number; failed: number };
        stores: StoreRow[];
        failures: { id: number; store: { id: number; name: string | null }; error: string; at: string }[];
        billed: number;
        meta: { configured: boolean; template: string | null; template_status: string | null };
    };
}>();

const RANGES = [
    { key: 'today', label: 'النهارده' },
    { key: '7d', label: '٧ أيام' },
    { key: '30d', label: '٣٠ يوم' },
    { key: '90d', label: '٣ شهور' },
    { key: '365d', label: 'سنة' },
];

const setRange = (key: string) =>
    router.get('/admin/whatsapp', { range: key }, { preserveState: true, preserveScroll: true, replace: true });

const f = computed(() => props.insights.funnel);
const rate = (part: number) => (f.value.sent ? `${Math.round((part / f.value.sent) * 100)}٪ من اللي اتبعت` : '—');

const CHANNEL: Record<string, string> = { platform: 'رقم المنصة', waha: 'رقم التاجر' };

const WAHA_STATUS: Record<string, string> = {
    SCAN_QR_CODE: 'مستني مسح الكود',
    FAILED: 'الكود خلص — محتاج يربط تاني',
    STOPPED: 'واقف',
    STARTING: 'بيتوصّل',
};

const TEMPLATE: Record<string, { label: string; tone: string }> = {
    APPROVED: { label: 'متوافق عليه', tone: 'badge-success' },
    PENDING: { label: 'مستني موافقة ميتا', tone: 'badge-warning' },
    REJECTED: { label: 'ميتا رفضته', tone: 'badge-danger' },
    PAUSED: { label: 'ميتا وقّفته مؤقتًا', tone: 'badge-danger' },
    DISABLED: { label: 'متوقف', tone: 'badge-danger' },
    NOT_FOUND: { label: 'مش موجود — شغّل whatsapp:platform-template', tone: 'badge-danger' },
};
</script>

<template>
    <AdminLayout title="واتساب" :subtitle="`${num(insights.channels.platform + insights.channels.waha)} متجر مفعّل التأكيد`">
        <Head title="واتساب" />

        <div class="mx-auto w-full max-w-7xl space-y-5">
            <!-- ── Dropped numbers ───────────────────────────────────────── -->
            <section v-if="insights.problems.length" class="surface border border-destructive/40 p-5">
                <p class="flex items-center gap-2 font-medium text-destructive">
                    <AlertTriangle class="size-4" />
                    {{ num(insights.problems.length) }} متجر التأكيد عنده واقف
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    طلباتهم بتيجي ومحدش بيتبعتله تأكيد. التاجر لازم يدخل «الربط والتأكيد» ويربط رقمه تاني.
                </p>
                <ul class="mt-3 divide-y divide-border/60">
                    <li v-for="p in insights.problems" :key="p.store.id" class="flex flex-wrap items-baseline justify-between gap-2 py-2.5 text-sm">
                        <Link :href="`/admin/stores/${p.store.id}`" class="font-medium hover:underline">{{ p.store.name }}</Link>
                        <span class="text-muted-foreground">
                            {{ CHANNEL[p.channel] ?? p.channel }}
                            <template v-if="p.status"> · {{ WAHA_STATUS[p.status] ?? p.status }}</template>
                            <template v-if="p.since"> · من {{ p.since }}</template>
                        </span>
                        <p v-if="p.error" class="w-full text-xs text-muted-foreground">{{ p.error }}</p>
                    </li>
                </ul>
            </section>

            <!-- ── Channels & Meta ───────────────────────────────────────── -->
            <section class="grid gap-4 md:grid-cols-3">
                <StatTile label="على رقم المنصة (ميتا)" :value="num(insights.channels.platform)" :icon="MessageCircle" />
                <StatTile label="على رقم التاجر" :value="num(insights.channels.waha)" :icon="MessageCircle" />
                <div class="surface flex flex-col gap-1 p-5">
                    <p class="text-sm text-muted-foreground">قالب رقم المنصة</p>
                    <template v-if="insights.meta.configured">
                        <p class="font-mono text-sm" dir="ltr" style="text-align: right">{{ insights.meta.template }}</p>
                        <span
                            v-if="insights.meta.template_status"
                            class="w-fit"
                            :class="TEMPLATE[insights.meta.template_status]?.tone ?? 'badge-neutral'"
                        >
                            {{ TEMPLATE[insights.meta.template_status]?.label ?? insights.meta.template_status }}
                        </span>
                        <p v-else class="text-xs text-muted-foreground">مقدرناش نسأل ميتا دلوقتي.</p>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">رقم المنصة مش متظبط.</p>
                </div>
            </section>

            <!-- ── Range ─────────────────────────────────────────────────── -->
            <div class="flex flex-wrap items-center gap-1 rounded-xl border border-border bg-card p-1">
                <button
                    v-for="r in RANGES"
                    :key="r.key"
                    class="rounded-lg px-3 py-1.5 text-sm transition-colors"
                    :class="range === r.key ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                    @click="setRange(r.key)"
                >
                    {{ r.label }}
                </button>
            </div>

            <!-- ── How confirmations are doing ───────────────────────────── -->
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <StatTile label="اتبعت" :value="num(f.sent)" :icon="Send" />
                <StatTile label="العميل أكّد" :value="num(f.confirmed)" :hint="rate(f.confirmed)" :icon="CheckCircle2" />
                <StatTile label="العميل لغى" :value="num(f.cancelled)" :hint="rate(f.cancelled)" :icon="XCircle" />
                <StatTile label="ماردّش" :value="num(f.awaiting + f.no_reply)" :hint="rate(f.awaiting + f.no_reply)" />
                <StatTile label="ماطلعتش" :value="num(f.failed)" hint="الرسالة نفسها فشلت" :icon="AlertTriangle" />
            </section>
            <StatTile label="اتحسب على التجار للتأكيدات" :value="money(insights.billed)" :hint="currency" :icon="Wallet" lux />

            <!-- ── Per store ─────────────────────────────────────────────── -->
            <section class="surface overflow-hidden">
                <p class="border-b border-border p-5 font-medium">المتاجر</p>
                <div v-if="insights.stores.length" class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="text-xs text-muted-foreground">
                            <tr class="border-b border-border">
                                <th class="px-4 py-3 text-right font-medium">المتجر</th>
                                <th class="px-4 py-3 text-right font-medium">الرقم</th>
                                <th class="px-4 py-3 text-right font-medium">اتبعت</th>
                                <th class="px-4 py-3 text-right font-medium">أكّد</th>
                                <th class="px-4 py-3 text-right font-medium">لغى</th>
                                <th class="px-4 py-3 text-right font-medium">ماردّش</th>
                                <th class="px-4 py-3 text-right font-medium">فشل</th>
                                <th class="px-4 py-3 text-right font-medium">نسبة الرد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in insights.stores" :key="s.store_id" class="border-b border-border/60 last:border-0 hover:bg-muted/40">
                                <td class="px-4 py-3">
                                    <Link :href="`/admin/stores/${s.store_id}`" class="font-medium hover:underline">{{ s.store }}</Link>
                                    <p v-if="s.error" class="mt-0.5 max-w-xs truncate text-xs text-destructive" :title="s.error">{{ s.error }}</p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ s.channel ? CHANNEL[s.channel] : 'اتفك' }}</td>
                                <td class="tabular px-4 py-3">{{ num(s.sent) }}</td>
                                <td class="tabular px-4 py-3 text-success">{{ num(s.confirmed) }}</td>
                                <td class="tabular px-4 py-3">{{ num(s.cancelled) }}</td>
                                <td class="tabular px-4 py-3">{{ num(s.awaiting) }}</td>
                                <td class="tabular px-4 py-3" :class="s.failed ? 'text-destructive' : ''">{{ num(s.failed) }}</td>
                                <td class="tabular px-4 py-3">{{ s.answer_rate === null ? '—' : `${s.answer_rate}٪` }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="p-5 text-sm text-muted-foreground">مفيش تأكيدات اتبعتت في الفترة دي.</p>
            </section>

            <!-- ── Latest failures ───────────────────────────────────────── -->
            <section class="surface overflow-hidden">
                <p class="border-b border-border p-5 font-medium">آخر رسايل ماطلعتش</p>
                <ul v-if="insights.failures.length">
                    <li v-for="m in insights.failures" :key="m.id" class="border-b border-border/60 px-5 py-3 last:border-0">
                        <div class="flex items-baseline justify-between gap-2 text-sm">
                            <Link :href="`/admin/stores/${m.store.id}`" class="font-medium hover:underline">{{ m.store.name ?? '—' }}</Link>
                            <span class="shrink-0 text-xs text-muted-foreground">{{ m.at }}</span>
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">{{ m.error }}</p>
                    </li>
                </ul>
                <p v-else class="p-5 text-sm text-muted-foreground">مفيش رسايل فشلت.</p>
            </section>
        </div>
    </AdminLayout>
</template>
