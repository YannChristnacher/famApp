import { describe, expect, it, vi } from 'vitest'
import { showFlashToast } from './useFlashToasts'

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
