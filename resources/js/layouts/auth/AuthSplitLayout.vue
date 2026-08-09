<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link } from '@inertiajs/vue3';
import { BadgeCheck, Truck, Wallet } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
}>();

// The same three promises the landing page leads with. A merchant who clicked
// through from an ad should not land on a blank form that drops the pitch
// halfway — the reason to sign up has to survive the click.
const promises = [
    { icon: Wallet, title: '٣ شهور مجانية', body: 'وبعدها ٥٠ قرش على الطلب — من غير اشتراك شهري ولا نسبة من مبيعاتك.' },
    { icon: Truck, title: 'شحن مع +٣٠٠ شركة', body: 'عبر شبكة سندباد، وفلوس التحصيل توصلك كل يوم مع ضمان.' },
    { icon: BadgeCheck, title: 'الدفع عند الاستلام', body: 'جاهز من أول لحظة، من غير أي إضافات.' },
];
</script>

<template>
    <div class="grid min-h-dvh lg:grid-cols-[1.1fr_1fr]">
        <!-- ── Brand panel ─────────────────────────────────────────────────
             Hidden below lg: on a phone it would push the form off-screen,
             and the form is the only thing that matters there. -->
        <aside class="relative hidden overflow-hidden bg-jade-950 p-12 text-jade-50 lg:flex lg:flex-col">
            <!-- Brand wash + a faint gold horizon line -->
            <div
                class="pointer-events-none absolute inset-0"
                style="
                    background-image:
                        radial-gradient(45rem 28rem at 80% -5%, hsl(var(--jade-400) / 0.22), transparent 60%),
                        radial-gradient(35rem 22rem at 0% 100%, hsl(var(--gold-500) / 0.16), transparent 60%);
                "
            />

            <Link :href="route('home')" class="relative flex items-center gap-3">
                <!-- Mid jade, not the panel's own jade-950: the mark is a filled
                     square, so matching the background erases it. -->
                <AppLogoIcon class="size-10 text-jade-600" />
                <span class="text-xl font-bold tracking-tight">متجر برو</span>
            </Link>

            <div class="relative my-auto max-w-md py-12">
                <h2 class="text-balance text-4xl font-bold leading-[1.2] tracking-tight">
                    متجرك الإلكتروني،
                    <span class="text-foil">من غير تعقيد</span>
                </h2>

                <ul class="mt-10 space-y-6">
                    <li v-for="promise in promises" :key="promise.title" class="flex gap-4">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-gold-300">
                            <component :is="promise.icon" class="size-5" />
                        </span>
                        <div>
                            <p class="font-semibold">{{ promise.title }}</p>
                            <p class="mt-1 text-sm leading-relaxed text-jade-200/80">{{ promise.body }}</p>
                        </div>
                    </li>
                </ul>
            </div>

            <p class="relative text-sm text-jade-200/60">صُنع في مصر</p>
        </aside>

        <!-- ── Form ──────────────────────────────────────────────────────── -->
        <main class="flex items-center justify-center bg-background px-6 py-12">
            <div class="w-full max-w-sm">
                <!-- Logo shows on phones, where the brand panel is hidden. -->
                <Link :href="route('home')" class="mb-10 flex items-center justify-center gap-2.5 lg:hidden">
                    <AppLogoIcon class="size-9 text-primary" />
                    <span class="text-lg font-bold tracking-tight">متجر برو</span>
                </Link>

                <div class="mb-8">
                    <h1 v-if="title" class="text-2xl font-bold tracking-tight">{{ title }}</h1>
                    <p v-if="description" class="mt-2 text-sm text-muted-foreground">{{ description }}</p>
                </div>

                <slot />
            </div>
        </main>
    </div>
</template>
