// localStorage keys shared across pages - kept in one place so nothing
// drifts out of sync on the key names.
export const CUSTOMER_UUID_KEY = 'loyalty_uuid';
export const LAST_SHOP_SLUG_KEY = 'loyalty_last_shop_slug';

// Staff device bearer token (Staff/Setup.jsx writes it, Staff/Dashboard.jsx
// reads it). Deliberately localStorage, never a cookie - see Stage 5 notes
// in CLAUDE.md.
export const STAFF_TOKEN_KEY = 'staff_token';

// A random id this browser keeps, sent with shop codes typed on the staff app's
// "Set up this device" screen: 3 wrong codes a day lock it (ShopCodeJoin).
export const STAFF_BROWSER_ID_KEY = 'staff_browser_id';
