export default function IconBadge({ children }) {
    return (
        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-accent/10 text-brand-accent">
            {children}
        </span>
    );
}
