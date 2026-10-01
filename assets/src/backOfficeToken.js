/**
 * The token of the back office, rendered by its layout in
 * `<meta name="bo-token">`.
 *
 * Every request of the editor that is not a read carries it, in the
 * `X-CSRF-Token` header: the server refuses a write without it. It never goes
 * into a URL, which ends up in the browser history and in the server logs.
 */
export const TOKEN_HEADER = "X-CSRF-Token";

export function backOfficeToken() {
    return document.querySelector('meta[name="bo-token"]')?.getAttribute("content") ?? "";
}
