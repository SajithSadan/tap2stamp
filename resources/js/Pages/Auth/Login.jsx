import { Head, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '' });

    function handleSubmit(e) {
        e.preventDefault();
        post('/login');
    }

    return (
        <>
            <Head title="Log in" />
            <div className="flex min-h-screen items-center justify-center bg-brand-bg px-5">
                <div className="w-full max-w-sm rounded-brand border border-brand-border bg-brand-card p-6 shadow-sm">
                    <h1 className="font-heading text-xl font-bold text-brand-text">Log in</h1>
                    <p className="mt-1 text-sm text-brand-muted">Shop owner and admin access.</p>

                    <form onSubmit={handleSubmit} noValidate className="mt-5 space-y-3">
                        <div>
                            <label className="block text-xs font-medium text-brand-muted">Email</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="mt-1 w-full rounded border border-brand-border px-3 py-2 text-sm text-brand-text outline-none focus:border-brand-accent"
                                autoComplete="email"
                            />
                            {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-brand-muted">Password</label>
                            <input
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="mt-1 w-full rounded border border-brand-border px-3 py-2 text-sm text-brand-text outline-none focus:border-brand-accent"
                                autoComplete="current-password"
                            />
                            {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-brand bg-brand-accent px-4 py-2.5 text-sm font-semibold text-brand-accent-text disabled:opacity-50"
                        >
                            {processing ? 'Logging in…' : 'Log in'}
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
