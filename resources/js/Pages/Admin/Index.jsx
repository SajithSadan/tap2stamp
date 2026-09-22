import { Head, Link, usePage } from '@inertiajs/react';
import AppHeader from '@/Components/AppHeader';

export default function Index({ shops }) {
    const { flash } = usePage().props;

    return (
        <>
            <Head title="Admin" />
            <div className="min-h-screen bg-brand-bg">
                <AppHeader title="Admin" />

                <div className="mx-auto max-w-2xl px-5 py-8">
                    <div className="flex items-center justify-between">
                        <h1 className="font-heading text-xl font-bold text-brand-text">Shops</h1>
                        <Link
                            href="/admin/shops/create"
                            className="rounded-brand bg-brand-accent px-4 py-2 text-sm font-semibold text-brand-accent-text"
                        >
                            Add shop
                        </Link>
                    </div>

                    {flash?.generatedPassword && (
                        <div className="mt-4 rounded-brand border border-brand-accent bg-brand-accent/10 p-4 text-sm text-brand-text">
                            <p className="font-semibold">Owner account created.</p>
                            <p className="mt-1 text-brand-muted">Temporary password — shown once, share it with the owner securely:</p>
                            <p className="mt-2 select-all rounded bg-brand-card px-3 py-2 font-mono text-sm">{flash.generatedPassword}</p>
                        </div>
                    )}

                    <div className="mt-5 space-y-3">
                        {shops.length === 0 && <p className="text-sm text-brand-muted">No shops yet.</p>}

                        {shops.map((shop) => (
                            <div key={shop.id} className="rounded-brand border border-brand-border bg-brand-card p-4">
                                <p className="text-sm font-semibold text-brand-text">{shop.name}</p>
                                <p className="text-xs text-brand-muted">/s/{shop.slug}</p>
                                <p className="mt-2 text-xs text-brand-muted">
                                    Owner: {shop.owner_name ?? '— not set'}
                                    {shop.owner_email && ` (${shop.owner_email})`}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}
