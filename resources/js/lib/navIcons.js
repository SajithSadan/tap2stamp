import {
    LuActivity,
    LuCircle,
    LuExternalLink,
    LuGlobe,
    LuLayoutDashboard,
    LuLightbulb,
    LuLogs,
    LuPackage,
    LuPalette,
    LuPlus,
    LuQrCode,
    LuSettings,
    LuStar,
    LuStore,
    LuUserCog,
    LuUsers,
    LuUtensils,
} from 'react-icons/lu';
import { FaWhatsapp } from 'react-icons/fa6';

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
    landing: LuGlobe,
    logs: LuLogs,
    menu: LuUtensils,
    orders: LuPackage,
    overview: LuLayoutDashboard,
    qr: LuQrCode,
    reviews: LuStar,
    settings: LuSettings,
    shops: LuStore,
    staff: LuUserCog,
    theme: LuPalette,
    whatsapp: FaWhatsapp,
};

export function navIcon(key) {
    return NAV_ICONS[key] ?? LuCircle;
}
