import { VueQueryPlugin, type VueQueryPluginOptions } from '@tanstack/vue-query'

export const queryClientOptions: VueQueryPluginOptions = {
  queryClientConfig: {
    defaultOptions: {
      queries: {
        retry: 1,
        staleTime: 30_000,
        refetchOnWindowFocus: false,
      },
    },
  },
}

export { VueQueryPlugin }
