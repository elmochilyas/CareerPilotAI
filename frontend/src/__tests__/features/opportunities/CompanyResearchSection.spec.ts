import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CompanyResearchSection from '@/features/opportunities/components/CompanyResearchSection.vue'

const mockStore = vi.hoisted(() => ({
  status: 'not_researched' as string,
  data: null as unknown,
  isProcessing: false,
  isFailed: false,
  isCompleted: false,
  isNotResearched: true,
  isBusy: false,
  queryPending: false,
  queryError: false,
  errorDetail: null as string | null,
}))

const startMock = vi.fn<() => void>()
const refreshMock = vi.fn<() => void>()

vi.mock('@/features/opportunities/composables/useCompanyResearch', () => ({
  useCompanyResearch: vi.fn<() => unknown>(() => {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const { ref } = require('vue') as typeof import('vue')
    return {
      query: ref({
        isPending: ref(mockStore.queryPending),
        isError: ref(mockStore.queryError),
        isSuccess: ref(true),
        refetch: vi.fn<() => void>(),
        error: ref(null),
      }),
      data: ref(mockStore.data),
      status: ref(mockStore.status),
      isProcessing: ref(mockStore.isProcessing),
      isFailed: ref(mockStore.isFailed),
      isCompleted: ref(mockStore.isCompleted),
      isNotResearched: ref(mockStore.isNotResearched),
      isBusy: ref(mockStore.isBusy),
      start: startMock,
      refresh: refreshMock,
      errorDetail: ref(mockStore.errorDetail),
    }
  }),
}))

describe('CompanyResearchSection', () => {
  beforeEach(() => {
    mockStore.status = 'not_researched'
    mockStore.data = null
    mockStore.isProcessing = false
    mockStore.isFailed = false
    mockStore.isCompleted = false
    mockStore.isNotResearched = true
    mockStore.isBusy = false
    mockStore.queryPending = false
    mockStore.queryError = false
    mockStore.errorDetail = null
    startMock.mockClear()
    refreshMock.mockClear()
  })

  it('renders not-researched CTA', async () => {
    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    expect(wrapper.text()).toContain('Company Research')
    expect(wrapper.text()).toContain('Research company')
  })

  it('triggers start on click', async () => {
    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    const btn = wrapper.findAll('button').find((b) => b.text().includes('Research company'))
    expect(btn).toBeTruthy()
    await btn!.trigger('click')
    expect(startMock).toHaveBeenCalled()
  })

  it('shows processing state', async () => {
    mockStore.status = 'processing'
    mockStore.isProcessing = true
    mockStore.isNotResearched = false

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    expect(wrapper.text()).toContain('Researching company')
  })

  it('renders completed brief with premium layout — About, Products, FACT/INFERENCE badges, domain, provenance', async () => {
    mockStore.status = 'completed'
    mockStore.isCompleted = true
    mockStore.isNotResearched = false
    mockStore.data = {
      status: 'completed',
      researched_at: '2026-08-23T12:00:00Z',
      stale: false,
      stale_reason: null,
      version: 1,
      fallback_reason: null,
      overview: {
        name: 'Acme',
        website: 'https://acme.example.com',
        industry: 'Tech',
        headquarters: 'Paris',
        description: 'Desc',
      },
      products: [
        {
          text: 'Company describes X as product',
          kind: 'fact',
          confidence: 'high',
          source_ids: [0],
        },
      ],
      technology_context: [
        {
          text: 'This suggests backend may support X',
          kind: 'inference',
          confidence: 'medium',
          source_ids: [1],
        },
      ],
      role_context: [],
      recent_information: [],
      candidate_preparation: [],
      sources: [
        {
          id: 0,
          url: 'https://acme.example.com',
          title: 'Official Website',
          retrieved_at: '2026-08-23T12:00:00Z',
          source_type: 'official_website',
        },
        {
          id: 1,
          url: 'https://careers.acme.example.com',
          title: 'Careers Page',
          retrieved_at: '2026-08-23T12:00:00Z',
          source_type: 'careers_page',
        },
      ],
      generated_at: '2026-08-23T12:00:00Z',
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })

    // New premium headings
    expect(wrapper.text()).toContain('About Company')
    expect(wrapper.text()).toContain('Products & Services')
    expect(wrapper.text()).toContain('Technology & Engineering')
    expect(wrapper.text()).toContain('FACT')
    expect(wrapper.text()).toContain('INFERENCE')
    expect(wrapper.text()).toContain('Acme')
    // Provenance table replaces old SourceChips "Sources:" label
    expect(wrapper.text()).toContain('Source & provenance')
    // Clean domain without protocol
    expect(wrapper.text()).toContain('acme.example.com')
    expect(wrapper.html()).not.toContain('v-html')
    // Header monogram initials for Acme -> AC
    expect(wrapper.text()).toContain('AC')
  })

  it('renders clean website domain without raw https prefix in visible text', async () => {
    mockStore.status = 'completed'
    mockStore.isCompleted = true
    mockStore.isNotResearched = false
    mockStore.data = {
      status: 'completed',
      researched_at: '2026-08-23T12:00:00Z',
      stale: false,
      stale_reason: null,
      version: 1,
      fallback_reason: null,
      overview: {
        name: 'Reiz Tech',
        website: 'https://reiz-tech.com',
        industry: 'IT Services',
        headquarters: 'Vilnius',
        description: 'International IT Services',
      },
      products: [],
      technology_context: [],
      role_context: [],
      recent_information: [],
      candidate_preparation: [],
      sources: [
        {
          id: 0,
          url: 'https://reiz-tech.com',
          title: 'Official Website',
          retrieved_at: '2026-08-23T12:00:00Z',
          source_type: 'official_website',
        },
      ],
      generated_at: '2026-08-23T12:00:00Z',
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })

    // Domain should appear as visible text
    expect(wrapper.text()).toContain('reiz-tech.com')
    // Find website link in header — its visible text is domain, not raw URL
    const websiteLinks = wrapper
      .findAll('a')
      .filter((a) => a.attributes('href') === 'https://reiz-tech.com')
    expect(websiteLinks.length).toBeGreaterThan(0)
    // At least one of those links should have text exactly the domain (not with https)
    const hasCleanDomainLink = websiteLinks.some((a) => a.text().trim() === 'reiz-tech.com')
    expect(hasCleanDomainLink).toBe(true)
    // href retains full safe URL for navigation
    expect(websiteLinks[0]!.attributes('href')).toBe('https://reiz-tech.com')
  })

  it('hides missing metadata fields instead of showing ugly placeholders', async () => {
    mockStore.status = 'completed'
    mockStore.isCompleted = true
    mockStore.isNotResearched = false
    mockStore.data = {
      status: 'completed',
      researched_at: '2026-08-23T12:00:00Z',
      stale: false,
      stale_reason: null,
      version: 1,
      fallback_reason: null,
      overview: {
        name: 'Minimal Co',
        website: null,
        industry: null,
        headquarters: null,
        description: null,
      },
      products: [],
      technology_context: [],
      role_context: [],
      recent_information: [],
      candidate_preparation: [],
      sources: [],
      generated_at: '2026-08-23T12:00:00Z',
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })

    // None of these labels should appear when value missing
    expect(wrapper.text()).not.toContain('Industry')
    expect(wrapper.text()).not.toContain('Headquarters')
    // Website metadata row should be absent
    expect(wrapper.text()).not.toContain('Website')
    // About Company card should be hidden when no description
    expect(wrapper.text()).not.toContain('About Company')
  })

  it('derives confidence badge from claim confidences', async () => {
    mockStore.status = 'completed'
    mockStore.isCompleted = true
    mockStore.isNotResearched = false
    mockStore.data = {
      status: 'completed',
      researched_at: '2026-08-23T12:00:00Z',
      stale: false,
      stale_reason: null,
      version: 1,
      fallback_reason: null,
      overview: {
        name: 'Acme',
        website: null,
        industry: null,
        headquarters: null,
        description: 'Desc',
      },
      products: [
        { text: 'Fact high', kind: 'fact', confidence: 'high', source_ids: [0] },
        { text: 'Fact high 2', kind: 'fact', confidence: 'high', source_ids: [0] },
      ],
      technology_context: [
        { text: 'Inference medium', kind: 'inference', confidence: 'medium', source_ids: [0] },
      ],
      role_context: [],
      recent_information: [],
      candidate_preparation: [],
      sources: [
        {
          id: 0,
          url: 'https://example.com',
          title: 'Src',
          retrieved_at: '2026-08-23T12:00:00Z',
          source_type: 'official_website',
        },
      ],
      generated_at: '2026-08-23T12:00:00Z',
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    // ResearchSummaryPanel should show High confidence (2 high vs 1 medium)
    expect(wrapper.text()).toContain('Confidence')
    expect(wrapper.text()).toContain('High')

    // Change to low dominant and ensure Low appears
    mockStore.data = {
      ...(mockStore.data as object),
      products: [
        { text: 'Low', kind: 'fact', confidence: 'low', source_ids: [0] },
        { text: 'Low 2', kind: 'fact', confidence: 'low', source_ids: [0] },
      ],
      technology_context: [{ text: 'High', kind: 'fact', confidence: 'high', source_ids: [0] }],
    } as unknown
    const wrapper2 = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    expect(wrapper2.text()).toContain('Low')
  })

  it('shows Research summary panel with freshness, status and last researched date', async () => {
    mockStore.status = 'completed'
    mockStore.isCompleted = true
    mockStore.isNotResearched = false
    mockStore.data = {
      status: 'completed',
      researched_at: '2026-08-23T12:00:00Z',
      stale: false,
      stale_reason: null,
      version: 1,
      fallback_reason: null,
      overview: {
        name: 'Acme',
        website: null,
        industry: null,
        headquarters: null,
        description: null,
      },
      products: [],
      technology_context: [],
      role_context: [],
      recent_information: [],
      candidate_preparation: [],
      sources: [],
      generated_at: '2026-08-23T12:00:00Z',
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    expect(wrapper.text()).toContain('Research summary')
    expect(wrapper.text()).toContain('Research status')
    expect(wrapper.text()).toContain('Completed')
    expect(wrapper.text()).toContain('Fresh')
    expect(wrapper.text()).toContain('Last researched')
    // Refresh button exists
    expect(wrapper.text()).toContain('Refresh research')
  })

  it('shows sources provenance table with domain', async () => {
    mockStore.status = 'completed'
    mockStore.isCompleted = true
    mockStore.isNotResearched = false
    mockStore.data = {
      status: 'completed',
      researched_at: '2026-08-23T12:00:00Z',
      stale: false,
      stale_reason: null,
      version: 1,
      fallback_reason: null,
      overview: {
        name: 'Acme',
        website: null,
        industry: null,
        headquarters: null,
        description: null,
      },
      products: [],
      technology_context: [],
      role_context: [],
      recent_information: [],
      candidate_preparation: [],
      sources: [
        {
          id: 0,
          url: 'https://acme.example.com',
          title: 'Official Website',
          retrieved_at: '2026-08-23T12:00:00Z',
          source_type: 'official_website',
        },
      ],
      generated_at: '2026-08-23T12:00:00Z',
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })

    expect(wrapper.text()).toContain('acme.example.com')
    expect(wrapper.text()).toContain('Source & provenance')
  })

  it('renders clean empty state for recent information', async () => {
    mockStore.status = 'completed'
    mockStore.isCompleted = true
    mockStore.isNotResearched = false
    mockStore.data = {
      status: 'completed',
      researched_at: '2026-08-23T12:00:00Z',
      stale: false,
      stale_reason: null,
      version: 1,
      fallback_reason: null,
      overview: {
        name: 'Acme',
        website: null,
        industry: null,
        headquarters: null,
        description: null,
      },
      products: [],
      technology_context: [],
      role_context: [],
      recent_information: [],
      candidate_preparation: [],
      sources: [],
      generated_at: '2026-08-23T12:00:00Z',
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    expect(wrapper.text()).toContain('Recent Information')
    expect(wrapper.text()).toContain('No recent verified company updates available.')
  })

  it('shows recent information items when present instead of empty state', async () => {
    mockStore.status = 'completed'
    mockStore.isCompleted = true
    mockStore.isNotResearched = false
    mockStore.data = {
      status: 'completed',
      researched_at: '2026-08-23T12:00:00Z',
      stale: false,
      stale_reason: null,
      version: 1,
      fallback_reason: null,
      overview: {
        name: 'Acme',
        website: null,
        industry: null,
        headquarters: null,
        description: null,
      },
      products: [],
      technology_context: [],
      role_context: [],
      recent_information: [
        {
          text: 'Launched new platform in 2026',
          kind: 'fact',
          confidence: 'high',
          source_ids: [0],
        },
      ],
      candidate_preparation: [],
      sources: [
        {
          id: 0,
          url: 'https://example.com',
          title: 'Src',
          retrieved_at: '2026-08-23T12:00:00Z',
          source_type: 'official_website',
        },
      ],
      generated_at: '2026-08-23T12:00:00Z',
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    expect(wrapper.text()).toContain('Launched new platform in 2026')
    expect(wrapper.text()).not.toContain('No recent verified company updates available.')
  })

  it('renders Engineering Culture card only when inference exists and marks INFERENCE', async () => {
    mockStore.status = 'completed'
    mockStore.isCompleted = true
    mockStore.isNotResearched = false
    // With inference in technology_context -> Engineering Culture should appear
    mockStore.data = {
      status: 'completed',
      researched_at: '2026-08-23T12:00:00Z',
      stale: false,
      stale_reason: null,
      version: 1,
      fallback_reason: null,
      overview: {
        name: 'Acme',
        website: null,
        industry: null,
        headquarters: null,
        description: null,
      },
      products: [],
      technology_context: [
        { text: 'Stack is PHP/Symfony', kind: 'fact', confidence: 'high', source_ids: [0] },
        {
          text: 'Based on careers page, engineering culture emphasizes mentorship',
          kind: 'inference',
          confidence: 'medium',
          source_ids: [0],
        },
      ],
      role_context: [],
      recent_information: [],
      candidate_preparation: [],
      sources: [
        {
          id: 0,
          url: 'https://example.com',
          title: 'Src',
          retrieved_at: '2026-08-23T12:00:00Z',
          source_type: 'official_website',
        },
      ],
      generated_at: '2026-08-23T12:00:00Z',
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    expect(wrapper.text()).toContain('Engineering Culture')
    expect(wrapper.text()).toContain('INFERENCE')

    // Without inference -> card hidden
    mockStore.data = {
      ...(mockStore.data as object),
      technology_context: [
        { text: 'Stack is PHP', kind: 'fact', confidence: 'high', source_ids: [0] },
      ],
    } as unknown
    const wrapper2 = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    expect(wrapper2.text()).not.toContain('Engineering Culture')
  })

  it('renders source provenance badges FACT/INFERENCE/UNKNOWN with correct variants', async () => {
    mockStore.status = 'completed'
    mockStore.isCompleted = true
    mockStore.isNotResearched = false
    mockStore.data = {
      status: 'completed',
      researched_at: '2026-08-23T12:00:00Z',
      stale: false,
      stale_reason: null,
      version: 1,
      fallback_reason: null,
      overview: {
        name: 'Acme',
        website: null,
        industry: null,
        headquarters: null,
        description: null,
      },
      products: [{ text: 'Product fact', kind: 'fact', confidence: 'high', source_ids: [0] }],
      technology_context: [
        { text: 'Inference', kind: 'inference', confidence: 'medium', source_ids: [1] },
      ],
      role_context: [],
      recent_information: [
        { text: 'Unknown item', kind: 'unknown', confidence: null, source_ids: [] },
      ],
      candidate_preparation: [],
      sources: [
        {
          id: 0,
          url: 'https://official.example.com',
          title: 'Official Website',
          retrieved_at: '2026-08-23T12:00:00Z',
          source_type: 'official_website',
        },
        {
          id: 1,
          url: 'https://careers.example.com',
          title: 'Careers Page',
          retrieved_at: '2026-08-23T12:00:00Z',
          source_type: 'careers_page',
        },
        {
          id: 2,
          url: 'https://unlinked.example.com',
          title: 'Unlinked',
          retrieved_at: '2026-08-23T12:00:00Z',
          source_type: 'other',
        },
      ],
      generated_at: '2026-08-23T12:00:00Z',
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    // Table should contain badges for each source kind
    expect(wrapper.text()).toContain('FACT')
    expect(wrapper.text()).toContain('INFERENCE')
    expect(wrapper.text()).toContain('UNKNOWN')
    // Supports column should map product/tech supports
    expect(wrapper.text()).toContain('Products & services')
    expect(wrapper.text()).toContain('Technology')
  })

  it('shows failed with retry', async () => {
    mockStore.status = 'failed'
    mockStore.isFailed = true
    mockStore.isNotResearched = false
    mockStore.errorDetail = 'Provider unavailable'

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    expect(wrapper.text()).toContain('Research failed')
    expect(wrapper.text()).toContain('Retry research')
  })

  it('shows limited fallback notice', async () => {
    mockStore.status = 'limited'
    mockStore.isCompleted = true
    mockStore.isNotResearched = false
    mockStore.data = {
      status: 'limited',
      researched_at: '2026-08-23T12:00:00Z',
      stale: false,
      stale_reason: null,
      version: 1,
      fallback_reason: 'fetch_unavailable',
      overview: {
        name: 'Acme',
        website: null,
        industry: null,
        headquarters: null,
        description: 'Limited',
      },
      products: [],
      technology_context: [{ text: 'Unknown', kind: 'unknown', confidence: null, source_ids: [] }],
      role_context: [],
      recent_information: [],
      candidate_preparation: [],
      sources: [],
      generated_at: '2026-08-23T12:00:00Z',
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    expect(wrapper.text()).toContain('Limited research')
    expect(wrapper.text()).toContain('fetch_unavailable')
  })

  it('shows stale badge', async () => {
    mockStore.status = 'completed'
    mockStore.isCompleted = true
    mockStore.isNotResearched = false
    mockStore.data = {
      status: 'completed',
      researched_at: '2026-07-01T12:00:00Z',
      stale: true,
      stale_reason: 'research_outdated',
      version: 1,
      fallback_reason: null,
      overview: {
        name: 'Acme',
        website: null,
        industry: null,
        headquarters: null,
        description: null,
      },
      products: [],
      technology_context: [],
      role_context: [],
      recent_information: [],
      candidate_preparation: [],
      sources: [],
      generated_at: '2026-07-01T12:00:00Z',
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    expect(wrapper.text()).toContain('Potentially outdated')
  })

  it('escapes malicious source title in provenance table', async () => {
    mockStore.status = 'completed'
    mockStore.isCompleted = true
    mockStore.isNotResearched = false
    mockStore.data = {
      status: 'completed',
      researched_at: '2026-08-23T12:00:00Z',
      stale: false,
      stale_reason: null,
      version: 1,
      fallback_reason: null,
      overview: {
        name: 'Acme',
        website: null,
        industry: null,
        headquarters: null,
        description: null,
      },
      products: [],
      technology_context: [],
      role_context: [],
      recent_information: [],
      candidate_preparation: [],
      sources: [
        {
          id: 0,
          url: 'https://example.com',
          title: '<img onerror=alert(1)>',
          retrieved_at: '2026-08-23T12:00:00Z',
          source_type: 'official_website',
        },
      ],
      generated_at: '2026-08-23T12:00:00Z',
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    // Provenance table is always visible; title should be escaped
    expect(wrapper.html()).not.toContain('<img')
    expect(wrapper.text()).toContain('<img onerror=alert(1)>')
  })

  it('disables refresh while processing', async () => {
    mockStore.status = 'processing'
    mockStore.isProcessing = true
    mockStore.isNotResearched = false
    mockStore.data = {
      status: 'processing',
      researched_at: null,
      stale: false,
      stale_reason: null,
      version: 1,
      fallback_reason: null,
      overview: null,
      products: [],
      technology_context: [],
      role_context: [],
      recent_information: [],
      candidate_preparation: [],
      sources: [],
      generated_at: null,
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    expect(wrapper.text()).toContain('Researching company')
  })

  it('hides technology and products cards when empty', async () => {
    mockStore.status = 'completed'
    mockStore.isCompleted = true
    mockStore.isNotResearched = false
    mockStore.data = {
      status: 'completed',
      researched_at: '2026-08-23T12:00:00Z',
      stale: false,
      stale_reason: null,
      version: 1,
      fallback_reason: null,
      overview: {
        name: 'Acme',
        website: null,
        industry: null,
        headquarters: null,
        description: null,
      },
      products: [],
      technology_context: [],
      role_context: [],
      recent_information: [],
      candidate_preparation: [],
      sources: [],
      generated_at: '2026-08-23T12:00:00Z',
      ai_meta: null,
    } as unknown

    const wrapper = mount(CompanyResearchSection as unknown as never, {
      props: { opportunityId: 8 },
    })
    expect(wrapper.text()).not.toContain('Products & Services')
    expect(wrapper.text()).not.toContain('Technology & Engineering')
  })
})
