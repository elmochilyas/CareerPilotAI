import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import IngestionRow from '@/features/opportunities/components/IngestionRow.vue'
import type { JobIngestion } from '@/features/opportunities/types'

function createIngestion(overrides: Partial<JobIngestion> = {}): JobIngestion {
  return {
    id: 14,
    status: 'review_ready',
    source_url: null,
    personal_label: 'Backend role · Essen',
    failure_reason: null,
    failure_code: null,
    retry_count: 0,
    last_retry_at: null,
    confirmed_at: null,
    version: 1,
    confirmed_opportunity_id: null,
    created_at: '2026-07-27T10:00:00Z',
    updated_at: '2026-07-27T10:00:00Z',
    ...overrides,
  }
}

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/opportunities/ingestions/:id', component: { template: '<div />' } }],
})

function mountIngestionRow(ingestion = createIngestion()) {
  return mount(IngestionRow, {
    props: {
      ingestion,
      to: `/opportunities/ingestions/${ingestion.id}`,
    },
    global: { plugins: [router] },
  })
}

describe('IngestionRow', () => {
  it('surfaces the label, status, and next action', () => {
    const wrapper = mountIngestionRow()

    expect(wrapper.text()).toContain('Backend role · Essen')
    expect(wrapper.text()).toContain('Ready to review')
    expect(wrapper.text()).toContain('Review extracted details')
  })

  it('renders the ingestion action as a semantic navigation link', () => {
    const wrapper = mountIngestionRow(createIngestion({ status: 'cancelled' }))

    expect(wrapper.get('a').attributes('href')).toBe('/opportunities/ingestions/14')
    expect(wrapper.text()).toContain('Reanalyze this job')
  })
})
