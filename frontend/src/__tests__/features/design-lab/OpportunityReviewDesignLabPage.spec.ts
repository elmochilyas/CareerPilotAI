import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import DesignLabResponsibilityRow from '@/features/design-lab/opportunity-review/DesignLabResponsibilityRow.vue'
import OpportunityReviewDesignLabPage from '@/features/design-lab/opportunity-review/OpportunityReviewDesignLabPage.vue'

const responsibility =
  'Konzeption und kontinuierliche Optimierung skalierbarer Backend-Funktionen mit Node.js.'

describe('OpportunityReviewDesignLabPage', () => {
  it('renders the isolated editorial review prototype with hardcoded data', () => {
    const wrapper = mount(OpportunityReviewDesignLabPage)

    expect(wrapper.get('h1').text()).toBe('Opportunity review')
    expect(wrapper.text()).toContain('Ratbacher GmbH')
    expect(wrapper.text()).toContain('0 of 22 reviewed')
    expect(wrapper.findAll('.responsibility')).toHaveLength(5)
  })

  it('renders the compact mobile step indicator in the document', () => {
    const wrapper = mount(OpportunityReviewDesignLabPage)

    expect(wrapper.get('.mobile-step').text()).toContain('Step 3 of 8')
    expect(wrapper.get('.mobile-step').text()).toContain('Responsibilities')
  })
})

describe('DesignLabResponsibilityRow', () => {
  it('includes a responsibility with one compact primary control', async () => {
    const wrapper = mount(DesignLabResponsibilityRow, {
      props: { index: 0, text: responsibility },
    })

    await wrapper.get('.include-control').trigger('click')

    expect(wrapper.text()).toContain('Included')
    expect(wrapper.get('.include-control').attributes('aria-pressed')).toBe('true')
  })

  it('moves secondary actions into an accessible overflow disclosure', async () => {
    const wrapper = mount(DesignLabResponsibilityRow, {
      props: { index: 0, text: responsibility },
    })

    expect(wrapper.get('.more-actions summary').attributes('aria-label')).toBe(
      'More actions for responsibility 1',
    )

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Exclude')!
      .trigger('click')

    expect(wrapper.text()).toContain('Excluded')
    expect(wrapper.text()).toContain('Restore responsibility')
  })

  it('supports the edited prototype state and preserves the original text', async () => {
    const wrapper = mount(DesignLabResponsibilityRow, {
      props: { index: 0, text: responsibility },
    })

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Edit responsibility')!
      .trigger('click')
    await wrapper.get('textarea').setValue('Edited responsibility text.')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.text()).toContain('Edited responsibility text.')
    expect(wrapper.text()).toContain('Edited')
    expect(wrapper.text()).toContain('View original text')
  })
})
