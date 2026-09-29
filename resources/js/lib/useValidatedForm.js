import { useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/** How long field errors stay on screen before they clear themselves. */
export const ERROR_TTL_MS = 6000;

/** Scrolls to and focuses whichever errored field comes first on the page (matched by input id = field name). */
function focusFirstError(errors) {
    const first = Object.keys(errors)
        .map((key) => document.getElementById(key))
        .filter(Boolean)
        .sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1))[0];

    if (!first) return;
    first.scrollIntoView({ block: 'center', behavior: 'smooth' });
    first.focus({ preventScroll: true });
}

/**
 * Inertia's useForm plus:
 *  - client-side `rules` ({ field: [rule, ...] }, see lib/validation.js),
 *    checked before anything is sent, so an obvious mistake never costs a
 *    request or a jump to the top of the page;
 *  - errors (client or server) that clear themselves after `expireAfter` ms,
 *    and as soon as the owner edits that field;
 *  - the first errored field is scrolled into view and focused;
 *  - scroll is kept on submit (the server answer is a redirect back).
 *
 * Pass `rememberKey` to keep the values across a page reload.
 *
 * `submit(method, url, { fields: [...], ...visitOptions })` validates only
 * the listed fields - handy for multi-step forms.
 */
export default function useValidatedForm(initial, { rules = {}, expireAfter = ERROR_TTL_MS, rememberKey } = {}) {
    // With a rememberKey, Inertia keeps the values in history state, so they survive a reload.
    const form = useForm(...(rememberKey ? [rememberKey, initial] : [initial]));
    const { data, errors, clearErrors } = form;

    // Every change to the error set restarts one timer; when it runs out, whatever is left is cleared.
    const timer = useRef(null);
    useEffect(() => {
        clearTimeout(timer.current);
        if (Object.keys(errors).length > 0) timer.current = setTimeout(() => clearErrors(), expireAfter);
        return () => clearTimeout(timer.current);
    }, [errors, expireAfter, clearErrors]);

    /** Same signatures as useForm's setData; also drops the error of any field that changed. */
    function setData(keyOrData, value) {
        const next = typeof keyOrData === 'string' ? { [keyOrData]: value } : typeof keyOrData === 'function' ? keyOrData(data) : keyOrData;
        const fixed = Object.keys(next).filter((key) => errors[key] && next[key] !== data[key]);
        if (fixed.length) clearErrors(...fixed);

        if (typeof keyOrData === 'string') form.setData(keyOrData, value);
        else form.setData(keyOrData);
    }

    /** Runs the rules for `fields` (default: all). Returns {} when everything passes. */
    function check(fields = Object.keys(rules)) {
        const found = {};
        for (const key of fields) {
            for (const rule of rules[key] ?? []) {
                const message = rule(data[key], data);
                if (message) {
                    found[key] = message;
                    break;
                }
            }
        }
        return found;
    }

    function submit(method, url, { fields, ...options } = {}) {
        const found = check(fields);
        if (Object.keys(found).length > 0) {
            form.clearErrors();
            form.setError(found);
            focusFirstError(found);
            return;
        }

        form.submit(method, url, {
            preserveScroll: true,
            ...options,
            onError: (serverErrors) => {
                options.onError?.(serverErrors);
                // After the page's own onError (which may switch step) has rendered.
                requestAnimationFrame(() => focusFirstError(serverErrors));
            },
        });
    }

    return {
        ...form,
        setData,
        check,
        submit,
        post: (url, options) => submit('post', url, options),
        put: (url, options) => submit('put', url, options),
        patch: (url, options) => submit('patch', url, options),
    };
}
