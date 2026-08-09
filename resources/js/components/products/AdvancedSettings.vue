<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';

export interface ProductSettings {
    buy_button_text: string;
    sticky_buy_bar: boolean;
    form_before_description: boolean;
    hide_header: boolean;
    free_shipping: boolean;
    hide_when_out_of_stock: boolean;
}

const settings = defineModel<ProductSettings>({ required: true });

const open = ref(false);

/*
 * Each toggle carries the reason to use it, not just its name.
 *
 * "اخفاء الهيدر" means nothing on its own; "for a page whose only job is to
 * convert ad traffic" tells a merchant whether it applies to them. This is the
 * screen where the platform either teaches or just piles up switches.
 */
const toggles: { key: keyof ProductSettings; label: string; help: string }[] = [
    {
        key: 'sticky_buy_bar',
        label: 'زر شراء ثابت أسفل الشاشة',
        help: 'أغلب الزيارات من الموبايل، والفورم تحت صورة طويلة. الزر الثابت بيوفّر على العميل إنه يرجع لفوق.',
    },
    {
        key: 'free_shipping',
        label: 'شحن مجاني للمنتج ده',
        help: 'بتظهر شارة «شحن مجاني» في صفحة المنتج.',
    },
    {
        key: 'form_before_description',
        label: 'الفورم قبل الوصف',
        help: 'مناسب للمنتج البسيط اللي بيتشرى بسرعة. المنتج اللي محتاج إقناع الأفضل يبدأ بالوصف.',
    },
    {
        key: 'hide_header',
        label: 'إخفاء هيدر المتجر',
        help: 'لصفحة شغلها الوحيد تحويل زيارات إعلان — مفيش روابط تشتّت العميل.',
    },
    {
        key: 'hide_when_out_of_stock',
        label: 'إخفاء المنتج لما المخزون يخلص',
        help: 'الافتراضي إنه يفضل ظاهر ومكتوب عليه «خلص» — الصفحة بتفضل تجيب زيارات والعميل يقدر يسأل.',
    },
];
</script>

<template>
    <section class="surface p-5">
        <button type="button" class="flex w-full items-center justify-between text-right" @click="open = !open">
            <span class="text-sm font-medium">إعدادات متقدمة</span>
            <ChevronDown class="size-4 transition-transform" :class="{ 'rotate-180': open }" />
        </button>

        <div v-show="open" class="mt-5 space-y-5">
            <div>
                <label class="field-label" for="buy_button_text">نص زر الشراء</label>
                <input
                    id="buy_button_text"
                    v-model="settings.buy_button_text"
                    class="field"
                    maxlength="40"
                    placeholder="اطلب دلوقتي"
                />
                <p class="field-hint">أكتر جملة بتفرق في الصفحة. جرّب أكتر من صيغة.</p>
            </div>

            <div v-for="toggle in toggles" :key="toggle.key" class="border-t border-border pt-4">
                <label class="flex cursor-pointer items-start gap-3">
                    <input
                        v-model="settings[toggle.key] as boolean"
                        type="checkbox"
                        class="mt-0.5 size-4 shrink-0 rounded border-input accent-primary"
                    />
                    <span class="min-w-0">
                        <span class="block text-sm font-medium">{{ toggle.label }}</span>
                        <span class="mt-1 block text-xs leading-relaxed text-muted-foreground">{{ toggle.help }}</span>
                    </span>
                </label>
            </div>
        </div>
    </section>
</template>
