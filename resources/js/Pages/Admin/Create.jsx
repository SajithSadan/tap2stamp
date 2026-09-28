import { Link, useForm } from '@inertiajs/react';
import { LuArrowLeft, LuKeyRound, LuStore, LuUser } from 'react-icons/lu';
import AdminLayout from '@/Components/Dashboard/AdminLayout';
import { CardPreview, MAX_STAMPS, MIN_STAMPS, SlugInput, StampStepper, slugify } from '@/Components/Dashboard/ShopFields';
import { FieldError, Panel, inputClass, primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';

function Field({ id, label, hint, error, children }) {
    return (
        <div>
            <label htmlFor={id} className="block text-sm font-medium text-brand-text">
                {label}
            </label>
            <div className="mt-1.5">{children}</div>
            {hint && !error && <p className="mt-1 text-xs text-brand-muted">{hint}</p>}
            <FieldError message={error} />
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
        <AdminLayout
            title="Add shop"
            description="Creates the shop and its owner login together."
            actions={
                <Link href="/admin" className={secondaryButton}>
                    <LuArrowLeft className="h-4 w-4" /> Back to shops
                </Link>
            }
        >
            <form onSubmit={handleSubmit} noValidate className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
                {/* Very wide screens: Shop and Owner side by side instead of over-long inputs. */}
                <div className="grid gap-6 2xl:grid-cols-2 2xl:items-start">
                    <Panel title="Shop" description="What customers see on their card.">
                        <div className="space-y-4">
                            <Field id="shop_name" label="Shop name" error={errors.shop_name}>
                                <input
                                    id="shop_name"
                                    value={data.shop_name}
                                    onChange={(e) => handleShopNameChange(e.target.value)}
                                    placeholder="e.g. Corner Bakery"
                                    className={inputClass}
                                />
                            </Field>

                            <Field id="shop_slug" label="Card link" hint="Lowercase letters, numbers and dashes. Can't be shared with another shop." error={errors.shop_slug}>
                                <SlugInput id="shop_slug" value={data.shop_slug} onChange={(v) => setData('shop_slug', v)} placeholder="corner-bakery" />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-[auto_minmax(0,1fr)]">
                                <Field id="shop_max_stamps" label="Stamps needed" hint={`${MIN_STAMPS}–${MAX_STAMPS}`} error={errors.shop_max_stamps}>
                                    <StampStepper id="shop_max_stamps" value={data.shop_max_stamps} onChange={(v) => setData('shop_max_stamps', v)} />
                                </Field>

                                <Field id="shop_reward_title" label="Reward" error={errors.shop_reward_title}>
                                    <input
                                        id="shop_reward_title"
                                        value={data.shop_reward_title}
                                        onChange={(e) => setData('shop_reward_title', e.target.value)}
                                        placeholder={`Free coffee after ${data.shop_max_stamps} stamps`}
                                        className={inputClass}
                                    />
                                </Field>
                            </div>
                        </div>
                    </Panel>

                    <Panel title="Owner login" description="The person who runs this shop's dashboard.">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field id="owner_name" label="Owner name" error={errors.owner_name}>
                                <input
                                    id="owner_name"
                                    value={data.owner_name}
                                    onChange={(e) => setData('owner_name', e.target.value)}
                                    autoComplete="off"
                                    className={inputClass}
                                />
                            </Field>
                            <Field id="owner_email" label="Owner email" error={errors.owner_email}>
                                <input
                                    id="owner_email"
                                    type="email"
                                    value={data.owner_email}
                                    onChange={(e) => setData('owner_email', e.target.value)}
                                    autoComplete="off"
                                    className={inputClass}
                                />
                            </Field>
                        </div>

                        <div className="mt-4 flex items-start gap-2.5 rounded-xl bg-brand-bg px-3.5 py-3 text-sm text-brand-muted">
                            <LuKeyRound className="mt-0.5 h-4 w-4 shrink-0 text-brand-accent" />
                            <p>A temporary password is generated automatically and shown to you once after the shop is created.</p>
                        </div>
                    </Panel>
                </div>

                <aside className="space-y-4 lg:sticky lg:top-10">
                    <div>
                        <p className="mb-2 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-brand-muted">
                            <LuStore className="h-3.5 w-3.5" /> Card preview
                        </p>
                        <CardPreview name={data.shop_name} slug={data.shop_slug} maxStamps={data.shop_max_stamps} reward={data.shop_reward_title} />
                    </div>

                    <button type="submit" disabled={processing} className={`${primaryButton} w-full py-3`}>
                        <LuUser className="h-4 w-4" />
                        {processing ? 'Creating…' : 'Create shop & owner'}
                    </button>
                </aside>
            </form>
        </AdminLayout>
    );
}
