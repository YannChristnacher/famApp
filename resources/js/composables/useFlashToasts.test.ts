import { describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import { mount } from '@vue/test-utils'
import { router } from '@inertiajs/vue3'
import ui from '@nuxt/ui/vue-plugin'
import { showFlashToast, useFlashToasts } from './useFlashToasts'

describe('useFlashToasts', () => {
  it('listens to the Inertia flashes while mounted only', () => {
    const off = vi.fn()
    const on = vi.spyOn(router, 'on').mockReturnValue(off)
    const Host = defineComponent({
      setup() {
        useFlashToasts()

        return () => null
      },
    })

    const wrapper = mount(Host, { global: { plugins: [ui] } })
    expect(on).toHaveBeenCalledWith('flash', expect.any(Function))
    expect(off).not.toHaveBeenCalled()

    wrapper.unmount()
    expect(off).toHaveBeenCalledOnce()
  })
})

describe('showFlashToast', () => {
  it('adds a green toast for a success', () => {
    const add = vi.fn()

    showFlashToast({ toast: { type: 'success', message: 'Compte créé.' } }, add)

    expect(add).toHaveBeenCalledWith({ title: 'Compte créé.', color: 'success', icon: 'i-lucide-circle-check' })
  })

  it('adds a red toast for an error', () => {
    const add = vi.fn()

    showFlashToast({ toast: { type: 'error', message: 'Cette demande a déjà été traitée.' } }, add)

    expect(add).toHaveBeenCalledWith({
      title: 'Cette demande a déjà été traitée.',
      color: 'error',
      icon: 'i-lucide-circle-alert',
    })
  })

  it('adds nothing when no toast was flashed', () => {
    const add = vi.fn()

    showFlashToast({}, add)

    expect(add).not.toHaveBeenCalled()
  })
})
