import type { useRoute } from 'ziggy-js'

type ZiggyConfig = NonNullable<Parameters<typeof useRoute>[0]>

declare global {
  // Named routes injected by the @routes Blade directive.
  var Ziggy: ZiggyConfig
}

export {}
