/**
 * Country picker for a shop's location (App\Support\Countries::options():
 * the UK first, then A-Z). A native <select>, so phones get their own picker
 * and typing a letter jumps through the list.
 */
export default function CountrySelect({ id = "country", value, onChange, countries, className = "" }) {
    return (
        <select id={id} value={value} onChange={(e) => onChange(e.target.value)} autoComplete="country" className={className}>
            {countries.map((c) => (
                <option key={c.code} value={c.code}>
                    {c.name}
                </option>
            ))}
        </select>
    );
}
