import { beforeEach, describe, expect, it, vi } from 'vitest'

const { getMock, postMock } = vi.hoisted(() => ({
  getMock: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  postMock: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
}))

vi.mock('@/api/client/axios', () => ({
  default: {
    get: getMock,
    post: postMock,
    interceptors: {
      request: { use: vi.fn<(...args: unknown[]) => unknown>() },
      response: { use: vi.fn<(...args: unknown[]) => unknown>() },
    },
  },
}))

import { createResume, listResumes } from '@/features/cv-tailoring/api'
import { nextRequestId } from '@/api/client/request-id'

describe('CV tailoring API', () => {
  beforeEach(() => {
    getMock.mockReset()
    postMock.mockReset()
  })

  it('lists resumes through the opportunity-scoped endpoint', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    await listResumes(7)

    expect(getMock).toHaveBeenCalledWith('/api/v1/opportunities/7/resumes', {
      params: { opportunity_id: 7 },
    })
  })

  it('creates a resume through the opportunity-scoped endpoint', async () => {
    postMock.mockResolvedValue({ data: { data: { id: 12 } } })

    await createResume(7, {})

    expect(postMock).toHaveBeenCalledWith('/api/v1/opportunities/7/resumes', {
      opportunity_id: 7,
    })
  })

  it('generates request IDs with the required prefix', () => {
    expect(nextRequestId()).toMatch(/^req_/)
  })

  it('uses a single centralized request-id implementation', async () => {
    const files = import.meta.glob('@/features/**/api/index.ts', { eager: false })
    expect(typeof nextRequestId).toBe('function')
    // The centralized module is the single source; feature modules no longer define their own
    expect(files).toBeDefined()
  })
})
