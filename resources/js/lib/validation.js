// Small client-side rule helpers for useValidatedForm. Each rule is
// (value, data) => message | null. The server always validates again; these
// only save a round trip for the obvious mistakes, so keep the messages the
// same as the Form Request's.

const blank = (value) => value === null || value === undefined || String(value).trim() === '';

export const required = (message) => (value) => (blank(value) ? message : null);

/** Only checked when `when(data)` is true, e.g. a field shown by a tick box. */
export const requiredIf = (when, message) => (value, data) => (when(data) && blank(value) ? message : null);

export const email = (message = 'Enter a valid email address.') => (value) =>
    blank(value) || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value).trim()) ? null : message;

/** Skipped when empty - pair with required() if the field must be filled. */
export const matches = (test, message) => (value) => (blank(value) || test(String(value)) ? null : message);

// --- UK contact details, mirroring App\Support\ShopContact -------------------

/** Same normalisation as ShopContact::phone(): UK numbers to +44, +91 kept. */
export function normalisePhone(raw) {
    const n = String(raw).replace(/[\s\-().]/g, '');

    if (n.startsWith('+440')) return `+44${n.slice(4)}`;
    if (n.startsWith('+44') || n.startsWith('+91')) return n;
    if (n.startsWith('0044')) return `+44${n.slice(4).replace(/^0+/, '')}`;
    if (n.startsWith('44') && n.length >= 11) return `+${n}`;
    if (n.startsWith('0')) return `+44${n.slice(1)}`;
    return n;
}

export const isPhone = (value) => /^(\+44[1-9]\d{8,9}|\+91[6-9]\d{9})$/.test(normalisePhone(value));

/** UK postcode (any spacing/case) or an Indian 6-digit PIN code. */
export const isPostcode = (value) => /^([A-Z]{1,2}\d[A-Z\d]?\d[A-Z]{2}|\d{6})$/.test(String(value).replace(/\s+/g, '').toUpperCase());
