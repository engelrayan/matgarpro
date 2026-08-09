<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    name: string;
    price: string | number;
    compareAtPrice: string | number | null;
    description: string;
    images: { url: string }[];
    options: { name: string; values: string[] }[];
    currency: string;
    storeName: string;
}>();

/*
 * A phone-shaped preview of the customer's actual view, updating as the
 * merchant types.
 *
 * Worth the code: a merchant filling this form is guessing what the page will
 * look like, and guessing is where bad product pages come from. Seeing the
 * struck-through price and the discount badge appear the moment a compare-at
 * price is typed teaches the feature better than any hint text.
 */
const num = (v: string | number | null) => {
    const n = typeof v === 'string' ? parseFloat(v) : v;
    return Number.isFinite(n as number) ? (n as number) : 0;
};

const money = (v: number) =>
    v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const price = computed(() => num(props.price));
const compare = computed(() => num(props.compareAtPrice));

const discount = computed(() => {
    if (compare.value <= price.value || compare.value === 0) return null;
    return Math.round((1 - price.value / compare.value) * 100);
});

const cover = computed(() => props.images[0]?.url ?? null);
</script>

<template>
    <div class="mx-auto w-full max-w-[300px]">
        <!-- Phone frame -->
        <div class="rounded-[2rem] border-8 border-sand-800 bg-sand-800 shadow-e3 dark:border-sand-900 dark:bg-sand-900">
            <div class="overflow-hidden rounded-[1.4rem] bg-background">
                <!-- Store bar -->
                <div class="flex items-center gap-2 border-b border-border px-3 py-2.5">
                    <span class="flex size-6 items-center justify-center rounded-lg bg-primary text-xs font-bold text-primary-foreground">
                        {{ storeName.trim().charAt(0) || 'م' }}
                    </span>
                    <span class="truncate text-xs font-semibold">{{ storeName || 'متجرك' }}</span>
                </div>

                <div class="max-h-[420px] overflow-y-auto">
                    <!-- Cover -->
                    <div class="relative aspect-square bg-muted">
                        <img v-if="cover" :src="cover" alt="" class="size-full object-cover" />
                        <div v-else class="flex size-full items-center justify-center text-xs text-muted-foreground">
                            صورة المنتج
                        </div>
                        <span v-if="discount" class="badge-danger absolute right-2 top-2">
                            خصم {{ discount }}%
                        </span>
                    </div>

                    <div class="space-y-3 p-3">
                        <h3 class="text-sm font-bold leading-snug">
                            {{ name || 'اسم المنتج' }}
                        </h3>

                        <div class="flex items-baseline gap-2">
                            <span class="tabular text-lg font-bold text-primary">{{ money(price) }}</span>
                            <span class="text-[11px] text-muted-foreground">{{ currency }}</span>
                            <span v-if="discount" class="tabular text-xs text-muted-foreground line-through">
                                {{ money(compare) }}
                            </span>
                        </div>

                        <!-- Option selectors, exactly as the storefront renders them -->
                        <div v-for="option in options" :key="option.name" class="space-y-1.5">
                            <p class="text-[11px] font-medium">{{ option.name }}</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="value in option.values"
                                    :key="value"
                                    class="rounded-lg border border-border px-2 py-1 text-[11px]"
                                >
                                    {{ value }}
                                </span>
                            </div>
                        </div>

                        <p v-if="description" class="whitespace-pre-line text-[11px] leading-relaxed text-muted-foreground">
                            {{ description.slice(0, 220) }}{{ description.length > 220 ? '…' : '' }}
                        </p>

                        <div class="space-y-1.5 pt-1">
                            <div class="h-7 rounded-lg border border-border" />
                            <div class="h-7 rounded-lg border border-border" />
                        </div>

                        <div class="flex h-9 items-center justify-center rounded-xl bg-primary text-xs font-semibold text-primary-foreground">
                            اطلب دلوقتي
                        </div>
                        <p class="text-center text-[10px] text-muted-foreground">الدفع عند الاستلام</p>
                    </div>
                </div>
            </div>
        </div>

        <p class="mt-3 text-center text-xs text-muted-foreground">
            ده اللي العميل هيشوفه
        </p>
    </div>
</template>
