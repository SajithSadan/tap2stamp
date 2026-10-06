import OwnerLayout from "@/Components/Dashboard/OwnerLayout";
import MenuEditor from "@/Components/Menu/MenuEditor";

/** The owner editing their own shop's menu - the admin's editor. */
export default function Menu(props) {
    return <MenuEditor layout={OwnerLayout} {...props} />;
}
