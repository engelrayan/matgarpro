<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, LoaderCircle, RefreshCw, Star, Trash2 } from 'lucide-vue-next';
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
    ({ issued: '🔒 مؤمّن', issuing: 'بيتأمّن…', pending: 'الأمان في الطابور', failed: 'الأمان فشل' })[status];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="الدومين المخصص" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="الدومين المخصص"
                    description="اربط دومينك الخاص بمتجرك. الشهادة الأمنية (SSL) بتتظبط لوحدها."
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
                <div v-if="domains.length" class="space-y-3">
                    <div v-for="domain in domains" :key="domain.id" class="surface overflow-hidden">
                        <div class="flex flex-wrap items-center gap-3 p-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="truncate font-mono text-sm font-medium" dir="ltr">{{ domain.domain }}</span>
                                    <span :class="badgeFor(domain.status)">{{ labelFor(domain.status) }}</span>
                                    <span :class="sslBadge(domain.ssl_status)">{{ sslLabel(domain.ssl_status) }}</span>
                                    <span v-if="domain.is_primary" class="badge-gold">الأساسي</span>
                                </div>
                                <p class="mt-1.5 text-xs" :class="domain.ssl_status === 'failed' ? 'text-destructive' : 'text-muted-foreground'">
                                    {{ domain.ssl_message }}
                                </p>

                                <p v-if="domain.last_error" class="mt-1.5 text-xs text-muted-foreground">
                                    {{ domain.last_error }}
                                </p>
                                <p v-else-if="domain.last_checked_at" class="mt-1.5 text-xs text-muted-foreground">
                                    آخر فحص {{ domain.last_checked_at }}
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <button
                                    class="btn-ghost px-2"
                                    title="افحص دلوقتي"
                                    :disabled="busyId === domain.id"
                                    @click="act(domain.id, 'post', route('domains.verify', domain.id))"
                                >
                                    <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': busyId === domain.id }" />
                                </button>
                                <button
                                    v-if="domain.status === 'active' && !domain.is_primary"
                                    class="btn-ghost px-2"
                                    title="اجعله الأساسي"
                                    :disabled="busyId === domain.id"
                                    @click="act(domain.id, 'post', route('domains.primary', domain.id))"
                                >
                                    <Star class="h-4 w-4" />
                                </button>
                                <button
                                    class="btn-ghost px-2 text-destructive"
                                    title="شيل الدومين"
                                    :disabled="busyId === domain.id"
                                    @click="act(domain.id, 'delete', route('domains.destroy', domain.id))"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

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
