<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, CircleSlash, RefreshCw, RotateCcw, Trash2, TriangleAlert, XCircle } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted } from 'vue';

/*
 * Is the platform running, part by part.
 *
 * Red at the top: a stopped worker means WhatsApp confirmations are not going
 * out right now, and that is the one thing on this page that costs merchants
 * money by the minute. Refreshes itself every 30 seconds while open.
 */

interface Check {
    key: string;
    label: string;
    status: 'ok' | 'warn' | 'fail' | 'off';
    detail: string;
    fix?: string | null;
}

interface FailedJob {
    id: number;
    uuid: string;
    queue: string;
    job: string;
    error: string;
    failed_at: string;
}

const props = defineProps<{ checks: Check[]; failed: FailedJob[]; checkedAt: string }>();

const page = usePage<{ auth: { admin: { is_super: boolean } | null } }>();
const isSuper = computed(() => !!page.props.auth.admin?.is_super);

const ORDER = { fail: 0, warn: 1, ok: 2, off: 3 } as const;
const sorted = computed(() => [...props.checks].sort((a, b) => ORDER[a.status] - ORDER[b.status]));
const problems = computed(() => props.checks.filter((c) => c.status === 'fail').length);

const STYLE = {
    ok: { icon: CheckCircle2, tone: 'text-success', ring: 'border-border' },
    warn: { icon: TriangleAlert, tone: 'text-warning', ring: 'border-warning/40' },
    fail: { icon: XCircle, tone: 'text-destructive', ring: 'border-destructive/40' },
    off: { icon: CircleSlash, tone: 'text-muted-foreground', ring: 'border-border' },
};

const refresh = () => router.reload({ only: ['checks', 'failed', 'checkedAt'] });
const retry = (job: FailedJob) => router.post(`/admin/health/failed/${job.uuid}/retry`, {}, { preserveScroll: true });
const forget = (job: FailedJob) => {
    if (!confirm('المهمة دي مش هتتشغّل تاني خالص. تمسحها؟')) return;
    router.delete(`/admin/health/failed/${job.uuid}`, { preserveScroll: true });
};

let timer: ReturnType<typeof setInterval>;
onMounted(() => (timer = setInterval(() => !document.hidden && refresh(), 30_000)));
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <AdminLayout title="صحة السيستم" :subtitle="problems ? `${problems} حاجة واقفة` : 'كله شغّال'">
        <Head title="صحة السيستم" />

        <template #actions>
            <button class="btn-outline shrink-0" @click="refresh">
                <RefreshCw class="size-4" />
                افحص تاني
            </button>
        </template>

        <div class="mx-auto w-full max-w-5xl space-y-5">
            <div class="grid gap-3 md:grid-cols-2">
                <div v-for="check in sorted" :key="check.key" class="surface border p-4" :class="STYLE[check.status].ring">
                    <div class="flex items-start gap-3">
                        <component :is="STYLE[check.status].icon" class="mt-0.5 size-5 shrink-0" :class="STYLE[check.status].tone" />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ check.label }}</p>
                            <p class="mt-0.5 text-sm text-muted-foreground">{{ check.detail }}</p>
                            <p v-if="check.fix" class="mt-2 rounded-lg bg-muted px-2.5 py-1.5 font-mono text-xs break-all" dir="ltr" style="text-align: left">
                                {{ check.fix }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-xs text-muted-foreground">آخر فحص {{ checkedAt }} · بيتحدّث لوحده كل ٣٠ ثانية.</p>

            <section class="surface overflow-hidden">
                <div class="border-b border-border p-5">
                    <p class="font-medium">المهام اللي فشلت</p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        مهمة فشلت بعد كل محاولاتها — رسالة واتساب ماطلعتش، أو كتابة بالذكاء ماخلصتش. «أعد التشغيل» بعد ما السبب يتصلّح.
                    </p>
                </div>
                <ul v-if="failed.length">
                    <li v-for="job in failed" :key="job.uuid" class="flex items-start gap-3 border-b border-border/60 px-5 py-3 last:border-0">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">
                                {{ job.job }}
                                <span class="badge-neutral mr-1">{{ job.queue }}</span>
                            </p>
                            <p class="mt-1 font-mono text-xs break-all text-muted-foreground" dir="ltr" style="text-align: left">{{ job.error }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ job.failed_at }}</p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button class="btn-ghost px-2 py-1.5" title="أعد التشغيل" @click="retry(job)">
                                <RotateCcw class="size-4" />
                            </button>
                            <button v-if="isSuper" class="btn-ghost px-2 py-1.5 text-destructive" title="امسحها" @click="forget(job)">
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </li>
                </ul>
                <p v-else class="p-5 text-sm text-muted-foreground">مفيش مهام فاشلة.</p>
            </section>
        </div>
    </AdminLayout>
</template>
