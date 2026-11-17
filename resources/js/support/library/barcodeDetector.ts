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
 */
async function polyfillDetector(): Promise<BookBarcodeDetector> {
  const [{ BarcodeDetector, setZXingModuleOverrides }, { default: wasmUrl }] = await Promise.all([
    import('barcode-detector/ponyfill'),
    import('zxing-wasm/reader/zxing_reader.wasm?url'),
  ])

  setZXingModuleOverrides({
    locateFile: (path: string, prefix: string) => (path.endsWith('.wasm') ? wasmUrl : prefix + path),
  })

  return new BarcodeDetector({ formats: FORMATS })
}

/** A detector of EAN-13 barcodes: the browser's own when it has one, else the polyfill. */
export async function createBookBarcodeDetector(): Promise<BookBarcodeDetector> {
  return (await nativeDetector()) ?? polyfillDetector()
}
