import {
    LuUserRound,
    LuActivity,
    LuCircle,
    LuExternalLink,
    LuGem,
    LuGlobe,
    LuLayoutDashboard,
    LuLightbulb,
    LuListTodo,
    LuLogs,
    LuSparkles,
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
    ai: LuSparkles,
    customers: LuUsers,
    external: LuExternalLink,
    'follow-ups': LuListTodo,
    insights: LuLightbulb,
    landing: LuGlobe,
    logs: LuLogs,
    menu: LuUtensils,
    orders: LuPackage,
    overview: LuLayoutDashboard,
    plans: LuGem,
    qr: LuQrCode,
    reviews: LuStar,
    settings: LuSettings,
    shops: LuStore,
    users: LuUserRound,
    staff: LuUserCog,
    theme: LuPalette,
    whatsapp: FaWhatsapp,
};

export function navIcon(key) {
    return NAV_ICONS[key] ?? LuCircle;
}
