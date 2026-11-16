import { defineComponent, h } from 'vue'

/**
 * Offline replacement for @iconify/vue's <Icon>, which fetches icons from the Iconify API.
 */
export default defineComponent({
  name: 'IconifyIcon',
  props: { icon: { type: String, required: true } },
  setup: (props) => () => h('span', { 'data-icon': props.icon }),
})
