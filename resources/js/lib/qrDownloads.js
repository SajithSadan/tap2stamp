/**
 * Tells the server an owner downloaded their QR (or that it failed) - the
 * file is made in the browser, so it wouldn't know otherwise. Fire and
 * forget: reporting must never get in the way of the download itself.
 * See App\Services\QrDownloads.
 */
export function reportQrDownload({ source, format, codes = [], ok = true, error = null }) {
    try {
        window.axios
            .post("/dashboard/qr-codes/downloaded", {
                source,
                format,
                qr_code_ids: codes.map((qr) => qr.id),
                ok,
                error: error ? String(error).slice(0, 200) : null,
            })
            .catch(() => {});
    } catch {
        // No axios / offline: the download still happened.
    }
}

/** Runs a download and reports how it went; rethrows so the page can show the error. */
export async function trackedQrDownload(details, task) {
    try {
        const result = await task();
        reportQrDownload({ ...details, ok: true });
        return result;
    } catch (error) {
        reportQrDownload({ ...details, ok: false, error: describe(error) });
        throw error;
    }
}

/** A failed <img> load rejects with an Event, which has no message. */
function describe(error) {
    if (error?.message) return error.message;
    if (error?.type === "error") return "An image didn't load (the design's background or logo)";
    return "Unknown error";
}
