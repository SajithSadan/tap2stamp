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

// --- Shop contact details, mirroring App\Support\ShopContact -----------------
// The country picks the postcode rules: UK and India exactly, anywhere else
// optional.

export const UK = 'GB';
export const INDIA = 'IN';

// The phone is two fields: the dialling code (digits, e.g. "44", its own
// picker - PhoneField) and the number without it. The code picks the rules.

export const UK_CODE = '44';
export const INDIA_CODE = '91';

/** The country's dialling code ("GB" -> "44"). */
export const dialCodeOf = (country, countries = []) => countries.find((c) => c.code === country)?.dial ?? UK_CODE;

const isInternational = (raw) => {
    const s = String(raw).trim();
    return s.startsWith('+') || s.replace(/\D/g, '').startsWith('00');
};

/** The code typed in front of the number (+91… / 0091…) when it isn't `picked` - same as ShopContact::typedCode(). */
export function typedCode(raw, picked, countries = []) {
    if (!isInternational(raw)) return null;
    const digits = String(raw).replace(/\D/g, '').replace(/^0+/, '');
    if (digits.startsWith(picked)) return null;
    const codes = [...new Set(countries.map((c) => c.dial))].sort((a, b) => b.length - a.length);
    return codes.find((c) => digits.startsWith(c)) ?? null;
}

/** The number without its code, digits only - same as ShopContact::phone(). */
export function nationalNumber(raw, code = UK_CODE) {
    let n = String(raw).replace(/\D/g, '');
    if (isInternational(raw)) {
        n = n.replace(/^0+/, '');
        if (n.startsWith(code)) n = n.slice(code.length);
    }
    return code === '39' ? n : n.replace(/^0/, '');
}

const PHONE = { [UK_CODE]: /^[1-9]\d{8,9}$/, [INDIA_CODE]: /^[6-9]\d{9}$/ };

export const isPhone = (value, code = UK_CODE, countries = []) => {
    const effective = typedCode(value, code, countries) ?? code;
    return (PHONE[effective] ?? /^\d{4,14}$/).test(nationalNumber(value, effective));
};

/** Same wording as ShopContact::messages(). */
export function phoneMessage(code) {
    if (code === UK_CODE) return 'Enter a valid UK phone number, e.g. 020 7946 0000 or 07700 900123.';
    if (code === INDIA_CODE) return 'Enter a valid Indian mobile number, e.g. 98765 43210.';
    return 'Enter a valid phone number.';
}

export function phonePlaceholder(code) {
    if (code === UK_CODE) return '07700 900123';
    if (code === INDIA_CODE) return '98765 43210';
    return 'Phone number';
}

/** Only the UK and India have a postcode format we check; elsewhere it's optional. */
export const hasPostcodeRule = (country) => country === UK || country === INDIA;

export const isPostcode = (value, country = UK) => {
    const p = String(value).replace(/\s+/g, '').toUpperCase();
    if (country === UK) return /^[A-Z]{1,2}\d[A-Z\d]?\d[A-Z]{2}$/.test(p);
    if (country === INDIA) return /^\d{6}$/.test(p);
    return p.length <= 20;
};

export const postcodeMessage = (country) => (country === INDIA ? 'Enter a valid 6-digit PIN code.' : 'Enter a valid postcode, e.g. SW1A 1AA.');
