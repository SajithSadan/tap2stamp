const themes = {
    light: {
        text: 'text-slate-500',
        solutions: 'text-slate-600',
        fixedBg: 'bg-white/60 border-slate-200/60',
    },
    dark: {
        text: 'text-slate-400',
        solutions: 'text-slate-400',
        fixedBg: 'bg-slate-900/60 border-slate-700/60',
    },
    auto: {
        text: 'text-slate-500 dark:text-slate-400',
        solutions: 'text-slate-600 dark:text-slate-400',
        fixedBg: 'bg-white/60 border-slate-200/60 dark:bg-slate-900/60 dark:border-slate-700/60',
    },
    // Follows the shop's theme colours (light or dark) instead of fixed slate.
    brand: {
        text: 'text-brand-muted',
        solutions: 'text-brand-muted',
        fixedBg: 'bg-brand-card/60 border-brand-border/60',
    },
};

const sizes = {
    md: { text: 'text-xs sm:text-sm', logo: 'h-4 sm:h-5' },
    sm: { text: 'text-[11px]', logo: 'h-3.5' },
};

/**
 * Discreet "Developed by Techsa Solutions" credit.
 *
 * variant: 'static' flows with the content, 'fixed' docks to the bottom of
 * the viewport without blocking clicks on what's behind it.
 */
function TechsaFooter({ variant = 'static', hideOnMobile = false, theme = 'auto', size = 'md', className = '' }) {
    const t = themes[theme] ?? themes.auto;
    const s = sizes[size] ?? sizes.md;
    const isFixed = variant === 'fixed';

    const wrapper = [
        'flex justify-center',
        isFixed ? 'pointer-events-none fixed inset-x-0 bottom-0 z-10 pb-[max(0.5rem,env(safe-area-inset-bottom))]' : 'py-2',
        hideOnMobile ? 'hidden md:flex' : '',
        className,
    ].join(' ');

    return (
        <footer className={wrapper}>
            <a
                href="https://techsasolutions.com"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Visit Techsa Solutions website"
                className={[
                    `group pointer-events-auto inline-flex items-center gap-1.5 rounded-full px-3 py-1 transition-colors ${s.text}`,
                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500',
                    isFixed ? `border backdrop-blur-md ${t.fixedBg}` : '',
                    t.text,
                ].join(' ')}
            >
                <span>Developed by</span>
                <img
                    src="/images/techsa-logo.png"
                    alt="Techsa Solutions"
                    className={`${s.logo} w-auto transition duration-300 group-hover:scale-110 group-hover:brightness-110`}
                    onError={(e) => {
                        e.currentTarget.style.display = 'none';
                    }}
                />
                <span className="font-semibold tracking-wide transition duration-300 group-hover:drop-shadow-[0_0_6px_rgba(2,132,199,0.45)]">
                    <span className="text-[#0284c7]">TECH</span>
                    <span className="text-[#ec4899]">SA</span>
                </span>
                <span className={`italic ${t.solutions}`}>Solutions</span>
            </a>
        </footer>
    );
}

export default TechsaFooter;
