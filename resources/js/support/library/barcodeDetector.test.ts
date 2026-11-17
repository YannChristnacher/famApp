import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const ponyfill = vi.hoisted(() => ({
  constructed: [] as unknown[],
  construct: vi.fn(),
  prepareZXingModule: vi.fn((options: unknown) => { void options; return Promise.resolve({}) }),
  purgeZXingModule: vi.fn(),
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
  prepareZXingModule: ponyfill.prepareZXingModule,
  purgeZXingModule: ponyfill.purgeZXingModule,
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
    ponyfill.prepareZXingModule.mockClear()
    ponyfill.purgeZXingModule.mockClear()
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
    expect(ponyfill.prepareZXingModule).not.toHaveBeenCalled()
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

  it('loads the WebAssembly file from our build, never from a CDN, before creating the detector', async () => {
    vi.stubGlobal('BarcodeDetector', undefined)

    await create()

    expect(ponyfill.prepareZXingModule).toHaveBeenCalledOnce()
    const options = ponyfill.prepareZXingModule.mock.lastCall![0] as {
      fireImmediately: boolean
      overrides: { locateFile: (path: string, prefix: string) => string }
    }
    expect(options.fireImmediately).toBe(true)
    expect(options.overrides.locateFile('zxing_reader.wasm', 'https://fastly.jsdelivr.net/npm/zxing-wasm/dist/reader/'))
      .toBe('/build/assets/zxing_reader-abc123.wasm')
    expect(ponyfill.prepareZXingModule.mock.invocationCallOrder[0])
      .toBeLessThan(ponyfill.construct.mock.invocationCallOrder[0]!)
  })

  it('fails when the WebAssembly file cannot be loaded, and allows a later retry', async () => {
    vi.stubGlobal('BarcodeDetector', undefined)
    ponyfill.prepareZXingModule.mockRejectedValueOnce(new Error('404 zxing_reader.wasm'))

    await expect(create()).rejects.toThrow('404 zxing_reader.wasm')

    expect(ponyfill.constructed).toEqual([])
    expect(ponyfill.purgeZXingModule).toHaveBeenCalledOnce()
  })
})

describe('isDetectorUnavailable', () => {
  it('recognises the error of a detector whose WebAssembly module is missing', async () => {
    const { isDetectorUnavailable } = await import('./barcodeDetector')

    expect(isDetectorUnavailable(new DOMException('Barcode detection service unavailable.', 'NotSupportedError'))).toBe(true)
    expect(isDetectorUnavailable(new DOMException('Invalid element or state.', 'InvalidStateError'))).toBe(false)
    expect(isDetectorUnavailable(new Error('other'))).toBe(false)
  })
})
