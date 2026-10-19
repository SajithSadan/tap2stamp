import { usePage } from "@inertiajs/react";
import { FaWhatsapp } from "react-icons/fa6";

/**
 * Floating "Chat with us" (bottom right): opens a WhatsApp chat with Tada Tap
 * support (the shared `supportWhatsapp` = the "UK" number on the Contact
 * page, Admin → Settings → Legal), with a first line typed in - naming the
 * shop when an owner sends it. On the landing, sign-up / log-in, setup,
 * legal pages and the owner dashboard; never on customers' cards, the staff
 * app or the admin panel. `className`: extra offset, e.g. above a tab bar.
 */
export default function SupportChatButton({ shopName = null, className = "bottom-5" }) {
    const { supportWhatsapp, viewAs } = usePage().props;
    // No number, or an admin looking at a shop as its owner: nothing to show.
    if (!supportWhatsapp || viewAs) return null;

    const text = shopName ? `Hi Tada Tap, I'm from ${shopName} and I need some help with ` : "Hi Tada Tap, I have a question about ";

    return (
        <a
            href={`${supportWhatsapp}?text=${encodeURIComponent(text)}`}
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Chat with Tada Tap support on WhatsApp"
            className={`group fixed right-4 z-40 flex items-center gap-2 rounded-full bg-[#25D366] p-3.5 text-white shadow-lg shadow-black/20 transition hover:scale-105 hover:bg-[#1ebe5a] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#25D366]/40 sm:right-6 sm:py-3 sm:pl-3.5 sm:pr-5 ${className}`}
        >
            <FaWhatsapp className="h-6 w-6 shrink-0" />
            <span className="hidden text-sm font-semibold sm:inline">Chat with us</span>
        </a>
    );
}
