import { Link, useForm } from "@inertiajs/react";
import AuthShell, {
    AuthField,
    GoogleButton,
    OrDivider,
    PasswordInput,
    authButtonClass,
    authInputClass,
} from "@/Components/AuthShell";

export default function Login({ googleEnabled }) {
    const { data, setData, post, processing, errors } = useForm({
        email: "",
        password: "",
    });

    function handleSubmit(e) {
        e.preventDefault();
        post("/login");
    }

    return (
        <AuthShell
            title="Log in"
            heading="Welcome back"
            subheading="Log in to your shop dashboard."
            footer={
                <>
                    New to TaDa Tap?{" "}
                    <Link
                        href="/register"
                        className="font-semibold text-brand-accent hover:underline"
                    >
                        Start free
                    </Link>
                </>
            }
        >
            {googleEnabled && (
                <div className="space-y-6">
                    <GoogleButton label="Continue with Google" />
                    <OrDivider />
                </div>
            )}

            <form
                onSubmit={handleSubmit}
                noValidate
                className={`space-y-4 ${googleEnabled ? "mt-6" : ""}`}
            >
                <AuthField id="email" label="Email" error={errors.email}>
                    <input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData("email", e.target.value)}
                        autoComplete="email"
                        className={authInputClass}
                    />
                </AuthField>

                <AuthField
                    id="password"
                    label="Password"
                    error={errors.password}
                >
                    <PasswordInput
                        id="password"
                        value={data.password}
                        onChange={(v) => setData("password", v)}
                        autoComplete="current-password"
                    />
                </AuthField>

                <button
                    type="submit"
                    disabled={processing}
                    className={`${authButtonClass} mt-2`}
                >
                    {processing ? "Logging in…" : "Log in"}
                </button>
            </form>
        </AuthShell>
    );
}
