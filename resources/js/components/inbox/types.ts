export interface ConversationItem {
    id: number;
    name: string | null;
    phone: string;
    preview: string | null;
    last_direction: 'in' | 'out' | null;
    last_at: string | null;
    unread: number;
    tags: string[];
}

export interface ThreadMessage {
    id: number;
    direction: 'in' | 'out';
    kind: 'text' | 'poll' | 'vote' | 'media';
    body: string | null;
    options: string[] | null;
    status: 'sent' | 'delivered' | 'read' | 'failed' | 'received';
    error: string | null;
    at: string;
    by: string | null;
    order_number: string | number | null;
}

export interface ThreadNote {
    id: number;
    body: string;
    by: string | null;
    at: string;
}

export interface CustomerOrder {
    id: number;
    number: string | number;
    status: string;
    status_label: string;
    total: string;
    items: string[];
    created_at: string;
}

export interface Thread {
    conversation: {
        id: number;
        name: string | null;
        phone: string;
        wa_link: string;
        tags: string[];
        summary: string | null;
        summary_at: string | null;
    };
    messages: ThreadMessage[];
    notes: ThreadNote[];
    orders: CustomerOrder[];
}

export interface QuickReply {
    id: number;
    title: string;
    body: string;
}

export interface Channel {
    linked: boolean;
    can_reply: boolean;
    reason: string | null;
}

/** «م» for محمد, a phone's last two digits when there is no name. */
export const initial = (name: string | null, phone: string) => (name?.trim()?.[0] ?? phone.slice(-2));

/** A steady colour per conversation, so a face is recognisable at a glance. */
const AVATAR_TONES = ['bg-sky-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500', 'bg-violet-500', 'bg-teal-500', 'bg-orange-500'];
export const avatarTone = (id: number) => AVATAR_TONES[id % AVATAR_TONES.length];

const sameDay = (a: Date, b: Date) => a.toDateString() === b.toDateString();

/** 14:05 today, «أمس», then the date — the way WhatsApp's own list reads. */
export function listTime(iso: string | null): string {
    if (!iso) return '';
    const date = new Date(iso);
    const now = new Date();
    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);

    if (sameDay(date, now)) return clock(iso);
    if (sameDay(date, yesterday)) return 'أمس';

    return date.toLocaleDateString('ar-EG', { day: 'numeric', month: 'short' });
}

export const clock = (iso: string) => new Date(iso).toLocaleTimeString('ar-EG', { hour: 'numeric', minute: '2-digit' });

/** The separator between days in a thread. */
export function dayLabel(iso: string): string {
    const date = new Date(iso);
    const now = new Date();
    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);

    if (sameDay(date, now)) return 'النهارده';
    if (sameDay(date, yesterday)) return 'أمس';

    return date.toLocaleDateString('ar-EG', { weekday: 'long', day: 'numeric', month: 'long' });
}
