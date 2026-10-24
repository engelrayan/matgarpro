<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Rocket } from 'lucide-vue-next';

/*
 * A product's own landing page, from the product form.
 *
 * Where a merchant already is when they think "this one needs a proper page
 * for the ad". The card says whether the page exists and whether customers
 * see it; the work itself happens in the builder, which already knows how to
 * lay a page out, preview it on a phone and publish it.
 *
 * The one setting here — replace the product page, or sit under it — is a
 * product setting, saved with the form like the rest.
 */
defineProps<{
    landing: { started: boolean; live: boolean; builder_url: string };
}>();

const below = defineModel<boolean>('below', { required: true });
</script>

<template>
    <section class="surface overflow-hidden p-0">
        <div class="bg-gradient-to-l from-primary/10 to-transparent p-5">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                    <Rocket class="size-5" />
                </span>
                <div class="min-w-0">
                    <h2 class="flex flex-wrap items-center gap-2 font-semibold">
                        صفحة هبوط للمنتج ده
                        <span v-if="landing.live" class="badge-success">منشورة</span>
                        <span v-else-if="landing.started" class="badge-neutral">مسودة</span>
                    </h2>
                    <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                        صفحة كاملة تبيع المنتج ده لوحده — عنوان، مميزات، مقارنة، ضمان، أسئلة، والفورم. نفس لينك المنتج،
                        فالإعلانات اللي شغّالة هتبدأ تودّي عليها أول ما تنشرها.
                    </p>
                </div>
            </div>

            <Link :href="landing.builder_url" class="btn-primary mt-4 w-full">
                {{ landing.started ? 'عدّل صفحة الهبوط' : 'اعمل صفحة هبوط' }}
            </Link>
        </div>

        <label class="flex cursor-pointer items-start gap-3 border-t border-border p-5">
            <input v-model="below" type="checkbox" class="mt-1 size-4 rounded border-border text-primary" />
            <span>
                <span class="block text-sm font-medium">اعرضها تحت صفحة المنتج العادية</span>
                <span class="mt-0.5 block text-xs leading-relaxed text-muted-foreground">
                    سيبها مقفولة عشان صفحة الهبوط تبقى هي الصفحة كلها — ده الأحسن للإعلانات. افتحها لو عايز صفحة المنتج
                    المعتادة تفضل فوق، وأجزاء صفحة الهبوط تنزل تحتها.
                </span>
            </span>
        </label>
    </section>
</template>
