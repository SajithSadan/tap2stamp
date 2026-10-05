import {
    LuActivity,
    LuCircle,
    LuExternalLink,
    LuLayoutDashboard,
    LuLightbulb,
    LuPackage,
    LuPalette,
    LuPlus,
    LuQrCode,
    LuSettings,
    LuStar,
    LuStore,
    LuUserCog,
    LuUsers,
} from 'react-icons/lu';

/**
 * Icon for each `icon` key used in App\Support\Navigation - keep the two in
 * sync when adding a menu item. Unknown keys fall back to a plain dot.
 */
const NAV_ICONS = {
    activity: LuActivity,
    add: LuPlus,
    customers: LuUsers,
    external: LuExternalLink,
    insights: LuLightbulb,
    orders: LuPackage,
    overview: LuLayoutDashboard,
    qr: LuQrCode,
    reviews: LuStar,
    settings: LuSettings,
    shops: LuStore,
    staff: LuUserCog,
    theme: LuPalette,
};

export function navIcon(key) {
    return NAV_ICONS[key] ?? LuCircle;
}
