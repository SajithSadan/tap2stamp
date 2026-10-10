import { Link, useForm } from "@inertiajs/react";
import AuthShell, { AuthField, PasswordInput, authButtonClass, authInputClass } from "@/Components/AuthShell";

/** Opened from the emailed link: choose a new password, then you're signed in. */
export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: "",
        password_confirmation: "",
    });

    function handleSubmit(e) {
        e.preventDefault();
        post("/reset-password");
    }

    return (
        <AuthShell
            title="Choose a new password"
            heading="Choose a new password"
            subheading="At least 8 characters. You'll be logged in straight after."
            footer={
                <>
                    Link not working?{" "}
                    <Link href="/forgot-password" className="font-semibold text-brand-accent hover:underline">
                        Send a new one
                    </Link>
                </>
            }
        >
            <form onSubmit={handleSubmit} noValidate className="space-y-4">
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

                <AuthField id="password" label="New password" error={errors.password}>
                    <PasswordInput id="password" value={data.password} onChange={(v) => setData("password", v)} autoComplete="new-password" />
                </AuthField>

                <AuthField id="password_confirmation" label="Confirm new password" error={errors.password_confirmation}>
                    <PasswordInput
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(v) => setData("password_confirmation", v)}
                        autoComplete="new-password"
                    />
                </AuthField>

                <button type="submit" disabled={processing} className={`${authButtonClass} mt-2`}>
                    {processing ? "Saving…" : "Save password and log in"}
                </button>
            </form>
        </AuthShell>
    );
}
