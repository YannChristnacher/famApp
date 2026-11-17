import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const ponyfill = vi.hoisted(() => ({
  constructed: [] as unknown[],
  construct: vi.fn(),
  setZXingModuleOverrides: vi.fn(),
}))

vi.mock('barcode-detector/ponyfill', () => ({
  BarcodeDetector: class {
    constructor(options: unknown) {
      ponyfill.constructed.push(options)
      ponyfill.construct()
    }

    detect() {
      return Promise.resolve([{ rawValue: 'from-ponyfill' }])
    }
  },
  setZXingModuleOverrides: ponyfill.setZXingModuleOverrides,
}))

vi.mock('zxing-wasm/reader/zxing_reader.wasm?url', () => ({ default: '/build/assets/zxing_reader-abc123.wasm' }))

function stubNativeDetector(formats: string[]) {
  const constructed: unknown[] = []
  class NativeBarcodeDetector {
    static getSupportedFormats = vi.fn(() => Promise.resolve(formats))

    constructor(options: unknown) {
      constructed.push(options)
    }

    detect() {
      return Promise.resolve([{ rawValue: 'from-native' }])
    }
  }
  vi.stubGlobal('BarcodeDetector', NativeBarcodeDetector)

  return constructed
}

async function create() {
  const { createBookBarcodeDetector } = await import('./barcodeDetector')

  return createBookBarcodeDetector()
}

describe('createBookBarcodeDetector', () => {
  beforeEach(() => {
    ponyfill.constructed.length = 0
    ponyfill.construct.mockClear()
    ponyfill.setZXingModuleOverrides.mockClear()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('uses the native detector when it reads EAN-13 codes', async () => {
    const constructed = stubNativeDetector(['qr_code', 'ean_13'])

    const detector = await create()

    expect(constructed).toEqual([{ formats: ['ean_13'] }])
    await expect(detector.detect(new Blob())).resolves.toEqual([{ rawValue: 'from-native' }])
    expect(ponyfill.constructed).toEqual([])
    expect(ponyfill.setZXingModuleOverrides).not.toHaveBeenCalled()
  })

  it('falls back to the polyfill when the native detector cannot read EAN-13 codes', async () => {
    const constructed = stubNativeDetector(['qr_code'])

    const detector = await create()

    expect(constructed).toEqual([])
    expect(ponyfill.constructed).toEqual([{ formats: ['ean_13'] }])
    await expect(detector.detect(new Blob())).resolves.toEqual([{ rawValue: 'from-ponyfill' }])
  })

  it('falls back to the polyfill without a native detector (Safari iOS)', async () => {
    vi.stubGlobal('BarcodeDetector', undefined)

    await create()

    expect(ponyfill.constructed).toEqual([{ formats: ['ean_13'] }])
  })

  it('loads the WebAssembly file from our build, never from a CDN', async () => {
    vi.stubGlobal('BarcodeDetector', undefined)

    await create()

    expect(ponyfill.setZXingModuleOverrides).toHaveBeenCalledOnce()
    const overrides = ponyfill.setZXingModuleOverrides.mock.lastCall![0] as {
      locateFile: (path: string, prefix: string) => string
    }
    expect(overrides.locateFile('zxing_reader.wasm', 'https://fastly.jsdelivr.net/npm/zxing-wasm/dist/reader/'))
      .toBe('/build/assets/zxing_reader-abc123.wasm')
    expect(ponyfill.setZXingModuleOverrides.mock.invocationCallOrder[0])
      .toBeLessThan(ponyfill.construct.mock.invocationCallOrder[0]!)
  })
})
