import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import OpportunityDashboardHero from '@/features/opportunities/components/OpportunityDashboardHero.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    {
      path: '/opportunities/import',
      name: 'opportunities-import',
      component: { template: '<div />' },
    },
  ],
})

describe('OpportunityDashboardHero', () => {
  it('presents the workspace heading, summary, and primary action', () => {
    const wrapper = mount(OpportunityDashboardHero, {
      props: {
        attentionCount: 1,
        activeCount: 2,
        savedCount: 3,
      },
      global: { plugins: [router] },
    })

    expect(wrapper.get('h1').text()).toBe('Opportunities')
    expect(wrapper.text()).toContain('Opportunity workspace')
    expect(wrapper.text()).toContain('Saved')
    expect(wrapper.text()).toContain('In progress')
    expect(wrapper.text()).toContain('Needs review')
    expect(wrapper.get('a').attributes('href')).toBe('/opportunities/import')
  })
})
