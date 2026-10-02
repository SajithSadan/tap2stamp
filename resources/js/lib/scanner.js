/**
 * Releases an html5-qrcode camera when a scanner closes. Safe at any moment:
 * - waits for a start that's still in progress, so a scanner closed while the
 *   camera opens doesn't leave it running;
 * - only stops a running scanner - stop() otherwise *throws* (synchronously,
 *   a plain string), and from a React effect cleanup that unmounts the whole
 *   app: a blank screen;
 * - swallows everything else (camera already gone, element removed).
 *
 * `started` is the promise from scanner.start(...).
 */
export function stopScanner(scanner, started) {
    Promise.resolve(started)
        .catch(() => {})
        .then(() => (scanner.isScanning ? scanner.stop() : null))
        .then(() => scanner.clear())
        .catch(() => {});
}
