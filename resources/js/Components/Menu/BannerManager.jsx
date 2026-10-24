import axios from "axios";
import { useRef, useState } from "react";
import { LuArrowLeft, LuArrowRight, LuImagePlus, LuLoaderCircle, LuTrash2 } from "react-icons/lu";

/** The server's message for a failed request, else a general one. */
function errorText(error) {
    const data = error.response?.data;

    return data?.errors?.image?.[0] ?? data?.errors?.banner?.[0] ?? data?.message ?? "That didn't work - try again.";
}

/**
 * The promotion carousel above the menu (App\Services\MenuBanners): add,
 * reorder and remove banners. Each change is saved straight away through
 * {base}/banners - it isn't part of the menu's Save.
 */
export default function BannerManager({ base, banners, max, onChange, confirm }) {
    const inputRef = useRef(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    async function run(request) {
        setBusy(true);
        setError(null);
        try {
            const { data } = await request();
            onChange(data.banners);
        } catch (e) {
            setError(errorText(e));
        } finally {
            setBusy(false);
        }
    }

    function add(e) {
        const file = e.target.files?.[0];
        e.target.value = "";
        if (!file) return;

        const form = new FormData();
        form.append("image", file);
        run(() => axios.post(`${base}/banners`, form));
    }

    async function remove(banner, i) {
        const ok = await confirm({
            title: `Remove banner ${i + 1}?`,
            message: "It comes off the menu straight away.",
            confirmLabel: "Remove",
            danger: true,
        });
        if (ok) run(() => axios.delete(`${base}/banners`, { data: { banner: banner.id } }));
    }

    function move(i, by) {
        const ids = banners.map((b) => b.id);
        [ids[i], ids[i + by]] = [ids[i + by], ids[i]];
        run(() => axios.put(`${base}/banners`, { ids }));
    }

    const iconButton = "rounded-full bg-black/55 p-1.5 text-white hover:bg-black/75 disabled:opacity-40";

    return (
        <section className="mb-4 rounded-2xl border border-brand-border bg-brand-card p-4">
            <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 className="font-heading text-base font-semibold text-brand-text">Promotion banners</h2>
                <p className="text-xs text-brand-muted">
                    A slideshow above your menu · {banners.length}/{max} · best size 16:9, e.g. 1600 × 900
                </p>
                {busy && <LuLoaderCircle className="h-4 w-4 animate-spin text-brand-muted" aria-label="Saving" />}
            </div>

            <ul className="mt-3 flex gap-3 overflow-x-auto pb-1">
                {banners.map((banner, i) => (
                    <li key={banner.id} className="relative w-44 shrink-0 overflow-hidden rounded-xl border border-brand-border">
                        <img src={banner.url} alt={`Banner ${i + 1}`} className="aspect-video w-full object-cover" />
                        <span className="absolute left-1.5 top-1.5 rounded-full bg-black/55 px-1.5 text-[11px] font-semibold text-white">{i + 1}</span>
                        <div className="absolute bottom-1.5 right-1.5 flex gap-1">
                            <button type="button" onClick={() => move(i, -1)} disabled={busy || i === 0} aria-label={`Move banner ${i + 1} earlier`} className={iconButton}>
                                <LuArrowLeft className="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                onClick={() => move(i, 1)}
                                disabled={busy || i === banners.length - 1}
                                aria-label={`Move banner ${i + 1} later`}
                                className={iconButton}
                            >
                                <LuArrowRight className="h-3.5 w-3.5" />
                            </button>
                            <button type="button" onClick={() => remove(banner, i)} disabled={busy} aria-label={`Remove banner ${i + 1}`} className={iconButton}>
                                <LuTrash2 className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </li>
                ))}
                {banners.length < max && (
                    <li className="w-44 shrink-0">
                        <button
                            type="button"
                            onClick={() => inputRef.current?.click()}
                            disabled={busy}
                            className="flex aspect-video w-full flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-brand-border text-sm font-medium text-brand-muted transition-colors hover:border-brand-accent hover:text-brand-text disabled:opacity-60"
                        >
                            <LuImagePlus className="h-5 w-5" /> Add banner
                        </button>
                        <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/webp" onChange={add} className="hidden" />
                    </li>
                )}
            </ul>

            {error && (
                <p role="alert" className="mt-2 text-sm text-red-700">
                    {error}
                </p>
            )}
        </section>
    );
}
