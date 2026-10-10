/**
 * State / union territory for countries whose addresses need one (India).
 * The list comes with each country from the server (Countries::STATES);
 * shown by the address forms only when the chosen country has one.
 */
export default function StateSelect({ id = "state", value, onChange, states, className }) {
    return (
        <select id={id} value={value ?? ""} onChange={(e) => onChange(e.target.value)} autoComplete="address-level1" className={className}>
            <option value="">Choose state…</option>
            {states.map((state) => (
                <option key={state} value={state}>
                    {state}
                </option>
            ))}
        </select>
    );
}
