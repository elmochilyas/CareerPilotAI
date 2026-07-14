import { describe, it, expect } from 'vitest'
import { queryClientOptions } from '@/app/providers/query-client'
import type { VueQueryPluginOptions } from '@tanstack/vue-query'

describe('query-client', () => {
  it('has default options configured', () => {
    const options = queryClientOptions as VueQueryPluginOptions & { queryClientConfig: unknown }

    expect(options.queryClientConfig).toBeDefined()
  })
})
