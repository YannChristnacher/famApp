import type { useRoute } from 'ziggy-js'
import type { SharedUser } from './auth'

type ZiggyConfig = NonNullable<Parameters<typeof useRoute>[0]>

declare global {
  // Named routes injected by the @routes Blade directive.
  var Ziggy: ZiggyConfig
}

declare module '@inertiajs/core' {
  export interface InertiaConfig {
    sharedPageProps: {
      auth: { user: SharedUser | null }
      status: string | null
    }
  }
}

export {}
