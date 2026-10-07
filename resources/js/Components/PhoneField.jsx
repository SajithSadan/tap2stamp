import { LuChevronDown } from "react-icons/lu";

/**
 * A phone number as two parts, saved separately (App\Support\ShopContact):
 * the dialling code - shown as "+44", picked from every country - and the
 * number without it. Wears the form's own input style (`inputClassName`).
 *
 * Several countries share a code (+1, +44, +7), so the picker's value is a
 * country; `country` (the shop's) is preferred when it has the code.
 */
export default function PhoneField({ id = "contact_phone", code, number, onCodeChange, onNumberChange, countries, country, placeholder, inputClassName = "" }) {
    const picked = countries.find((c) => c.code === country && c.dial === code) ?? countries.find((c) => c.dial === code);

    return (
        <div className="flex gap-2">
            {/* The native <select> sits invisibly on top: phones get their own picker, the box shows just "+44". */}
            <div className="relative w-24 shrink-0 [&:focus-within>div]:border-brand-accent [&:focus-within>div]:ring-4 [&:focus-within>div]:ring-brand-accent/10">
                <div className={`${inputClassName} flex items-center justify-between gap-1 tabular-nums`} aria-hidden="true">
                    <span>+{code}</span>
                    <LuChevronDown className="h-4 w-4 shrink-0 opacity-50" />
                </div>
                <select
                    id={`${id}_code`}
                    aria-label="Country code"
                    value={picked?.code ?? ""}
                    onChange={(e) => onCodeChange(countries.find((c) => c.code === e.target.value)?.dial ?? code)}
                    className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                >
                    {countries.map((c) => (
                        <option key={c.code} value={c.code}>
                            {c.name} (+{c.dial})
                        </option>
                    ))}
                </select>
            </div>
            <input
                id={id}
                type="tel"
                inputMode="tel"
                autoComplete="tel-national"
                value={number}
                onChange={(e) => onNumberChange(e.target.value)}
                placeholder={placeholder}
                className={`${inputClassName} min-w-0 flex-1`}
            />
        </div>
    );
}
