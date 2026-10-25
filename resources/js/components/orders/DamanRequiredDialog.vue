<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Link } from '@inertiajs/vue3';
import { Settings, Truck } from 'lucide-vue-next';

/*
 * «شحن عبر ضمان» pressed on a store that has not linked Daman yet.
 *
 * The button is shown either way: hidden, it never told a merchant that
 * confirmed orders can go straight to a carrier, nor where that is switched
 * on. This says both, in the same three steps the Daman settings page uses.
 */
const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <span class="ios-tile mb-2 bg-primary text-primary-foreground"><Truck class="size-[18px]" /></span>
                <DialogTitle>فعّل الشحن مع ضمان الأول</DialogTitle>
                <DialogDescription class="leading-relaxed">
                    عشان الطلبات المؤكدة تتبعت للشحن على طول، اربط متجرك بحسابك في ضمان. ضمان بيوصّلها لشركة الشحن اللي متعاقد معاها، ويرجّعلك رقم البوليصة هنا.
                </DialogDescription>
            </DialogHeader>

            <ol class="space-y-2 text-sm leading-relaxed text-muted-foreground">
                <li class="rounded-xl bg-muted/60 px-3 py-2.5">
                    <span class="font-semibold text-foreground">١ —</span> ادخل
                    <span class="font-semibold text-foreground">الإعدادات ← الشحن مع ضمان</span>.
                </li>
                <li class="rounded-xl bg-muted/60 px-3 py-2.5">
                    <span class="font-semibold text-foreground">٢ —</span> من حسابك في ضمان ← إعدادات الـ API، اعمل مفتاح جديد والصقه هناك.
                </li>
                <li class="rounded-xl bg-muted/60 px-3 py-2.5">
                    <span class="font-semibold text-foreground">٣ —</span> ارجع للطلبات، حدّد المؤكد منها ودوس
                    <span class="font-semibold text-foreground">شحن عبر ضمان</span>.
                </li>
            </ol>

            <DialogFooter class="gap-2 sm:gap-2">
                <button type="button" class="btn-ghost" @click="open = false">بعدين</button>
                <Link href="/settings/daman" class="btn-primary">
                    <Settings class="size-4" />
                    روح لإعدادات ضمان
                </Link>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
