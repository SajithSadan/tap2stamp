// Small generic line icons, not the real Google/Instagram marks - keeps
// tiles recognisable without reproducing trademarked logos.

export function StoreIcon(props) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" {...props}>
            <path d="M4 10v9a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-9" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M3 6l1.5-3h15L21 6" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M3 6a2 2 0 0 0 4 0 2 2 0 0 0 4 0 2 2 0 0 0 4 0 2 2 0 0 0 4 0 2 2 0 0 0 4 0" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M9 20v-5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function StarIcon(props) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" {...props}>
            <path d="M12 2.5l2.9 6.1 6.6.7-4.9 4.5 1.3 6.6L12 17.1l-5.9 3.3 1.3-6.6-4.9-4.5 6.6-.7z" />
        </svg>
    );
}

export function CameraIcon(props) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" {...props}>
            <rect x="3" y="6" width="18" height="14" rx="3" />
            <circle cx="12" cy="13" r="4" />
            <path d="M8 6l1.5-2h5L16 6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function WifiIcon(props) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" {...props}>
            <path d="M2 8.5a16 16 0 0 1 20 0" strokeLinecap="round" />
            <path d="M5.5 12.5a11 11 0 0 1 13 0" strokeLinecap="round" />
            <path d="M9 16.5a6 6 0 0 1 6 0" strokeLinecap="round" />
            <circle cx="12" cy="19.5" r="1.2" fill="currentColor" stroke="none" />
        </svg>
    );
}

export function SparkleIcon(props) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" {...props}>
            <path d="M12 2l1.8 5.2L19 9l-5.2 1.8L12 16l-1.8-5.2L5 9l5.2-1.8L12 2z" />
            <path d="M19 14l.9 2.6 2.6.9-2.6.9L19 21l-.9-2.6-2.6-.9 2.6-.9L19 14z" opacity="0.7" />
        </svg>
    );
}

export function CheckCircleIcon(props) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" {...props}>
            <circle cx="12" cy="12" r="9" />
            <path d="M8 12.5l2.5 2.5L16 9.5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function ArrowRightIcon(props) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" {...props}>
            <path d="M5 12h14M13 6l6 6-6 6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function ChevronDownIcon(props) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" {...props}>
            <path d="M6 9l6 6 6-6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function CardsIcon(props) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" {...props}>
            <rect x="3" y="7" width="14" height="14" rx="2.5" />
            <path d="M7 7V5.5A2.5 2.5 0 0 1 9.5 3h9A2.5 2.5 0 0 1 21 5.5v9a2.5 2.5 0 0 1-2.5 2.5H17" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}
