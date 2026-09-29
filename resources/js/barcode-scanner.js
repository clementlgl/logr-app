/**
 * Camera barcode scanner for Alpine components.
 *
 * Uses the native BarcodeDetector API when available (Chrome/Android) and
 * falls back to ZXing (Safari/iOS, Firefox), which is lazy-loaded on first use.
 *
 * Usage: x-data="barcodeScanner(code => $wire.scanBarcode(code))"
 */
const FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e'];

window.barcodeScanner = (onDetect) => ({
    open: false,
    error: '',
    manualCode: '',
    stream: null,
    controls: null,
    detecting: false,

    async start() {
        this.open = true;
        this.error = '';
        this.manualCode = '';

        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            this.error = 'Camera access requires HTTPS. Type the barcode below instead.';
            return;
        }

        await this.$nextTick();
        const video = this.$refs.video;

        try {
            if (await this.hasNativeDetector()) {
                await this.startNative(video);
            } else {
                await this.startZxing(video);
            }
        } catch (e) {
            this.error = e?.name === 'NotAllowedError'
                ? 'Camera permission denied. Type the barcode below instead.'
                : 'Could not start the camera. Type the barcode below instead.';
            this.stop();
        }
    },

    async hasNativeDetector() {
        if (!('BarcodeDetector' in window)) return false;
        const supported = await window.BarcodeDetector.getSupportedFormats();
        return FORMATS.some(f => supported.includes(f));
    },

    async startNative(video) {
        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        video.srcObject = this.stream;
        await video.play();

        const detector = new window.BarcodeDetector({ formats: FORMATS });
        this.detecting = true;

        const tick = async () => {
            if (!this.detecting) return;
            try {
                const codes = await detector.detect(video);
                if (codes.length) {
                    this.found(codes[0].rawValue);
                    return;
                }
            } catch (e) {
                // Frame not ready yet, retry on next tick
            }
            setTimeout(tick, 150);
        };
        tick();
    },

    async startZxing(video) {
        const [{ BrowserMultiFormatReader }, { BarcodeFormat, DecodeHintType }] = await Promise.all([
            import('@zxing/browser'),
            import('@zxing/library'),
        ]);

        const hints = new Map();
        hints.set(DecodeHintType.POSSIBLE_FORMATS, [
            BarcodeFormat.EAN_13, BarcodeFormat.EAN_8, BarcodeFormat.UPC_A, BarcodeFormat.UPC_E,
        ]);

        const reader = new BrowserMultiFormatReader(hints);
        this.controls = await reader.decodeFromConstraints(
            { video: { facingMode: 'environment' } },
            video,
            (result) => {
                if (result) this.found(result.getText());
            },
        );
    },

    found(code) {
        if (!this.open) return;
        this.stop();
        this.open = false;
        onDetect(code);
    },

    submitManual() {
        const code = this.manualCode.replace(/\D/g, '');
        if (code.length < 8) {
            this.error = 'A barcode has 8 to 14 digits.';
            return;
        }
        this.found(code);
    },

    stop() {
        this.detecting = false;
        this.controls?.stop();
        this.controls = null;
        this.stream?.getTracks().forEach(t => t.stop());
        this.stream = null;
    },

    close() {
        this.stop();
        this.open = false;
    },

    destroy() {
        this.stop();
    },
});
