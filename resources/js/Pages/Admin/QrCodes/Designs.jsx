import { Link, router } from '@inertiajs/react';
import { LuArrowLeft, LuImagePlus, LuPencil, LuPlus, LuTrash2 } from 'react-icons/lu';
import { useConfirm } from '@/Components/ConfirmDialog';
import AdminLayout from '@/Components/Dashboard/AdminLayout';
import { EmptyState, primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';
import QrDesignStage from '@/Components/QrDesignStage';
import { designLayout } from '@/lib/qrPrint';
import { useQrPreview } from '@/lib/qrRender';
import { blockAspect } from '@/lib/qrStyle';

function DesignCard({ design, confirm }) {
    const qrSrc = useQrPreview(design.style, design.logo_url, 240);
    const layout = designLayout(design);

    async function remove() {
        const ok = await confirm({
            title: `Delete “${design.name}”?`,
            message: 'Stickers you’ve already printed with it aren’t affected.',
            confirmLabel: 'Delete design',
            danger: true,
        });
        if (ok) router.delete(`/admin/qr-codes/designs/${design.id}`, { preserveScroll: true });
    }

    return (
        <li className="group flex flex-col overflow-hidden rounded-2xl border border-brand-border bg-brand-card">
            <Link href={`/admin/qr-codes/designs/${design.id}/edit`} className="flex flex-1 items-center justify-center bg-brand-bg p-5">
                <div className="w-full max-w-60">
                    <QrDesignStage
                        imageUrl={design.image_url}
                        aspect={design.image_height / design.image_width}
                        block={blockAspect(design.style)}
                        qr={{ x: design.qr_x, y: design.qr_y, size: design.qr_size }}
                        qrSrc={qrSrc}
                        serialStyle={design.style}
                        className="rounded-md"
                    />
                </div>
            </Link>
            <div className="flex items-center gap-3 border-t border-brand-border px-4 py-3">
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-brand-text">{design.name}</p>
                    <p className="text-xs text-brand-muted">
                        {design.preset && <span className="font-semibold capitalize text-brand-text">{design.preset} · </span>}
                        {Math.round(layout.w)} × {Math.round(layout.h)} mm · {layout.perPage} per A4
                    </p>
                </div>
                <Link href={`/admin/qr-codes/designs/${design.id}/edit`} aria-label={`Edit ${design.name}`} className="rounded-lg p-2 text-brand-muted hover:bg-brand-bg hover:text-brand-text">
                    <LuPencil className="h-4 w-4" />
                </Link>
                <button type="button" onClick={remove} aria-label={`Delete ${design.name}`} className="rounded-lg p-2 text-brand-muted hover:bg-red-50 hover:text-red-600">
                    <LuTrash2 className="h-4 w-4" />
                </button>
            </div>
        </li>
    );
}

export default function Designs({ designs }) {
    const [confirm, confirmDialog] = useConfirm();

    return (
        <AdminLayout
            title="Sticker designs"
            description="Your artwork with a styled QR placed on it. Pick one under “Print with” on the QR codes page."
            actions={
                <div className="flex flex-wrap gap-2">
                    <Link href="/admin/qr-codes" className={secondaryButton}>
                        <LuArrowLeft className="h-4 w-4" /> QR codes
                    </Link>
                    <Link href="/admin/qr-codes/designs/create" className={primaryButton}>
                        <LuPlus className="h-4 w-4" /> New design
                    </Link>
                </div>
            }
        >
            {designs.length === 0 ? (
                <div className="rounded-2xl border border-brand-border bg-brand-card">
                    <EmptyState icon={LuImagePlus} title="No designs yet">
                        <p>Upload your artwork, place the QR on it and style it. Printing without a design gives the plain QR sheet.</p>
                        <Link href="/admin/qr-codes/designs/create" className={`${primaryButton} mt-4`}>
                            <LuPlus className="h-4 w-4" /> Create your first design
                        </Link>
                    </EmptyState>
                </div>
            ) : (
                <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    {designs.map((design) => (
                        <DesignCard key={design.id} design={design} confirm={confirm} />
                    ))}
                </ul>
            )}
            {confirmDialog}
        </AdminLayout>
    );
}
