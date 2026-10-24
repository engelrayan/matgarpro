<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { ArrowUp, Plus, Trash2 } from 'lucide-vue-next';

export interface OfferRow {
    id: number | null;
    quantity: number | string;
    unit_price: string;
    title: string | null;
    badge: string | null;
    note: string | null;
    free_shipping: boolean;
    is_default: boolean;
}

const offers = defineModel<OfferRow[]>({ required: true });

const props = defineProps<{
    price: string | number;
    compareAtPrice: string | number | null;
    currency: string;
    hasOptions: boolean;
    errors: Record<string, string | undefined>;
}>();

const num = (v: unknown) => {
    const n = parseFloat(String(v ?? ''));
    return Number.isFinite(n) ? n : 0;
};

const money = (n: number) => n.toLocaleString('en-US', { maximumFractionDigits: 2 });

/** Mirrors ProductOffer::pieces() — counted the way it is said. */
const pieces = (count: number) =>
    count === 1 ? 'قطعة' : count === 2 ? 'قطعتين' : count <= 10 ? `${count} قطع` : `${count} قطعة`;

/** What the customer reads when the merchant leaves the wording blank. */
const autoTitle = (offer: OfferRow) => {
    const qty = Math.max(1, Math.round(num(offer.quantity)));
    return `اشتري ${pieces(qty)} بـ ${money(qty * num(offer.unit_price))}`;
};

/** The same "before" price the product page strikes through. */
const reference = () => Math.max(num(props.compareAtPrice), num(props.price));

const saving = (offer: OfferRow) => Math.max(0, (reference() - num(offer.unit_price)) * num(offer.quantity));

const add = () => {
    const last = offers.value[offers.value.length - 1];
    offers.value.push({
        id: null,
        quantity: last ? num(last.quantity) + 1 : 1,
        unit_price: String(props.price || ''),
        title: '',
        badge: '',
        note: '',
        free_shipping: false,
        is_default: false,
    });
};

const remove = (index: number) => offers.value.splice(index, 1);

const moveUp = (index: number) => {
    if (index === 0) return;
    const [row] = offers.value.splice(index, 1);
    offers.value.splice(index - 1, 0, row);
};

/** One offer starts selected; ticking another moves the tick. */
const makeDefault = (index: number) => {
    const wasDefault = offers.value[index].is_default;
    offers.value.forEach((o, i) => (o.is_default = i === index && !wasDefault));
};

const error = (index: number, field: string) => props.errors[`offers.${index}.${field}`];
</script>

<template>
    <section class="surface p-6">
        <HeadingSmall
            title="عروض الكمية"
            description="زي «اشتري قطعتين بـ 400». لما تضيف عروض، العميل بيختار عرض منهم بدل ما يكتب الكمية."
        />

        <p v-if="offers.length && hasOptions" class="mt-3 rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
            المنتج ليه مقاسات/ألوان، فالعميل هيختار لكل قطعة في العرض لوحدها.
        </p>

        <p v-if="offers.length && !offers.some((o) => num(o.quantity) === 1)" class="mt-3 rounded-lg bg-warning/10 px-3 py-2 text-xs text-warning">
            مفيش عرض لقطعة واحدة، فالعميل مش هيقدر يشتري قطعة لوحدها.
        </p>

        <div v-if="offers.length" class="mt-5 space-y-4">
            <div v-for="(offer, index) in offers" :key="index" class="rounded-xl border border-border p-4">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold">عرض {{ index + 1 }}</p>
                    <div class="flex items-center gap-1">
                        <button v-if="index > 0" type="button" class="btn-ghost px-2" aria-label="لفوق" @click="moveUp(index)">
                            <ArrowUp class="h-4 w-4" />
                        </button>
                        <button type="button" class="btn-ghost px-2 text-destructive" aria-label="امسح العرض" @click="remove(index)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label" :for="`offer-qty-${index}`">عدد القطع</label>
                        <input :id="`offer-qty-${index}`" v-model="offer.quantity" class="field tabular" inputmode="numeric" />
                        <InputError :message="error(index, 'quantity')" />
                    </div>
                    <div>
                        <label class="field-label" :for="`offer-price-${index}`">سعر القطعة ({{ currency }})</label>
                        <input :id="`offer-price-${index}`" v-model="offer.unit_price" class="field tabular" inputmode="decimal" />
                        <InputError :message="error(index, 'unit_price')" />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="field-label" :for="`offer-title-${index}`">نص العرض</label>
                        <input :id="`offer-title-${index}`" v-model="offer.title" class="field" :placeholder="autoTitle(offer)" maxlength="120" />
                        <p class="field-hint">سيبه فاضي وهيتكتب «{{ autoTitle(offer) }}».</p>
                    </div>

                    <div>
                        <label class="field-label" :for="`offer-badge-${index}`">
                            شارة <span class="font-normal text-muted-foreground">— اختياري</span>
                        </label>
                        <input :id="`offer-badge-${index}`" v-model="offer.badge" class="field" placeholder="الأكثر طلبًا" maxlength="40" />
                    </div>
                    <div>
                        <label class="field-label" :for="`offer-note-${index}`">
                            سطر تحت العرض <span class="font-normal text-muted-foreground">— اختياري</span>
                        </label>
                        <input :id="`offer-note-${index}`" v-model="offer.note" class="field" placeholder="+ هدية مع الطلب" maxlength="120" />
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2">
                    <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                        <input v-model="offer.free_shipping" type="checkbox" class="size-4 rounded border-input accent-primary" />
                        شحن مجاني للعرض ده
                    </label>
                    <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                        <input :checked="offer.is_default" type="checkbox" class="size-4 rounded border-input accent-primary" @change="makeDefault(index)" />
                        يبقى مختار من الأول
                    </label>
                </div>

                <p class="mt-3 border-t border-border pt-3 text-xs text-muted-foreground">
                    العميل هيدفع
                    <span class="tabular font-semibold text-foreground">{{ money(num(offer.quantity) * num(offer.unit_price)) }} {{ currency }}</span>
                    <template v-if="saving(offer) > 0">
                        · هيشوف «هتوفّر <span class="tabular">{{ money(saving(offer)) }}</span> {{ currency }}»
                    </template>
                </p>
            </div>
        </div>

        <InputError :message="errors.offers" />

        <button v-if="offers.length < 6" type="button" class="btn-outline mt-5" @click="add">
            <Plus class="h-4 w-4" />
            أضف عرض
        </button>
    </section>
</template>
