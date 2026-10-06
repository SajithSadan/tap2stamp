import AdminLayout from "@/Components/Dashboard/AdminLayout";
import MenuEditor from "@/Components/Menu/MenuEditor";

/** The admin editing any shop's menu (Admin → shop settings → Menu). */
export default function Edit(props) {
    return <MenuEditor layout={AdminLayout} isAdmin {...props} />;
}
