import { Link, usePage } from '@inertiajs/react';

export default function AppHeader({ title }) {
    const { auth } = usePage().props;

    return (
        <div className="flex items-center justify-between border-b border-brand-border bg-brand-card px-5 py-4">
            <div>
                <p className="font-heading text-lg font-bold text-brand-text">{title}</p>
                {auth?.user && <p className="text-xs text-brand-muted">{auth.user.email}</p>}
            </div>
            <Link href="/logout" method="post" as="button" className="text-sm font-medium text-brand-accent">
                Log out
            </Link>
        </div>
    );
}
