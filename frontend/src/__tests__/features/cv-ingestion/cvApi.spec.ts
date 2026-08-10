import { beforeEach, describe, expect, it, vi } from 'vitest'

const { postMock } = vi.hoisted(() => ({
  postMock: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
}))

vi.mock('@/api/client/axios', () => ({
  default: {
    post: postMock,
  },
}))

import { batchUpdateSuggestions } from '@/features/cv-ingestion/api'

describe('CV ingestion API', () => {
  beforeEach(() => {
    postMock.mockReset()
  })

  it('submits batch review decisions using the POST route contract', async () => {
    const decisions = [
      { id: 11, decision: 'accepted' as const },
      { id: 13, decision: 'accepted' as const },
    ]
    postMock.mockResolvedValue({ data: { data: [] } })

    await batchUpdateSuggestions(1, decisions)

    expect(postMock).toHaveBeenCalledWith('/api/v1/cv/1/suggestions/batch', { decisions })
  })
})
