<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { Link } from '@inertiajs/vue3';
import { KeyRound, LogOut, Palette, UserRound } from 'lucide-vue-next';

defineProps<{ user: User }>();

/*
 * Account settings, not store settings.
 *
 * The sidebar carries everything about the shop; these three are about the
 * person signed in, so they live under their own name rather than competing
 * with "الطلبات" for room in the rail.
 */
const accountItems = [
    { title: 'الملف الشخصي', href: '/settings/profile', icon: UserRound },
    { title: 'كلمة المرور', href: '/settings/password', icon: KeyRound },
    { title: 'المظهر', href: '/settings/appearance', icon: Palette },
];
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-right text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>

    <DropdownMenuSeparator />

    <DropdownMenuGroup>
        <DropdownMenuItem v-for="item in accountItems" :key="item.href" :as-child="true">
            <Link class="block w-full" :href="item.href" as="button">
                <component :is="item.icon" class="ml-2 size-4" />
                {{ item.title }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>

    <DropdownMenuSeparator />

    <DropdownMenuItem :as-child="true">
        <Link class="block w-full" method="post" :href="route('logout')" as="button">
            <LogOut class="ml-2 size-4" />
            تسجيل الخروج
        </Link>
    </DropdownMenuItem>
</template>
