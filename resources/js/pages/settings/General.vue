<script setup lang="ts">
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Check, Copy, Globe, ImagePlus, KeyRound, LoaderCircle, Lock, Palette,
    RefreshCw, Star, Store as StoreIcon, Trash2, TriangleAlert, User, X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

/*
 * Account and domain settings, on one screen.
 *
 * These were four pages holding one small form each, and a merchant changing a
 * password and then a domain paid two page loads and two hunts through a
 * sidebar of eleven links. Every form here still posts to the endpoint it
 * always posted to — this page only puts them in one place, which is what
 * keeps a screen this size from being one nobody dares to touch.
 */

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
    instructions: DnsRecord[];
}

const props = defineProps<{
    mustVerifyEmail: boolean;
    status?: string;
    store: {
        name: string;
        slug: string;
        description: string | null;
        logo_url: string | null;
        platform_host: string;
        canonical_url: string;
    };
    domains: Domain[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'إعدادات المتجر والدومين', href: '/settings/general' }];

const user = computed(() => usePage().props.auth.user as { name: string; email: string; email_verified_at: string | null });

// ── Store ────────────────────────────────────────────────────────────────
const storeForm = useForm({
    name: props.store.name,
    description: props.store.description ?? '',
    logo: null as File | null,
    remove_logo: false,
});

const logoPreview = ref<string | null>(null);

/** What the merchant should be looking at right now — not what is saved. */
const logo = computed(() => {
    if (storeForm.remove_logo) return null;
    return logoPreview.value ?? props.store.logo_url;
});

const pickLogo = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    storeForm.logo = file;
    storeForm.remove_logo = false;
    logoPreview.value = URL.createObjectURL(file);
};

const dropLogo = () => {
    storeForm.logo = null;
    storeForm.remove_logo = true;
    logoPreview.value = null;
};

// The storefront falls back to the first letter of the name when there is no
// logo, so the preview here has to show the same thing.
const initial = computed(() => storeForm.name.trim().charAt(0) || 'م');

const saveStore = () =>
    // POST, not PATCH: the form carries a file, and PHP does not populate
    // uploads on a spoofed PATCH body.
    storeForm.post(route('store.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            storeForm.remove_logo = false;
            storeForm.logo = null;
            logoPreview.value = null;
        },
    });

// ── Profile ──────────────────────────────────────────────────────────────
const profile = useForm({ name: user.value.name, email: user.value.email });

const saveProfile = () => profile.patch(route('profile.update'), { preserveScroll: true });

// ── Password ─────────────────────────────────────────────────────────────
const password = useForm({ current_password: '', password: '', password_confirmation: '' });

const savePassword = () =>
    password.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => password.reset(),
        // The field that failed is the field that should be focused and
        // cleared — retyping a password you cannot see, twice, because one of
        // the three boxes was wrong is how people give up on a settings page.
        onError: () => {
            if (password.errors.password) password.reset('password', 'password_confirmation');
            if (password.errors.current_password) password.reset('current_password');
        },
    });

// ── Domains ──────────────────────────────────────────────────────────────
const domainForm = useForm({ domain: '' });

const addDomain = () =>
    domainForm.post(route('domains.store'), { preserveScroll: true, onSuccess: () => domainForm.reset() });

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

const isLive = (domain: Domain) => domain.status === 'active' && domain.is_secure;

/**
 * The three things that have to happen, and which one we are on.
 *
 * A single "pending" badge cannot say whether the wait is on the merchant's
 * DNS panel, on our verifier, or on the certificate — and merchants asked
 * support instead of waiting.
 */
const steps = (domain: Domain) => [
    { label: 'سجلات الـ DNS', hint: 'تحطها في لوحة الدومين بتاعتك', done: domain.status === 'active', failed: domain.status === 'failed' },
    { label: 'التأكد من الربط', hint: 'بنفحصها لوحدنا كل شوية', done: domain.status === 'active', failed: domain.status === 'failed' },
    { label: 'شهادة الأمان', hint: 'بتتصدر وتتجدد تلقائي', done: domain.is_secure, failed: domain.ssl_status === 'failed' },
];

// ── Delete account ───────────────────────────────────────────────────────
const confirmingDelete = ref(false);
const deleteForm = useForm({ password: '' });

const deleteAccount = () =>
    deleteForm.delete(route('profile.destroy'), {
        preserveScroll: true,
        onError: () => deleteForm.reset('password'),
    });

const SECTIONS = [
    // The store comes first. A merchant opening settings is far more often
    // here to change their shop than their own password.
    { id: 'store', label: 'بيانات المتجر', icon: StoreIcon },
    { id: 'profile', label: 'الملف الشخصي', icon: User },
    { id: 'password', label: 'كلمة المرور', icon: KeyRound },
    { id: 'appearance', label: 'المظهر', icon: Palette },
    { id: 'domain', label: 'الدومين', icon: Globe },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="إعدادات المتجر والدومين" />

        <div class="mx-auto w-full max-w-5xl p-4 md:p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold tracking-tight">إعدادات المتجر والدومين</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    بياناتك، كلمة المرور، شكل اللوحة، ودومين متجرك — كلهم هنا.
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-[13rem_1fr]">
                <!--
                    A jump list, not a second navigation. It scrolls the page it
                    is already on, so a merchant never loses what they typed in
                    another section by clicking a link.
                -->
                <nav class="hidden lg:block">
                    <div class="sticky top-6 space-y-1">
                        <a
                            v-for="section in SECTIONS"
                            :key="section.id"
                            :href="`#${section.id}`"
                            class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            <component :is="section.icon" class="size-4" />
                            {{ section.label }}
                        </a>
                    </div>
                </nav>

                <div class="min-w-0 space-y-6">
                    <p v-if="status" class="rounded-xl bg-success/10 px-4 py-3 text-sm text-success">
                        {{ status === 'profile-updated' ? 'اتحفظت بياناتك.' : 'تمام، اتحفظ.' }}
                    </p>

                    <!-- ── Store ───────────────────────────────────────── -->
                    <section id="store" class="surface scroll-mt-6 p-6">
                        <h2 class="text-base font-semibold">بيانات المتجر</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            الاسم واللوجو اللي الزبون بيشوفهم فوق في متجرك.
                        </p>

                        <form class="mt-5 space-y-5" @submit.prevent="saveStore">
                            <div>
                                <label class="field-label">اللوجو</label>
                                <div class="flex items-center gap-4">
                                    <!-- The preview shows exactly what the
                                         storefront will render, initial fallback
                                         included — a merchant should not have to
                                         save to find out. -->
                                    <span class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-muted">
                                        <img v-if="logo" :src="logo" alt="" class="size-full object-cover" />
                                        <span v-else class="text-2xl font-bold text-primary">{{ initial }}</span>
                                    </span>

                                    <div class="flex flex-wrap gap-2">
                                        <label class="btn-outline cursor-pointer">
                                            <ImagePlus class="size-4" />
                                            {{ logo ? 'غيّر اللوجو' : 'ارفع لوجو' }}
                                            <input type="file" accept="image/*" class="hidden" @change="pickLogo" />
                                        </label>
                                        <button v-if="logo" type="button" class="btn-ghost text-destructive" @click="dropLogo">
                                            <X class="size-4" />
                                            شيله
                                        </button>
                                    </div>
                                </div>
                                <p class="field-hint">مربع أفضل. أقصى حجم ٢ ميجا.</p>
                                <InputError :message="storeForm.errors.logo" />
                            </div>

                            <div>
                                <label class="field-label" for="store_name">اسم المتجر</label>
                                <input id="store_name" v-model="storeForm.name" class="field" required />
                                <InputError :message="storeForm.errors.name" />
                            </div>

                            <div>
                                <label class="field-label" for="store_description">وصف قصير</label>
                                <textarea id="store_description" v-model="storeForm.description" class="field" rows="3" />
                                <p class="field-hint">بيظهر تحت اسم المتجر وفي نتايج البحث على جوجل.</p>
                                <InputError :message="storeForm.errors.description" />
                            </div>

                            <div class="flex items-center gap-3">
                                <button type="submit" class="btn-primary" :disabled="storeForm.processing">
                                    <LoaderCircle v-if="storeForm.processing" class="size-4 animate-spin" />
                                    احفظ
                                </button>
                                <span v-if="storeForm.recentlySuccessful" class="text-sm text-success">اتحفظ</span>
                            </div>
                        </form>
                    </section>

                    <!-- ── Profile ─────────────────────────────────────── -->
                    <section id="profile" class="surface scroll-mt-6 p-6">
                        <h2 class="text-base font-semibold">الملف الشخصي</h2>
                        <p class="mt-1 text-sm text-muted-foreground">اسمك وإيميلك — دول بيظهروا لينا إحنا بس، مش للزباين.</p>

                        <form class="mt-5 space-y-4" @submit.prevent="saveProfile">
                            <div>
                                <label class="field-label" for="name">الاسم</label>
                                <input id="name" v-model="profile.name" class="field" required autocomplete="name" />
                                <InputError :message="profile.errors.name" />
                            </div>

                            <div>
                                <label class="field-label" for="email">الإيميل</label>
                                <input id="email" v-model="profile.email" type="email" class="field" dir="ltr" required autocomplete="username" />
                                <InputError :message="profile.errors.email" />
                            </div>

                            <div v-if="mustVerifyEmail && !user.email_verified_at" class="rounded-xl bg-warning/10 p-3 text-sm text-warning">
                                إيميلك لسه مش متأكد.
                                <Link :href="route('verification.send')" method="post" as="button" class="font-medium underline underline-offset-2">
                                    ابعت رسالة التأكيد تاني
                                </Link>
                            </div>

                            <button type="submit" class="btn-primary" :disabled="profile.processing">
                                <LoaderCircle v-if="profile.processing" class="size-4 animate-spin" />
                                احفظ
                            </button>
                        </form>
                    </section>

                    <!-- ── Password ────────────────────────────────────── -->
                    <section id="password" class="surface scroll-mt-6 p-6">
                        <h2 class="text-base font-semibold">كلمة المرور</h2>
                        <p class="mt-1 text-sm text-muted-foreground">استخدم كلمة طويلة ومش مستخدمة في مكان تاني.</p>

                        <form class="mt-5 space-y-4" @submit.prevent="savePassword">
                            <div>
                                <label class="field-label" for="current_password">كلمة المرور الحالية</label>
                                <input id="current_password" v-model="password.current_password" type="password" class="field" dir="ltr" autocomplete="current-password" />
                                <InputError :message="password.errors.current_password" />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="field-label" for="new_password">كلمة المرور الجديدة</label>
                                    <input id="new_password" v-model="password.password" type="password" class="field" dir="ltr" autocomplete="new-password" />
                                    <InputError :message="password.errors.password" />
                                </div>
                                <div>
                                    <label class="field-label" for="confirm_password">أكّدها</label>
                                    <input id="confirm_password" v-model="password.password_confirmation" type="password" class="field" dir="ltr" autocomplete="new-password" />
                                    <InputError :message="password.errors.password_confirmation" />
                                </div>
                            </div>

                            <button type="submit" class="btn-primary" :disabled="password.processing">
                                <LoaderCircle v-if="password.processing" class="size-4 animate-spin" />
                                غيّر كلمة المرور
                            </button>
                        </form>
                    </section>

                    <!-- ── Appearance ──────────────────────────────────── -->
                    <section id="appearance" class="surface scroll-mt-6 p-6">
                        <h2 class="text-base font-semibold">المظهر</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            شكل لوحة التحكم عندك انت. مالوش أي علاقة بشكل متجرك عند الزباين — ده بيتظبط من «الثيمات».
                        </p>

                        <AppearanceTabs class="mt-5" />
                    </section>

                    <!-- ── Domain ──────────────────────────────────────── -->
                    <section id="domain" class="scroll-mt-6 space-y-4">
                        <div class="surface p-6">
                            <h2 class="text-base font-semibold">الدومين المخصص</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                اربط دومينك الخاص بمتجرك. شهادة الأمان مجانية وبتتظبط وتتجدد لوحدها.
                            </p>

                            <!-- The free sub-domain never goes away; say so,
                                 because merchants assume adding a domain
                                 replaces it and hesitate. -->
                            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-muted/50 p-4">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">العنوان الدائم لمتجرك</p>
                                    <a :href="store.canonical_url" target="_blank" rel="noopener" class="mt-0.5 block font-mono text-sm text-muted-foreground hover:underline" dir="ltr">
                                        {{ store.platform_host }}
                                    </a>
                                </div>
                                <span class="badge-neutral shrink-0">بيفضل شغّال دايمًا</span>
                            </div>

                            <form class="mt-5 space-y-2" @submit.prevent="addDomain">
                                <label class="field-label" for="domain">أضف دومين</label>
                                <div class="flex gap-2">
                                    <input id="domain" v-model="domainForm.domain" class="field flex-1 font-mono" dir="ltr" placeholder="mahmoud.com" autocomplete="off" />
                                    <button type="submit" class="btn-primary shrink-0" :disabled="domainForm.processing">
                                        <LoaderCircle v-if="domainForm.processing" class="size-4 animate-spin" />
                                        إضافة
                                    </button>
                                </div>
                                <p class="field-hint">اكتبه من غير http ومن غير أي مسار.</p>
                                <InputError :message="domainForm.errors.domain" />
                            </form>
                        </div>

                        <div
                            v-for="domain in domains"
                            :key="domain.id"
                            class="overflow-hidden rounded-2xl border shadow-e1"
                            :class="isLive(domain) ? 'border-success/30' : 'border-border'"
                        >
                            <div class="flex flex-wrap items-center gap-3 p-4" :class="isLive(domain) ? 'bg-success/5' : 'bg-card'">
                                <span
                                    class="flex size-11 shrink-0 items-center justify-center rounded-xl"
                                    :class="isLive(domain) ? 'bg-success/15 text-success' : 'bg-muted text-muted-foreground'"
                                >
                                    <component :is="isLive(domain) ? Lock : Globe" class="size-5" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a v-if="isLive(domain)" :href="`https://${domain.domain}`" target="_blank" rel="noopener" class="truncate font-mono font-semibold hover:underline" dir="ltr">{{ domain.domain }}</a>
                                        <span v-else class="truncate font-mono font-semibold" dir="ltr">{{ domain.domain }}</span>
                                        <span v-if="domain.is_primary" class="badge-gold">الأساسي</span>
                                    </div>
                                    <p class="mt-1 text-sm" :class="isLive(domain) ? 'text-success' : 'text-muted-foreground'">
                                        {{ isLive(domain) ? 'شغّال ومؤمّن — القفل ظاهر لزباينك' : domain.ssl_message }}
                                    </p>
                                </div>

                                <div class="flex shrink-0 items-center gap-1">
                                    <button class="btn-ghost px-2.5" title="افحص دلوقتي" :disabled="busyId === domain.id" @click="act(domain.id, 'post', route('domains.verify', domain.id))">
                                        <RefreshCw class="size-4" :class="{ 'animate-spin': busyId === domain.id }" />
                                    </button>
                                    <button v-if="domain.status === 'active' && !domain.is_primary" class="btn-ghost px-2.5" title="اجعله الأساسي" :disabled="busyId === domain.id" @click="act(domain.id, 'post', route('domains.primary', domain.id))">
                                        <Star class="size-4" />
                                    </button>
                                    <button class="btn-ghost px-2.5 text-destructive" title="شيل الدومين" :disabled="busyId === domain.id" @click="act(domain.id, 'delete', route('domains.destroy', domain.id))">
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </div>

                            <div v-if="!isLive(domain)" class="grid gap-px bg-border sm:grid-cols-3">
                                <div v-for="(step, i) in steps(domain)" :key="i" class="bg-card p-4">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                            :class="step.done ? 'bg-success text-white' : step.failed ? 'bg-destructive text-white' : 'bg-muted text-muted-foreground'"
                                        >
                                            <Check v-if="step.done" class="size-3.5" />
                                            <template v-else>{{ i + 1 }}</template>
                                        </span>
                                        <span class="text-sm font-medium">{{ step.label }}</span>
                                    </div>
                                    <p class="mt-1.5 pr-8 text-xs text-muted-foreground">{{ step.hint }}</p>
                                </div>
                            </div>

                            <!-- Instructions only while they are still needed. A
                                 working domain showing DNS records reads as an
                                 unfinished task. -->
                            <div v-if="domain.status !== 'active'" class="border-t border-border bg-muted/40 p-4">
                                <p class="mb-3 text-xs font-medium">افتح لوحة الدومين بتاعتك وضيف السجلات دي:</p>
                                <div class="space-y-2">
                                    <div v-for="record in domain.instructions" :key="record.type + record.name" class="flex flex-wrap items-center gap-2 rounded-xl bg-card p-3 text-xs">
                                        <span class="badge-neutral shrink-0">{{ record.type }}</span>
                                        <span class="font-mono" dir="ltr">{{ record.name }}</span>
                                        <span class="mx-1 text-muted-foreground">←</span>
                                        <span class="font-mono" dir="ltr">{{ Array.isArray(record.value) ? record.value.join(' , ') : record.value }}</span>
                                        <button
                                            class="btn-ghost mr-auto px-2 py-1"
                                            :title="'انسخ ' + record.type"
                                            @click="copy(Array.isArray(record.value) ? record.value.join(', ') : record.value)"
                                        >
                                            <Check v-if="copied === (Array.isArray(record.value) ? record.value.join(', ') : record.value)" class="size-3.5 text-success" />
                                            <Copy v-else class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <p v-if="domain.last_error" class="border-t border-border bg-muted/30 px-4 py-2.5 text-xs text-muted-foreground">
                                {{ domain.last_error }}
                            </p>
                            <p v-else-if="domain.last_checked_at && !isLive(domain)" class="border-t border-border bg-muted/30 px-4 py-2.5 text-xs text-muted-foreground">
                                آخر فحص {{ domain.last_checked_at }} · بنفحص لوحدنا كل شوية
                            </p>
                        </div>
                    </section>

                    <!-- ── Danger ──────────────────────────────────────── -->
                    <section class="rounded-2xl border border-destructive/30 bg-destructive/5 p-6">
                        <div class="flex items-start gap-3">
                            <TriangleAlert class="mt-0.5 size-5 shrink-0 text-destructive" />
                            <div class="min-w-0 flex-1">
                                <h2 class="text-base font-semibold text-destructive">حذف الحساب</h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    هيتمسح متجرك ومنتجاتك وطلباتك ودوميناتك. مفيش رجوع في الخطوة دي.
                                </p>

                                <button v-if="!confirmingDelete" class="btn-danger mt-4" @click="confirmingDelete = true">
                                    امسح حسابي
                                </button>

                                <form v-else class="mt-4 space-y-3" @submit.prevent="deleteAccount">
                                    <!-- The password is the confirmation. A
                                         typed "DELETE" proves nothing about who
                                         is sitting at the keyboard. -->
                                    <div>
                                        <label class="field-label" for="delete_password">اكتب كلمة المرور للتأكيد</label>
                                        <input id="delete_password" v-model="deleteForm.password" type="password" class="field max-w-sm" dir="ltr" autocomplete="current-password" />
                                        <InputError :message="deleteForm.errors.password" />
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" class="btn-danger" :disabled="deleteForm.processing">
                                            <LoaderCircle v-if="deleteForm.processing" class="size-4 animate-spin" />
                                            أيوه، امسح كل حاجة
                                        </button>
                                        <button type="button" class="btn-outline" @click="confirmingDelete = false; deleteForm.reset()">
                                            رجوع
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
