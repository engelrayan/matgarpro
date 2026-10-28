<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { router, useForm } from '@inertiajs/vue3';
import { Check, Copy, LoaderCircle, MessageCircle } from 'lucide-vue-next';
import { ref } from 'vue';

/*
 * Adding money: a request waiting for its transfer, or picking an amount.
 *
 * One component for رصيدك and the dashboard, so the steps a merchant follows
 * are the same wherever they start them.
 *
 * Topping up is a choice from fixed amounts, never a typed number: the same
 * screen has to work unchanged when online payment arrives, and a payment
 * gateway is never handed an amount typed by hand. Until then the money moves
 * by InstaPay and an operator confirms it.
 */

const props = defineProps<{
    currency: string;
    amounts: number[];
    instapay: string;
    pending: { id: number; amount: number; created_at: string; whatsapp_url: string | null } | null;
}>();

const whole = (v: number) => v.toLocaleString('en-US');

const form = useForm({ amount: props.amounts[2] ?? props.amounts[0] });
// preserveState keeps the page around it as it was — on the dashboard, the
// panel stays open and the transfer steps take the form's place.
const submit = () => form.post(route('billing.topups.store'), { preserveScroll: true, preserveState: true });

const cancelling = ref(false);
const cancel = () => {
    if (!props.pending) return;
    cancelling.value = true;
    router.delete(route('billing.topups.destroy', props.pending.id), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (cancelling.value = false),
    });
};

const copied = ref(false);
const copyNumber = async () => {
    try {
        await navigator.clipboard.writeText(props.instapay);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard refused (an old browser, an iframe): the number is on screen.
    }
};
</script>

<template>
    <!-- A request already waiting: what to do next, in order. -->
    <div v-if="pending" class="space-y-4">
        <p class="rounded-xl bg-primary/5 px-4 py-3 text-sm">
            طلب شحن بـ <strong class="tabular">{{ whole(pending.amount) }} {{ currency }}</strong> مستني التأكيد
            <span class="text-muted-foreground">— {{ pending.created_at }}</span>
        </p>

        <ol class="space-y-4 text-sm">
            <li class="flex gap-3">
                <span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary text-xs text-primary-foreground">١</span>
                <div class="space-y-2">
                    <p>
                        حوّل <strong class="tabular">{{ whole(pending.amount) }} {{ currency }}</strong> على إنستاباي للرقم ده:
                    </p>
                    <button type="button" class="btn-outline tabular py-1.5" dir="ltr" @click="copyNumber">
                        {{ instapay }}
                        <Check v-if="copied" class="h-4 w-4 text-success" />
                        <Copy v-else class="h-4 w-4" />
                    </button>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary text-xs text-primary-foreground">٢</span>
                <div class="space-y-2">
                    <p>ابعت صورة الإيصال على واتساب لنفس الرقم:</p>
                    <a v-if="pending.whatsapp_url" :href="pending.whatsapp_url" target="_blank" rel="noopener" class="btn-primary py-1.5">
                        <MessageCircle class="h-4 w-4" />
                        ابعت الإيصال على واتساب
                    </a>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary text-xs text-primary-foreground">٣</span>
                <p>هنراجع التحويل ونضيف المبلغ لرصيدك.</p>
            </li>
        </ol>

        <button type="button" class="btn-ghost text-sm text-destructive" :disabled="cancelling" @click="cancel">إلغاء الطلب</button>
    </div>

    <!-- Picking an amount. -->
    <form v-else class="space-y-4" @submit.prevent="submit">
        <p class="text-sm text-muted-foreground">اختار المبلغ اللي هتشحن بيه:</p>
        <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
            <button
                v-for="amount in amounts"
                :key="amount"
                type="button"
                class="tabular rounded-xl border px-2 py-3 text-sm font-semibold transition-colors"
                :class="form.amount === amount ? 'border-primary bg-primary/10 text-primary' : 'border-border'"
                @click="form.amount = amount"
            >
                {{ whole(amount) }}
            </button>
        </div>
        <InputError :message="form.errors.amount" />

        <button type="submit" class="btn-primary" :disabled="form.processing">
            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
            اشحن {{ whole(form.amount) }} {{ currency }}
        </button>
        <p class="text-xs text-muted-foreground">بعدها هتحوّل المبلغ على إنستاباي وتبعت الإيصال على واتساب.</p>
    </form>
</template>
