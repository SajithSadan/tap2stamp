import { Link, router, useForm } from '@inertiajs/react';
import { LuExternalLink, LuLink, LuQrCode, LuScanLine, LuUnlink } from 'react-icons/lu';
import AdminLayout from '@/Components/Dashboard/AdminLayout';
import { FieldError, Panel, inputClass, primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';

/**
 * What a logged-in admin sees after scanning a sticker with their phone
 * camera (GET /qr/{code}). Customers scanning the same sticker are
 * redirected, or get "Nothing found" - never this page.
 */
export default function Scan({ qr }) {
    const form = useForm({ destination_url: qr.destination_url ?? '' });
    const mapped = !!qr.destination_url;

    function save(e) {
        e.preventDefault();
        form.put(`/admin/qr-codes/${qr.id}`, { preserveScroll: true });
    }

    function unmap() {
        if (!window.confirm('Unmap this sticker? Anyone scanning it will see "Nothing found" until it\'s mapped again.')) return;

        router.put(`/admin/qr-codes/${qr.id}`, { destination_url: '' }, { preserveScroll: true, onSuccess: () => form.setData('destination_url', '') });
    }

    return (
        <AdminLayout title={mapped ? 'Sticker mapped' : 'Map this sticker'} description="You're seeing this because you're logged in as admin.">
            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
                <Panel>
                    <div className="flex items-center gap-4">
                        <span
                            className={`flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl ${
                                mapped ? 'bg-emerald-500/10 text-emerald-600' : 'bg-brand-accent/10 text-brand-accent'
                            }`}
                        >
                            <LuQrCode className="h-7 w-7" />
                        </span>
                        <div className="min-w-0">
                            <p className="font-mono text-2xl font-bold tracking-wider text-brand-text">{qr.code}</p>
                            <p className="truncate text-xs text-brand-muted">
                                {qr.batch_label}
                                {qr.mapped_at && ` · mapped ${qr.mapped_at}`}
                            </p>
                        </div>
                    </div>

                    {mapped && (
                        <div className="mt-4 flex items-center gap-2 rounded-xl bg-emerald-500/10 px-3.5 py-2.5">
                            <LuLink className="h-4 w-4 shrink-0 text-emerald-600" />
                            <p className="min-w-0 flex-1 truncate text-sm text-brand-text" title={qr.destination_url}>
                                {qr.destination_url}
                            </p>
                            <a
                                href={qr.destination_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="Open destination"
                                className="rounded-lg p-1.5 text-brand-muted hover:bg-brand-card hover:text-brand-text"
                            >
                                <LuExternalLink className="h-4 w-4" />
                            </a>
                        </div>
                    )}

                    <form onSubmit={save} noValidate className="mt-5 space-y-3">
                        <div>
                            <label htmlFor="destination_url" className="block text-sm font-medium text-brand-text">
                                {mapped ? 'Change destination' : 'Where should this sticker go?'}
                            </label>
                            <input
                                id="destination_url"
                                type="url"
                                inputMode="url"
                                value={form.data.destination_url}
                                onChange={(e) => form.setData('destination_url', e.target.value)}
                                placeholder="https://example.com/event/summer-festival"
                                autoFocus={!mapped}
                                className={`${inputClass} mt-1.5 py-3`}
                            />
                            <FieldError message={form.errors.destination_url} />
                        </div>

                        <button type="submit" disabled={form.processing} className={`${primaryButton} w-full py-3`}>
                            <LuLink className="h-4 w-4" /> {form.processing ? 'Saving…' : mapped ? 'Update destination' : 'Map sticker'}
                        </button>

                        {mapped && (
                            <button type="button" onClick={unmap} className={`${secondaryButton} w-full text-red-600`}>
                                <LuUnlink className="h-4 w-4" /> Unmap
                            </button>
                        )}
                    </form>
                </Panel>

                <div className="flex items-start gap-3 rounded-2xl border border-dashed border-brand-border px-4 py-3.5 text-sm text-brand-muted">
                    <LuScanLine className="mt-0.5 h-4 w-4 shrink-0 text-brand-accent" />
                    <p>
                        Done? Scan the next sticker with your camera. Customers who scan this one are sent straight to its destination.{' '}
                        <Link href="/admin/qr-codes" className="font-medium text-brand-accent hover:underline">
                            All QR codes
                        </Link>
                    </p>
                </div>
            </div>
        </AdminLayout>
    );
}
