import { Link, useForm } from '@inertiajs/react';
import { LuArrowRight } from 'react-icons/lu';
import AuthShell, { AuthField, GoogleButton, OrDivider, PasswordInput, authButtonClass, authInputClass } from '@/Components/AuthShell';

export default function Register({ googleEnabled }) {
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
            </form>
        </AuthShell>
    );
}
