import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import OpportunityPageHeader from '@/features/opportunities/components/OpportunityPageHeader.vue'

describe('OpportunityPageHeader', () => {
  it('renders one clear page heading with supporting content and actions', () => {
    const wrapper = mount(OpportunityPageHeader, {
      props: {
        title: 'Opportunities',
        description: 'Track the roles that matter.',
      },
      slots: {
        meta: '<span>2 saved</span>',
        actions: '<button type="button">Add opportunity</button>',
      },
    })

    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(wrapper.get('h1').text()).toBe('Opportunities')
    expect(wrapper.text()).toContain('Track the roles that matter.')
    expect(wrapper.text()).toContain('2 saved')
    expect(wrapper.text()).toContain('Add opportunity')
  })

  it('emits back when the optional back control is activated', async () => {
    const wrapper = mount(OpportunityPageHeader, {
      props: {
        title: 'Review job information',
        backLabel: 'Opportunity desk',
      },
    })

    await wrapper.get('button').trigger('click')

    expect(wrapper.emitted('back')).toHaveLength(1)
  })

  it('omits optional regions when they are not supplied', () => {
    const wrapper = mount(OpportunityPageHeader, {
      props: {
        title: 'Processing job description',
      },
    })

    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.find('.opportunity-description').exists()).toBe(false)
    expect(wrapper.find('.opportunity-meta').exists()).toBe(false)
  })
})
