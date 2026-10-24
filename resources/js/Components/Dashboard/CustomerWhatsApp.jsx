import { FaWhatsapp } from "react-icons/fa6";

/**
 * Opens a chat with a customer in the owner's own WhatsApp - nothing is sent
 * by us. `customer.whatsapp` = wa.me digits from the server (null = no button).
 */
export default function CustomerWhatsApp({ customer }) {
    if (!customer.whatsapp) return null;

    const text = `Hi ${customer.name.split(" ")[0]}, `;

    return (
        <a
            href={`https://wa.me/${customer.whatsapp}?text=${encodeURIComponent(text)}`}
            target="_blank"
            rel="noopener noreferrer"
            title={`WhatsApp ${customer.name}`}
            aria-label={`WhatsApp ${customer.name}`}
            className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[#25D366] hover:bg-[#25D366]/10"
        >
            <FaWhatsapp className="h-5 w-5" />
        </a>
    );
}
