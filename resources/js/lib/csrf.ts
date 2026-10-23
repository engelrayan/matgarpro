/*
 * CSRF headers for the requests that do not go through Inertia (the builder's
 * image upload), taken from Laravel's XSRF-TOKEN cookie rather than the page's
 * <meta> tag.
 *
 * The meta tag is written once, when the HTML document loads. Signing in
 * through Inertia rotates the session's token without reloading the document,
 * so a merchant who logs in and clicks through to the builder carries a dead
 * token in the meta tag — and their first upload fails with "CSRF token
 * mismatch". The cookie is refreshed on every response, so it is always
 * current. Laravel decrypts it from the X-XSRF-TOKEN header itself.
 *
 * The meta tag stays as a fallback for a browser that refuses the cookie.
 */
export function csrfHeaders(): Record<string, string> {
    const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));

    if (cookie) {
        return { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) };
    }

    return { 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' };
}
