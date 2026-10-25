<script setup lang="ts">
import { api, InboxError } from '@/components/inbox/api';
import ChatThread from '@/components/inbox/ChatThread.vue';
import CustomerPanel from '@/components/inbox/CustomerPanel.vue';
import {
    avatarTone,
    initial,
    listTime,
    type Channel,
    type ConversationItem,
    type CustomerOrder,
    type QuickReply,
    type Thread,
} from '@/components/inbox/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCheck, Loader2, MessagesSquare, Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/*
 * The WhatsApp inbox.
 *
 * Three panes on a wide screen — conversations, the chat, the customer — and
 * one at a time on a phone, the way WhatsApp itself works there. Nothing
 * pushes to the browser yet, so the open chat is asked for every few seconds
 * and the list a little less often; both stop while the tab is hidden.
 */

const props = defineProps<{
    conversations: ConversationItem[];
    selected: number | null;
    channel: Channel;
    quickReplies: QuickReply[];
    suggestedTags: string[];
    currency: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'المحادثات', href: '/inbox' }];

const list = ref<ConversationItem[]>(props.conversations);
const replies = ref<QuickReply[]>(props.quickReplies);
const filter = ref<'all' | 'unread'>('all');
const search = ref('');

const openId = ref<number | null>(props.selected);
const thread = ref<Thread | null>(null);
const loadingThread = ref(false);
const threadError = ref('');
// Customer panel: always on at xl, a drawer below that.
const panelOpen = ref(false);

// ── The list ───────────────────────────────────────────────────────────
const refreshList = async () => {
    try {
        const params = new URLSearchParams();
        if (filter.value === 'unread') params.set('filter', 'unread');
        if (search.value.trim()) params.set('q', search.value.trim());
        const { conversations } = await api<{ conversations: ConversationItem[] }>('GET', `${route('inbox.conversations')}?${params}`);
        list.value = conversations;
    } catch {
        // A missed poll is not worth a message; the next one will do.
    }
};

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(refreshList, 300);
});
watch(filter, refreshList);

const unreadTotal = computed(() => list.value.filter((c) => c.unread > 0).length);

// ── The open conversation ──────────────────────────────────────────────
const loadThread = async (id: number, quiet = false) => {
    if (!quiet) {
        loadingThread.value = true;
        threadError.value = '';
    }
    try {
        const fresh = await api<Thread>('GET', route('inbox.show', id));
        if (openId.value !== id) return;

        // Keep the same object while polling, so the composer's draft and
        // the summary panel survive the refresh.
        if (quiet && thread.value?.conversation.id === id) {
            thread.value.messages = fresh.messages;
            thread.value.notes = fresh.notes;
            thread.value.orders = fresh.orders;
            thread.value.conversation.tags = fresh.conversation.tags;
        } else {
            thread.value = fresh;
        }

        const row = list.value.find((c) => c.id === id);
        if (row) row.unread = 0;
    } catch (e) {
        if (!quiet) threadError.value = e instanceof InboxError ? e.message : 'المحادثة مافتحتش.';
    } finally {
        loadingThread.value = false;
    }
};

const open = (id: number) => {
    if (openId.value === id) return;
    openId.value = id;
    thread.value = null;
    panelOpen.value = false;
    loadThread(id);
    // In the address bar, so a refresh or a shared link reopens it. Through
    // Inertia: it rewrites history from its own copy of the URL, and would
    // put the old one back.
    router.replace({ url: `${route('inbox.index')}?c=${id}`, preserveState: true, preserveScroll: true });
};

const back = () => {
    openId.value = null;
    thread.value = null;
    router.replace({ url: route('inbox.index'), preserveState: true, preserveScroll: true });
};

const markUnread = async () => {
    if (!openId.value) return;
    const id = openId.value;
    await api('PATCH', route('inbox.unread', id));
    back();
    const row = list.value.find((c) => c.id === id);
    if (row) row.unread = Math.max(1, row.unread);
};

const busyOrder = ref<number | null>(null);
const setOrderStatus = async (order: CustomerOrder, status: 'confirmed' | 'cancelled') => {
    if (!thread.value) return;
    busyOrder.value = order.id;
    try {
        const { orders } = await api<{ orders: CustomerOrder[] }>('PATCH', route('inbox.orders.status', [thread.value.conversation.id, order.id]), { status });
        thread.value.orders = orders;
    } catch (e) {
        threadError.value = e instanceof InboxError ? e.message : 'ماتغيرش. جرّب تاني.';
    } finally {
        busyOrder.value = null;
    }
};

const setTags = async (tags: string[]) => {
    if (!thread.value) return;
    const id = thread.value.conversation.id;
    thread.value.conversation.tags = tags;
    const saved = await api<{ tags: string[] }>('PUT', route('inbox.tags', id), { tags });
    if (thread.value?.conversation.id === id) thread.value.conversation.tags = saved.tags;
    const row = list.value.find((c) => c.id === id);
    if (row) row.tags = saved.tags;
};

// ── Polling ────────────────────────────────────────────────────────────
let tick = 0;
let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    if (openId.value) loadThread(openId.value);

    timer = setInterval(() => {
        if (document.hidden) return;
        tick++;
        if (openId.value && !loadingThread.value) loadThread(openId.value, true);
        if (tick % 2 === 0) refreshList();
        // The sidebar badge is a shared prop; refresh it now and then.
        if (tick % 6 === 0) router.reload({ only: ['inbox_unread'] });
    }, 4000);
});

onBeforeUnmount(() => {
    clearInterval(timer);
    clearTimeout(searchTimer);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="المحادثات" />

        <!-- Fills the space under the header; on phones, above the tab bar. -->
        <div class="flex h-[calc(100dvh-6.75rem)] min-h-0 overflow-hidden border-t border-border md:h-[calc(100dvh-5.5rem)] md:rounded-b-xl">
            <!-- ── Conversations ─────────────────────────────────────── -->
            <section
                class="flex w-full min-w-0 flex-col border-l border-border bg-card md:w-80 md:shrink-0 lg:w-96"
                :class="openId ? 'hidden md:flex' : 'flex'"
            >
                <div class="space-y-2.5 border-b border-border p-3">
                    <div class="flex items-center justify-between">
                        <h1 class="text-lg font-bold">المحادثات</h1>
                        <span v-if="unreadTotal" class="rounded-full bg-success px-2 py-0.5 text-xs font-semibold text-white">{{ unreadTotal }} جديدة</span>
                    </div>
                    <div class="relative">
                        <Search class="absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="search" class="field h-9 pr-9 text-sm" placeholder="اسم أو رقم" />
                    </div>
                    <div class="flex gap-1.5">
                        <button
                            v-for="tab in [{ key: 'all', label: 'الكل' }, { key: 'unread', label: 'غير مقروءة' }] as const"
                            :key="tab.key"
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                            :class="filter === tab.key ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:text-foreground'"
                            @click="filter = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <div v-if="!list.length" class="p-6 text-center text-sm text-muted-foreground">
                        <MessagesSquare class="mx-auto mb-2 size-8 opacity-40" />
                        <template v-if="search || filter === 'unread'">مفيش محادثات بالشكل ده.</template>
                        <template v-else-if="!channel.linked">
                            أول ما تربط رقم واتساب، كل رسايل عملائك هتبقى هنا.
                            <Link href="/settings/whatsapp" class="mt-2 block font-medium text-primary">اربط رقمك</Link>
                        </template>
                        <template v-else>لسه مفيش رسايل. أول ما عميل يكتبلك هتلاقيه هنا.</template>
                    </div>

                    <button
                        v-for="c in list"
                        :key="c.id"
                        type="button"
                        class="flex w-full items-start gap-3 border-b border-border/60 px-3 py-3 text-right transition-colors hover:bg-muted/60"
                        :class="openId === c.id ? 'bg-muted' : ''"
                        @click="open(c.id)"
                    >
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white" :class="avatarTone(c.id)">
                            {{ initial(c.name, c.phone) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="truncate font-semibold" :class="c.unread ? '' : 'font-medium'">{{ c.name ?? c.phone }}</p>
                                <span class="shrink-0 text-[11px]" :class="c.unread ? 'font-semibold text-success' : 'text-muted-foreground'">{{ listTime(c.last_at) }}</span>
                            </div>
                            <div class="mt-0.5 flex items-center gap-2">
                                <p class="flex min-w-0 flex-1 items-center gap-1 truncate text-sm" :class="c.unread ? 'text-foreground' : 'text-muted-foreground'">
                                    <CheckCheck v-if="c.last_direction === 'out'" class="size-3.5 shrink-0 opacity-60" />
                                    <span class="truncate">{{ c.preview }}</span>
                                </p>
                                <span v-if="c.unread" class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-success px-1.5 text-[11px] font-semibold text-white">
                                    {{ c.unread }}
                                </span>
                            </div>
                            <div v-if="c.tags.length" class="mt-1.5 flex flex-wrap gap-1">
                                <span v-for="tag in c.tags" :key="tag" class="rounded-md bg-primary/10 px-1.5 py-0.5 text-[10.5px] text-primary">{{ tag }}</span>
                            </div>
                        </div>
                    </button>
                </div>
            </section>

            <!-- ── Chat ──────────────────────────────────────────────── -->
            <section class="relative min-w-0 flex-1" :class="openId ? 'flex flex-col' : 'hidden md:flex md:flex-col'">
                <ChatThread
                    v-if="thread"
                    :thread="thread"
                    :channel="channel"
                    :quick-replies="replies"
                    @back="back"
                    @toggle-panel="panelOpen = !panelOpen"
                    @sent="refreshList"
                    @unread="markUnread"
                    @quick-replies="replies = $event"
                />
                <div v-else-if="loadingThread" class="flex flex-1 items-center justify-center text-muted-foreground">
                    <Loader2 class="size-6 animate-spin" />
                </div>
                <div v-else class="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center text-muted-foreground">
                    <MessagesSquare class="size-12 opacity-30" />
                    <p v-if="threadError" class="text-sm text-destructive">{{ threadError }}</p>
                    <p v-else class="text-sm">اختار محادثة من القايمة.</p>
                </div>
            </section>

            <!-- ── Customer ──────────────────────────────────────────── -->
            <template v-if="thread">
                <div class="hidden w-72 shrink-0 border-r border-border xl:block">
                    <CustomerPanel
                        :thread="thread"
                        :currency="currency"
                        :suggested-tags="suggestedTags"
                        :busy-order="busyOrder"
                        @order-status="setOrderStatus"
                        @tags="setTags"
                    />
                </div>
                <!-- Below xl: a drawer over the chat. -->
                <div v-if="panelOpen" class="fixed inset-0 z-50 flex xl:hidden" @click.self="panelOpen = false">
                    <div class="absolute inset-0 bg-black/30" @click="panelOpen = false" />
                    <div class="relative mr-auto h-full w-80 max-w-[85vw] shadow-xl">
                        <CustomerPanel
                            :thread="thread"
                            :currency="currency"
                            :suggested-tags="suggestedTags"
                            :busy-order="busyOrder"
                            @order-status="setOrderStatus"
                            @tags="setTags"
                            @close="panelOpen = false"
                        />
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
