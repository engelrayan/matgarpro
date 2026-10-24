<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutTemplate, Palette, PenLine, Sparkles } from 'lucide-vue-next';

/*
 * The way into the AI store builder, for a store that has nothing in it yet.
 *
 * Shown on the dashboard home rather than left to the button in the builder:
 * a merchant who has just signed up is the one person who most needs the
 * builder and is least likely to find it there. Its own component so the
 * dashboard page carries one line for it, not a block of markup.
 */
defineProps<{ storeName: string }>();

// What the builder actually does — every line here is something it makes,
// so the card promises nothing the next screen will not deliver.
const makes = [
    { icon: Palette, label: 'يختار الثيم' },
    { icon: LayoutTemplate, label: 'يعمل الأقسام' },
    { icon: PenLine, label: 'يكتب الصفحة الرئيسية' },
];
</script>

<template>
    <section class="ai-ring rounded-[22px]">
        <div class="relative overflow-hidden rounded-[20.5px] bg-card p-5 md:p-6">
            <!-- A wash of the ring's own colours, kept faint behind the text. -->
            <div aria-hidden="true" class="pointer-events-none absolute -end-24 -top-24 size-72 rounded-full bg-[hsl(205_85%_66%/0.14)] blur-3xl" />
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-28 end-1/3 size-64 rounded-full bg-[hsl(330_75%_72%/0.12)] blur-3xl" />

            <div class="relative flex flex-col gap-5 md:flex-row md:items-center">
                <span class="grid size-14 shrink-0 place-items-center rounded-[16px] bg-gradient-to-br from-jade-500 via-[hsl(205_75%_55%)] to-[hsl(330_65%_62%)] text-white shadow-[0_8px_24px_-8px_hsl(205_75%_45%/0.55)]">
                    <Sparkles class="size-7" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-[13px] font-semibold text-muted-foreground">متجرك لسه فاضي</p>
                    <h2 class="mt-0.5 text-[22px] font-bold leading-tight tracking-tight md:text-2xl">
                        خلّي <span class="ai-text">الذكاء الاصطناعي</span> يجهّز {{ storeName }}
                    </h2>
                    <p class="mt-1.5 text-[15px] leading-relaxed text-muted-foreground">
                        احكي في كام سطر بتبيع إيه ولمين. كل حاجة بتتحط مسودة، ومش بتظهر للزبون غير لما تراجعها وتنشرها.
                    </p>

                    <ul class="mt-3 flex flex-wrap gap-1.5">
                        <li v-for="item in makes" :key="item.label" class="ios-chip gap-1.5">
                            <component :is="item.icon" class="size-3.5 text-primary" />
                            {{ item.label }}
                        </li>
                    </ul>
                </div>

                <Link href="/ai/store-builder" class="ios-btn-lg ios-btn-filled pressable w-full shrink-0 md:w-auto">
                    <Sparkles class="size-[18px]" />
                    جهّزلي المتجر
                </Link>
            </div>
        </div>
    </section>
</template>
