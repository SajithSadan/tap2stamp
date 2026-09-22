import { Head, Link, useForm } from '@inertiajs/react';
import AppHeader from '@/Components/AppHeader';

function slugify(value) {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

function Field({ label, value, onChange, error, type = 'text' }) {
    return (
        <div>
            <label className="block text-xs font-medium text-brand-muted">{label}</label>
            <input
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="mt-1 w-full rounded border border-brand-border px-3 py-2 text-sm text-brand-text outline-none focus:border-brand-accent"
            />
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        owner_name: '',
        owner_email: '',
        shop_name: '',
        shop_slug: '',
        shop_max_stamps: 8,
        shop_reward_title: '',
    });

    // Auto-fills the slug from the shop name, but stops following it the
    // moment the admin edits the slug field directly.
    function handleShopNameChange(value) {
        setData((prev) => ({
            ...prev,
            shop_name: value,
            shop_slug: prev.shop_slug === slugify(prev.shop_name) ? slugify(value) : prev.shop_slug,
        }));
    }

    function handleSubmit(e) {
        e.preventDefault();
        post('/admin/shops');
    }

    return (
        <>
            <Head title="Add shop" />
            <div className="min-h-screen bg-brand-bg">
                <AppHeader title="Add shop" />

                <div className="mx-auto max-w-lg px-5 py-8">
                    <Link href="/admin" className="text-sm text-brand-accent">
                        &larr; Back to shops
                    </Link>

                    <form onSubmit={handleSubmit} noValidate className="mt-4 space-y-4 rounded-brand border border-brand-border bg-brand-card p-5">
                        <fieldset className="space-y-3">
                            <legend className="text-sm font-semibold text-brand-text">Shop</legend>
                            <Field label="Shop name" value={data.shop_name} onChange={handleShopNameChange} error={errors.shop_name} />
                            <Field
                                label="Slug (used in the card URL: /s/…)"
                                value={data.shop_slug}
                                onChange={(v) => setData('shop_slug', slugify(v))}
                                error={errors.shop_slug}
                            />
                            <Field
                                label="Reward title"
                                value={data.shop_reward_title}
                                onChange={(v) => setData('shop_reward_title', v)}
                                error={errors.shop_reward_title}
                            />
                            <div>
                                <label className="block text-xs font-medium text-brand-muted">Stamps needed (4–12)</label>
                                <input
                                    type="number"
                                    min={4}
                                    max={12}
                                    value={data.shop_max_stamps}
                                    onChange={(e) => setData('shop_max_stamps', Number(e.target.value))}
                                    className="mt-1 w-full rounded border border-brand-border px-3 py-2 text-sm text-brand-text outline-none focus:border-brand-accent"
                                />
                                {errors.shop_max_stamps && <p className="mt-1 text-xs text-red-600">{errors.shop_max_stamps}</p>}
                            </div>
                        </fieldset>

                        <fieldset className="space-y-3 border-t border-brand-border pt-4">
                            <legend className="text-sm font-semibold text-brand-text">Owner login</legend>
                            <Field label="Owner name" value={data.owner_name} onChange={(v) => setData('owner_name', v)} error={errors.owner_name} />
                            <Field
                                label="Owner email"
                                type="email"
                                value={data.owner_email}
                                onChange={(v) => setData('owner_email', v)}
                                error={errors.owner_email}
                            />
                        </fieldset>

                        <p className="text-xs text-brand-muted">
                            A temporary password is generated automatically and shown once after the shop is created.
                        </p>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-brand bg-brand-accent px-4 py-2.5 text-sm font-semibold text-brand-accent-text disabled:opacity-50"
                        >
                            {processing ? 'Creating…' : 'Create shop'}
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
