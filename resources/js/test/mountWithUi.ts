import type { Component } from 'vue'
import { defineComponent, h } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import type { ComponentMountingOptions } from '@vue/test-utils'
import ui from '@nuxt/ui/vue-plugin'
import UApp from '@nuxt/ui/components/App.vue'
import { fr } from '@nuxt/ui/locale'

/**
 * Mounts a component inside <UApp> (French locale) with the Nuxt UI plugin
 * installed, exactly as the real application root does in app.ts.
 */
export async function mountWithUi<T extends Component>(
  component: T,
  options: ComponentMountingOptions<T> = {},
) {
  const { props, slots, global, attachTo } = options
  const TestRoot = defineComponent({
    name: 'TestRoot',
    setup: () => () => h(UApp, { locale: fr }, () => h(component, props ?? {}, slots ?? {})),
  })
  const wrapper = mount(TestRoot, {
    attachTo,
    global: {
      ...global,
      plugins: [ui, ...(global?.plugins ?? [])],
    },
  })
  await flushPromises()

  return wrapper
}
