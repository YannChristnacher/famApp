import '../css/app.css'
import type { DefineComponent } from 'vue'
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import ui from '@nuxt/ui/vue-plugin'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { renderInAppRoot } from './appRoot'

const appName = import.meta.env.VITE_APP_NAME || 'famApp'

createInertiaApp({
  title: (title) => (title ? `${title} · ${appName}` : appName),
  resolve: (name) =>
    resolvePageComponent(
      `./pages/${name}.vue`,
      import.meta.glob<DefineComponent>('./pages/**/*.vue'),
    ),
  setup({ el, App, props, plugin }) {
    createApp({ render: () => renderInAppRoot(() => h(App, props)) })
      .use(plugin)
      .use(ui)
      .mount(el)
  },
  progress: {
    color: '#10b981',
  },
})
