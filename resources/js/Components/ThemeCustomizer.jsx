import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const COLOR_FIELDS = [
    ['page_bg', 'Page background'],
    ['card_bg', 'Card background'],
    ['text', 'Text'],
    ['muted', 'Muted text'],
    ['accent', 'Accent'],
    ['accent_text', 'Text on accent'],
    ['stamp_filled', 'Stamp (filled)'],
    ['stamp_empty', 'Stamp (empty)'],
    ['border', 'Border'],
];

function extractFontName(cssFontValue) {
    return cssFontValue.match(/'([^']+)'/)?.[1] ?? null;
}

function matchCuratedFont(cssFontValue, availableFonts) {
    const name = extractFontName(cssFontValue);
    return availableFonts.find((f) => f.name === name) ?? availableFonts[0];
}

function ColorField({ label, value, onChange }) {
    return (
        <label className="flex items-center justify-between gap-3">
            <span className="text-stone-600">{label}</span>
            <span className="flex items-center gap-2">
                <input
                    type="color"
                    value={value}
                    onChange={(e) => onChange(e.target.value.toUpperCase())}
                    className="h-8 w-8 cursor-pointer rounded border border-stone-300 bg-white p-0.5"
                />
                <input
                    type="text"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    maxLength={7}
                    className="w-24 rounded border border-stone-300 px-2 py-1 font-mono text-xs"
                />
            </span>
        </label>
    );
}

export default function ThemeCustomizer({
    theme,
    isCustom,
    slug,
    customThemeData,
    availableFonts,
    radiusPresets,
    onDraftChange,
}) {
    const headingMatch = customThemeData
        ? { name: customThemeData.heading_font_name, fallback: customThemeData.heading_font_fallback }
        : matchCuratedFont(theme.heading_font, availableFonts);
    const bodyMatch = customThemeData
        ? { name: customThemeData.body_font_name, fallback: customThemeData.body_font_fallback }
        : matchCuratedFont(theme.body_font, availableFonts);

    const form = useForm({
        name: isCustom ? theme.name : `${theme.name} (Custom)`,
        base_theme_slug: customThemeData?.base_theme_slug ?? slug,
        page_bg: theme.page_bg,
        card_bg: theme.card_bg,
        text: theme.text,
        muted: theme.muted,
        accent: theme.accent,
        accent_text: theme.accent_text,
        stamp_filled: theme.stamp_filled,
        stamp_empty: theme.stamp_empty,
        border: theme.border,
        heading_font_name: headingMatch.name,
        body_font_name: bodyMatch.name,
        radius: customThemeData?.radius ?? theme.radius,
        button_radius: customThemeData?.button_radius ?? theme.button_radius,
    });

    // Push every field change up so the mockup preview stays live.
    useEffect(() => {
        const headingFallback = availableFonts.find((f) => f.name === form.data.heading_font_name)?.fallback ?? 'sans-serif';
        const bodyFallback = availableFonts.find((f) => f.name === form.data.body_font_name)?.fallback ?? 'sans-serif';
        const families = [...new Set([form.data.heading_font_name, form.data.body_font_name])];

        onDraftChange({
            ...form.data,
            heading_font: `'${form.data.heading_font_name}', ${headingFallback}`,
            body_font: `'${form.data.body_font_name}', ${bodyFallback}`,
            google_fonts: families.map((f) => f.replace(/ /g, '+')).join('|'),
        });
        // form.data is a plain object recreated each change; stringify keeps
        // this effect from re-running on every unrelated parent render.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [JSON.stringify(form.data)]);

    function submit(event) {
        event.preventDefault();
        if (isCustom) {
            form.put(`/dev/custom-themes/${slug}`);
        } else {
            form.post('/dev/custom-themes');
        }
    }

    function destroy() {
        if (window.confirm('Delete this custom theme? This can\'t be undone.')) {
            form.delete(`/dev/custom-themes/${slug}`);
        }
    }

    return (
        <form
            onSubmit={submit}
            className="mt-4 space-y-4 rounded-xl border p-4 text-sm"
            style={{ background: theme.card_bg, borderColor: theme.border, color: theme.text }}
        >
            <div>
                <label className="block text-xs font-medium text-stone-600">Name</label>
                <input
                    type="text"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    className="mt-1 w-full rounded border border-stone-300 px-2 py-1.5 text-sm"
                />
                {form.errors.name && <p className="mt-1 text-xs text-red-600">{form.errors.name}</p>}
            </div>

            <div className="space-y-2">
                <p className="text-xs font-medium text-stone-600">Colors</p>
                {COLOR_FIELDS.map(([field, label]) => (
                    <ColorField
                        key={field}
                        label={label}
                        value={form.data[field]}
                        onChange={(value) => form.setData(field, value)}
                    />
                ))}
                {Object.entries(form.errors)
                    .filter(([field]) => COLOR_FIELDS.some(([f]) => f === field))
                    .map(([field, message]) => (
                        <p key={field} className="text-xs text-red-600">
                            {message}
                        </p>
                    ))}
            </div>

            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="block text-xs font-medium text-stone-600">Heading font</label>
                    <select
                        value={form.data.heading_font_name}
                        onChange={(e) => form.setData('heading_font_name', e.target.value)}
                        className="mt-1 w-full rounded border border-stone-300 px-2 py-1.5 text-sm"
                    >
                        {availableFonts.map((font) => (
                            <option key={font.name} value={font.name}>
                                {font.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-medium text-stone-600">Body font</label>
                    <select
                        value={form.data.body_font_name}
                        onChange={(e) => form.setData('body_font_name', e.target.value)}
                        className="mt-1 w-full rounded border border-stone-300 px-2 py-1.5 text-sm"
                    >
                        {availableFonts.map((font) => (
                            <option key={font.name} value={font.name}>
                                {font.name}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="block text-xs font-medium text-stone-600">Card corners</label>
                    <select
                        value={form.data.radius}
                        onChange={(e) => form.setData('radius', e.target.value)}
                        className="mt-1 w-full rounded border border-stone-300 px-2 py-1.5 text-sm"
                    >
                        {radiusPresets.map((preset) => (
                            <option key={preset} value={preset}>
                                {preset === '9999px' ? 'Pill' : preset}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-medium text-stone-600">Button corners</label>
                    <select
                        value={form.data.button_radius}
                        onChange={(e) => form.setData('button_radius', e.target.value)}
                        className="mt-1 w-full rounded border border-stone-300 px-2 py-1.5 text-sm"
                    >
                        {radiusPresets.map((preset) => (
                            <option key={preset} value={preset}>
                                {preset === '9999px' ? 'Pill' : preset}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="flex flex-wrap gap-2 pt-1">
                <button
                    type="submit"
                    disabled={form.processing}
                    className="rounded-full bg-stone-900 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50"
                >
                    {isCustom ? 'Update this theme' : 'Save as new custom theme'}
                </button>
                {isCustom && (
                    <button
                        type="button"
                        onClick={destroy}
                        disabled={form.processing}
                        className="rounded-full border border-red-300 px-4 py-2 text-xs font-semibold text-red-600 disabled:opacity-50"
                    >
                        Delete
                    </button>
                )}
            </div>
        </form>
    );
}
