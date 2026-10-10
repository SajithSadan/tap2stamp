import axios from "axios";
import { LuScanLine, LuX } from "react-icons/lu";
import CardScanner from "@/Components/CardScanner";

/**
 * The owner's "Scan customer card" overlay (OwnerLayout). The scanning itself
 * is the shared CardScanner - one scan at a time, "Scan next", and a full
 * card asks "Mark reward as given" before it resets.
 */
export default function OwnerScanner({ onClose }) {
    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-black" role="dialog" aria-modal="true" aria-label="Scan customer card">
            <header className="flex items-center justify-between bg-brand-deep px-4 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))] text-white">
                <div className="flex items-center gap-2">
                    <LuScanLine className="h-5 w-5 text-brand-accent" />
                    <h2 className="font-semibold">Scan customer card</h2>
                </div>
                <button type="button" onClick={onClose} aria-label="Close scanner" className="rounded-lg p-2 text-white/80 hover:bg-white/10">
                    <LuX className="h-5 w-5" />
                </button>
            </header>

            <CardScanner request={(payload, redeem) => axios.post("/dashboard/scan", { payload, ...(redeem && { redeem: true }) })} />
        </div>
    );
}
