import { VueQueryPlugin, type VueQueryPluginOptions } from '@tanstack/vue-query'

export const queryClientOptions: VueQueryPluginOptions = {
  queryClientConfig: {
    defaultOptions: {
      queries: {
        retry: 1,
        staleTime: 30_000,
        gcTime: 5 * 60_000,
        refetchOnWindowFocus: false,
        refetchOnMount: true,
        refetchOnReconnect: true,
      },
    },
  },
}

export { VueQueryPlugin }
