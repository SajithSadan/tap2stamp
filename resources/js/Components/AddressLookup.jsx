import { usePage } from "@inertiajs/react";
import { useState } from "react";
import { LuCircleAlert, LuCircleCheck, LuLoaderCircle, LuSearch } from "react-icons/lu";

/**
 * "Find address": postcode + house number/name → fills the address fields
 * below it (which stay editable). Calls our own /address-lookup, which asks
 * findaddress.io server-side - the API key never reaches the browser.
 * Renders nothing when the lookup isn't configured (shared `addressLookup`).
 *
 * onFound({ address_line1, address_line2, town, postcode })
 */
export default function AddressLookup({ initialPostcode = "", onFound, inputClassName, buttonClassName }) {
    const { addressLookup } = usePage().props;
    const [postcode, setPostcode] = useState(initialPostcode);
    const [house, setHouse] = useState("");
    const [state, setState] = useState({ status: "idle", message: null });

    if (!addressLookup) return null;

    async function find() {
        if (!postcode.trim() || !house.trim()) {
            setState({ status: "error", message: "Enter the house number or name and the postcode." });
            return;
        }

        setState({ status: "loading", message: null });

        try {
            const { data } = await window.axios.get("/address-lookup", {
                params: { postcode, house },
                headers: { Accept: "application/json" },
            });
            onFound(data.address);
            setState(
                data.status === "partial"
                    ? { status: "partial", message: "We found part of the address - please check and complete it below." }
                    : { status: "found", message: "Address filled in below - check it looks right." },
            );
        } catch (error) {
            const body = error.response?.data;
            const message =
                body?.message && !body?.errors
                    ? body.message
                    : (body?.errors && Object.values(body.errors)[0]?.[0]) ||
                      (error.response?.status === 429
                          ? "Too many lookups - wait a minute, or type the address below."
                          : "Address lookup isn't working right now - please type the address below.");
            setState({ status: "error", message });
        }
    }

    // Enter in either box runs the lookup instead of submitting the whole form.
    const onKeyDown = (e) => {
        if (e.key === "Enter") {
            e.preventDefault();
            find();
        }
    };

    const loading = state.status === "loading";

    return (
        <div className="rounded-2xl border border-brand-border bg-brand-bg/60 p-4">
            <p className="text-sm font-semibold text-brand-text">Find your address</p>
            <p className="mt-0.5 text-xs text-brand-muted">UK only. Or just fill in the fields below.</p>

            {/* Two equal fields, button underneath: works in narrow form columns too. */}
            <div className="mt-3 grid grid-cols-2 gap-3">
                <label className="block min-w-0">
                    <span className="mb-1 block truncate text-xs font-medium text-brand-muted">House no. or name</span>
                    <input
                        value={house}
                        onChange={(e) => setHouse(e.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder="12"
                        autoComplete="off"
                        className={inputClassName}
                    />
                </label>
                <label className="block min-w-0">
                    <span className="mb-1 block truncate text-xs font-medium text-brand-muted">Postcode</span>
                    <input
                        value={postcode}
                        onChange={(e) => setPostcode(e.target.value.toUpperCase())}
                        onKeyDown={onKeyDown}
                        placeholder="LS1 4AP"
                        autoComplete="off"
                        autoCapitalize="characters"
                        className={inputClassName}
                    />
                </label>
                <button type="button" onClick={find} disabled={loading} className={`col-span-2 w-full ${buttonClassName}`}>
                    {loading ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <LuSearch className="h-4 w-4" />}
                    Find address
                </button>
            </div>

            {state.message && (
                <p
                    role={state.status === "error" ? "alert" : "status"}
                    className={`mt-3 flex items-start gap-1.5 text-xs font-medium ${
                        state.status === "error" ? "text-red-600" : state.status === "partial" ? "text-amber-700" : "text-emerald-700"
                    }`}
                >
                    {state.status === "error" ? (
                        <LuCircleAlert className="mt-px h-3.5 w-3.5 shrink-0" />
                    ) : (
                        <LuCircleCheck className="mt-px h-3.5 w-3.5 shrink-0" />
                    )}
                    {state.message}
                </p>
            )}
        </div>
    );
}
