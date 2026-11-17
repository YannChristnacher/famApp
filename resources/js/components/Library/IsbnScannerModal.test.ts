import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { mountWithUi } from '@/test/mountWithUi'
import { createBookBarcodeDetector } from '@/support/library/barcodeDetector'
import type { DetectedBarcode } from '@/support/library/barcodeDetector'
import IsbnScannerModal from './IsbnScannerModal.vue'

vi.mock('@/support/library/barcodeDetector', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/support/library/barcodeDetector')>()),
  createBookBarcodeDetector: vi.fn(),
}))

const BOOK = '9782070368228'
const NOT_A_BOOK = '3017620422003'

interface FakeTrack {
  stop: ReturnType<typeof vi.fn>
}

function fakeStream(trackCount = 2) {
  const tracks: FakeTrack[] = Array.from({ length: trackCount }, () => ({ stop: vi.fn() }))

  // happy-dom only accepts its own MediaStream as srcObject, without tracks.
  const stream = Object.assign(new MediaStream(), { getTracks: () => tracks }) as unknown as MediaStream

  return { stream, tracks }
}

/** A detector whose next answers are queued; it answers [] once the queue is empty. */
function stubDetector() {
  const answers: DetectedBarcode[][] = []
  const detect = vi.fn((source: unknown) => {
    void source

    return Promise.resolve(answers.shift() ?? [])
  })
  vi.mocked(createBookBarcodeDetector).mockResolvedValue({ detect })

  return { detect, answers }
}

function stubCamera(getUserMedia: () => Promise<MediaStream>) {
  const spy = vi.fn(getUserMedia)
  Object.defineProperty(navigator, 'mediaDevices', { value: { getUserMedia: spy }, configurable: true })

  return spy
}

function unavailableDetector(): DOMException {
  return new DOMException('Barcode detection service unavailable.', 'NotSupportedError')
}

function cameraError(name: string): DOMException {
  return new DOMException('camera error', name)
}

const open = ref(true)
const mounted = ref(true)
const detected = vi.fn()

const Host = defineComponent({
  setup: () => () => mounted.value && h(IsbnScannerModal, {
    open: open.value,
    'onUpdate:open': (value: boolean) => { open.value = value },
    onDetected: detected,
  }),
})

async function mountScanner() {
  const wrapper = await mountWithUi(Host, { attachTo: document.body })
  await flushPromises()

  return wrapper
}

function bodyText(): string {
  return document.body.textContent ?? ''
}

function video(): HTMLVideoElement {
  const element = document.body.querySelector<HTMLVideoElement>('video[data-testid="isbn-scanner-video"]')
  expect(element, 'camera preview').not.toBeNull()

  return element!
}

async function choosePhoto(file: File): Promise<void> {
  const input = document.body.querySelector<HTMLInputElement>('input[type="file"]')
  expect(input, 'photo input').not.toBeNull()
  Object.defineProperty(input, 'files', { value: [file], configurable: true })
  input!.dispatchEvent(new Event('change'))
  await flushPromises()
}

describe('Library/IsbnScannerModal', () => {
  beforeEach(() => {
    open.value = true
    mounted.value = true
    detected.mockReset()
    vi.mocked(createBookBarcodeDetector).mockReset()
    vi.spyOn(HTMLMediaElement.prototype, 'play').mockImplementation(() => Promise.resolve())
    Object.defineProperty(navigator, 'vibrate', { value: vi.fn(() => true), configurable: true })
  })

  afterEach(() => {
    Reflect.deleteProperty(navigator, 'mediaDevices')
    Reflect.deleteProperty(navigator, 'vibrate')
    vi.unstubAllGlobals()
  })

  it('asks for the back camera without audio and shows a muted inline preview', async () => {
    stubDetector()
    const { stream } = fakeStream()
    const getUserMedia = stubCamera(() => Promise.resolve(stream))

    await mountScanner()

    expect(getUserMedia).toHaveBeenCalledWith({ video: { facingMode: { ideal: 'environment' } }, audio: false })
    await vi.waitFor(() => expect(video().srcObject).toBe(stream))
    expect(video().muted).toBe(true)
    expect(video().hasAttribute('playsinline')).toBe(true)
  })

  it('emits a book barcode once, stops every track, vibrates and closes', async () => {
    const { detect, answers } = stubDetector()
    answers.push([], [{ rawValue: BOOK }], [{ rawValue: BOOK }])
    const { stream, tracks } = fakeStream()
    stubCamera(() => Promise.resolve(stream))

    await mountScanner()

    await vi.waitFor(() => expect(detected).toHaveBeenCalled())
    expect(detected).toHaveBeenCalledExactlyOnceWith(BOOK)
    expect(detect.mock.calls[0]?.[0]).toBeInstanceOf(HTMLVideoElement)
    tracks.forEach((track) => expect(track.stop).toHaveBeenCalled())
    expect(navigator.vibrate).toHaveBeenCalled()
    expect(open.value).toBe(false)

    const callsAtDetection = detect.mock.calls.length
    await new Promise((resolve) => setTimeout(resolve, 500))
    expect(detect.mock.calls.length).toBe(callsAtDetection)
    expect(detected).toHaveBeenCalledOnce()
  })

  it('works without the vibration API', async () => {
    Reflect.deleteProperty(navigator, 'vibrate')
    const { answers } = stubDetector()
    answers.push([{ rawValue: BOOK }])
    stubCamera(() => Promise.resolve(fakeStream().stream))

    await mountScanner()

    await vi.waitFor(() => expect(detected).toHaveBeenCalledWith(BOOK))
  })

  it('ignores a barcode that is not a book, with a discreet notice, and keeps scanning', async () => {
    const { answers } = stubDetector()
    answers.push([{ rawValue: NOT_A_BOOK }])
    const { stream, tracks } = fakeStream()
    stubCamera(() => Promise.resolve(stream))

    await mountScanner()

    await vi.waitFor(() => expect(bodyText()).toContain('n’est pas celui d’un livre'))
    expect(detected).not.toHaveBeenCalled()
    expect(open.value).toBe(true)
    tracks.forEach((track) => expect(track.stop).not.toHaveBeenCalled())

    answers.push([{ rawValue: NOT_A_BOOK }, { rawValue: BOOK }])
    await vi.waitFor(() => expect(detected).toHaveBeenCalledExactlyOnceWith(BOOK))
  })

  it('explains that the camera needs a secure context when mediaDevices is missing', async () => {
    stubDetector()

    await mountScanner()

    expect(bodyText()).toContain('La caméra n’est pas disponible dans ce navigateur')
    expect(bodyText()).toContain('HTTPS')
    expect(bodyText()).toContain('saisissez l’ISBN à la main')
    expect(detected).not.toHaveBeenCalled()
  })

  it.each([
    ['NotAllowedError', 'L’accès à la caméra a été refusé'],
    ['SecurityError', 'L’accès à la caméra a été refusé'],
    ['NotFoundError', 'Aucune caméra n’a été trouvée'],
    ['OverconstrainedError', 'Aucune caméra n’a été trouvée'],
    ['NotReadableError', 'La caméra est déjà utilisée'],
    ['AbortError', 'La caméra n’a pas pu démarrer'],
  ])('shows a French message when getUserMedia fails with %s', async (name, message) => {
    const { detect } = stubDetector()
    stubCamera(() => Promise.reject(cameraError(name)))

    await mountScanner()

    await vi.waitFor(() => expect(bodyText()).toContain(message))
    expect(detect).not.toHaveBeenCalled()
    expect(detected).not.toHaveBeenCalled()
  })

  it('stops the camera and explains when the barcode reader cannot load', async () => {
    vi.mocked(createBookBarcodeDetector).mockRejectedValue(new Error('wasm failed'))
    const { stream, tracks } = fakeStream()
    stubCamera(() => Promise.resolve(stream))

    await mountScanner()

    await vi.waitFor(() => expect(bodyText()).toContain('Le lecteur de code-barres n’a pas pu se charger'))
    tracks.forEach((track) => expect(track.stop).toHaveBeenCalled())
    expect(detected).not.toHaveBeenCalled()
  })

  it('stops the camera and explains when the detector turns out to be unavailable', async () => {
    const { detect } = stubDetector()
    detect.mockRejectedValue(unavailableDetector())
    const { stream, tracks } = fakeStream()
    stubCamera(() => Promise.resolve(stream))

    await mountScanner()

    await vi.waitFor(() => expect(bodyText()).toContain('Le lecteur de code-barres n’a pas pu se charger'))
    tracks.forEach((track) => expect(track.stop).toHaveBeenCalled())
    const calls = detect.mock.calls.length
    await new Promise((resolve) => setTimeout(resolve, 500))
    expect(detect.mock.calls.length).toBe(calls)
    expect(detected).not.toHaveBeenCalled()
  })

  it('loads the detector again when reopened after it failed', async () => {
    const { detect } = stubDetector()
    detect.mockRejectedValue(unavailableDetector())
    stubCamera(() => Promise.resolve(fakeStream().stream))
    await mountScanner()
    await vi.waitFor(() => expect(bodyText()).toContain('Le lecteur de code-barres n’a pas pu se charger'))

    open.value = false
    await flushPromises()
    open.value = true
    await flushPromises()

    expect(createBookBarcodeDetector).toHaveBeenCalledTimes(2)
  })

  it('keeps scanning while the video is not ready yet', async () => {
    const { detect } = stubDetector()
    detect
      .mockRejectedValueOnce(new DOMException('Invalid element or state.', 'InvalidStateError'))
      .mockResolvedValueOnce([{ rawValue: BOOK }])
    stubCamera(() => Promise.resolve(fakeStream().stream))

    await mountScanner()

    await vi.waitFor(() => expect(detected).toHaveBeenCalledExactlyOnceWith(BOOK))
  })

  it('stops every track and stops scanning when the modal is closed', async () => {
    const { detect } = stubDetector()
    const { stream, tracks } = fakeStream()
    stubCamera(() => Promise.resolve(stream))

    await mountScanner()
    await vi.waitFor(() => expect(detect).toHaveBeenCalled())

    open.value = false
    await flushPromises()

    tracks.forEach((track) => expect(track.stop).toHaveBeenCalled())
    const calls = detect.mock.calls.length
    await new Promise((resolve) => setTimeout(resolve, 500))
    expect(detect.mock.calls.length).toBe(calls)
  })

  it('stops every track when unmounted', async () => {
    const { detect } = stubDetector()
    const { stream, tracks } = fakeStream()
    stubCamera(() => Promise.resolve(stream))

    await mountScanner()
    await vi.waitFor(() => expect(detect).toHaveBeenCalled())

    mounted.value = false
    await flushPromises()

    tracks.forEach((track) => expect(track.stop).toHaveBeenCalled())
  })

  it('stops a stream granted after the modal was closed', async () => {
    stubDetector()
    const { stream, tracks } = fakeStream()
    let grant: (value: MediaStream) => void = () => {}
    stubCamera(() => new Promise((resolve) => { grant = resolve }))

    await mountScanner()
    open.value = false
    await flushPromises()

    grant(stream)
    await flushPromises()

    tracks.forEach((track) => expect(track.stop).toHaveBeenCalled())
    expect(detected).not.toHaveBeenCalled()
  })

  it('restarts the camera when reopened', async () => {
    stubDetector()
    const getUserMedia = stubCamera(() => Promise.resolve(fakeStream().stream))

    await mountScanner()
    open.value = false
    await flushPromises()
    open.value = true
    await flushPromises()

    expect(getUserMedia).toHaveBeenCalledTimes(2)
  })

  describe('photo fallback', () => {
    it('offers to take a photo with the back camera', async () => {
      stubDetector()

      await mountScanner()

      const input = document.body.querySelector<HTMLInputElement>('input[type="file"]')
      expect(input?.getAttribute('accept')).toBe('image/*')
      expect(input?.getAttribute('capture')).toBe('environment')
      expect(bodyText()).toContain('Prendre une photo du code-barres')
    })

    it('decodes the photo in the browser and emits a book barcode', async () => {
      const fetchMock = vi.fn()
      vi.stubGlobal('fetch', fetchMock)
      const { detect } = stubDetector()
      const { stream, tracks } = fakeStream()
      stubCamera(() => Promise.resolve(stream))
      await mountScanner()
      await vi.waitFor(() => expect(detect).toHaveBeenCalled())
      const photo = new File(['jpeg'], 'code.jpg', { type: 'image/jpeg' })
      detect.mockImplementation((source: unknown) => Promise.resolve(source === photo ? [{ rawValue: BOOK }] : []))

      await choosePhoto(photo)

      await vi.waitFor(() => expect(detected).toHaveBeenCalledExactlyOnceWith(BOOK))
      expect(detect).toHaveBeenCalledWith(photo)
      tracks.forEach((track) => expect(track.stop).toHaveBeenCalled())
      expect(open.value).toBe(false)
      expect(fetchMock).not.toHaveBeenCalled()
    })

    it('works when the camera is unavailable', async () => {
      const { detect } = stubDetector()
      await mountScanner()
      detect.mockResolvedValue([{ rawValue: BOOK }])

      await choosePhoto(new File(['jpeg'], 'code.jpg', { type: 'image/jpeg' }))

      await vi.waitFor(() => expect(detected).toHaveBeenCalledExactlyOnceWith(BOOK))
    })

    it('ignores a photo of a barcode that is not a book', async () => {
      const { detect } = stubDetector()
      await mountScanner()
      detect.mockResolvedValue([{ rawValue: NOT_A_BOOK }])

      await choosePhoto(new File(['jpeg'], 'code.jpg', { type: 'image/jpeg' }))

      await vi.waitFor(() => expect(bodyText()).toContain('n’est pas celui d’un livre'))
      expect(detected).not.toHaveBeenCalled()
      expect(open.value).toBe(true)
    })

    it('explains when no barcode can be read on the photo', async () => {
      const { detect } = stubDetector()
      await mountScanner()
      detect.mockResolvedValue([])

      await choosePhoto(new File(['jpeg'], 'flou.jpg', { type: 'image/jpeg' }))

      await vi.waitFor(() => expect(bodyText()).toContain('Aucun code-barres lisible sur cette photo'))
      expect(detected).not.toHaveBeenCalled()
    })

    it('explains that the reader is unavailable instead of blaming the photo', async () => {
      const { detect } = stubDetector()
      await mountScanner()
      detect.mockRejectedValue(unavailableDetector())

      await choosePhoto(new File(['jpeg'], 'code.jpg', { type: 'image/jpeg' }))

      await vi.waitFor(() => expect(bodyText()).toContain('Le lecteur de code-barres n’a pas pu se charger'))
      expect(bodyText()).not.toContain('Aucun code-barres lisible')
      expect(detected).not.toHaveBeenCalled()
    })

    it('ignores a photo decoded after the modal was closed and reopened', async () => {
      const { detect } = stubDetector()
      await mountScanner()
      let answer: (codes: DetectedBarcode[]) => void = () => {}
      detect.mockImplementationOnce(() => new Promise((resolve) => { answer = resolve }))

      await choosePhoto(new File(['jpeg'], 'code.jpg', { type: 'image/jpeg' }))
      open.value = false
      await flushPromises()
      open.value = true
      await flushPromises()
      answer([{ rawValue: BOOK }])
      await flushPromises()

      expect(detected).not.toHaveBeenCalled()
      expect(open.value).toBe(true)
    })

    it('explains when the photo cannot be decoded', async () => {
      const { detect } = stubDetector()
      await mountScanner()
      detect.mockRejectedValue(new Error('not an image'))

      await choosePhoto(new File(['text'], 'notes.txt', { type: 'text/plain' }))

      await vi.waitFor(() => expect(bodyText()).toContain('Aucun code-barres lisible sur cette photo'))
    })
  })
})
