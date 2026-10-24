<script setup lang="ts">
import { computed } from 'vue';

/*
 * A storefront in miniature, drawn from a theme's own settings.
 *
 * Everything that makes two themes look like two different shops is here,
 * scaled down: the typeface, the header shape, the product-card shape, the
 * corner radius and the palette — over three real products from that theme's
 * showroom. It mirrors storefront/partials/product-card and
 * sections/header_nav in shape only; it is a picture, not the template.
 */
export interface PreviewProduct {
    name: string;
    price: string;
    compare: string | null;
    image: string;
}

const props = defineProps<{
    palette: Record<string, string>;
    radius: string;
    header: string;
    card: string;
    fontFamily: string;
    products: PreviewProduct[];
    size?: 'md' | 'lg';
}>();

const hsl = (value: string | undefined, alpha = 1) => (value ? `hsl(${value} / ${alpha})` : undefined);

// The theme's radius, scaled to the miniature so 1.75rem stays playful
// without turning a 90px card into a pill.
const r = computed(() => `calc(${props.radius} * 0.55)`);
const rCard = computed(() => `calc(${props.radius} * 0.55 + 4px)`);

const discount = (p: PreviewProduct) => {
    if (!p.compare) return null;
    const now = Number(p.price.replace(/,/g, ''));
    const was = Number(p.compare.replace(/,/g, ''));
    return was > now ? Math.round(((was - now) / was) * 100) : null;
};
</script>

<template>
    <div
        class="overflow-hidden"
        :style="{ background: hsl(palette.background), color: hsl(palette.foreground), fontFamily }"
    >
        <!-- Announcement bar -->
        <div class="truncate px-3 py-1 text-center text-[9px] font-medium" :style="{ background: hsl(palette.primary), color: hsl(palette['primary-foreground']) }">
            الدفع عند الاستلام · شحن لكل المحافظات
        </div>

        <!-- Header, in the theme's shape -->
        <div class="border-b px-3" :style="{ borderColor: hsl(palette.border), background: hsl(palette.card) }">
            <div v-if="header === 'centered'" class="flex flex-col items-center gap-1 py-2">
                <span class="text-[12px] font-bold leading-none">متجرك</span>
                <span class="flex gap-2 text-[8px]" :style="{ color: hsl(palette['muted-foreground']) }">
                    <span>جديد</span><span>الأكثر مبيعًا</span><span>العروض</span>
                </span>
            </div>
            <div v-else-if="header === 'minimal'" class="flex justify-center py-2.5">
                <span class="text-[12px] font-bold leading-none">متجرك</span>
            </div>
            <div v-else class="flex items-center gap-1.5 py-2">
                <span
                    class="flex size-5 shrink-0 items-center justify-center text-[9px] font-bold"
                    :style="{ background: hsl(palette.primary), color: hsl(palette['primary-foreground']), borderRadius: r }"
                >م</span>
                <span class="text-[11px] font-bold">متجرك</span>
                <span class="ms-auto h-4 w-16 rounded-full" :style="{ background: hsl(palette.foreground, 0.06) }" />
            </div>
        </div>

        <!-- Products, in the theme's card shape -->
        <div class="grid grid-cols-3 gap-2 p-3" :class="size === 'lg' ? 'md:gap-3 md:p-4' : ''">
            <div
                v-for="product in products"
                :key="product.image"
                class="min-w-0"
                :style="
                    card === 'soft'
                        ? { background: hsl(palette.card), borderRadius: rCard, boxShadow: `0 0 0 0.5px ${hsl(palette.foreground, 0.08)}, 0 2px 6px -2px ${hsl(palette.foreground, 0.12)}`, overflow: 'hidden' }
                        : card === 'frame'
                          ? { background: hsl(palette.card), borderRadius: rCard, border: `1px solid ${hsl(palette.border)}`, padding: '3px' }
                          : {}
                "
            >
                <div class="relative overflow-hidden" :style="{ borderRadius: card === 'soft' ? '0' : r }">
                    <img
                        :src="product.image"
                        alt=""
                        loading="lazy"
                        decoding="async"
                        width="360"
                        height="360"
                        class="block w-full object-cover"
                        :class="card === 'full' ? 'aspect-[4/5]' : 'aspect-square'"
                    />
                    <span
                        v-if="discount(product)"
                        class="absolute start-1 top-1 px-1 py-px text-[7.5px] font-bold"
                        :style="
                            card === 'sharp'
                                ? { background: hsl(palette.foreground), color: hsl(palette.background) }
                                : { background: 'hsl(355 72% 47%)', color: '#fff', borderRadius: '999px' }
                        "
                        dir="ltr"
                    >−{{ discount(product) }}%</span>
                </div>

                <div :class="card === 'soft' ? 'px-1.5 pb-1.5 pt-1' : 'px-0.5 pt-1'">
                    <p
                        class="truncate leading-tight"
                        :class="card === 'sharp' ? 'text-[8px] tracking-wide' : 'text-[9px] font-medium'"
                        :style="card === 'sharp' ? { color: hsl(palette['muted-foreground']) } : {}"
                    >
                        {{ product.name }}
                    </p>
                    <p class="mt-0.5 flex items-baseline gap-1 text-[9.5px] font-bold leading-tight" :class="card === 'sharp' ? 'tracking-wide' : ''">
                        <span class="tabular">{{ product.price }}</span>
                        <span v-if="product.compare" class="tabular text-[7.5px] font-normal line-through" :style="{ color: hsl(palette['muted-foreground']) }">{{ product.compare }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="px-3 pb-3" :class="size === 'lg' ? 'md:px-4 md:pb-4' : ''">
            <div
                class="flex h-6 items-center justify-center text-[10px] font-semibold"
                :style="{ background: hsl(palette.primary), color: hsl(palette['primary-foreground']), borderRadius: r }"
            >اطلب دلوقتي</div>
        </div>
    </div>
</template>
