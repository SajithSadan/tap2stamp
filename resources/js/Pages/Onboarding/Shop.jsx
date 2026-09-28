import { Head, Link, useForm } from '@inertiajs/react';
import { LuArrowRight, LuCheck, LuLogOut } from 'react-icons/lu';
import { AuthField, Wordmark, authButtonClass, authInputClass } from '@/Components/AuthShell';
import { CardPreview, MAX_STAMPS, MIN_STAMPS, SlugInput, StampStepper, slugify } from '@/Components/Dashboard/ShopFields';

function Steps() {
    return (
        <ol className="flex items-center gap-3 text-xs font-medium">
            <li className="flex items-center gap-2 text-brand-muted">
                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-brand-accent text-brand-accent-text">
                    <LuCheck className="h-3.5 w-3.5" />
                </span>
                Account
            </li>
            <li className="h-px w-8 bg-brand-border" aria-hidden="true" />
            <li className="flex items-center gap-2 text-brand-text" aria-current="step">
                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-brand-text text-brand-bg">2</span>
                Your shop
            </li>
        </ol>
    );
}

function Section({ title, description, children }) {
    return (
        <section className="rounded-2xl border border-brand-border bg-brand-card p-6 shadow-sm">
            <h2 className="font-heading text-lg font-semibold text-brand-text">{title}</h2>
            {description && <p className="mt-0.5 text-sm text-brand-muted">{description}</p>}
            <div className="mt-5 space-y-5">{children}</div>
        </section>
    );
}

const optional = <span className="font-normal text-brand-muted">(optional)</span>;

export default function Shop({ ownerName, ownerEmail }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        max_stamps: 8,
        reward_title: '',
        // Contact person + email start as the account's own.
        contact_name: ownerName ?? '',
        contact_email: ownerEmail ?? '',
        contact_phone: '',
        address_line1: '',
        address_line2: '',
        town: '',
        postcode: '',
        delivery_same: true,
        delivery_address: '',
    });

    const field = (key) => ({ id: key, value: data[key], onChange: (e) => setData(key, e.target.value), className: authInputClass });

    // Link follows the shop name until the owner edits it themselves.
    function handleNameChange(value) {
        setData((prev) => ({
            ...prev,
            name: value,
            slug: prev.slug === slugify(prev.name) ? slugify(value) : prev.slug,
        }));
    }

    function submit(e) {
        e.preventDefault();
        post('/onboarding');
    }

    const firstName = ownerName?.split(' ')[0];

    return (
        <>
            <Head title="Set up your shop" />

            <div className="min-h-dvh bg-brand-bg">
                <header className="border-b border-brand-border bg-brand-card">
                    <div className="mx-auto flex max-w-5xl items-center justify-between px-5 py-4">
                        <Wordmark className="text-xl text-brand-text" />
                        <Link href="/logout" method="post" as="button" className="inline-flex items-center gap-1.5 text-sm text-brand-muted hover:text-brand-text">
                            <LuLogOut className="h-4 w-4" /> Log out
                        </Link>
                    </div>
                </header>

                <main className="mx-auto max-w-5xl px-5 py-10">
                    <Steps />
                    <h1 className="mt-6 font-heading text-3xl font-bold text-brand-text">{firstName ? `Welcome, ${firstName}!` : 'Welcome!'} Let's set up your shop</h1>
                    <p className="mt-2 text-sm text-brand-muted">A few details about your business, then your loyalty card. You can change all of it later in Settings.</p>

                    <form onSubmit={submit} noValidate className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
                        <div className="space-y-6">
                            <Section title="Your business" description="Who we can contact about your account.">
                                <AuthField id="name" label="Shop / business name" error={errors.name}>
                                    <input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => handleNameChange(e.target.value)}
                                        placeholder="e.g. The Coffee Corner"
                                        autoComplete="organization"
                                        autoFocus
                                        className={authInputClass}
                                    />
                                </AuthField>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <AuthField id="contact_name" label="Contact person name" error={errors.contact_name}>
                                        <input {...field('contact_name')} autoComplete="name" placeholder="Jamie Smith" />
                                    </AuthField>
                                    <AuthField id="contact_phone" label="Contact number" error={errors.contact_phone} hint="Mobile or landline, e.g. 07700 900123.">
                                        <input {...field('contact_phone')} type="tel" inputMode="tel" autoComplete="tel" placeholder="07700 900123" />
                                    </AuthField>
                                </div>

                                <AuthField id="contact_email" label="Email" error={errors.contact_email}>
                                    <input {...field('contact_email')} type="email" autoComplete="email" placeholder="you@yourshop.co.uk" />
                                </AuthField>
                            </Section>

                            <Section title="Location" description="Where your shop is.">
                                <AuthField id="address_line1" label="Address line 1" error={errors.address_line1}>
                                    <input {...field('address_line1')} autoComplete="address-line1" placeholder="12 High Street" />
                                </AuthField>
                                <AuthField id="address_line2" label={<>Address line 2 {optional}</>} error={errors.address_line2}>
                                    <input {...field('address_line2')} autoComplete="address-line2" placeholder="Unit, building, area" />
                                </AuthField>
                                <div className="grid gap-5 sm:grid-cols-[minmax(0,1fr)_10rem]">
                                    <AuthField id="town" label="Town / city" error={errors.town}>
                                        <input {...field('town')} autoComplete="address-level2" placeholder="Leeds" />
                                    </AuthField>
                                    <AuthField id="postcode" label="Postcode" error={errors.postcode}>
                                        <input
                                            {...field('postcode')}
                                            onChange={(e) => setData('postcode', e.target.value.toUpperCase())}
                                            autoComplete="postal-code"
                                            autoCapitalize="characters"
                                            placeholder="LS1 4AP"
                                        />
                                    </AuthField>
                                </div>

                                <div className="rounded-xl border border-brand-border bg-brand-bg p-4">
                                    <p className="text-sm font-medium text-brand-text">Delivery address {optional}</p>
                                    <label className="mt-2 flex cursor-pointer items-center gap-2.5 text-sm text-brand-text">
                                        <input
                                            type="checkbox"
                                            checked={data.delivery_same}
                                            onChange={(e) => setData('delivery_same', e.target.checked)}
                                            className="h-4 w-4 accent-[var(--color-brand-accent)]"
                                        />
                                        Same as the shop address
                                    </label>
                                    {!data.delivery_same && (
                                        <div className="mt-3">
                                            <textarea
                                                id="delivery_address"
                                                value={data.delivery_address}
                                                onChange={(e) => setData('delivery_address', e.target.value)}
                                                rows={3}
                                                autoComplete="shipping street-address"
                                                placeholder={'Name / company\nStreet\nTown, postcode'}
                                                aria-label="Delivery address"
                                                className={`${authInputClass} resize-y`}
                                            />
                                            {errors.delivery_address && <p className="mt-1 text-xs text-red-600">{errors.delivery_address}</p>}
                                        </div>
                                    )}
                                </div>
                            </Section>

                            <Section title="Loyalty card" description="What your customers will see.">
                                <AuthField id="slug" label="Your card link" error={errors.slug} hint="Customers scan a QR that opens this link. Letters, numbers and dashes.">
                                    <SlugInput id="slug" value={data.slug} onChange={(v) => setData('slug', v)} placeholder="the-coffee-corner" />
                                </AuthField>

                                <div className="grid gap-5 sm:grid-cols-[auto_minmax(0,1fr)]">
                                    <AuthField id="max_stamps" label="Stamps for a reward" error={errors.max_stamps} hint={`${MIN_STAMPS}–${MAX_STAMPS}`}>
                                        <StampStepper id="max_stamps" value={data.max_stamps} onChange={(v) => setData('max_stamps', v)} />
                                    </AuthField>

                                    <AuthField id="reward_title" label="Reward" error={errors.reward_title}>
                                        <input
                                            id="reward_title"
                                            value={data.reward_title}
                                            onChange={(e) => setData('reward_title', e.target.value)}
                                            placeholder={`Free coffee after ${data.max_stamps} stamps`}
                                            className={authInputClass}
                                        />
                                    </AuthField>
                                </div>
                            </Section>

                            <button type="submit" disabled={processing} className={`${authButtonClass} sm:w-auto sm:px-8`}>
                                {processing ? 'Creating your shop…' : 'Create my shop'}
                                {!processing && <LuArrowRight className="h-4 w-4" />}
                            </button>
                        </div>

                        <aside className="lg:sticky lg:top-10">
                            <p className="mb-2 text-xs font-medium uppercase tracking-wide text-brand-muted">Live preview</p>
                            <CardPreview name={data.name} slug={data.slug} maxStamps={data.max_stamps} reward={data.reward_title} />
                        </aside>
                    </form>
                </main>
            </div>
        </>
    );
}
