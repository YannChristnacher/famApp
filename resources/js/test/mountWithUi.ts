import type { Component } from 'vue'
import { defineComponent, h } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import type { ComponentMountingOptions } from '@vue/test-utils'
import ui from '@nuxt/ui/vue-plugin'
import { renderInAppRoot } from '@/appRoot'

/**
 * Mounts a component inside the application root (<UApp>, French locale) with the
 * Nuxt UI plugin installed, as app.ts does, and returns the wrapper of that component.
 * Every mounting option is forwarded; props and slots go to the tested component.
 */
export async function mountWithUi<T extends Component>(
  component: T,
  options: ComponentMountingOptions<T> = {},
) {
  const { props, slots, global, ...mountOptions } = options
  const TestRoot = defineComponent({
    name: 'TestRoot',
    setup: () => () => renderInAppRoot(() => h(component, props ?? {}, slots ?? {})),
  })
  const root = mount(TestRoot, {
    ...(mountOptions as ComponentMountingOptions<typeof TestRoot>),
    global: {
      ...global,
      plugins: [ui, ...(global?.plugins ?? [])],
    },
  })
  await flushPromises()

  return root.findComponent(component)
}
