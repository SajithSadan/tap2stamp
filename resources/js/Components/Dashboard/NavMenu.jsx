import { Link, usePage } from '@inertiajs/react';
import { Fragment } from 'react';
import { sideLinkClass, tabLinkClass } from '@/Components/Dashboard/Ui';
import { navIcon } from '@/lib/navIcons';

// Renders the menu built by App\Support\Navigation (the shared `navigation`
// prop). Layouts never hard-code their links - add/remove pages and decide
// who sees them in that one PHP file.

export function useNavigation() {
    const nav = usePage().props.navigation;

    return { main: nav?.main ?? [], footer: nav?.footer ?? [] };
}

function NavLink({ item, className, iconClass }) {
    const Icon = navIcon(item.icon);

    if (item.external) {
        return (
            <a href={item.href} target="_blank" rel="noopener noreferrer" className={className}>
                <Icon className={iconClass} />
                {item.label}
            </a>
        );
    }

    return (
        <Link href={item.href} className={className} aria-current={item.active ? 'page' : undefined}>
            <Icon className={iconClass} />
            {item.label}
        </Link>
    );
}

/** Desktop sidebar links, with a small heading whenever an item starts a new `group`. */
export function SidebarLinks({ items }) {
    return items.map((item, i) => (
        <Fragment key={item.href}>
            {item.group && item.group !== items[i - 1]?.group && (
                <p className="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-nav-text/40 first:pt-0">{item.group}</p>
            )}
            <NavLink item={item} className={sideLinkClass(item.active)} iconClass="h-[18px] w-[18px]" />
        </Fragment>
    ));
}

/** Mobile bottom tab links. `scroll` lets long menus scroll sideways instead of squashing. */
export function TabLinks({ items, scroll = false }) {
    return items.map((item) => (
        <NavLink key={item.href} item={item} className={`flex-1 ${scroll ? 'min-w-16 shrink-0' : ''} ${tabLinkClass(item.active)}`} iconClass="h-5 w-5" />
    ));
}
