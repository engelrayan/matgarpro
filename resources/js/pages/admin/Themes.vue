<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { num } from '@/composables/useFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ExternalLink } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    themes: {
        key: string;
        name: string;
        description: string;
        primary: string;
        accent: string;
        layout: string;
        stores: number;
        share: number;
        is_default: boolean;
        preview_url: string | null;
    }[];
    orphans: { key: string; stores: number }[];
    selected: string;
    stores: { id: number; name: string; slug: string; status: string; orders_count: number }[];
}>();

const select = (key: string) =>
    router.get('/admin/themes', key === props.selected ? {} : { theme: key }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });

const totalStores = computed(() => props.themes.reduce((sum, t) => sum + t.stores, 0));

const selectedTheme = computed(() => props.themes.find((t) => t.key === props.selected));

const STATUS_LABEL: Record<string, string> = {
    active: 'شغّال',
    draft: 'مسودة',
    suspended: 'موقوف',
};
</script>

<template>
    <AdminLayout title="الثيمات" :subtitle="`${num(themes.length)} ثيم على ${num(totalStores)} متجر`">
        <Head title="الثيمات" />

        <div class="mx-auto w-full max-w-6xl space-y-5">
            <!--
                A theme key on a store that we stopped shipping. Those stores
                are silently rendering the default right now, which is the kind
                of thing nobody notices until a merchant asks why their shop
                changed colour.
            -->
            <div
                v-if="orphans.length"
                class="flex items-start gap-3 rounded-2xl border border-warning/30 bg-warning/5 p-4 text-sm"
            >
                <AlertTriangle class="mt-0.5 size-5 shrink-0 text-warning" />
                <div>
                    <p class="font-medium">فيه متاجر على ثيمات مش موجودة في المنصة.</p>
                    <p class="mt-1 text-muted-foreground">
                        بتتعرض بالثيم الافتراضي دلوقتي:
                        <span v-for="o in orphans" :key="o.key" class="font-mono">{{ o.key }} ({{ o.stores }}) </span>
                    </p>
                </div>
            </div>

            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="theme in themes"
                    :key="theme.key"
                    class="surface flex flex-col gap-3 p-5 transition-shadow"
                    :class="selected === theme.key ? 'ring-2 ring-primary' : ''"
                >
                    <div class="flex items-start gap-3">
                        <span class="flex shrink-0 gap-1">
                            <span class="size-9 rounded-lg" :style="{ background: `hsl(${theme.primary})` }" />
                            <span class="size-9 rounded-lg" :style="{ background: `hsl(${theme.accent})` }" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate font-semibold">{{ theme.name }}</p>
                                <span v-if="theme.is_default" class="badge-gold shrink-0">افتراضي</span>
                            </div>
                            <p class="font-mono text-xs text-muted-foreground" dir="ltr">{{ theme.key }}</p>
                        </div>
                    </div>

                    <p class="text-sm text-muted-foreground">{{ theme.description }}</p>

                    <div class="mt-auto flex items-center justify-between gap-2 border-t border-border pt-3">
                        <div>
                            <p class="tabular text-lg font-semibold">{{ num(theme.stores) }}</p>
                            <p class="text-xs text-muted-foreground">متجر · {{ theme.share }}%</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a
                                v-if="theme.preview_url"
                                :href="theme.preview_url"
                                target="_blank"
                                rel="noopener"
                                class="btn-ghost px-2.5 py-1.5 text-xs"
                            >
                                <ExternalLink class="size-3.5" />
                                معاينة
                            </a>
                            <button class="btn-outline px-2.5 py-1.5 text-xs" @click="select(theme.key)">
                                {{ selected === theme.key ? 'إخفاء' : 'المتاجر' }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Drilldown ─────────────────────────────────────────────── -->
            <section v-if="selected" class="surface overflow-hidden">
                <p class="border-b border-border p-5 text-sm font-medium">
                    متاجر على «{{ selectedTheme?.name ?? selected }}»
                    <span class="text-muted-foreground">— أول ٥٠</span>
                </p>
                <ul v-if="stores.length">
                    <li
                        v-for="s in stores"
                        :key="s.id"
                        class="flex items-center justify-between gap-3 border-b border-border/60 px-5 py-3 last:border-0"
                    >
                        <div class="min-w-0">
                            <Link :href="`/admin/stores/${s.id}`" class="truncate text-sm font-medium hover:underline">
                                {{ s.name }}
                            </Link>
                            <p class="font-mono text-xs text-muted-foreground" dir="ltr">{{ s.slug }}</p>
                        </div>
                        <p class="shrink-0 text-xs text-muted-foreground">
                            {{ STATUS_LABEL[s.status] }} · {{ num(s.orders_count) }} طلب
                        </p>
                    </li>
                </ul>
                <p v-else class="p-5 text-sm text-muted-foreground">مفيش متاجر على الثيم ده.</p>
            </section>

            <p class="text-xs text-muted-foreground">
                الثيمات بتتظبط من <span class="font-mono">config/themes.php</span> — الشاشة دي بتقول مين بيستخدم إيه.
                تغيير ثيم متجر معيّن بيتم من صفحة المتجر.
            </p>
        </div>
    </AdminLayout>
</template>
