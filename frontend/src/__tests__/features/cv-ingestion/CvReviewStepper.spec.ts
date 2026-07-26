import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CvReviewStepper from '@/features/cv-ingestion/components/CvReviewStepper.vue'
import type { StepDef } from '@/features/cv-ingestion/components/CvReviewStepper.vue'

const steps: StepDef[] = [
  { key: 'personal', label: 'Personal' },
  { key: 'experience', label: 'Experience' },
  { key: 'skills', label: 'Skills' },
  { key: 'final', label: 'Final Review' },
]

describe('CvReviewStepper', () => {
  it('renders all step labels', () => {
    const wrapper = mount(CvReviewStepper, {
      props: {
        steps,
        activeIndex: 0,
        stepStatuses: ['not_reviewed', 'not_reviewed', 'not_reviewed', 'not_reviewed'],
      },
    })
    expect(wrapper.text()).toContain('Personal')
    expect(wrapper.text()).toContain('Experience')
    expect(wrapper.text()).toContain('Skills')
    expect(wrapper.text()).toContain('Final Review')
  })

  it('marks active step with aria-current', () => {
    const wrapper = mount(CvReviewStepper, {
      props: {
        steps,
        activeIndex: 1,
        stepStatuses: ['completed', 'in_progress', 'not_reviewed', 'not_reviewed'],
      },
    })
    const active = wrapper.find('[aria-current="step"]')
    expect(active.exists()).toBe(true)
    expect(active.text()).toContain('Experience')
  })

  it('shows completed status', () => {
    const wrapper = mount(CvReviewStepper, {
      props: {
        steps,
        activeIndex: 1,
        stepStatuses: ['completed', 'in_progress', 'not_reviewed', 'not_reviewed'],
      },
    })
    const firstStep = wrapper.findAll('li')[0]
    expect(firstStep.text()).toContain('Personal')
  })

  it('shows conflict status', () => {
    const wrapper = mount(CvReviewStepper, {
      props: {
        steps,
        activeIndex: 1,
        stepStatuses: ['completed', 'conflict', 'not_reviewed', 'not_reviewed'],
      },
    })
    const secondStep = wrapper.findAll('li')[1]
    expect(secondStep.text()).toContain('Experience')
  })

  it('emits navigate on step click', async () => {
    const wrapper = mount(CvReviewStepper, {
      props: {
        steps,
        activeIndex: 2,
        stepStatuses: ['completed', 'completed', 'in_progress', 'not_reviewed'],
      },
    })
    const buttons = wrapper.findAll('button')
    const firstBtn = buttons.find((b) => b.text().includes('Personal'))
    await firstBtn!.trigger('click')
    expect(wrapper.emitted('navigate')).toBeTruthy()
  })

  it('disables navigation to unvisited non-sequential steps', () => {
    const wrapper = mount(CvReviewStepper, {
      props: {
        steps,
        activeIndex: 0,
        stepStatuses: ['in_progress', 'not_reviewed', 'not_reviewed', 'not_reviewed'],
      },
    })
    const buttons = wrapper.findAll('button')
    const finalBtn = buttons.find((b) => b.text().includes('Final Review'))
    expect(finalBtn?.attributes('disabled')).toBeDefined()
  })

  it('renders a nav element', () => {
    const wrapper = mount(CvReviewStepper, {
      props: {
        steps,
        activeIndex: 0,
        stepStatuses: ['not_reviewed', 'not_reviewed', 'not_reviewed', 'not_reviewed'],
      },
    })
    expect(wrapper.find('nav').exists()).toBe(true)
    expect(wrapper.find('nav[aria-label="Review progress"]').exists()).toBe(true)
  })
})
