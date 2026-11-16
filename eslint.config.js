import pluginVue from 'eslint-plugin-vue'
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript'

export default defineConfigWithVueTs(
  {
    ignores: [
      'vendor/**',
      'node_modules/**',
      'public/**',
      'bootstrap/ssr/**',
      'storage/**',
      'auto-imports.d.ts',
      'components.d.ts',
    ],
  },
  pluginVue.configs['flat/recommended'],
  vueTsConfigs.recommended,
  {
    rules: {
      'vue/multi-word-component-names': 'off',
    },
  },
)
