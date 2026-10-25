import { csrfHeaders } from '@/lib/csrf';

/** Thrown with the server's own words, ready to show. */
export class InboxError extends Error {}

/**
 * JSON in, JSON out, for the inbox's own endpoints. Not Inertia: the page
 * stays put while a message is sent or a tag added, and only the piece that
 * changed is redrawn.
 */
export async function api<T>(method: string, url: string, body?: unknown): Promise<T> {
    let response: Response;

    try {
        response = await fetch(url, {
            method,
            headers: {
                ...csrfHeaders(),
                Accept: 'application/json',
                ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new InboxError('النت فصل. جرّب تاني.');
    }

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        // A failed send still returns the message, marked failed — let the
        // caller have it.
        if (payload?.message && typeof payload.message === 'object') {
            return payload as T;
        }

        const first = Object.values((payload?.errors ?? {}) as Record<string, string[]>)[0]?.[0];
        throw new InboxError(first ?? payload?.message ?? 'حصلت مشكلة. جرّب تاني.');
    }

    return payload as T;
}
