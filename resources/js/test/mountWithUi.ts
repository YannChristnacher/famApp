import type { Component } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import type { ComponentMountingOptions } from '@vue/test-utils'
import ui from '@nuxt/ui/vue-plugin'

/**
 * Mounts a component with the Nuxt UI plugin installed, as in the real app.
 */
export async function mountWithUi<T extends Component>(
  component: T,
  options: ComponentMountingOptions<T> = {},
) {
  const wrapper = mount(component, {
    ...options,
    global: {
      ...options.global,
      plugins: [ui, ...(options.global?.plugins ?? [])],
    },
  })
  await flushPromises()

  return wrapper
}
