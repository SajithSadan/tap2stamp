import { Head } from "@inertiajs/react";
import MenuView from "@/Components/MenuView";
import { useDocumentTheme } from "@/lib/theme";

/** The public menu page (/menu/{shop}), in the menu theme the admin chose. */
export default function Menu({ shop, sections, theme }) {
    useDocumentTheme(theme);

    return (
        <>
            <Head title={`${shop.name} · Menu`} />
            <MenuView shop={shop} sections={sections} theme={theme} />
        </>
    );
}
