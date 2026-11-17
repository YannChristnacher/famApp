/** The part of the standard BarcodeDetector API used to read book barcodes. */
export interface DetectedBarcode {
  rawValue: string
}

export interface BookBarcodeDetector {
  detect(source: ImageBitmapSource): Promise<DetectedBarcode[]>
}

interface BarcodeDetectorConstructor {
  new (options: { formats: string[] }): BookBarcodeDetector
  getSupportedFormats(): Promise<string[]>
}

const FORMATS: ['ean_13'] = ['ean_13']

async function nativeDetector(): Promise<BookBarcodeDetector | null> {
  const Native = (globalThis as { BarcodeDetector?: BarcodeDetectorConstructor }).BarcodeDetector
  if (typeof Native !== 'function') {
    return null
  }

  try {
    const supported = await Native.getSupportedFormats()

    return supported.includes('ean_13') ? new Native({ formats: FORMATS }) : null
  } catch {
    return null
  }
}

/**
 * The polyfill (zxing-cpp in WebAssembly), loaded on demand. Its WebAssembly
 * file is served by our build: the library would fetch it from jsDelivr otherwise.
 * The module is loaded before the detector is created, because the polyfill's
 * constructor swallows a loading failure (each detect() would then reject).
 */
async function polyfillDetector(): Promise<BookBarcodeDetector> {
  const [{ BarcodeDetector, prepareZXingModule, purgeZXingModule }, { default: wasmUrl }] = await Promise.all([
    import('barcode-detector/ponyfill'),
    import('zxing-wasm/reader/zxing_reader.wasm?url'),
  ])

  try {
    await prepareZXingModule({
      overrides: {
        locateFile: (path: string, prefix: string) => (path.endsWith('.wasm') ? wasmUrl : prefix + path),
      },
      fireImmediately: true,
    })
  } catch (cause) {
    // Forgets the failed module, so that the next attempt loads it again.
    purgeZXingModule()
    throw cause
  }

  return new BarcodeDetector({ formats: FORMATS })
}

/**
 * A detect() failure that will not go away by retrying: the polyfill rejects
 * with NotSupportedError when its WebAssembly module is unavailable.
 */
export function isDetectorUnavailable(cause: unknown): boolean {
  return cause instanceof DOMException && cause.name === 'NotSupportedError'
}

/** A detector of EAN-13 barcodes: the browser's own when it has one, else the polyfill. */
export async function createBookBarcodeDetector(): Promise<BookBarcodeDetector> {
  return (await nativeDetector()) ?? polyfillDetector()
}
