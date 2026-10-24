<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    { breadcrumbs: () => [] },
);

const current = computed(() => props.breadcrumbs.at(-1));
const parent = computed(() => (props.breadcrumbs.length > 1 ? props.breadcrumbs.at(-2) : undefined));

/*
 * The iOS large title, collapsing.
 *
 * Every page opens with its own big heading. Once that has scrolled away the
 * bar fades in a small centred copy of it, so the merchant always knows where
 * they are — the behaviour of every navigation bar on the phone. Driven by a
 * single passive scroll listener rather than per-page observers, so a page
 * gets it for free just by being inside the layout.
 */
const scrolled = ref(false);
const onScroll = () => (scrolled.value = window.scrollY > 44);

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-[52px] shrink-0 items-center gap-2 px-2 transition-[background-color,border-color,height] duration-200 md:h-14 md:rounded-t-[14px] md:px-4"
        :class="scrolled ? 'material hairline-b' : 'border-b-[0.5px] border-transparent'"
    >
        <!-- Phone: a back button to the parent screen, as in any iOS app. The
             root tabs have none — the tab bar is how you leave them. -->
        <div class="flex min-w-0 flex-1 items-center md:hidden">
            <Link
                v-if="parent"
                :href="parent.href"
                class="-ms-1 flex min-h-[44px] min-w-0 items-center gap-0.5 rounded-lg px-1 text-[17px] text-primary active:opacity-60"
            >
                <ChevronRight class="size-7 shrink-0" :stroke-width="2.4" />
                <span class="max-w-[7.5rem] truncate">{{ parent.title }}</span>
            </Link>
        </div>

        <p
            class="pointer-events-none absolute inset-x-0 mx-auto w-fit max-w-[55%] truncate text-center text-[17px] font-semibold transition-opacity duration-200 md:hidden"
            :class="scrolled ? 'opacity-100' : 'opacity-0'"
            aria-hidden="true"
        >
            {{ current?.title }}
        </p>

        <div class="flex-1 md:hidden" />

        <!-- Desktop keeps the rail toggle and the trail. -->
        <div class="hidden items-center gap-2 md:flex">
            <SidebarTrigger class="-ms-1 text-muted-foreground hover:text-foreground" />
            <Breadcrumb v-if="breadcrumbs.length > 0">
                <BreadcrumbList>
                    <template v-for="(item, index) in breadcrumbs" :key="index">
                        <BreadcrumbItem>
                            <BreadcrumbPage v-if="index === breadcrumbs.length - 1" class="font-semibold">{{ item.title }}</BreadcrumbPage>
                            <BreadcrumbLink v-else :href="item.href" class="text-primary hover:text-primary/80">{{ item.title }}</BreadcrumbLink>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                    </template>
                </BreadcrumbList>
            </Breadcrumb>
        </div>
    </header>
</template>
