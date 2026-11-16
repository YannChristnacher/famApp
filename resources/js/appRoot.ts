import type { VNode } from 'vue'
import { h } from 'vue'
import UApp from '@nuxt/ui/components/App.vue'
import { fr } from '@nuxt/ui/locale'

/**
 * Wraps the application (or a component under test) in the single <UApp> root, in French.
 */
export function renderInAppRoot(content: () => VNode): VNode {
  return h(UApp, { locale: fr }, content)
}
