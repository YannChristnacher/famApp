import { onBeforeUnmount, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import type { FlashData } from '@inertiajs/core'
import { useToast } from '@nuxt/ui/composables'
import type { Toast } from '@nuxt/ui/composables/useToast'

type AddToast = (toast: Partial<Toast>) => unknown

const appearance = {
  success: { color: 'success', icon: 'i-lucide-circle-check' },
  error: { color: 'error', icon: 'i-lucide-circle-alert' },
} as const

export function showFlashToast(flash: FlashData, add: AddToast): void {
  if (!flash.toast) {
    return
  }

  add({ title: flash.toast.message, ...appearance[flash.toast.type] })
}

/**
 * Shows the toasts flashed by the server while the component is mounted.
 */
export function useFlashToasts(): void {
  const toast = useToast()
  let stopListening: (() => void) | undefined

  onMounted(() => {
    stopListening = router.on('flash', (event) => showFlashToast(event.detail.flash, toast.add))
  })

  onBeforeUnmount(() => stopListening?.())
}
