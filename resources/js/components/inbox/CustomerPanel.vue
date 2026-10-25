<script setup lang="ts">
import { Check, ExternalLink, Plus, ShoppingBag, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { type CustomerOrder, type Thread } from './types';

/*
 * Who this is to the shop, beside the chat: their orders with the two
 * buttons that matter while talking to them, and the tags the team uses to
 * sort conversations.
 */

const props = defineProps<{
    thread: Thread;
    currency: string;
    suggestedTags: string[];
    busyOrder: number | null;
}>();

const emit = defineEmits<{
    orderStatus: [order: CustomerOrder, status: 'confirmed' | 'cancelled'];
    tags: [tags: string[]];
    close: [];
}>();

const newTag = ref('');

const toggleTag = (tag: string) => {
    const tags = props.thread.conversation.tags;
    emit('tags', tags.includes(tag) ? tags.filter((t) => t !== tag) : [...tags, tag]);
};

const addTag = () => {
    const tag = newTag.value.trim();
    if (tag && !props.thread.conversation.tags.includes(tag)) {
        emit('tags', [...props.thread.conversation.tags, tag]);
    }
    newTag.value = '';
};

const money = (v: string) => Number(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

const statusTone: Record<string, string> = {
    pending: 'bg-warning/15 text-warning',
    confirmed: 'bg-success/15 text-success',
    shipped: 'bg-info/15 text-info',
    delivered: 'bg-success/15 text-success',
    cancelled: 'bg-destructive/10 text-destructive',
    returned: 'bg-destructive/10 text-destructive',
};
</script>

<template>
    <aside class="flex h-full flex-col overflow-y-auto bg-card">
        <div class="flex items-center justify-between border-b border-border px-4 py-3">
            <h3 class="font-semibold">عن العميل</h3>
            <button type="button" class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted xl:hidden" aria-label="اقفل" @click="emit('close')">
                <X class="size-4" />
            </button>
        </div>

        <div class="space-y-5 p-4">
            <div>
                <p class="font-semibold">{{ thread.conversation.name ?? 'من غير اسم' }}</p>
                <a :href="thread.conversation.wa_link" target="_blank" rel="noopener" class="mt-0.5 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground" dir="ltr">
                    {{ thread.conversation.phone }}
                    <ExternalLink class="size-3" />
                </a>
            </div>

            <!-- Tags -->
            <section>
                <p class="mb-2 text-xs font-medium text-muted-foreground">الوسوم</p>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="tag in [...new Set([...suggestedTags, ...thread.conversation.tags])]"
                        :key="tag"
                        type="button"
                        class="rounded-full border px-2.5 py-1 text-xs transition-colors"
                        :class="thread.conversation.tags.includes(tag)
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border text-muted-foreground hover:text-foreground'"
                        @click="toggleTag(tag)"
                    >
                        {{ tag }}
                    </button>
                </div>
                <form class="mt-2 flex gap-1.5" @submit.prevent="addTag">
                    <input v-model="newTag" class="field h-8 flex-1 text-xs" maxlength="30" placeholder="وسم جديد" />
                    <button type="submit" class="rounded-lg border border-border px-2 text-muted-foreground hover:bg-muted" aria-label="ضيف الوسم">
                        <Plus class="size-4" />
                    </button>
                </form>
            </section>

            <!-- Orders -->
            <section>
                <p class="mb-2 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                    <ShoppingBag class="size-3.5" />
                    طلباته ({{ thread.orders.length }})
                </p>

                <p v-if="!thread.orders.length" class="rounded-xl bg-muted/50 p-3 text-xs text-muted-foreground">
                    مفيش طلبات من الرقم ده لسه.
                </p>

                <div v-for="order in thread.orders" :key="order.id" class="mb-2 rounded-xl border border-border p-3">
                    <div class="flex items-center justify-between gap-2">
                        <a :href="`/orders/${order.id}`" class="font-semibold hover:underline">#{{ order.number }}</a>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusTone[order.status] ?? 'bg-muted'">
                            {{ order.status_label }}
                        </span>
                    </div>
                    <p class="mt-1.5 text-xs leading-relaxed text-muted-foreground">{{ order.items.join('، ') }}</p>
                    <p class="tabular mt-1.5 text-sm font-semibold">{{ money(order.total) }} {{ currency }}</p>

                    <div v-if="order.status === 'pending'" class="mt-2.5 grid grid-cols-2 gap-1.5">
                        <button
                            type="button"
                            class="flex items-center justify-center gap-1 rounded-lg bg-success px-2 py-1.5 text-xs font-medium text-white disabled:opacity-50"
                            :disabled="busyOrder === order.id"
                            @click="emit('orderStatus', order, 'confirmed')"
                        >
                            <Check class="size-3.5" />
                            تأكيد
                        </button>
                        <button
                            type="button"
                            class="flex items-center justify-center gap-1 rounded-lg border border-destructive/40 px-2 py-1.5 text-xs font-medium text-destructive disabled:opacity-50"
                            :disabled="busyOrder === order.id"
                            @click="emit('orderStatus', order, 'cancelled')"
                        >
                            <X class="size-3.5" />
                            إلغاء
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </aside>
</template>
