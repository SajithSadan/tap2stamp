import { Link, useForm } from "@inertiajs/react";
import { LuMailCheck } from "react-icons/lu";
import AuthShell, { AuthField, authButtonClass, authInputClass } from "@/Components/AuthShell";

/** "Forgot password?": we email a link to choose a new one. */
export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({ email: "" });

    function handleSubmit(e) {
        e.preventDefault();
        post("/forgot-password", { preserveScroll: true });
    }

    return (
        <AuthShell
            title="Forgot password"
            heading="Forgot your password?"
            subheading="Enter the email you log in with and we'll send you a link to choose a new one."
            footer={
                <>
                    Remembered it?{" "}
                    <Link href="/login" className="font-semibold text-brand-accent hover:underline">
                        Log in
                    </Link>
                </>
            }
        >
            {status && (
                <p role="status" className="mb-5 flex items-start gap-2.5 rounded-xl bg-emerald-500/10 px-4 py-3 text-sm text-brand-text">
                    <LuMailCheck className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                    {status}
                </p>
            )}

            <form onSubmit={handleSubmit} noValidate className="space-y-4">
                <AuthField id="email" label="Email" error={errors.email}>
                    <input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData("email", e.target.value)}
                        autoComplete="email"
                        autoFocus
                        className={authInputClass}
                    />
                </AuthField>

                <button type="submit" disabled={processing} className={`${authButtonClass} mt-2`}>
                    {processing ? "Sending…" : status ? "Send the link again" : "Send reset link"}
                </button>
            </form>
        </AuthShell>
    );
}
