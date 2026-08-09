<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Check, Copy, Link2Off, LoaderCircle, ShieldCheck, Truck } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Connected {
    connected: true;
    key_prefix: string | null;
    environment: 'test' | 'live';
    is_active: boolean;
    cod_includes_shipping: boolean;
    webhook_url: string;
    webhook_ready: boolean;
    connected_at: string | null;
    last_shipped_at: string | null;
    last_webhook_at: string | null;
    last_error: string | null;
}

const props = defineProps<{
    integration: Connected | { connected: false };
    stats: { shipped: number; failed: number; awaiting: number };
}>();

const page = usePage();
const breadcrumbItems: BreadcrumbItem[] = [{ title: 'الشحن مع ضمان', href: '/settings/daman' }];

const link = computed(() => (props.integration.connected ? (props.integration as Connected) : null));

// The controller flashes a key, not a sentence — the wording belongs next to
// the screen that shows it.
const FLASH_MESSAGES: Record<string, string> = {
    'daman-connected': 'تم الربط مع ضمان. تقدر تبعت طلباتك دلوقتي.',
    'daman-enabled': 'الربط اشتغل تاني.',
    'daman-disabled': 'الربط اتوقف مؤقتًا — زرار الشحن مش هيظهر في الطلبات.',
    'daman-webhook-saved': 'اتحفظ. تحديثات حالة الشحنات هتبدأ توصلك.',
    'daman-pricing-saved': 'اتحفظ.',
    'daman-disconnected': 'اتفك الربط مع ضمان.',
};

const flash = computed(() => FLASH_MESSAGES[page.props.flash?.status as string] ?? null);

/* ── Connecting ─────────────────────────────────────────────────────────── */

const keyForm = useForm({ api_key: '' });

// Checked against Daman before it is saved, so a mistyped key fails here rather
// than on the first parcel the merchant thinks is on its way.
const connect = () =>
    keyForm.put(route('daman.update'), {
        preserveScroll: true,
        onSuccess: () => keyForm.reset(),
    });

const disconnect = () => {
    if (!confirm('هتفك الربط مع ضمان. الطلبات اللي اتشحنت هتفضل بأرقامها زي ما هي.')) return;
    router.delete(route('daman.destroy'), { preserveScroll: true });
};

const toggle = () => router.patch(route('daman.toggle'), {}, { preserveScroll: true });

/* ── Status updates ─────────────────────────────────────────────────────── */

const secretForm = useForm({ webhook_secret: '' });

const saveSecret = () =>
    secretForm.put(route('daman.webhook'), {
        preserveScroll: true,
        onSuccess: () => secretForm.reset(),
    });

const copied = ref(false);

const copyUrl = async () => {
    if (!link.value) return;
    await navigator.clipboard.writeText(link.value.webhook_url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
};

/* ── Collection amount ──────────────────────────────────────────────────── */

const setPricing = (inclusive: boolean) =>
    router.put(route('daman.pricing'), { cod_includes_shipping: inclusive }, { preserveScroll: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="الشحن مع ضمان" />

        <SettingsLayout>
            <div class="space-y-6">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">الشحن مع ضمان</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        اربط متجرك بحسابك في ضمان، وابعتله الطلبات المتأكدة بضغطة واحدة. ضمان
                        بيوصّلها لشركة الشحن اللي إنت متعاقد معاها ويرجّعلك رقم الطلب ورقم البوليصة.
                    </p>
                </div>

                <div v-if="flash" class="flex items-start gap-2 rounded-xl border border-success/30 bg-success/5 p-4 text-sm text-success">
                    <Check class="mt-0.5 size-4 shrink-0" />
                    <span>{{ flash }}</span>
                </div>

                <!-- ── Not connected ──────────────────────────────────────── -->
                <section v-if="!link" class="surface p-5">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <Truck class="size-4" />
                        اربط حسابك
                    </h2>

                    <ol class="mt-3 space-y-2 text-sm leading-relaxed text-muted-foreground">
                        <li>١ — افتح حسابك في ضمان → إعدادات الـ API.</li>
                        <li>٢ — اعمل مفتاح جديد (Live لو عايز شحن حقيقي، Test لو بتجرّب).</li>
                        <li>٣ — الصقه هنا. المفتاح بيظهر مرة واحدة بس عند ضمان، فخده وقتها.</li>
                    </ol>

                    <form class="mt-4 space-y-3" @submit.prevent="connect">
                        <input
                            v-model="keyForm.api_key"
                            class="field font-mono text-sm"
                            dir="ltr"
                            placeholder="dm_live_…"
                            autocomplete="off"
                        />
                        <InputError :message="keyForm.errors.api_key" />

                        <button type="submit" class="btn-primary" :disabled="keyForm.processing || !keyForm.api_key">
                            <LoaderCircle v-if="keyForm.processing" class="size-4 animate-spin" />
                            اربط دلوقتي
                        </button>
                    </form>
                </section>

                <!-- ── Connected ──────────────────────────────────────────── -->
                <template v-else>
                    <section class="surface p-5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span v-if="link.is_active" class="badge-success gap-1">
                                <ShieldCheck class="size-3" />
                                مربوط وشغّال
                            </span>
                            <span v-else class="badge-neutral">مربوط بس متوقّف</span>

                            <!-- A test key never reaches a real carrier. Said
                                 loudly, because the failure mode is a merchant
                                 believing a day's parcels are on their way. -->
                            <span v-if="link.environment === 'test'" class="badge-warning">
                                مفتاح تجريبي — مفيش شحن حقيقي
                            </span>

                            <span v-if="link.key_prefix" class="font-mono text-xs text-muted-foreground" dir="ltr">
                                {{ link.key_prefix }}…
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-xl border border-border p-3">
                                <p class="tabular text-xl font-bold">{{ stats.shipped }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">اتشحن عبر ضمان</p>
                            </div>
                            <div class="rounded-xl border border-border p-3">
                                <p class="tabular text-xl font-bold">{{ stats.awaiting }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">متأكد ولسه ماتبعتش</p>
                            </div>
                            <div class="rounded-xl border border-border p-3">
                                <p class="tabular text-xl font-bold" :class="stats.failed ? 'text-destructive' : ''">
                                    {{ stats.failed }}
                                </p>
                                <p class="mt-0.5 text-xs text-muted-foreground">اترفض</p>
                            </div>
                        </div>

                        <p v-if="link.last_shipped_at" class="mt-3 text-xs text-muted-foreground">
                            آخر إرسال {{ link.last_shipped_at }}
                        </p>

                        <p v-if="link.last_error" class="mt-2 flex items-start gap-1.5 text-xs text-destructive">
                            <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                            {{ link.last_error }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button class="btn-outline" @click="toggle">
                                {{ link.is_active ? 'وقّف الربط مؤقتًا' : 'شغّل الربط' }}
                            </button>
                            <button class="btn-ghost text-destructive" @click="disconnect">
                                <Link2Off class="size-4" />
                                فك الربط
                            </button>
                        </div>
                    </section>

                    <!-- ── Collection amount ──────────────────────────────
                         The one setting here that can cost the merchant money
                         if it disagrees with their Daman account.
                    -->
                    <section class="surface p-5">
                        <h2 class="font-semibold">المبلغ اللي المندوب هيحصّله</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            لازم يطابق نفس الإعداد في حسابك على ضمان.
                        </p>

                        <div class="mt-4 space-y-2">
                            <button
                                class="w-full rounded-xl border p-3 text-right transition-colors"
                                :class="link.cod_includes_shipping ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/40'"
                                @click="setPricing(true)"
                            >
                                <span class="flex items-center gap-2 text-sm font-medium">
                                    <Check v-if="link.cod_includes_shipping" class="size-4 text-primary" />
                                    إجمالي الطلب زي ما العميل شافه
                                </span>
                                <span class="mt-1 block text-xs leading-relaxed text-muted-foreground">
                                    بنبعت الإجمالي كله، وضمان بيخصم أجرة الشحن منه. ده اللي بيخلّي
                                    العميل يدفع نفس الرقم المكتوب في المتجر.
                                </span>
                            </button>

                            <button
                                class="w-full rounded-xl border p-3 text-right transition-colors"
                                :class="!link.cod_includes_shipping ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/40'"
                                @click="setPricing(false)"
                            >
                                <span class="flex items-center gap-2 text-sm font-medium">
                                    <Check v-if="!link.cod_includes_shipping" class="size-4 text-primary" />
                                    قيمة المنتجات بس، وضمان يزوّد الشحن
                                </span>
                                <span class="mt-1 block text-xs leading-relaxed text-muted-foreground">
                                    المبلغ اللي هيتحصّل ممكن يطلع مختلف عن اللي العميل شافه في
                                    المتجر، لأن تسعيرة ضمان هي اللي بتحسب الشحن.
                                </span>
                            </button>
                        </div>

                        <p class="mt-3 flex items-start gap-1.5 rounded-xl border border-warning/30 bg-warning/5 p-3 text-xs leading-relaxed text-warning">
                            <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                            لو الإعداد ده مختلف عن اللي في ضمان، المندوب هيطلب من العميل مبلغ غير
                            اللي اتفقتوا عليه.
                        </p>
                    </section>

                    <!-- ── Status updates ─────────────────────────────────── -->
                    <section class="surface p-5">
                        <h2 class="flex items-center gap-2 font-semibold">
                            تحديث حالة الشحنات
                            <span v-if="link.webhook_ready" class="badge-success">مفعّل</span>
                            <span v-else class="badge-warning">ناقص خطوة</span>
                        </h2>
                        <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                            من غير الخطوة دي الطلب هيفضل «تم الشحن» عندك لحد ما تغيّره بإيدك، حتى
                            لو اتسلّم أو رجع.
                        </p>

                        <div class="mt-4 space-y-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium">١ — حط الرابط ده في ضمان (Webhook URL)</label>
                                <div class="flex gap-2">
                                    <input :value="link.webhook_url" readonly class="field flex-1 font-mono text-xs" dir="ltr" />
                                    <button class="btn-outline shrink-0" @click="copyUrl">
                                        <Check v-if="copied" class="size-4 text-success" />
                                        <Copy v-else class="size-4" />
                                        نسخ
                                    </button>
                                </div>
                            </div>

                            <form @submit.prevent="saveSecret">
                                <label class="mb-1.5 block text-sm font-medium">٢ — الصق الـ secret اللي ضمان عرضه عليك</label>
                                <div class="flex gap-2">
                                    <input
                                        v-model="secretForm.webhook_secret"
                                        class="field flex-1 font-mono text-xs"
                                        dir="ltr"
                                        :placeholder="link.webhook_ready ? 'غيّر الـ secret…' : 'whsec_…'"
                                        autocomplete="off"
                                    />
                                    <button type="submit" class="btn-primary shrink-0" :disabled="secretForm.processing || !secretForm.webhook_secret">
                                        <LoaderCircle v-if="secretForm.processing" class="size-4 animate-spin" />
                                        حفظ
                                    </button>
                                </div>
                                <InputError :message="secretForm.errors.webhook_secret" />
                                <p class="field-hint">
                                    بنستخدمه عشان نتأكد إن التحديث جاي من ضمان فعلًا مش من حد تاني.
                                </p>
                            </form>
                        </div>

                        <p v-if="link.last_webhook_at" class="mt-3 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Check class="size-3.5 text-success" />
                            آخر تحديث وصل {{ link.last_webhook_at }}
                        </p>
                    </section>

                    <!-- Where the button actually is, because a merchant who
                         just connected goes looking for it. -->
                    <section class="surface p-5">
                        <p class="font-medium">وبعدين؟</p>
                        <ul class="mt-3 space-y-2 text-sm text-muted-foreground">
                            <li class="flex items-start gap-2">
                                <Check class="mt-0.5 size-4 shrink-0 text-success" />
                                أكّد الطلبات من صفحة الطلبات زي ما إنت عمل عادي.
                            </li>
                            <li class="flex items-start gap-2">
                                <Check class="mt-0.5 size-4 shrink-0 text-success" />
                                حدّد اللي عايز تشحنه، وهيظهرلك زرار «شحن عبر ضمان».
                            </li>
                            <li class="flex items-start gap-2">
                                <Check class="mt-0.5 size-4 shrink-0 text-success" />
                                رقم طلب ضمان ورقم البوليصة هيتكتبوا على كل طلب أول ما ضمان يرد.
                            </li>
                        </ul>
                    </section>
                </template>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
