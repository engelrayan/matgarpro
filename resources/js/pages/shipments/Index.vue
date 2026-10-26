<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Banknote, Check, CircleCheck, LoaderCircle, PackageCheck, Truck, Undo2, Wallet } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

/*
 * الشحن والتحصيل.
 *
 * Cash on delivery means the money for a delivered parcel sits with the
 * carrier until they pay it over. This page says, per carrier, how much that
 * is and for which orders — and is where the merchant records a payment when
 * it lands, so "how much does Bosta still owe me" is a number, not a call.
 */

interface Carrier {
    key: string;
    name: string | null;
    in_transit_count: number;
    in_transit_value: number;
    delivered_count: number;
    due_count: number;
    due_value: number;
    returned_count: number;
    collected_value: number;
    last_collected_on: string | null;
    delivery_rate: number | null;
}

interface CollectionRow {
    id: number;
    carrier: string | null;
    amount: number;
    expected_amount: number;
    difference: number;
    orders_count: number;
    received_on: string;
    reference: string | null;
    note: string | null;
}

interface DueOrder {
    id: number;
    number: number;
    customer_name: string;
    governorate: string | null;
    total: number;
    tracking: string | null;
    delivered_on: string | null;
}

const props = defineProps<{
    carriers: Carrier[];
    totals: { in_transit_count: number; in_transit_value: number; due_count: number; due_value: number; collected_value: number; returned_count: number };
    collections: CollectionRow[];
    currency: string;
    manualKey: string;
    damanLinked: boolean;
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'الشحن والتحصيل', href: '/shipments' }];

const money = (v: number) => v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const num = (v: number) => v.toLocaleString('en-US');
const carrierName = (name: string | null) => name ?? 'شحن بنفسك';

const page = usePage();
const flash = computed(() => page.props.flash?.status as string | undefined);

/* ── Recording a collection ─────────────────────────────────────────────── */

const open = ref(false);
const carrier = ref<Carrier | null>(null);
const due = ref<DueOrder[]>([]);
const loading = ref(false);
const loadError = ref('');

const form = reactive({ order_ids: [] as number[], amount: '', received_on: '', reference: '', note: '' });
const errors = ref<Record<string, string>>({});
const saving = ref(false);
// Until the merchant types an amount, it follows the selection: the common
// case is "they paid exactly what these came to".
const amountTouched = ref(false);

// The merchant's own calendar day: toISOString() is UTC, which in Cairo is
// still yesterday until two or three in the morning.
const today = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const expected = computed(() => due.value.filter((o) => form.order_ids.includes(o.id)).reduce((sum, o) => sum + o.total, 0));
const difference = computed(() => Math.round((expected.value - Number(form.amount || 0)) * 100) / 100);

watch(expected, (value) => {
    if (!amountTouched.value) form.amount = value ? value.toFixed(2) : '';
});

const startCollection = async (row: Carrier) => {
    carrier.value = row;
    open.value = true;
    loading.value = true;
    loadError.value = '';
    errors.value = {};
    due.value = [];
    amountTouched.value = false;
    Object.assign(form, { order_ids: [], amount: '', received_on: today(), reference: '', note: '' });

    try {
        const response = await fetch(`/shipments/due?carrier=${encodeURIComponent(row.key)}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(String(response.status));
        due.value = (await response.json()).orders;
        form.order_ids = due.value.map((o) => o.id);
    } catch {
        loadError.value = 'ماقدرناش نجيب الطلبات. جرّب تاني.';
    } finally {
        loading.value = false;
    }
};

const allSelected = computed(() => due.value.length > 0 && form.order_ids.length === due.value.length);
const toggleAll = () => (form.order_ids = allSelected.value ? [] : due.value.map((o) => o.id));

const submit = () => {
    if (!carrier.value) return;
    saving.value = true;
    router.post('/shipments/collections', { carrier: carrier.value.key, ...form }, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
        onError: (e) => (errors.value = e),
        onFinish: () => (saving.value = false),
    });
};

const undo = (row: CollectionRow) => {
    if (!confirm(`هتلغي تحصيل ${money(row.amount)} ${props.currency} من ${carrierName(row.carrier)}، وطلباته (${row.orders_count}) هترجع "مستنية تحصيل". تمام؟`)) return;
    router.delete(`/shipments/collections/${row.id}`, { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="الشحن والتحصيل" />

        <div class="mx-auto w-full max-w-7xl space-y-6 px-4 pb-10 md:space-y-7 md:px-6">
            <section>
                <h1 class="text-large-title">الشحن والتحصيل</h1>
                <p class="mt-1 max-w-2xl text-[15px] leading-relaxed text-muted-foreground">
                    فلوس الطلب اللي بيتدفع عند الاستلام بتفضل مع شركة الشحن لحد ما تحوّلهالك. هنا تعرف كل شركة معاها كام، وتسجّل التحصيل أول ما يوصلك.
                </p>
            </section>

            <p v-if="flash === 'collection-recorded'" class="surface flex items-center gap-2 p-3 text-[15px] text-success" role="status">
                <CircleCheck class="size-5" /> اتسجّل التحصيل، والطلبات دي اتعلّمت إن فلوسها وصلت.
            </p>
            <p v-else-if="flash === 'collection-undone'" class="surface flex items-center gap-2 p-3 text-[15px]" role="status">
                <Undo2 class="size-5" /> اتلغى التحصيل، وطلباته رجعت مستنية تحصيل.
            </p>

            <!-- ── Nothing shipped yet ─────────────────────────────────── -->
            <section v-if="!carriers.length" class="surface flex flex-col items-center gap-3 px-6 py-12 text-center">
                <span class="ios-tile size-14 bg-primary text-primary-foreground"><Truck class="size-7" /></span>
                <p class="text-xl font-bold">لسه مفيش شحنات</p>
                <p class="max-w-md text-[15px] text-muted-foreground">
                    أول ما تبعت طلبات مؤكدة للشحن، هتلاقي هنا كل شركة معاها كام شحنة وكام فلوس.
                </p>
                <Link v-if="!damanLinked" href="/settings/daman" class="btn-primary mt-2">اربط ضمان عشان تشحن بضغطة</Link>
                <Link v-else href="/orders?status=confirmed" class="btn-primary mt-2">ابعت الطلبات المؤكدة</Link>
            </section>

            <template v-else>
                <!-- ── Where the money is ─────────────────────────────── -->
                <section class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
                    <div class="surface-lux col-span-2 flex flex-col p-4 md:p-5 lg:col-span-1">
                        <p class="flex items-center gap-1.5 text-[13px] font-medium text-muted-foreground"><Wallet class="size-4" /> فلوسك عند شركات الشحن</p>
                        <p class="tabular mt-2 text-[32px] font-bold leading-none text-primary">{{ money(totals.due_value) }}</p>
                        <p class="mt-1.5 text-[13px] text-muted-foreground">{{ currency }} · <span class="tabular">{{ num(totals.due_count) }}</span> طلب اتسلّم ومستني تحصيل</p>
                    </div>
                    <div class="surface flex flex-col p-4 md:p-5">
                        <p class="flex items-center gap-1.5 text-[13px] font-medium text-muted-foreground"><Truck class="size-4" /> في الطريق</p>
                        <p class="tabular mt-2 text-[26px] font-bold leading-none">{{ money(totals.in_transit_value) }}</p>
                        <p class="mt-1.5 text-[13px] text-muted-foreground"><span class="tabular">{{ num(totals.in_transit_count) }}</span> شحنة لسه ماوصلتش</p>
                    </div>
                    <div class="surface flex flex-col p-4 md:p-5">
                        <p class="flex items-center gap-1.5 text-[13px] font-medium text-muted-foreground"><Banknote class="size-4" /> اتحصّل</p>
                        <p class="tabular mt-2 text-[26px] font-bold leading-none text-success">{{ money(totals.collected_value) }}</p>
                        <p class="mt-1.5 text-[13px] text-muted-foreground">{{ currency }} وصلك من الشركات</p>
                    </div>
                    <div class="surface flex flex-col p-4 md:p-5">
                        <p class="flex items-center gap-1.5 text-[13px] font-medium text-muted-foreground"><Undo2 class="size-4" /> مرتجع</p>
                        <p class="tabular mt-2 text-[26px] font-bold leading-none">{{ num(totals.returned_count) }}</p>
                        <p class="mt-1.5 text-[13px] text-muted-foreground">شحنة رجعت من غير ما تتسلّم</p>
                    </div>
                </section>

                <!-- ── Per carrier ───────────────────────────────────── -->
                <section>
                    <h2 class="ios-caption mb-1.5">شركات الشحن</h2>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:gap-4">
                        <article v-for="c in carriers" :key="c.key" class="surface flex flex-col p-4 md:p-5">
                            <header class="flex items-center gap-3">
                                <span class="ios-tile bg-primary text-primary-foreground"><Truck class="size-[18px]" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[17px] font-semibold">{{ carrierName(c.name) }}</p>
                                    <p class="text-[13px] text-muted-foreground">
                                        <template v-if="c.delivery_rate !== null">نسبة التسليم <span class="tabular font-medium text-foreground" dir="ltr">{{ c.delivery_rate }}%</span> · </template>
                                        <span class="tabular">{{ num(c.delivered_count) }}</span> اتسلّم
                                    </p>
                                </div>
                            </header>

                            <dl class="ios-group mt-4 text-[15px]">
                                <div class="ios-row justify-between bg-primary/[0.05]">
                                    <dt class="font-semibold">مستني تحصيل</dt>
                                    <dd class="text-left">
                                        <span class="tabular font-bold text-primary">{{ money(c.due_value) }}</span>
                                        <span class="text-[13px] text-muted-foreground"> · <span class="tabular">{{ num(c.due_count) }}</span> طلب</span>
                                    </dd>
                                </div>
                                <div class="ios-row justify-between">
                                    <dt class="text-muted-foreground">في الطريق</dt>
                                    <dd class="text-left">
                                        <span class="tabular font-medium">{{ money(c.in_transit_value) }}</span>
                                        <span class="text-[13px] text-muted-foreground"> · <span class="tabular">{{ num(c.in_transit_count) }}</span> شحنة</span>
                                    </dd>
                                </div>
                                <div class="ios-row justify-between">
                                    <dt class="text-muted-foreground">اتحصّل</dt>
                                    <dd class="text-left">
                                        <span class="tabular font-medium text-success">{{ money(c.collected_value) }}</span>
                                        <span v-if="c.last_collected_on" class="text-[13px] text-muted-foreground"> · آخر مرة {{ c.last_collected_on }}</span>
                                    </dd>
                                </div>
                                <div class="ios-row justify-between">
                                    <dt class="text-muted-foreground">مرتجع</dt>
                                    <dd class="tabular font-medium">{{ num(c.returned_count) }}</dd>
                                </div>
                            </dl>

                            <button class="btn-primary mt-4" :disabled="!c.due_count" @click="startCollection(c)">
                                <Banknote class="size-4" />
                                سجّل تحصيل
                            </button>
                            <p v-if="!c.due_count" class="mt-1.5 text-center text-[12px] text-muted-foreground">مفيش طلبات متسلّمة مستنية فلوسها.</p>
                        </article>
                    </div>
                </section>

                <!-- ── What has been paid over ─────────────────────────── -->
                <section>
                    <h2 class="ios-caption mb-1.5">التحصيلات</h2>
                    <div v-if="collections.length" class="ios-group">
                        <div v-for="row in collections" :key="row.id" class="ios-row min-h-[64px] gap-3">
                            <span class="ios-tile bg-success text-white"><PackageCheck class="size-[18px]" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[15px] font-semibold">
                                    {{ carrierName(row.carrier) }}
                                    <span class="font-normal text-muted-foreground">· <span class="tabular">{{ num(row.orders_count) }}</span> طلب</span>
                                </p>
                                <p class="truncate text-[13px] text-muted-foreground">
                                    {{ row.received_on }}<template v-if="row.reference"> · {{ row.reference }}</template><template v-if="row.note"> · {{ row.note }}</template>
                                </p>
                            </div>
                            <div class="shrink-0 text-left">
                                <p class="tabular text-[15px] font-bold">{{ money(row.amount) }}</p>
                                <p v-if="row.difference > 0" class="text-[12px] text-warning">
                                    أقل بـ <span class="tabular">{{ money(row.difference) }}</span>
                                </p>
                                <p v-else-if="row.difference < 0" class="text-[12px] text-success">
                                    زيادة <span class="tabular">{{ money(-row.difference) }}</span>
                                </p>
                            </div>
                            <button class="btn-ghost shrink-0 px-2" title="إلغاء التحصيل ده" @click="undo(row)">
                                <Undo2 class="size-4" />
                            </button>
                        </div>
                    </div>
                    <p v-else class="surface p-4 text-[15px] text-muted-foreground">
                        لسه ماسجّلتش تحصيل. أول ما شركة تحوّلك فلوس، دوس «سجّل تحصيل» على كارتها.
                    </p>
                </section>
            </template>
        </div>

        <!-- ── The collection form ───────────────────────────────────── -->
        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>تحصيل من {{ carrierName(carrier?.name ?? null) }}</DialogTitle>
                    <DialogDescription>علّم الطلبات اللي الفلوس دي عنها، واكتب المبلغ اللي وصلك فعلًا.</DialogDescription>
                </DialogHeader>

                <div v-if="loading" class="flex justify-center py-10"><LoaderCircle class="size-6 animate-spin text-muted-foreground" /></div>
                <p v-else-if="loadError" class="field-error">{{ loadError }}</p>

                <form v-else class="space-y-4" @submit.prevent="submit">
                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <p class="text-[13px] font-semibold">الطلبات المتسلّمة (<span class="tabular">{{ num(form.order_ids.length) }}</span> من <span class="tabular">{{ num(due.length) }}</span>)</p>
                            <button type="button" class="text-[13px] font-medium text-primary" @click="toggleAll">{{ allSelected ? 'شيل الكل' : 'علّم الكل' }}</button>
                        </div>
                        <div class="ios-group max-h-64 overflow-y-auto">
                            <label v-for="o in due" :key="o.id" class="ios-row cursor-pointer gap-3 text-[14px]">
                                <input v-model="form.order_ids" type="checkbox" :value="o.id" class="size-4 shrink-0 accent-[hsl(var(--primary))]" />
                                <span class="min-w-0 flex-1">
                                    <span class="font-medium">#{{ o.number }} · {{ o.customer_name }}</span>
                                    <span class="block truncate text-[12px] text-muted-foreground" dir="auto">
                                        {{ o.governorate }}<template v-if="o.tracking"> · {{ o.tracking }}</template><template v-if="o.delivered_on"> · {{ o.delivered_on }}</template>
                                    </span>
                                </span>
                                <span class="tabular shrink-0 font-semibold">{{ money(o.total) }}</span>
                            </label>
                        </div>
                        <p v-if="errors.order_ids" class="field-error">{{ errors.order_ids }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="field-label" for="amount">المبلغ اللي وصلك</label>
                            <input id="amount" v-model="form.amount" type="number" step="0.01" min="0" inputmode="decimal" class="field tabular" dir="ltr" @input="amountTouched = true" />
                            <p v-if="errors.amount" class="field-error">{{ errors.amount }}</p>
                        </div>
                        <div>
                            <label class="field-label" for="received_on">وصلك يوم</label>
                            <input id="received_on" v-model="form.received_on" type="date" :max="today()" class="field" dir="ltr" />
                            <p v-if="errors.received_on" class="field-error">{{ errors.received_on }}</p>
                        </div>
                    </div>

                    <!-- Expected against received: the gap is the carrier's
                         fees, or a shortfall worth a phone call. -->
                    <div class="ios-group text-[14px]">
                        <div class="ios-row justify-between">
                            <span class="text-muted-foreground">المفروض حسب الطلبات</span>
                            <span class="tabular font-semibold">{{ money(expected) }} {{ currency }}</span>
                        </div>
                        <div class="ios-row justify-between">
                            <span class="text-muted-foreground">الفرق</span>
                            <span class="tabular font-semibold" :class="difference > 0 ? 'text-warning' : difference < 0 ? 'text-success' : ''">
                                <template v-if="difference > 0">أقل بـ {{ money(difference) }} (مصاريف الشحن أو عجز)</template>
                                <template v-else-if="difference < 0">زيادة {{ money(-difference) }}</template>
                                <template v-else><Check class="inline size-4" /> مظبوط</template>
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="field-label" for="reference">رقم التحويل <span class="font-normal text-muted-foreground">— اختياري</span></label>
                            <input id="reference" v-model="form.reference" class="field" maxlength="120" placeholder="InstaPay / رقم الإيصال" />
                        </div>
                        <div>
                            <label class="field-label" for="note">ملاحظة <span class="font-normal text-muted-foreground">— اختياري</span></label>
                            <input id="note" v-model="form.note" class="field" maxlength="500" />
                        </div>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="btn-primary flex-1" :disabled="saving || !form.order_ids.length || form.amount === ''">
                            <LoaderCircle v-if="saving" class="size-4 animate-spin" />
                            سجّل التحصيل
                        </button>
                        <button type="button" class="btn-ghost" @click="open = false">إلغاء</button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
