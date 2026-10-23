import { csrfHeaders } from '@/lib/csrf';
import { onBeforeUnmount } from 'vue';

/*
 * Start an AI generation and wait for its draft.
 *
 * Every on-screen AI feature works the same way: POST the merchant's input,
 * get an id back at once, then poll until the queued job has an answer. Kept
 * here so the waiting rules — how often to ask, when to give up, which words
 * the merchant sees when something goes wrong — are the same on every screen.
 */

interface Generation<T> {
    id: string;
    status: 'pending' | 'running' | 'done' | 'failed';
    draft: T | null;
    error: string | null;
}

const POLL_EVERY_MS = 2000;
// A little past the server's own expiry, so the server's message wins.
const GIVE_UP_AFTER_MS = 6 * 60 * 1000;

const CONNECTION_PROBLEM = 'حصلت مشكلة في الاتصال. جرّب تاني.';

/** Thrown with words a merchant can act on; show `message` as-is. */
export class AiGenerationError extends Error {}

/** The server's own words: "اكتب بتبيع إيه" tells the merchant what to do, "خطأ ٤٢٢" does not. */
const messageFrom = (payload: { message?: string; errors?: Record<string, string[]> }) =>
    Object.values(payload.errors ?? {})[0]?.[0] ?? payload.message ?? 'حصلت مشكلة. جرّب تاني.';

export function useAiGeneration() {
    let unmounted = false;
    let timer: ReturnType<typeof setTimeout> | undefined;

    onBeforeUnmount(() => {
        unmounted = true;
        clearTimeout(timer);
    });

    const wait = () => new Promise<void>((resolve) => (timer = setTimeout(resolve, POLL_EVERY_MS)));

    /**
     * @param url   the feature's POST endpoint; polling goes to `${url}/${id}`
     * @param body  FormData for uploads, a plain object otherwise
     * @returns     the draft, and the generation id it belongs to
     */
    const run = async <T>(url: string, body: FormData | Record<string, unknown>): Promise<{ id: string; draft: T }> => {
        let response: Response;

        try {
            const isForm = body instanceof FormData;
            response = await fetch(url, {
                method: 'POST',
                body: isForm ? body : JSON.stringify(body),
                headers: {
                    ...csrfHeaders(),
                    Accept: 'application/json',
                    ...(isForm ? {} : { 'Content-Type': 'application/json' }),
                },
            });
        } catch {
            throw new AiGenerationError(CONNECTION_PROBLEM);
        }

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new AiGenerationError(messageFrom(payload));
        }

        const started = Date.now();
        let current = payload as Generation<T>;

        while (!unmounted) {
            if (current.status === 'done' && current.draft) {
                return { id: current.id, draft: current.draft };
            }

            if (current.status === 'failed') {
                throw new AiGenerationError(current.error ?? 'حصلت مشكلة وإحنا بنكتب. جرّب تاني.');
            }

            if (Date.now() - started > GIVE_UP_AFTER_MS) {
                throw new AiGenerationError('الموضوع أخد وقت أكتر من اللازم. جرّب تاني.');
            }

            await wait();

            try {
                const poll = await fetch(`${url}/${current.id}`, { headers: { Accept: 'application/json' } });
                if (!poll.ok) throw new Error();
                current = await poll.json();
            } catch {
                throw new AiGenerationError(CONNECTION_PROBLEM);
            }
        }

        // Left the page: nobody is waiting for the answer any more.
        throw new AiGenerationError('');
    };

    return { run };
}
