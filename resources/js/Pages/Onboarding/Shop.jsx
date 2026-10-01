import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { LuArrowLeft, LuArrowRight, LuCheck, LuLogOut } from 'react-icons/lu';
import { AuthField, Wordmark, authButtonClass, authInputClass } from '@/Components/AuthShell';
import { CardPreview, MAX_STAMPS, MIN_STAMPS, SlugInput, slugify } from '@/Components/Dashboard/ShopFields';
import useValidatedForm from '@/lib/useValidatedForm';
import { email, isPhone, isPostcode, matches, required, requiredIf } from '@/lib/validation';

const STEPS = [
    { key: 'account', label: 'Account', text: 'Your sign-in is ready.' },
    { key: 'business', label: 'Your business', text: 'Contact details and where you are.' },
    { key: 'card', label: 'Loyalty card', text: 'Stamps, reward and your card link.' },
];

// Checked in the browser before each step is sent (the server checks again).
const RULES = {
    name: [required('Enter your shop or business name.')],
    contact_name: [required('Enter a contact name.')],
    contact_phone: [required('Enter a contact number.'), matches(isPhone, 'Enter a valid UK phone number, e.g. 020 7946 0000 or 07700 900123.')],
    contact_email: [required('Enter an email address.'), email()],
    address_line1: [required('Enter the first line of the address.')],
    town: [required('Enter the town or city.')],
    postcode: [required('Enter the postcode.'), matches(isPostcode, 'Enter a valid postcode, e.g. SW1A 1AA.')],
    delivery_address: [requiredIf((data) => !data.delivery_same, 'Enter the delivery address, or tick "Same as the shop address".')],
    slug: [required('Choose a link for your card.')],
    reward_title: [required('Say what the reward is.')],
};

// Fields on the loyalty card step; any other error belongs to "Your business".
const CARD_FIELDS = ['slug', 'max_stamps', 'reward_title'];
const BUSINESS_FIELDS = Object.keys(RULES).filter((key) => !CARD_FIELDS.includes(key));

/** Vertical stepper for the navy side panel (desktop). */
function StepList({ currentIndex }) {
    return (
        <ol className="space-y-0">
            {STEPS.map((step, i) => {
                const done = i < currentIndex;
                const current = i === currentIndex;

                return (
                    <li key={step.key} className="relative flex gap-4 pb-8 last:pb-0" aria-current={current ? 'step' : undefined}>
                        {i < STEPS.length - 1 && (
                            <span className={`absolute left-[15px] top-9 h-[calc(100%-2.75rem)] w-px ${done ? 'bg-brand-accent' : 'bg-white/15'}`} aria-hidden="true" />
                        )}
                        <span
                            className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                                done ? 'bg-brand-accent text-brand-accent-text' : current ? 'bg-white text-brand-deep' : 'border border-white/20 text-white/50'
                            }`}
                        >
                            {done ? <LuCheck className="h-4 w-4" /> : i + 1}
                        </span>
                        <div className="pt-1">
                            <p className={`text-sm font-semibold ${current ? 'text-white' : done ? 'text-white/80' : 'text-white/50'}`}>{step.label}</p>
                            <p className={`mt-0.5 text-xs ${current ? 'text-white/70' : 'text-white/40'}`}>{step.text}</p>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

/** Compact progress for phones, where the side panel is hidden. */
function StepBar({ currentIndex }) {
    return (
        <div className="lg:hidden">
            <p className="text-xs font-medium text-brand-muted">
                Step {currentIndex + 1} of {STEPS.length} · <span className="text-brand-text">{STEPS[currentIndex].label}</span>
            </p>
            <div className="mt-2 grid grid-cols-3 gap-1.5" aria-hidden="true">
                {STEPS.map((step, i) => (
                    <span key={step.key} className={`h-1 rounded-full ${i <= currentIndex ? 'bg-brand-accent' : 'bg-brand-border'}`} />
                ))}
            </div>
        </div>
    );
}

/** One flat form row: title on the left, fields on the right, hairline between rows. */
function Section({ title, description, children }) {
    return (
        <section className="grid gap-5 border-t border-brand-border py-9 first:border-t-0 first:pt-0 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-10">
            <div>
                <h2 className="font-heading text-base font-semibold text-brand-text">{title}</h2>
                {description && <p className="mt-1 text-sm leading-relaxed text-brand-muted">{description}</p>}
            </div>
            <div className="space-y-5">{children}</div>
        </section>
    );
}

/** A numbered question on the loyalty card step: question, short explanation, then the answer. */
function Question({ number, title, description, children }) {
    return (
        <section className="border-t border-brand-border py-9 first:border-t-0 first:pt-0">
            <div className="flex gap-4">
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-deep text-xs font-semibold text-white">{number}</span>
                <div className="min-w-0 flex-1">
                    <h2 className="font-heading text-lg font-semibold text-brand-text">{title}</h2>
                    <p className="mt-1 text-sm text-brand-muted">{description}</p>
                    <div className="mt-5">{children}</div>
                </div>
            </div>
        </section>
    );
}

const REWARD_IDEAS = ['Free coffee', 'Free hot drink', 'Free cake or pastry', 'Free haircut', '10% off your next visit'];

/** Every allowed stamp count as one tap target, so there's nothing to type or get wrong. */
function StampPicker({ value, onChange }) {
    const counts = Array.from({ length: MAX_STAMPS - MIN_STAMPS + 1 }, (_, i) => MIN_STAMPS + i);

    return (
        <div id="max_stamps" role="radiogroup" aria-label="Stamps for a reward" className="flex flex-wrap gap-2">
            {counts.map((n) => (
                <button
                    key={n}
                    type="button"
                    role="radio"
                    aria-checked={value === n}
                    onClick={() => onChange(n)}
                    className={`flex h-11 w-11 items-center justify-center rounded-full border text-sm font-semibold tabular-nums transition ${
                        value === n
                            ? 'border-brand-accent bg-brand-accent text-brand-accent-text'
                            : 'border-brand-border bg-brand-card text-brand-text hover:border-brand-accent'
                    }`}
                >
                    {n}
                </button>
            ))}
        </div>
    );
}

const optional =<span className="font-normal text-brand-muted">(optional)</span>;

/** The server's step-1 draft as form values (it stores empty fields as null). */
function fromDraft(draft) {
    if (!draft) return {};
    const values = Object.fromEntries(Object.entries(draft).map(([key, value]) => [key, value ?? '']));
    return { ...values, delivery_same: draft.delivery_same ?? true, slug: slugify(draft.name ?? '') };
}

export default function Shop({ ownerName, ownerEmail, draft }) {
    // Step 1 already passed (draft kept by the server): a reload or a failed
    // final submit picks up on the loyalty card step, nothing to re-type.
    const [step, setStep] = useState(draft ? 'card' : 'business');
    const { data, setData, post, processing, errors } = useValidatedForm(
        {
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
            ...fromDraft(draft),
        },
        // Remembered in browser history too, so card-step answers survive a reload.
        { rules: RULES, rememberKey: 'Onboarding/Shop' },
    );

    const field = (key) => ({ id: key, value: data[key], onChange: (e) => setData(key, e.target.value), className: authInputClass });

    // Link follows the shop name until the owner edits it themselves.
    function handleNameChange(value) {
        setData((prev) => ({
            ...prev,
            name: value,
            slug: prev.slug === slugify(prev.name) ? slugify(value) : prev.slug,
        }));
    }

    // Unticking "same as the shop" starts the delivery box from the shop
    // address already typed, so the owner only changes what's different.
    function handleDeliverySame(same) {
        setData((prev) => {
            const shopAddress = [prev.name, prev.address_line1, prev.address_line2, [prev.town, prev.postcode].filter(Boolean).join(', ')]
                .map((line) => line.trim())
                .filter(Boolean)
                .join('\n');

            return { ...prev, delivery_same: same, delivery_address: !same && !prev.delivery_address.trim() ? shopAddress : prev.delivery_address };
        });
    }

    function goTo(next) {
        setStep(next);
        window.scrollTo({ top: 0 });
    }

    // Step 1 is checked by the server before moving on; nothing is saved yet.
    function submitBusiness(e) {
        e.preventDefault();
        post('/onboarding/business', { fields: BUSINESS_FIELDS, onSuccess: () => goTo('card') });
    }

    function submitCard(e) {
        e.preventDefault();
        post('/onboarding', {
            fields: CARD_FIELDS,
            // Everything is re-validated here; send the owner back if step 1 is the problem.
            onError: (errs) => {
                if (Object.keys(errs).some((key) => !CARD_FIELDS.includes(key))) goTo('business');
            },
        });
    }

    const firstName = ownerName?.split(' ')[0];
    const onCardStep = step === 'card';
    const currentIndex = STEPS.findIndex((s) => s.key === step);
    const preview = <CardPreview name={data.name} slug={data.slug} maxStamps={data.max_stamps} reward={data.reward_title} />;

    return (
        <>
            <Head title="Set up your shop" />

            <div className="flex min-h-dvh bg-brand-bg">
                {/* Brand panel (desktop), same navy as log in / sign up. */}
                <aside className="sticky top-0 hidden h-dvh w-[22rem] shrink-0 flex-col overflow-hidden bg-brand-deep p-10 text-white lg:flex xl:w-[26rem]">
                    <div className="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-brand-accent/20 blur-3xl" />
                    <div className="pointer-events-none absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-brand-accent/10 blur-3xl" />

                    <Wordmark variant="light" className="relative" />

                    <div className="relative mt-14">
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-brand-accent">Shop setup</p>
                        <div className="mt-6">
                            <StepList currentIndex={currentIndex} />
                        </div>
                    </div>

                    <p className="relative mt-auto text-sm leading-relaxed text-white/60">Takes about two minutes. You can change everything later in Settings.</p>
                </aside>

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="flex items-center justify-between px-5 py-5 sm:px-10 xl:px-16">
                        <Wordmark className="h-7 lg:invisible" />
                        <Link href="/logout" method="post" as="button" className="inline-flex items-center gap-1.5 text-sm text-brand-muted transition hover:text-brand-text">
                            <LuLogOut className="h-4 w-4" /> Log out
                        </Link>
                    </header>

                    <main className={`w-full ${onCardStep ? 'max-w-5xl' : 'max-w-3xl'} px-5 pb-16 pt-4 sm:px-10 lg:pt-10 xl:px-16`}>
                        <StepBar currentIndex={currentIndex} />

                        <div className="mt-6 lg:mt-0">
                            {onCardStep ? (
                                <>
                                    <h1 className="font-heading text-3xl font-bold tracking-tight text-brand-text sm:text-4xl">Now, your loyalty card</h1>
                                    <p className="mt-3 text-base text-brand-muted">Three quick choices. The preview updates as you go.</p>
                                </>
                            ) : (
                                <>
                                    <h1 className="font-heading text-3xl font-bold tracking-tight text-brand-text sm:text-4xl">
                                        {firstName ? `Welcome, ${firstName}.` : 'Welcome.'} Let's set up your shop
                                    </h1>
                                    <p className="mt-3 text-base text-brand-muted">First, a few details about your business.</p>
                                </>
                            )}
                        </div>

                        <div className="mt-10 h-px bg-brand-border" />

                        {!onCardStep && (
                            <form onSubmit={submitBusiness} noValidate className="pt-9">
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
                                        <AuthField id="contact_name" label="Contact person" error={errors.contact_name}>
                                            <input {...field('contact_name')} autoComplete="name" placeholder="Jamie Smith" />
                                        </AuthField>
                                        <AuthField id="contact_phone" label="Contact number" error={errors.contact_phone} hint="Mobile or landline.">
                                            <input {...field('contact_phone')} type="tel" inputMode="tel" autoComplete="tel" placeholder="07700 900123" />
                                        </AuthField>
                                    </div>

                                    <AuthField id="contact_email" label="Email" error={errors.contact_email}>
                                        <input {...field('contact_email')} type="email" autoComplete="email" placeholder="you@yourshop.co.uk" />
                                    </AuthField>
                                </Section>

                                <Section title="Location" description="Where customers find your shop.">
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
                                </Section>

                                <Section title="Delivery address" description="Where post for your shop should go.">
                                    <label className="flex cursor-pointer items-center gap-3 text-sm text-brand-text">
                                        <input
                                            type="checkbox"
                                            checked={data.delivery_same}
                                            onChange={(e) => handleDeliverySame(e.target.checked)}
                                            className="h-4 w-4 accent-[var(--color-brand-accent)]"
                                        />
                                        Same as the shop address
                                    </label>
                                    {!data.delivery_same && (
                                        <AuthField id="delivery_address" label="Delivery address" error={errors.delivery_address}>
                                            <textarea
                                                {...field('delivery_address')}
                                                rows={3}
                                                autoComplete="shipping street-address"
                                                placeholder={'Name / company\nStreet\nTown, postcode'}
                                                className={`${authInputClass} resize-y`}
                                            />
                                        </AuthField>
                                    )}
                                </Section>

                                <div className="flex flex-col-reverse gap-4 border-t border-brand-border pt-8 sm:flex-row sm:items-center sm:justify-between">
                                    <p className="text-center text-sm text-brand-muted sm:text-left">Next: your loyalty card</p>
                                    <button type="submit" disabled={processing} className={`${authButtonClass} sm:w-auto sm:px-8`}>
                                        {processing ? 'Checking…' : 'Continue'}
                                        {!processing && <LuArrowRight className="h-4 w-4" />}
                                    </button>
                                </div>
                            </form>
                        )}

                        {onCardStep && (
                            <form onSubmit={submitCard} noValidate className="pt-9">
                                <div className="grid gap-12 lg:grid-cols-[minmax(0,1fr)_15rem] lg:gap-10 xl:grid-cols-[minmax(0,1fr)_18rem] xl:gap-16">
                                    <div>
                                        <Question number={1} title="What's the reward?" description="What customers get once their card is full.">
                                            <AuthField id="reward_title" label={<span className="sr-only">Reward</span>} error={errors.reward_title}>
                                                <input
                                                    id="reward_title"
                                                    value={data.reward_title}
                                                    onChange={(e) => setData('reward_title', e.target.value)}
                                                    placeholder="e.g. Free coffee"
                                                    autoFocus
                                                    className={authInputClass}
                                                />
                                            </AuthField>
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                {REWARD_IDEAS.map((idea) => (
                                                    <button
                                                        key={idea}
                                                        type="button"
                                                        onClick={() => setData('reward_title', idea)}
                                                        className={`rounded-full border px-3.5 py-1.5 text-xs font-medium transition ${
                                                            data.reward_title === idea
                                                                ? 'border-brand-accent bg-brand-accent/10 text-brand-text'
                                                                : 'border-brand-border text-brand-muted hover:border-brand-accent hover:text-brand-text'
                                                        }`}
                                                    >
                                                        {idea}
                                                    </button>
                                                ))}
                                            </div>
                                        </Question>

                                        <Question number={2} title="How many stamps to earn it?" description="One stamp per visit. Fewer stamps means the reward comes round sooner.">
                                            <StampPicker value={data.max_stamps} onChange={(v) => setData('max_stamps', v)} />
                                            <p className="mt-3 text-sm text-brand-muted">
                                                Customers get <span className="font-medium text-brand-text">{data.reward_title.trim() || 'the reward'}</span> on their{' '}
                                                {data.max_stamps}th visit.
                                            </p>
                                        </Question>

                                        <Question number={3} title="Your card link" description="The QR at your counter opens this page. It's filled in from your shop name; change it if you like.">
                                            <AuthField id="slug" label={<span className="sr-only">Card link</span>} error={errors.slug} hint="Letters, numbers and dashes.">
                                                <SlugInput id="slug" value={data.slug} onChange={(v) => setData('slug', v)} placeholder="the-coffee-corner" />
                                            </AuthField>
                                        </Question>
                                    </div>

                                    <aside className="lg:sticky lg:top-10 lg:self-start">
                                        {/* Centred under the questions on phones/tablets; its own column on desktop. */}
                                        <p className="mb-3 text-center text-xs font-semibold uppercase tracking-[0.18em] text-brand-muted lg:text-left">Live preview</p>
                                        <div className="mx-auto w-full max-w-xs lg:mx-0 lg:max-w-none">{preview}</div>
                                    </aside>
                                </div>

                                <div className="mt-12 flex flex-col-reverse gap-3 border-t border-brand-border pt-8 sm:flex-row sm:items-center sm:justify-between">
                                    <button
                                        type="button"
                                        onClick={() => goTo('business')}
                                        disabled={processing}
                                        className="inline-flex items-center justify-center gap-1.5 py-2.5 text-sm font-medium text-brand-muted transition hover:text-brand-text disabled:opacity-50"
                                    >
                                        <LuArrowLeft className="h-4 w-4" /> Back
                                    </button>
                                    <button type="submit" disabled={processing} className={`${authButtonClass} sm:w-auto sm:px-8`}>
                                        {processing ? 'Creating your shop…' : 'Create my shop'}
                                        {!processing && <LuArrowRight className="h-4 w-4" />}
                                    </button>
                                </div>
                            </form>
                        )}
                    </main>
                </div>
            </div>
        </>
    );
}
