<script setup lang="ts">
import { AiGenerationError, useAiGeneration } from '@/composables/useAiGeneration';
import {
    AlertCircle,
    ArrowRight,
    Check,
    CheckCheck,
    Loader2,
    MailWarning,
    PanelLeft,
    Send,
    Sparkles,
    StickyNote,
    Trash2,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { api, InboxError } from './api';
import { avatarTone, clock, dayLabel, initial, type Channel, type QuickReply, type Thread, type ThreadMessage, type ThreadNote } from './types';

const props = defineProps<{
    thread: Thread;
    channel: Channel;
    quickReplies: QuickReply[];
}>();

const emit = defineEmits<{
    back: [];
    togglePanel: [];
    sent: [];
    unread: [];
    quickReplies: [replies: QuickReply[]];
}>();

const id = computed(() => props.thread.conversation.id);

// ── Timeline: messages and notes, by time, with a line per day ──────────
type Entry =
    | { type: 'day'; key: string; label: string }
    | { type: 'message'; key: string; message: ThreadMessage }
    | { type: 'note'; key: string; note: ThreadNote };

const timeline = computed<Entry[]>(() => {
    const items = [
        ...props.thread.messages.map((message) => ({ at: message.at, entry: { type: 'message' as const, key: `m${message.id}`, message } })),
        ...props.thread.notes.map((note) => ({ at: note.at, entry: { type: 'note' as const, key: `n${note.id}`, note } })),
    ].sort((a, b) => a.at.localeCompare(b.at));

    const out: Entry[] = [];
    let lastDay = '';
    for (const item of items) {
        const day = new Date(item.at).toDateString();
        if (day !== lastDay) {
            out.push({ type: 'day', key: `d${day}`, label: dayLabel(item.at) });
            lastDay = day;
        }
        out.push(item.entry);
    }

    return out;
});

// ── Scrolling: to the bottom on open, and on news if already near it ──
const scroller = ref<HTMLElement | null>(null);
const nearBottom = () => {
    const el = scroller.value;
    return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 160;
};
const toBottom = () => nextTick(() => scroller.value && (scroller.value.scrollTop = scroller.value.scrollHeight));

watch(id, () => {
    toBottom();
    suggestion.value = null;
    aiError.value = '';
    draft.value = '';
    noteMode.value = false;
}, { immediate: true });

watch(
    () => props.thread.messages.length + props.thread.notes.length,
    () => nearBottom() && toBottom(),
    { flush: 'pre' },
);

// ── Composer ───────────────────────────────────────────────────────────
const draft = ref('');
const noteMode = ref(false);
const sending = ref(false);
const sendError = ref('');
const textarea = ref<HTMLTextAreaElement | null>(null);

const canSendHere = computed(() => noteMode.value || props.channel.can_reply);

const grow = () => {
    const el = textarea.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, 160)}px`;
};
watch(draft, () => nextTick(grow));

const send = async () => {
    const body = draft.value.trim();
    if (!body || sending.value || !canSendHere.value) return;

    sending.value = true;
    sendError.value = '';

    try {
        if (noteMode.value) {
            const { note } = await api<{ note: ThreadNote }>('POST', route('inbox.notes.store', id.value), { body });
            props.thread.notes.push(note);
            noteMode.value = false;
        } else {
            const { message } = await api<{ message: ThreadMessage }>('POST', route('inbox.reply', id.value), { body });
            props.thread.messages.push(message);
            if (message.status === 'failed') sendError.value = message.error ?? 'الرسالة ماوصلتش.';
        }
        draft.value = '';
        suggestion.value = null;
        toBottom();
        emit('sent');
    } catch (e) {
        sendError.value = e instanceof InboxError ? e.message : 'حصلت مشكلة. جرّب تاني.';
    } finally {
        sending.value = false;
    }
};

const onKeydown = (e: KeyboardEvent) => {
    if (slashOpen.value && (e.key === 'Enter' || e.key === 'Tab') && slashMatches.value.length) {
        e.preventDefault();
        pickReply(slashMatches.value[0]);
        return;
    }
    if (e.key === 'Escape') {
        draft.value = draft.value.startsWith('/') ? '' : draft.value;
        return;
    }
    // Enter sends, Shift+Enter is a new line — as in WhatsApp Web.
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
        e.preventDefault();
        send();
    }
};

// ── Ready replies: «/» opens them, the text after it filters ────────────
const slashOpen = computed(() => draft.value.startsWith('/') && !noteMode.value);
const slashQuery = computed(() => draft.value.slice(1).trim());
const slashMatches = computed(() =>
    props.quickReplies.filter((r) => !slashQuery.value || r.title.includes(slashQuery.value) || r.body.includes(slashQuery.value)),
);

const pickReply = (reply: QuickReply) => {
    draft.value = reply.body;
    nextTick(() => textarea.value?.focus());
};

const addingReply = ref(false);
const replyForm = ref({ title: '', body: '' });
const replyError = ref('');

const saveReply = async () => {
    replyError.value = '';
    try {
        const { quickReplies } = await api<{ quickReplies: QuickReply[] }>('POST', route('inbox.quick-replies.store'), replyForm.value);
        emit('quickReplies', quickReplies);
        replyForm.value = { title: '', body: '' };
        addingReply.value = false;
    } catch (e) {
        replyError.value = e instanceof InboxError ? e.message : 'ماتحفظش. جرّب تاني.';
    }
};

const deleteReply = async (reply: QuickReply) => {
    const { quickReplies } = await api<{ quickReplies: QuickReply[] }>('DELETE', route('inbox.quick-replies.destroy', reply.id));
    emit('quickReplies', quickReplies);
};

const deleteNote = async (note: ThreadNote) => {
    await api('DELETE', route('inbox.notes.destroy', note.id));
    props.thread.notes.splice(props.thread.notes.indexOf(note), 1);
};

// ── AI: one reading gives the summary and a reply to send ──────────────
const { run } = useAiGeneration();
const thinking = ref(false);
const aiError = ref('');
const suggestion = ref<string | null>(null);
const showSummary = ref(false);

const assist = async (want: 'summary' | 'reply') => {
    if (thinking.value) return;
    thinking.value = true;
    aiError.value = '';
    if (want === 'summary') showSummary.value = true;

    const asked = id.value;
    try {
        const { draft: answer } = await run<{ summary: string; reply: string }>(route('inbox.ai.store', asked), {});
        if (asked !== id.value) return;
        props.thread.conversation.summary = answer.summary;
        props.thread.conversation.summary_at = new Date().toISOString();
        suggestion.value = answer.reply || null;
        if (want === 'reply' && !answer.reply) aiError.value = 'مفيش حاجة مستنية رد منك في المحادثة دي.';
    } catch (e) {
        if (e instanceof AiGenerationError && e.message) aiError.value = e.message;
    } finally {
        thinking.value = false;
    }
};

const useSuggestion = () => {
    draft.value = suggestion.value ?? '';
    suggestion.value = null;
    noteMode.value = false;
    nextTick(() => textarea.value?.focus());
};

// ── Bits of a bubble ───────────────────────────────────────────────────
const pollChoice = computed(() => {
    // The customer's latest vote, to tick it in the poll above it.
    const votes = props.thread.messages.filter((m) => m.kind === 'vote');
    return votes.length ? votes[votes.length - 1].body : null;
});
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <!-- ── Header ───────────────────────────────────────────────── -->
        <header class="flex items-center gap-3 border-b border-border bg-card px-3 py-2.5">
            <button type="button" class="rounded-lg p-1.5 hover:bg-muted md:hidden" aria-label="رجوع" @click="emit('back')">
                <ArrowRight class="size-5" />
            </button>
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white" :class="avatarTone(id)">
                {{ initial(thread.conversation.name, thread.conversation.phone) }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate font-semibold">{{ thread.conversation.name ?? thread.conversation.phone }}</p>
                <p class="truncate text-xs text-muted-foreground" dir="ltr" style="text-align: right">{{ thread.conversation.phone }}</p>
            </div>
            <button
                type="button"
                class="flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/5 px-3 py-1.5 text-xs font-medium text-primary transition-colors hover:bg-primary/10"
                @click="showSummary && thread.conversation.summary ? (showSummary = false) : assist('summary')"
            >
                <Loader2 v-if="thinking" class="size-3.5 animate-spin" />
                <Sparkles v-else class="size-3.5" />
                ملخّص الذكاء
            </button>
            <button type="button" class="rounded-lg p-2 text-muted-foreground hover:bg-muted" title="علّمها مش مقروءة" @click="emit('unread')">
                <MailWarning class="size-4" />
            </button>
            <button type="button" class="rounded-lg p-2 text-muted-foreground hover:bg-muted" title="طلبات العميل والوسوم" @click="emit('togglePanel')">
                <PanelLeft class="size-4" />
            </button>
        </header>

        <!-- ── AI summary: for the team only ─────────────────────────── -->
        <div v-if="showSummary" class="border-b border-primary/20 bg-primary/5 px-4 py-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-1.5 text-xs font-semibold text-primary">
                        <Sparkles class="size-3.5" />
                        ملخّص الذكاء
                        <span class="font-normal text-muted-foreground">· للفريق بس — مابيتبعتش للعميل</span>
                    </p>
                    <p v-if="thinking && !thread.conversation.summary" class="mt-1.5 text-sm text-muted-foreground">بيقرا المحادثة…</p>
                    <p v-else-if="thread.conversation.summary" class="mt-1.5 text-sm leading-relaxed whitespace-pre-line">{{ thread.conversation.summary }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <button v-if="thread.conversation.summary && !thinking" type="button" class="rounded-lg px-2 py-1 text-xs text-primary hover:bg-primary/10" @click="assist('summary')">
                        حدّثه
                    </button>
                    <button type="button" class="rounded-lg p-1 text-muted-foreground hover:bg-muted" aria-label="اقفل" @click="showSummary = false">
                        <X class="size-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Messages ──────────────────────────────────────────────── -->
        <div ref="scroller" class="chat-wallpaper min-h-0 flex-1 space-y-1.5 overflow-y-auto px-3 py-4 md:px-6">
            <template v-for="entry in timeline" :key="entry.key">
                <div v-if="entry.type === 'day'" class="flex justify-center py-2">
                    <span class="rounded-lg bg-card/90 px-3 py-1 text-[11px] text-muted-foreground shadow-sm">{{ entry.label }}</span>
                </div>

                <!-- A note: the team's, never sent -->
                <div v-else-if="entry.type === 'note'" class="mx-auto my-2 max-w-md rounded-xl border border-warning/30 bg-warning/10 px-3 py-2 text-sm">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] font-semibold text-warning">
                            <StickyNote class="-mt-0.5 inline size-3" />
                            ملاحظة<span v-if="entry.note.by"> · {{ entry.note.by }}</span>
                        </p>
                        <button type="button" class="text-muted-foreground hover:text-destructive" aria-label="امسح الملاحظة" @click="deleteNote(entry.note)">
                            <Trash2 class="size-3" />
                        </button>
                    </div>
                    <p class="mt-0.5 whitespace-pre-line">{{ entry.note.body }}</p>
                    <p class="mt-0.5 text-[10px] text-muted-foreground">{{ clock(entry.note.at) }}</p>
                </div>

                <!-- A message. Outgoing on the left, as WhatsApp draws it in Arabic. -->
                <div v-else class="flex" :class="entry.message.direction === 'out' ? 'justify-end' : 'justify-start'">
                    <div
                        class="max-w-[85%] rounded-2xl px-3 py-2 text-sm shadow-sm md:max-w-[70%]"
                        :class="entry.message.direction === 'out'
                            ? 'rounded-tl-sm bg-[#d9fdd3] text-[#111b21] dark:bg-[#005c4b] dark:text-white'
                            : 'rounded-tr-sm bg-card'"
                    >
                        <p v-if="entry.message.by" class="mb-0.5 text-[11px] font-semibold text-info">
                            كتبها {{ entry.message.by }}<span v-if="entry.message.order_number" class="font-normal text-muted-foreground"> · طلب #{{ entry.message.order_number }}</span>
                        </p>

                        <template v-if="entry.message.kind === 'poll'">
                            <p class="leading-relaxed font-medium whitespace-pre-line">{{ entry.message.body }}</p>
                            <div class="mt-2 space-y-1.5 border-t border-black/10 pt-2 dark:border-white/15">
                                <div v-for="option in entry.message.options ?? []" :key="option" class="flex items-center gap-2">
                                    <span
                                        class="flex size-4 shrink-0 items-center justify-center rounded-full border-2"
                                        :class="pollChoice === option ? 'border-success bg-success text-white' : 'border-current opacity-40'"
                                    >
                                        <Check v-if="pollChoice === option" class="size-2.5" :stroke-width="4" />
                                    </span>
                                    <span>{{ option }}</span>
                                </div>
                            </div>
                        </template>

                        <p v-else-if="entry.message.kind === 'vote'" class="flex items-center gap-1.5">
                            <span class="text-xs text-muted-foreground">اختار في الاستطلاع:</span>
                            <span class="font-semibold">{{ entry.message.body }}</span>
                        </p>

                        <p v-else class="leading-relaxed whitespace-pre-line" :class="entry.message.kind === 'media' ? 'italic text-muted-foreground' : ''">{{ entry.message.body }}</p>

                        <p class="mt-1 flex items-center justify-end gap-1 text-[10px] opacity-60">
                            {{ clock(entry.message.at) }}
                            <template v-if="entry.message.direction === 'out'">
                                <AlertCircle v-if="entry.message.status === 'failed'" class="size-3.5 text-destructive opacity-100" />
                                <CheckCheck v-else-if="entry.message.status === 'read'" class="size-3.5 text-sky-500 opacity-100" />
                                <CheckCheck v-else-if="entry.message.status === 'delivered'" class="size-3.5" />
                                <Check v-else class="size-3.5" />
                            </template>
                        </p>
                        <p v-if="entry.message.status === 'failed' && entry.message.error" class="mt-0.5 text-[11px] text-destructive">{{ entry.message.error }}</p>
                    </div>
                </div>
            </template>
        </div>

        <!-- ── AI suggested reply ───────────────────────────────────── -->
        <div v-if="suggestion" class="border-t border-info/20 bg-info/5 px-4 py-3">
            <p class="flex items-center gap-1.5 text-xs font-semibold text-info">
                <Sparkles class="size-3.5" />
                اقتراح الذكاء
            </p>
            <p class="mt-1 text-sm leading-relaxed whitespace-pre-line">{{ suggestion }}</p>
            <div class="mt-2 flex gap-2">
                <button type="button" class="rounded-lg bg-info px-3 py-1.5 text-xs font-medium text-white" @click="useSuggestion">استخدمه</button>
                <button type="button" class="rounded-lg px-3 py-1.5 text-xs text-muted-foreground hover:bg-muted" @click="suggestion = null">تجاهل</button>
            </div>
        </div>

        <!-- ── Composer ─────────────────────────────────────────────── -->
        <footer class="relative border-t border-border bg-card p-2.5 md:p-3">
            <!-- Ready replies -->
            <div v-if="slashOpen" class="absolute inset-x-3 bottom-full mb-2 max-h-72 overflow-y-auto rounded-xl border border-border bg-popover p-1.5 shadow-lg">
                <p class="px-2 py-1 text-[11px] font-medium text-muted-foreground">الردود الجاهزة</p>
                <div v-for="reply in slashMatches" :key="reply.id" class="group flex items-start gap-2 rounded-lg px-2 py-1.5 hover:bg-muted">
                    <button type="button" class="min-w-0 flex-1 text-right" @click="pickReply(reply)">
                        <p class="text-sm font-medium">{{ reply.title }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ reply.body }}</p>
                    </button>
                    <button type="button" class="p-1 text-muted-foreground opacity-0 group-hover:opacity-100 hover:text-destructive" aria-label="امسح الرد" @click="deleteReply(reply)">
                        <Trash2 class="size-3.5" />
                    </button>
                </div>
                <p v-if="!slashMatches.length && !addingReply" class="px-2 py-1.5 text-xs text-muted-foreground">
                    {{ quickReplies.length ? 'مفيش رد بالاسم ده.' : 'لسه مفيش ردود جاهزة — ضيف أول واحد.' }}
                </p>

                <form v-if="addingReply" class="space-y-1.5 border-t border-border p-2" @submit.prevent="saveReply">
                    <input v-model="replyForm.title" class="field h-8 text-xs" maxlength="60" placeholder="اسم الرد (مثلاً: الشحن)" />
                    <textarea v-model="replyForm.body" class="field min-h-16 text-xs" maxlength="2000" placeholder="نص الرد" />
                    <p v-if="replyError" class="text-xs text-destructive">{{ replyError }}</p>
                    <div class="flex gap-1.5">
                        <button type="submit" class="rounded-lg bg-primary px-3 py-1 text-xs font-medium text-primary-foreground">احفظ</button>
                        <button type="button" class="rounded-lg px-3 py-1 text-xs text-muted-foreground hover:bg-muted" @click="addingReply = false">إلغاء</button>
                    </div>
                </form>
                <button v-else type="button" class="mt-1 w-full rounded-lg px-2 py-1.5 text-right text-xs font-medium text-primary hover:bg-primary/5" @click="addingReply = true">
                    + رد جاهز جديد
                </button>
            </div>

            <p v-if="!channel.can_reply && !noteMode" class="mb-2 rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
                {{ channel.reason }} <span v-if="channel.linked">ممكن تكتب ملاحظة للفريق.</span>
            </p>
            <p v-if="sendError" class="mb-2 text-xs text-destructive">{{ sendError }}</p>
            <p v-if="aiError" class="mb-2 text-xs text-destructive">{{ aiError }}</p>

            <div class="flex items-end gap-2">
                <div class="flex shrink-0 gap-0.5 pb-1">
                    <button
                        type="button"
                        class="rounded-lg p-2 transition-colors"
                        :class="noteMode ? 'bg-warning/15 text-warning' : 'text-muted-foreground hover:bg-muted'"
                        :title="noteMode ? 'رجوع للرد على العميل' : 'ملاحظة داخلية للفريق'"
                        @click="noteMode = !noteMode"
                    >
                        <StickyNote class="size-5" />
                    </button>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40"
                        title="اقترح رد بالذكاء"
                        :disabled="thinking"
                        @click="assist('reply')"
                    >
                        <Loader2 v-if="thinking" class="size-5 animate-spin" />
                        <Zap v-else class="size-5" />
                    </button>
                </div>

                <textarea
                    ref="textarea"
                    v-model="draft"
                    rows="1"
                    class="field max-h-40 min-h-10 flex-1 resize-none py-2 text-sm"
                    :class="noteMode ? 'border-warning/50 bg-warning/5' : ''"
                    :placeholder="noteMode ? 'ملاحظة للفريق — العميل مش هيشوفها' : 'اكتب ردّك… أو / للردود الجاهزة'"
                    :disabled="!canSendHere"
                    @keydown="onKeydown"
                />

                <button
                    type="button"
                    class="mb-0.5 flex size-10 shrink-0 items-center justify-center rounded-full text-white transition-opacity disabled:opacity-40"
                    :class="noteMode ? 'bg-warning' : 'bg-success'"
                    :disabled="!draft.trim() || sending || !canSendHere"
                    :aria-label="noteMode ? 'احفظ الملاحظة' : 'ابعت'"
                    @click="send"
                >
                    <Loader2 v-if="sending" class="size-4 animate-spin" />
                    <Send v-else class="size-4 -scale-x-100" />
                </button>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* WhatsApp's beige, without its doodles: the bubbles read as a chat at a glance. */
.chat-wallpaper {
    background-color: #efeae2;
}
:global(.dark) .chat-wallpaper {
    background-color: #0b141a;
}
</style>
