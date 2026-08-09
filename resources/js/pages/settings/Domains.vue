<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, Globe, LoaderCircle, Lock, RefreshCw, Star, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface DnsRecord {
    type: string;
    name: string;
    value: string | string[];
    note: string;
}

interface Domain {
    id: number;
    domain: string;
    status: 'pending' | 'active' | 'failed';
    ssl_status: 'pending' | 'issuing' | 'issued' | 'failed';
    ssl_message: string;
    is_secure: boolean;
    is_primary: boolean;
    is_apex: boolean;
    last_error: string | null;
    last_checked_at: string | null;
    verified_at: string | null;
    instructions: DnsRecord[];
}

defineProps<{
    store: { name: string; slug: string; platform_host: string; canonical_url: string };
    domains: Domain[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'الدومين المخصص', href: '/settings/domains' }];

const form = useForm({ domain: '' });

const addDomain = () =>
    form.post(route('domains.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });

/** Which row is mid-request, so only that button spins. */
const busyId = ref<number | null>(null);

const act = (id: number, method: 'post' | 'delete', url: string) => {
    busyId.value = id;
    router[method](url, {}, { preserveScroll: true, onFinish: () => (busyId.value = null) });
};

const copied = ref<string | null>(null);

const copy = async (value: string) => {
    await navigator.clipboard.writeText(value);
    copied.value = value;
    setTimeout(() => (copied.value = null), 1500);
};

const asText = (value: string | string[]) => (Array.isArray(value) ? value.join('\n') : value);

const badgeFor = (status: Domain['status']) =>
    ({ active: 'badge-success', pending: 'badge-warning', failed: 'badge-danger' })[status];

const labelFor = (status: Domain['status']) =>
    ({ active: 'شغّال', pending: 'في انتظار الـ DNS', failed: 'فشل التحقق' })[status];

/*
 * The padlock gets its own badge, never merged into the one above.
 *
 * A domain can be serving the shop while its certificate is still being
 * issued — and a merchant looking at a single green badge next to a browser
 * saying "Not secure" concludes the platform is lying to them. Two states,
 * two badges, and the sentence underneath says which one is which.
 */
const sslBadge = (status: Domain['ssl_status']) =>
    ({ issued: 'badge-success', issuing: 'badge-info', pending: 'badge-neutral', failed: 'badge-danger' })[status];

const sslLabel = (status: Domain['ssl_status']) =>
    ({ issued: 'مؤمّن', issuing: 'بيتأمّن…', pending: 'في الطابور', failed: 'فشل' })[status];

/**
 * Where this domain is along the three things that have to happen.
 *
 * Merchants kept asking "هو خلص ولا لأ" because a single "pending" badge
 * cannot answer it — they could not tell whether the wait was on their DNS
 * panel, on our verifier, or on the certificate. Three numbered steps say it
 * without a support message.
 */
const steps = (domain: Domain) => [
    {
        label: 'سجلات الـ DNS',
        hint: 'تحطها في لوحة الدومين بتاعتك',
        done: domain.status === 'active',
        failed: domain.status === 'failed',
    },
    {
        label: 'التأكد من الربط',
        hint: 'بنفحصها لوحدنا كل شوية',
        done: domain.status === 'active',
        failed: domain.status === 'failed',
    },
    {
        label: 'شهادة الأمان',
        hint: 'بتتصدر وتتجدد تلقائي',
        done: domain.is_secure,
        failed: domain.ssl_status === 'failed',
    },
];

const isLive = (domain: Domain) => domain.status === 'active' && domain.is_secure;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="الدومين المخصص" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="الدومين المخصص"
                    description="اربط دومينك الخاص بمتجرك. شهادة الأمان مجانية وبتتظبط وتتجدد لوحدها."
                />

                <!-- The free sub-domain never goes away; say so, because merchants
                     assume adding a domain replaces it and hesitate. -->
                <div class="surface flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <p class="text-sm font-medium">العنوان الدائم لمتجرك</p>
                        <p class="mt-0.5 font-mono text-sm text-muted-foreground" dir="ltr">
                            {{ store.platform_host }}
                        </p>
                    </div>
                    <span class="badge-neutral shrink-0">بيفضل شغّال دايمًا</span>
                </div>

                <!-- Add -->
                <form @submit.prevent="addDomain" class="space-y-2">
                    <label class="field-label" for="domain">أضف دومين</label>
                    <div class="flex gap-2">
                        <input
                            id="domain"
                            v-model="form.domain"
                            class="field flex-1 font-mono"
                            dir="ltr"
                            placeholder="mahmoud.com"
                            autocomplete="off"
                        />
                        <button type="submit" class="btn-primary shrink-0" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                            إضافة
                        </button>
                    </div>
                    <p class="field-hint">اكتبه من غير http ومن غير أي مسار.</p>
                    <InputError :message="form.errors.domain" />
                </form>

                <!-- List -->
                <div v-if="domains.length" class="space-y-4">
                    <div
                        v-for="domain in domains"
                        :key="domain.id"
                        class="overflow-hidden rounded-2xl border shadow-e1"
                        :class="isLive(domain) ? 'border-success/30' : 'border-border'"
                    >
                        <!--
                            A live, certified domain gets a green header with a
                            padlock. This is the one screen where the merchant
                            is waiting for a specific outcome, and the outcome
                            deserves to be unmistakable rather than a small
                            badge among four other small badges.
                        -->
                        <div
                            class="flex flex-wrap items-center gap-3 p-4"
                            :class="isLive(domain) ? 'bg-success/5' : 'bg-card'"
                        >
                            <span
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl"
                                :class="isLive(domain) ? 'bg-success/15 text-success' : 'bg-muted text-muted-foreground'"
                            >
                                <component :is="isLive(domain) ? Lock : Globe" class="size-5" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a
                                        v-if="isLive(domain)"
                                        :href="`https://${domain.domain}`"
                                        target="_blank"
                                        rel="noopener"
                                        class="truncate font-mono font-semibold hover:underline"
                                        dir="ltr"
                                    >{{ domain.domain }}</a>
                                    <span v-else class="truncate font-mono font-semibold" dir="ltr">{{ domain.domain }}</span>

                                    <span v-if="domain.is_primary" class="badge-gold">الأساسي</span>
                                </div>

                                <p
                                    class="mt-1 text-sm"
                                    :class="isLive(domain) ? 'text-success' : 'text-muted-foreground'"
                                >
                                    {{ isLive(domain) ? 'شغّال ومؤمّن — القفل ظاهر لزباينك' : domain.ssl_message }}
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <button
                                    class="btn-ghost px-2.5"
                                    title="افحص دلوقتي"
                                    :disabled="busyId === domain.id"
                                    @click="act(domain.id, 'post', route('domains.verify', domain.id))"
                                >
                                    <RefreshCw class="size-4" :class="{ 'animate-spin': busyId === domain.id }" />
                                </button>
                                <button
                                    v-if="domain.status === 'active' && !domain.is_primary"
                                    class="btn-ghost px-2.5"
                                    title="اجعله الأساسي"
                                    :disabled="busyId === domain.id"
                                    @click="act(domain.id, 'post', route('domains.primary', domain.id))"
                                >
                                    <Star class="size-4" />
                                </button>
                                <button
                                    class="btn-ghost px-2.5 text-destructive"
                                    title="شيل الدومين"
                                    :disabled="busyId === domain.id"
                                    @click="act(domain.id, 'delete', route('domains.destroy', domain.id))"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>

                        <!--
                            The three things that have to happen, and which one
                            we are on. Hidden once everything is done — a
                            finished checklist sitting permanently ticked is
                            just noise on a screen the merchant revisits.
                        -->
                        <div v-if="!isLive(domain)" class="grid gap-px bg-border sm:grid-cols-3">
                            <div v-for="(step, i) in steps(domain)" :key="i" class="bg-card p-4">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                        :class="
                                            step.done
                                                ? 'bg-success text-white'
                                                : step.failed
                                                  ? 'bg-destructive text-white'
                                                  : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        <Check v-if="step.done" class="size-3.5" />
                                        <template v-else>{{ i + 1 }}</template>
                                    </span>
                                    <span class="text-sm font-medium">{{ step.label }}</span>
                                </div>
                                <p class="mt-1.5 pr-8 text-xs text-muted-foreground">{{ step.hint }}</p>
                            </div>
                        </div>

                        <p
                            v-if="domain.last_error"
                            class="border-t border-border bg-muted/30 px-4 py-2.5 text-xs text-muted-foreground"
                        >
                            {{ domain.last_error }}
                        </p>
                        <p
                            v-else-if="domain.last_checked_at && !isLive(domain)"
                            class="border-t border-border bg-muted/30 px-4 py-2.5 text-xs text-muted-foreground"
                        >
                            آخر فحص {{ domain.last_checked_at }} · بنفحص لوحدنا كل شوية
                        </p>

                        <!-- Instructions only while they are still needed. A working
                             domain showing DNS records reads as an unfinished task. -->
                        <div v-if="domain.status !== 'active'" class="border-t border-border bg-muted/40 p-4">
                            <p class="mb-3 text-xs font-medium">
                                افتح لوحة الدومين بتاعتك وضيف السجلات دي:
                            </p>
                            <div class="space-y-2">
                                <div
                                    v-for="record in domain.instructions"
                                    :key="record.type + record.name"
                                    class="flex flex-wrap items-center gap-2 rounded-xl bg-card p-3 text-xs"
                                >
                                    <span class="badge-neutral shrink-0">{{ record.type }}</span>
                                    <span class="font-mono" dir="ltr">{{ record.name }}</span>
                                    <span class="mx-1 text-muted-foreground">←</span>
                                    <span class="flex-1 whitespace-pre-line break-all font-mono" dir="ltr">
                                        {{ asText(record.value) }}
                                    </span>
                                    <button class="btn-ghost shrink-0 px-2 py-1" @click="copy(asText(record.value))">
                                        <Check v-if="copied === asText(record.value)" class="h-3.5 w-3.5 text-success" />
                                        <Copy v-else class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-muted-foreground">
                                بنفحص لوحدنا كل شوية — مش لازم تفضل فاتح الصفحة. الانتشار ممكن ياخد لحد ٢٤ ساعة.
                            </p>
                        </div>
                    </div>
                </div>

                <p v-else class="text-sm text-muted-foreground">
                    لسه مفيش دومين مربوط. متجرك شغّال على العنوان الدائم فوق.
                </p>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
