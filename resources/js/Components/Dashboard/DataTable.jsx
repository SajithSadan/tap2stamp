import {
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from "@tanstack/react-table";
import { useEffect, useRef, useState } from "react";
import {
    LuArrowDown,
    LuArrowUp,
    LuArrowUpDown,
    LuChevronLeft,
    LuChevronRight,
    LuColumns3,
    LuDownload,
    LuFilterX,
    LuSearch,
    LuX,
} from "react-icons/lu";
import { inputClass, secondaryButton } from "@/Components/Dashboard/Ui";

/**
 * Data grid on TanStack Table (headless - the logic is theirs, the look is
 * ours): search, multi-column sort (shift-click), column show/hide, paging,
 * sticky header + first column, and CSV export of exactly the rows shown.
 *
 * Column `meta`:
 *   label   - name in the Columns menu and CSV header
 *   align   - 'right' for numbers
 *   stickyRight - keep this column visible at the right edge while scrolling horizontally
 *   csv     - (row) => value, or false to leave the column out of the CSV
 *
 * `storageKey` remembers column visibility + page size per browser (a
 * convenience - fine if storage is unavailable).
 */

function readStored(key) {
    try {
        return JSON.parse(window.localStorage.getItem(key) ?? "null");
    } catch {
        return null;
    }
}

function writeStored(key, value) {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Private mode / blocked storage - just don't remember.
    }
}

/** Spreadsheet-safe CSV cell: quoted, and formula-looking text neutralised. */
function csvCell(value) {
    let text = value === null || value === undefined ? "" : String(value);
    if (/^[=+\-@\t\r]/.test(text)) text = `'${text}`;

    return `"${text.replace(/"/g, '""')}"`;
}

function ColumnsMenu({ table }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        if (!open) return undefined;
        const close = (e) => !ref.current?.contains(e.target) && setOpen(false);
        const onKey = (e) => e.key === "Escape" && setOpen(false);
        document.addEventListener("mousedown", close);
        document.addEventListener("keydown", onKey);

        return () => {
            document.removeEventListener("mousedown", close);
            document.removeEventListener("keydown", onKey);
        };
    }, [open]);

    const hideable = table.getAllLeafColumns().filter((c) => c.getCanHide());

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                aria-expanded={open}
                className={`${secondaryButton} h-[42px]`}
            >
                <LuColumns3 className="h-4 w-4" />{" "}
                <span className="hidden sm:inline">Columns</span>
            </button>
            {open && (
                <div className="absolute right-0 z-30 mt-2 w-56 rounded-xl border border-brand-border bg-brand-card p-2 shadow-lg">
                    {hideable.map((column) => (
                        <label
                            key={column.id}
                            className="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-brand-text hover:bg-brand-bg"
                        >
                            <input
                                type="checkbox"
                                checked={column.getIsVisible()}
                                onChange={column.getToggleVisibilityHandler()}
                                className="h-4 w-4 accent-[var(--color-brand-accent)]"
                            />
                            {column.columnDef.meta?.label ?? column.id}
                        </label>
                    ))}
                    <button
                        type="button"
                        onClick={() => table.resetColumnVisibility()}
                        className="mt-1 w-full rounded-lg px-2.5 py-2 text-left text-xs font-medium text-brand-muted hover:bg-brand-bg hover:text-brand-text"
                    >
                        Reset to default
                    </button>
                </div>
            )}
        </div>
    );
}

function SortIcon({ state }) {
    if (state === "asc")
        return <LuArrowUp className="h-3.5 w-3.5 text-brand-text" />;
    if (state === "desc")
        return <LuArrowDown className="h-3.5 w-3.5 text-brand-text" />;

    return (
        <LuArrowUpDown className="h-3.5 w-3.5 opacity-0 transition-opacity group-hover:opacity-60" />
    );
}

export default function DataTable({
    data,
    columns,
    initialSorting = [],
    initialHidden = {},
    searchPlaceholder = "Search",
    toolbar = null,
    exportName = "export",
    storageKey = null,
    empty = null,
    onClearAll = null,
    filteredExternally = false, // e.g. status chips outside the table narrowed `data`
    pageSizes = [10, 25, 50, 100],
}) {
    const stored = storageKey ? readStored(storageKey) : null;

    const [sorting, setSorting] = useState(initialSorting);
    const [globalFilter, setGlobalFilter] = useState("");
    const [columnVisibility, setColumnVisibility] = useState(
        stored?.columns ?? initialHidden,
    );
    const [pagination, setPagination] = useState({
        pageIndex: 0,
        pageSize: stored?.pageSize ?? pageSizes[1] ?? pageSizes[0],
    });

    useEffect(() => {
        if (storageKey)
            writeStored(storageKey, {
                columns: columnVisibility,
                pageSize: pagination.pageSize,
            });
    }, [storageKey, columnVisibility, pagination.pageSize]);

    const table = useReactTable({
        data,
        columns,
        state: { sorting, globalFilter, columnVisibility, pagination },
        onSortingChange: setSorting,
        onGlobalFilterChange: setGlobalFilter,
        onColumnVisibilityChange: setColumnVisibility,
        onPaginationChange: setPagination,
        getCoreRowModel: getCoreRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        globalFilterFn: "includesString",
        autoResetPageIndex: true,
        enableMultiSort: true,
    });

    // New filters from outside (e.g. status chips) change `data` - go back to page 1.
    useEffect(() => setPagination((p) => ({ ...p, pageIndex: 0 })), [data]);

    const filteredRows = table.getPrePaginationRowModel().rows;
    const total = filteredRows.length;
    const { pageIndex, pageSize } = table.getState().pagination;
    const from = total === 0 ? 0 : pageIndex * pageSize + 1;
    const to = Math.min(total, (pageIndex + 1) * pageSize);
    const visibleColumns = table.getVisibleLeafColumns();
    const isFiltered = globalFilter !== "" || filteredExternally;

    // The visible width of the scroll box, so the "no rows" message centres
    // in what you see, not across a wider-than-screen table.
    const scrollBox = useRef(null);
    const [boxWidth, setBoxWidth] = useState(null);
    useEffect(() => {
        const box = scrollBox.current;
        if (!box || typeof ResizeObserver === "undefined") return undefined;
        const observer = new ResizeObserver(() => setBoxWidth(box.clientWidth));
        observer.observe(box);
        return () => observer.disconnect();
    }, []);

    function clearAll() {
        setGlobalFilter("");
        onClearAll?.();
    }

    function exportCsv() {
        const cols = visibleColumns.filter(
            (c) => c.columnDef.meta?.csv !== false,
        );
        const header = cols
            .map((c) => csvCell(c.columnDef.meta?.label ?? c.id))
            .join(",");
        const lines = filteredRows.map((row) =>
            cols
                .map((c) =>
                    csvCell(
                        typeof c.columnDef.meta?.csv === "function"
                            ? c.columnDef.meta.csv(row.original)
                            : row.getValue(c.id),
                    ),
                )
                .join(","),
        );

        const blob = new Blob([`﻿${[header, ...lines].join("\r\n")}`], {
            type: "text/csv;charset=utf-8",
        });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `${exportName}-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    }

    const alignClass = (column) =>
        column.columnDef.meta?.align === "right" ? "text-right" : "text-left";
    const stickyFirst = (i, bg) => (i === 0 ? `sticky left-0 ${bg} pl-5` : "");
    const stickyRight = (column, bg) =>
        column.columnDef.meta?.stickyRight
            ? `sticky right-0 ${bg} shadow-[-8px_0_10px_-10px_rgba(15,23,42,0.45)]`
            : "";

    return (
        <div className="min-w-0 rounded-2xl border border-brand-border bg-brand-card shadow-sm">
            {/* Toolbar */}
            <div className="flex flex-col gap-3 border-b border-brand-border px-4 py-3 sm:px-5 lg:flex-row lg:items-center">
                <div className="min-w-0 flex-1">{toolbar}</div>
                <div className="flex items-center gap-2">
                    <div className="relative min-w-0 flex-1 lg:w-64 lg:flex-none">
                        <LuSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-muted" />
                        {/* type="text", not "search": the browser's own clear button would double ours. */}
                        <input
                            type="text"
                            inputMode="search"
                            enterKeyHint="search"
                            role="searchbox"
                            value={globalFilter}
                            onChange={(e) => setGlobalFilter(e.target.value)}
                            placeholder={searchPlaceholder}
                            aria-label={searchPlaceholder}
                            className={`${inputClass} pl-9 pr-8`}
                        />
                        {globalFilter && (
                            <button
                                type="button"
                                onClick={() => setGlobalFilter("")}
                                aria-label="Clear search"
                                className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-brand-muted hover:text-brand-text"
                            >
                                <LuX className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>
                    <ColumnsMenu table={table} />
                    <button
                        type="button"
                        onClick={exportCsv}
                        disabled={total === 0}
                        className={`${secondaryButton} h-[42px]`}
                        title="Download the rows shown as CSV"
                    >
                        <LuDownload className="h-4 w-4" />{" "}
                        <span className="hidden sm:inline">CSV</span>
                    </button>
                </div>
            </div>

            {/* The table (and its headers) always stays, even with no matches. */}
            <div ref={scrollBox} className="max-h-[70vh] overflow-auto">
                <table className="w-full min-w-max border-separate border-spacing-0 text-sm">
                    <thead className="sticky top-0 z-10">
                        {table.getHeaderGroups().map((group) => (
                            <tr key={group.id}>
                                {group.headers.map((header, i) => {
                                    const canSort = header.column.getCanSort();
                                    const sorted = header.column.getIsSorted();

                                    return (
                                        <th
                                            key={header.id}
                                            scope="col"
                                            aria-sort={
                                                sorted === "asc"
                                                    ? "ascending"
                                                    : sorted === "desc"
                                                      ? "descending"
                                                      : undefined
                                            }
                                            className={`whitespace-nowrap border-b border-brand-border bg-brand-bg px-4 py-3 text-xs font-medium uppercase tracking-wide text-brand-muted ${alignClass(header.column)} ${stickyFirst(i, "z-20 bg-brand-bg")} ${stickyRight(header.column, "z-30 bg-brand-bg")}`}
                                        >
                                            {header.isPlaceholder ? null : canSort ? (
                                                <button
                                                    type="button"
                                                    onClick={header.column.getToggleSortingHandler()}
                                                    title="Sort (shift-click to add a second sort)"
                                                    className={`group inline-flex items-center gap-1 uppercase hover:text-brand-text ${
                                                        header.column.columnDef
                                                            .meta?.align ===
                                                        "right"
                                                            ? "flex-row-reverse"
                                                            : ""
                                                    } ${sorted ? "text-brand-text" : ""}`}
                                                >
                                                    {flexRender(
                                                        header.column.columnDef
                                                            .header,
                                                        header.getContext(),
                                                    )}
                                                    <SortIcon state={sorted} />
                                                </button>
                                            ) : (
                                                flexRender(
                                                    header.column.columnDef
                                                        .header,
                                                    header.getContext(),
                                                )
                                            )}
                                        </th>
                                    );
                                })}
                            </tr>
                        ))}
                    </thead>
                    <tbody>
                        {total === 0 ? (
                            <tr>
                                <td
                                    colSpan={visibleColumns.length}
                                    className="border-b border-brand-border"
                                >
                                    {/* Pinned to the visible width so the message sits centred even when the table scrolls sideways. */}
                                    <div
                                        className="sticky left-0 w-[min(100%,calc(100vw-4rem))]"
                                        style={boxWidth ? { width: boxWidth } : undefined}
                                    >
                                        {empty ?? (
                                            <p className="px-5 py-12 text-center text-sm text-brand-muted">
                                                No rows match.
                                            </p>
                                        )}
                                        {isFiltered && (
                                            <p className="-mt-6 pb-8 text-center">
                                                <button
                                                    type="button"
                                                    onClick={clearAll}
                                                    className={secondaryButton}
                                                >
                                                    <LuFilterX className="h-4 w-4" />{" "}
                                                    Clear search and filters
                                                </button>
                                            </p>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ) : (
                            table.getRowModel().rows.map((row) => (
                                <tr key={row.id} className="group/row">
                                    {row.getVisibleCells().map((cell, i) => (
                                        <td
                                            key={cell.id}
                                            className={`border-b border-brand-border px-4 py-3 align-middle transition-colors group-hover/row:bg-brand-bg ${alignClass(cell.column)} ${stickyFirst(
                                                i,
                                                "z-[5] bg-brand-card",
                                            )} ${stickyRight(cell.column, "z-10 bg-brand-card group-hover/row:bg-brand-bg")}`}
                                        >
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {/* Footer: count + paging */}
            <div className="flex flex-col gap-3 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <p className="text-brand-muted">
                    {total === 0
                        ? "No rows"
                        : `Showing ${from}–${to} of ${total}`}
                    {isFiltered &&
                        total !== data.length &&
                        ` (filtered from ${data.length})`}
                </p>
                <div className="flex items-center gap-3">
                    <label className="flex items-center gap-2 text-brand-muted">
                        Rows
                        <select
                            value={pageSize}
                            onChange={(e) =>
                                table.setPageSize(Number(e.target.value))
                            }
                            className="rounded-lg border border-brand-border bg-brand-card px-2 py-1 text-sm text-brand-text outline-none focus:border-brand-accent"
                        >
                            {pageSizes.map((size) => (
                                <option key={size} value={size}>
                                    {size}
                                </option>
                            ))}
                        </select>
                    </label>
                    <div className="flex items-center gap-1">
                        <button
                            type="button"
                            onClick={() => table.previousPage()}
                            disabled={!table.getCanPreviousPage()}
                            aria-label="Previous page"
                            className="rounded-lg p-1.5 text-brand-text hover:bg-brand-bg disabled:opacity-30"
                        >
                            <LuChevronLeft className="h-4 w-4" />
                        </button>
                        <span className="min-w-16 text-center tabular-nums text-brand-muted">
                            {table.getPageCount() === 0
                                ? "0 / 0"
                                : `${pageIndex + 1} / ${table.getPageCount()}`}
                        </span>
                        <button
                            type="button"
                            onClick={() => table.nextPage()}
                            disabled={!table.getCanNextPage()}
                            aria-label="Next page"
                            className="rounded-lg p-1.5 text-brand-text hover:bg-brand-bg disabled:opacity-30"
                        >
                            <LuChevronRight className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
