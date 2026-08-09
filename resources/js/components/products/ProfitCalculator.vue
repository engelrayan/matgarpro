<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    price: string | number;
    cost: string | number | null;
    currency: string;
}>();

/*
 * Live margin, shown while the merchant is still choosing the price.
 *
 * Cash-on-delivery sellers in this market routinely price by copying a
 * competitor and only discover the margin after a month of shipping. Putting
 * the number next to the field is the difference between pricing and guessing.
 */
const num = (v: string | number | null) => {
    const n = typeof v === 'string' ? parseFloat(v) : v;
    return Number.isFinite(n as number) ? (n as number) : 0;
};

const price = computed(() => num(props.price));
const cost = computed(() => num(props.cost));

const hasBoth = computed(() => price.value > 0 && cost.value > 0);

const profit = computed(() => price.value - cost.value);
const margin = computed(() => (price.value > 0 ? (profit.value / price.value) * 100 : 0));

const money = (v: number) =>
    v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Selling below cost is a mistake worth shouting about, not a subtle tint.
const tone = computed(() => {
    if (profit.value < 0) return 'text-destructive';
    if (margin.value < 20) return 'text-warning';
    return 'text-success';
});
</script>

<template>
    <div class="rounded-xl border border-border bg-muted/40 p-4">
        <p class="text-xs font-medium text-muted-foreground">ربحك من القطعة</p>

        <template v-if="hasBoth">
            <div class="mt-1.5 flex items-baseline gap-2">
                <span class="tabular text-2xl font-bold tracking-tight" :class="tone">
                    {{ money(profit) }}
                </span>
                <span class="text-xs text-muted-foreground">{{ currency }}</span>
                <span class="tabular text-sm font-medium" :class="tone">
                    ({{ Math.round(margin) }}%)
                </span>
            </div>

            <p v-if="profit < 0" class="mt-2 text-xs font-medium text-destructive">
                إنت بتبيع بأقل من التكلفة — كل قطعة خسارة.
            </p>
            <p v-else-if="margin < 20" class="mt-2 text-xs text-warning">
                الهامش ضعيف. الشحن والمرتجع ممكن ياكلوه بالكامل.
            </p>
            <p v-else class="mt-2 text-xs text-muted-foreground">
                لو بعت ١٠٠ قطعة: <span class="tabular font-medium text-foreground">{{ money(profit * 100) }}</span> {{ currency }}
            </p>
        </template>

        <p v-else class="mt-1.5 text-xs text-muted-foreground">
            اكتب السعر والتكلفة وهنحسبلك الربح والهامش.
        </p>
    </div>
</template>
