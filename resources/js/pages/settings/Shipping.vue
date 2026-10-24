<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, LoaderCircle, Truck, UserRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/*
 * What delivery costs the customer, and who carries the parcel.
 *
 * Priced by zone, because that is how Egyptian couriers price: four numbers
 * cover the country, and a zone opens up to its governorates for the one the
 * merchant's courier charges differently or does not reach.
 */

interface Governorate {
    name: string;
    price: number | null;
    closed: boolean;
}

interface Zone {
    key: string;
    label: string;
    price: number | null;
    governorates: Governorate[];
}

const props = defineProps<{
    enabled: boolean;
    carrier: 'daman' | 'self';
    damanConnected: boolean;
    currency: string;
    zones: Zone[];
}>();

const page = usePage();
const breadcrumbItems: BreadcrumbItem[] = [{ title: 'الشحن', href: '/settings/shipping' }];
const saved = computed(() => page.props.flash?.status === 'shipping-updated');

const form = useForm({
    enabled: props.enabled,
    carrier: props.carrier,
    zones: Object.fromEntries(props.zones.map((z) => [z.key, z.price === null ? '' : String(z.price)])) as Record<string, string>,
    governorates: Object.fromEntries(
        props.zones.flatMap((z) => z.governorates).map((g) => [g.name, { price: g.price === null ? '' : String(g.price), closed: g.closed }]),
    ) as Record<string, { price: string; closed: boolean }>,
});

const open = ref<Record<string, boolean>>({});

const openCount = (zone: Zone) => zone.governorates.filter((g) => !form.governorates[g.name].closed).length;

// Merchants type prices on phones with Arabic keyboards; "٥٠" has to save as 50.
const westernDigits = (value: string) => value.replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d))).trim();

const submit = () =>
    form
        .transform((data) => ({
            ...data,
            zones: Object.fromEntries(Object.entries(data.zones).map(([k, v]) => [k, westernDigits(v) || null])),
            governorates: Object.fromEntries(
                Object.entries(data.governorates).map(([name, g]) => [name, { price: westernDigits(g.price) || null, closed: g.closed }]),
            ),
        }))
        .put(route('shipping.update'), { preserveScroll: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="الشحن" />

        <SettingsLayout>
            <form class="space-y-6" @submit.prevent="submit">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">الشحن</h1>
                    <p class="mt-1 text-sm text-muted-foreground">مين بيوصّل طلباتك، والعميل بيدفع كام شحن لكل محافظة.</p>
                </div>

                <p v-if="saved" class="flex items-center gap-2 rounded-xl bg-success/10 px-4 py-3 text-sm text-success">
                    <Check class="h-4 w-4" />
                    اتحفظ.
                </p>

                <!-- ── Who carries the parcels ─────────────────────────────── -->
                <section class="surface space-y-4 p-6">
                    <h2 class="font-medium">بتشحن مع مين؟</h2>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors"
                            :class="form.carrier === 'daman' ? 'border-primary bg-primary/5' : 'border-border'"
                        >
                            <input v-model="form.carrier" type="radio" value="daman" class="mt-1 accent-primary" />
                            <span class="flex-1">
                                <span class="flex items-center gap-2 font-medium">
                                    <Truck class="h-4 w-4" />
                                    ضمان
                                    <span v-if="damanConnected" class="badge-success">متوصّل</span>
                                    <span v-else class="badge-neutral">مش متوصّل</span>
                                </span>
                                <span class="mt-1 block text-sm text-muted-foreground"> بتبعت الطلبات المتأكدة لضمان بضغطة من صفحة الطلبات. </span>
                                <Link href="/settings/daman" class="mt-2 inline-block text-sm text-primary underline">
                                    {{ damanConnected ? 'إعدادات الربط مع ضمان' : 'اربط حسابك في ضمان' }}
                                </Link>
                            </span>
                        </label>

                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors"
                            :class="form.carrier === 'self' ? 'border-primary bg-primary/5' : 'border-border'"
                        >
                            <input v-model="form.carrier" type="radio" value="self" class="mt-1 accent-primary" />
                            <span class="flex-1">
                                <span class="flex items-center gap-2 font-medium">
                                    <UserRound class="h-4 w-4" />
                                    مندوب أو شركة شحن تانية
                                </span>
                                <span class="mt-1 block text-sm text-muted-foreground">بتطبع البوالص من صفحة الطلبات وتسلّمها للمندوب.</span>
                            </span>
                        </label>
                    </div>
                </section>

                <!-- ── What the customer pays ──────────────────────────────── -->
                <section class="surface space-y-5 p-6">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input v-model="form.enabled" type="checkbox" class="mt-1 size-4 rounded border-input accent-primary" />
                        <span>
                            <span class="font-medium">العميل يدفع شحن حسب محافظته</span>
                            <span class="mt-1 block text-sm text-muted-foreground">
                                سعر الشحن بيظهر للعميل لما يختار محافظته، وبيتضاف على إجمالي الطلب مرة واحدة مهما كانت الكمية. خانة المحافظة في فورم
                                الطلب بتبقى إجبارية.
                            </span>
                        </span>
                    </label>
                    <InputError :message="form.errors.enabled" />

                    <p v-if="!form.enabled" class="rounded-xl bg-muted px-4 py-3 text-sm text-muted-foreground">
                        الشحن مش بيتحسب على العميل دلوقتي — الإجمالي هو سعر المنتج بس.
                    </p>

                    <div class="space-y-3" :class="{ 'pointer-events-none opacity-50': !form.enabled }">
                        <div v-for="zone in zones" :key="zone.key" class="rounded-xl border border-border">
                            <div class="flex flex-wrap items-center gap-3 p-4">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium">{{ zone.label }}</p>
                                    <p class="text-xs text-muted-foreground">{{ openCount(zone) }} من {{ zone.governorates.length }} محافظة مفتوحة</p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="form.zones[zone.key]"
                                        class="field tabular w-28"
                                        inputmode="decimal"
                                        placeholder="السعر"
                                        :aria-label="`سعر الشحن — ${zone.label}`"
                                    />
                                    <span class="text-sm text-muted-foreground">{{ currency }}</span>
                                </div>

                                <button type="button" class="btn-ghost px-2.5 py-1.5 text-sm" @click="open[zone.key] = !open[zone.key]">
                                    المحافظات
                                    <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open[zone.key] }" />
                                </button>
                            </div>
                            <InputError class="px-4 pb-3" :message="form.errors[`zones.${zone.key}`]" />

                            <div v-show="open[zone.key]" class="divide-y divide-border border-t border-border">
                                <div v-for="gov in zone.governorates" :key="gov.name" class="flex flex-wrap items-center gap-3 px-4 py-2.5">
                                    <span
                                        class="min-w-0 flex-1 text-sm"
                                        :class="{ 'text-muted-foreground line-through': form.governorates[gov.name].closed }"
                                    >
                                        {{ gov.name }}
                                    </span>

                                    <input
                                        v-model="form.governorates[gov.name].price"
                                        class="field tabular w-28 py-1.5"
                                        inputmode="decimal"
                                        :placeholder="form.zones[zone.key] ? `${form.zones[zone.key]} (زي القطاع)` : 'زي القطاع'"
                                        :disabled="form.governorates[gov.name].closed"
                                        :aria-label="`سعر الشحن — ${gov.name}`"
                                    />

                                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            class="size-4 rounded border-input accent-primary"
                                            :checked="!form.governorates[gov.name].closed"
                                            @change="form.governorates[gov.name].closed = !($event.target as HTMLInputElement).checked"
                                        />
                                        بنشحن هنا
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        المنتج اللي معلّم عليه «شحن مجاني» شحنه صفر. والطلب بيتسجّل بسعر الشحن وقت ما اتعمل، فلو غيّرت الأسعار بعد كده الطلبات القديمة
                        مش هتتغير.
                    </p>
                </section>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        حفظ
                    </button>
                </div>
            </form>
        </SettingsLayout>
    </AppLayout>
</template>
