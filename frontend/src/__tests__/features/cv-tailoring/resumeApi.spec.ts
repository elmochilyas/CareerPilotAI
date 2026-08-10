import { beforeEach, describe, expect, it, vi } from 'vitest'

const { getMock, postMock } = vi.hoisted(() => ({
  getMock: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  postMock: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
}))

vi.mock('@/api/client/axios', () => ({
  default: {
    get: getMock,
    post: postMock,
  },
}))

import { createResume, listResumes } from '@/features/cv-tailoring/api'

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
      headers: { 'X-Request-ID': expect.stringMatching(/^req_/) },
    })
  })

  it('creates a resume through the opportunity-scoped endpoint', async () => {
    postMock.mockResolvedValue({ data: { data: { id: 12 } } })

    await createResume(7, {})

    expect(postMock).toHaveBeenCalledWith(
      '/api/v1/opportunities/7/resumes',
      { opportunity_id: 7 },
      { headers: { 'X-Request-ID': expect.stringMatching(/^req_/) } },
    )
  })
})
