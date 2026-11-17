<script setup lang="ts">
import { onBeforeUnmount, ref, shallowRef, toRaw, watch } from 'vue'
import { bookIsbnFromBarcode } from '@/support/library/bookBarcode'
import { createBookBarcodeDetector, isDetectorUnavailable } from '@/support/library/barcodeDetector'
import type { BookBarcodeDetector, DetectedBarcode } from '@/support/library/barcodeDetector'

const open = defineModel<boolean>('open', { required: true })

const emit = defineEmits<{
  /** The ISBN of a book barcode; the modal closes itself. */
  detected: [isbn: string]
}>()

const SCAN_INTERVAL_MS = 200
const VIBRATION_MS = 100

const NO_CAMERA_API = 'La caméra n’est pas disponible dans ce navigateur : elle nécessite une connexion sécurisée (HTTPS). Prenez une photo du code-barres ou saisissez l’ISBN à la main.'
const CAMERA_DENIED = 'L’accès à la caméra a été refusé. Autorisez-le dans les réglages du navigateur, prenez une photo du code-barres ou saisissez l’ISBN à la main.'
const NO_CAMERA = 'Aucune caméra n’a été trouvée sur cet appareil. Prenez une photo du code-barres ou saisissez l’ISBN à la main.'
const CAMERA_BUSY = 'La caméra est déjà utilisée par une autre application. Fermez-la puis réessayez, ou saisissez l’ISBN à la main.'
const CAMERA_FAILED = 'La caméra n’a pas pu démarrer. Réessayez, prenez une photo du code-barres ou saisissez l’ISBN à la main.'
const READER_FAILED = 'Le lecteur de code-barres n’a pas pu se charger. Vérifiez votre connexion ou saisissez l’ISBN à la main.'
const NOT_A_BOOK = 'Ce code-barres n’est pas celui d’un livre (ISBN commençant par 978 ou 979).'
const NOTHING_ON_PHOTO = 'Aucun code-barres lisible sur cette photo. Cadrez le code de près, bien éclairé et sans reflet.'

const video = ref<HTMLVideoElement | null>(null)
const stream = shallowRef<MediaStream | null>(null)
const starting = ref(false)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
/** Shallow: native decoders refuse a reactive proxy of the File. */
const photo = shallowRef<File | null>(null)
const decodingPhoto = ref(false)

/** Incremented on every start and stop: late answers of an old session are ignored. */
let session = 0
let scanTimer: ReturnType<typeof setTimeout> | undefined
let detectorPromise: Promise<BookBarcodeDetector | null> | null = null

function detector(): Promise<BookBarcodeDetector | null> {
  detectorPromise ??= createBookBarcodeDetector().catch(() => {
    detectorPromise = null

    return null
  })

  return detectorPromise
}

/** The barcodes read on the source, or null when the detector is unavailable. */
async function detectOn(reader: BookBarcodeDetector, source: ImageBitmapSource): Promise<DetectedBarcode[] | null> {
  try {
    return await reader.detect(source)
  } catch (cause) {
    // Other failures are transient (video not ready yet) or tied to one photo.
    return isDetectorUnavailable(cause) ? null : []
  }
}

function failReader(): void {
  stopCamera()
  detectorPromise = null
  notice.value = null
  error.value = READER_FAILED
}

function cameraErrorMessage(cause: unknown): string {
  const name = cause instanceof DOMException || cause instanceof Error ? cause.name : ''

  switch (name) {
    case 'NotAllowedError':
    case 'SecurityError':
      return CAMERA_DENIED
    case 'NotFoundError':
    case 'OverconstrainedError':
      return NO_CAMERA
    case 'NotReadableError':
      return CAMERA_BUSY
    default:
      return CAMERA_FAILED
  }
}

function stopTracks(media: MediaStream): void {
  media.getTracks().forEach((track) => track.stop())
}

function stopCamera(): void {
  session++
  clearTimeout(scanTimer)
  if (stream.value !== null) {
    stopTracks(stream.value)
    stream.value = null
  }
  if (video.value !== null) {
    video.value.srcObject = null
  }
  starting.value = false
}

async function startCamera(): Promise<void> {
  stopCamera()
  const current = session
  error.value = null
  notice.value = null

  if (typeof navigator.mediaDevices?.getUserMedia !== 'function') {
    error.value = NO_CAMERA_API

    return
  }

  starting.value = true
  const reader = detector()
  let media: MediaStream
  try {
    media = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
  } catch (cause) {
    if (current === session) {
      starting.value = false
      error.value = cameraErrorMessage(cause)
    }

    return
  }

  if (current !== session) {
    stopTracks(media)

    return
  }
  stream.value = media

  const loaded = await reader
  if (current !== session) {
    return
  }
  starting.value = false
  if (loaded === null) {
    failReader()

    return
  }

  scheduleScan(current, loaded)
}

function scheduleScan(current: number, reader: BookBarcodeDetector): void {
  scanTimer = setTimeout(async () => {
    if (current !== session) {
      return
    }

    const codes = video.value === null ? [] : await detectOn(reader, video.value)
    if (current !== session) {
      return
    }
    if (codes === null) {
      failReader()

      return
    }
    if (accepts(codes)) {
      return
    }

    scheduleScan(current, reader)
  }, SCAN_INTERVAL_MS)
}

/** Accepts the first book barcode; any other barcode only shows a notice. */
function accepts(codes: DetectedBarcode[]): boolean {
  const isbn = codes.map((code) => bookIsbnFromBarcode(code.rawValue)).find((value) => value !== null)
  if (isbn !== undefined && isbn !== null) {
    navigator.vibrate?.(VIBRATION_MS)
    stopCamera()
    emit('detected', isbn)
    open.value = false

    return true
  }

  if (codes.length > 0) {
    notice.value = NOT_A_BOOK
  }

  return false
}

async function decodePhoto(file: File): Promise<void> {
  const current = session
  decodingPhoto.value = true
  notice.value = null

  try {
    const reader = await detector()
    if (current !== session) {
      return
    }
    if (reader === null) {
      failReader()

      return
    }

    const codes = await detectOn(reader, file)
    // Closed (or closed and reopened) meanwhile: this photo belongs to another session.
    if (current !== session || !open.value) {
      return
    }
    if (codes === null) {
      failReader()

      return
    }
    if (!accepts(codes)) {
      notice.value = codes.length === 0 ? NOTHING_ON_PHOTO : NOT_A_BOOK
    }
  } finally {
    decodingPhoto.value = false
  }
}

watch(photo, (file) => {
  if (file === null) {
    return
  }

  // Resets the input so that the same photo can be chosen again.
  photo.value = null
  void decodePhoto(toRaw(file))
})

watch([video, stream], ([element, media]) => {
  if (element === null || media === null) {
    return
  }

  element.srcObject = media
  void element.play()?.catch(() => {})
})

watch(open, (isOpen) => {
  if (isOpen) {
    void startCamera()

    return
  }

  stopCamera()
}, { immediate: true })

onBeforeUnmount(stopCamera)
</script>

<template>
  <UModal
    v-model:open="open"
    title="Scanner le code-barres"
    description="Placez le code-barres du livre (au dos, ISBN commençant par 978 ou 979) dans le cadre."
  >
    <template #body>
      <div class="space-y-4">
        <UAlert
          v-if="error"
          color="warning"
          variant="subtle"
          icon="i-lucide-camera-off"
          :description="error"
          data-testid="isbn-scanner-error"
        />

        <div
          v-else
          class="relative overflow-hidden rounded-md bg-black"
        >
          <video
            ref="video"
            class="aspect-[4/3] w-full object-cover"
            muted
            autoplay
            playsinline
            aria-label="Aperçu de la caméra"
            data-testid="isbn-scanner-video"
          />
          <div
            class="pointer-events-none absolute inset-x-8 top-1/2 h-24 -translate-y-1/2 rounded-md border-2 border-white/80"
            aria-hidden="true"
          />
          <p
            v-if="starting"
            class="absolute inset-0 flex items-center justify-center text-sm text-white"
          >
            Démarrage de la caméra…
          </p>
        </div>

        <p
          v-if="notice"
          class="text-sm text-muted"
          role="status"
          data-testid="isbn-scanner-notice"
        >
          {{ notice }}
        </p>

        <UFileUpload
          v-slot="{ open: choosePhoto }"
          v-model="photo"
          accept="image/*"
          capture="environment"
          :dropzone="false"
          :interactive="false"
          aria-label="Photo du code-barres"
        >
          <UButton
            icon="i-lucide-camera"
            color="neutral"
            variant="outline"
            block
            :loading="decodingPhoto"
            @click="choosePhoto()"
          >
            Prendre une photo du code-barres
          </UButton>
        </UFileUpload>
        <p class="text-xs text-muted">
          La photo est lue sur votre appareil, elle n’est pas envoyée.
        </p>
      </div>
    </template>
  </UModal>
</template>
