import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { LuArrowRight, LuPlay } from 'react-icons/lu';
import VideoDialog from '@/Components/VideoDialog';
import AuthShell, { AuthField, GoogleButton, OrDivider, PasswordInput, authButtonClass, authInputClass } from '@/Components/AuthShell';

/** "How to get started": the landing page's video, in a pop-up (only when one is set). */
function HowToStart({ videoId }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="group mb-6 flex w-full items-center gap-3 rounded-2xl border border-brand-border bg-brand-card p-2 pr-4 text-left transition hover:border-brand-accent"
            >
                <span className="relative h-14 w-24 shrink-0 overflow-hidden rounded-xl bg-brand-deep">
                    <img src={`https://i.ytimg.com/vi/${videoId}/mqdefault.jpg`} alt="" className="h-full w-full object-cover opacity-90" />
                    <span className="absolute inset-0 m-auto flex h-8 w-8 items-center justify-center rounded-full bg-brand-accent text-brand-accent-text shadow transition group-hover:scale-110">
                        <LuPlay className="ml-0.5 h-4 w-4 fill-current" />
                    </span>
                </span>
                <span className="min-w-0">
                    <span className="block text-sm font-semibold text-brand-text">How to get started</span>
                    <span className="block text-xs text-brand-muted">See how it works before you sign up</span>
                </span>
            </button>
            {open && <VideoDialog id={videoId} title="How to get started with Tada Tap" onClose={() => setOpen(false)} />}
        </>
    );
}

export default function Register({ googleEnabled, videoId = null }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e) {
        e.preventDefault();
        post('/register');
    }

    return (
        <AuthShell
            title="Start free"
            heading="Create your free account"
            subheading="Set up your shop's digital loyalty card in about two minutes."
            footer={
                <>
                    Already have an account?{' '}
                    <Link href="/login" className="font-semibold text-brand-accent hover:underline">
                        Log in
                    </Link>
                </>
            }
        >
            {videoId && <HowToStart videoId={videoId} />}

            {googleEnabled && (
                <div className="space-y-6">
                    <GoogleButton label="Sign up with Google" />
                    <OrDivider />
                </div>
            )}

            <form onSubmit={submit} noValidate className={`space-y-4 ${googleEnabled ? 'mt-6' : ''}`}>
                <AuthField id="name" label="Your name" error={errors.name}>
                    <input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        autoComplete="name"
                        placeholder="Jamie Smith"
                        className={authInputClass}
                    />
                </AuthField>

                <AuthField id="email" label="Email" error={errors.email}>
                    <input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="email"
                        placeholder="you@yourshop.co.uk"
                        className={authInputClass}
                    />
                </AuthField>

                <AuthField id="password" label="Password" error={errors.password} hint="At least 8 characters.">
                    <PasswordInput id="password" value={data.password} onChange={(v) => setData('password', v)} autoComplete="new-password" />
                </AuthField>

                <AuthField id="password_confirmation" label="Confirm password" error={errors.password_confirmation}>
                    <PasswordInput
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(v) => setData('password_confirmation', v)}
                        autoComplete="new-password"
                    />
                </AuthField>

                <button type="submit" disabled={processing} className={`${authButtonClass} mt-2`}>
                    {processing ? 'Creating account…' : 'Create account'}
                    {!processing && <LuArrowRight className="h-4 w-4" />}
                </button>
                <p className="text-center text-xs text-brand-muted">
                    By creating an account you agree to our{' '}
                    <a href="/terms" target="_blank" className="font-medium text-brand-accent hover:underline">
                        Terms
                    </a>{' '}
                    and{' '}
                    <a href="/privacy" target="_blank" className="font-medium text-brand-accent hover:underline">
                        Privacy Policy
                    </a>
                    . The free trial needs no payment details.
                </p>
            </form>
        </AuthShell>
    );
}
