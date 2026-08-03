import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ReviewTimeline from '@/features/opportunities/components/ReviewTimeline.vue'

const steps = [
  {
    key: 'overview',
    label: 'Overview',
    itemCount: 2,
    reviewedCount: 2,
    unresolvedCount: 0,
  },
  {
    key: 'work',
    label: 'Work details',
    itemCount: 3,
    reviewedCount: 1,
    unresolvedCount: 2,
  },
  {
    key: 'skills',
    label: 'Required skills with a deliberately long section name',
    itemCount: 4,
    reviewedCount: 0,
    unresolvedCount: 4,
  },
  {
    key: 'final',
    label: 'Final review',
    itemCount: 9,
    reviewedCount: 3,
    unresolvedCount: 6,
    kind: 'summary' as const,
  },
]

function desktopButtons(wrapper: ReturnType<typeof mount>) {
  return wrapper.get('nav[aria-label="Opportunity review sections"]').findAll('button')
}

describe('ReviewTimeline', () => {
  it('keeps current selection independent from completion status', () => {
    const wrapper = mount(ReviewTimeline, { props: { steps, currentIndex: 0 } })
    const active = wrapper.get(
      'nav[aria-label="Opportunity review sections"] [aria-current="step"]',
    )

    expect(active.text()).toContain('Overview')
    expect(active.attributes('data-status')).toBe('complete')
    expect(active.attributes('aria-label')).toContain('complete')
    expect(active.attributes('aria-label')).toContain('current step')
  })

  it('exposes complete, in-progress, not-started, and blocked summary states', () => {
    const wrapper = mount(ReviewTimeline, { props: { steps, currentIndex: 1 } })
    const buttons = desktopButtons(wrapper)

    expect(buttons.map((button) => button.attributes('data-status'))).toEqual([
      'complete',
      'in-progress',
      'not-started',
      'blocked',
    ])
    expect(buttons[2]?.text()).toContain('Required skills with a deliberately long section name')
    expect(buttons[3]?.attributes('aria-label')).toContain('6 items remaining')
  })

  it('shows a resolved final summary as ready', () => {
    const readySteps = [
      ...steps.slice(0, -1),
      {
        ...steps[3],
        reviewedCount: 9,
        unresolvedCount: 0,
      },
    ]
    const wrapper = mount(ReviewTimeline, {
      props: { steps: readySteps, currentIndex: 3 },
    })
    const finalStep = desktopButtons(wrapper)[3]

    expect(finalStep?.attributes('data-status')).toBe('ready')
    expect(finalStep?.attributes('aria-label')).toContain('ready for final review')
  })

  it('emits direct selection for every visible step', async () => {
    const wrapper = mount(ReviewTimeline, { props: { steps, currentIndex: 0 } })

    await desktopButtons(wrapper)[3]?.trigger('click')

    expect(wrapper.emitted('select')?.[0]).toEqual([3])
  })

  it('moves desktop selection and focus with arrows, Home, and End', async () => {
    const wrapper = mount(ReviewTimeline, {
      attachTo: document.body,
      props: { steps, currentIndex: 0 },
    })
    const buttons = desktopButtons(wrapper)

    buttons[0]?.element.focus()
    await buttons[0]?.trigger('keydown', { key: 'ArrowDown' })
    expect(wrapper.emitted('select')?.[0]).toEqual([1])
    expect(document.activeElement).toBe(buttons[1]?.element)

    await buttons[1]?.trigger('keydown', { key: 'ArrowUp' })
    expect(wrapper.emitted('select')?.[1]).toEqual([0])
    expect(document.activeElement).toBe(buttons[0]?.element)

    await buttons[0]?.trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.emitted('select')?.[2]).toEqual([1])
    expect(document.activeElement).toBe(buttons[1]?.element)

    await buttons[1]?.trigger('keydown', { key: 'End' })
    expect(wrapper.emitted('select')?.[3]).toEqual([3])
    expect(document.activeElement).toBe(buttons[3]?.element)

    await buttons[3]?.trigger('keydown', { key: 'Home' })
    expect(wrapper.emitted('select')?.[4]).toEqual([0])
    expect(document.activeElement).toBe(buttons[0]?.element)

    await buttons[0]?.trigger('keydown', { key: 'ArrowLeft' })
    expect(wrapper.emitted('select')).toHaveLength(5)
    expect(document.activeElement).toBe(buttons[0]?.element)
    wrapper.unmount()
  })

  it('renders the compact mobile step indicator', () => {
    const wrapper = mount(ReviewTimeline, {
      props: { steps, currentIndex: 1 },
    })

    const indicator = wrapper.get('nav[aria-label="Current review step"]')
    expect(indicator.text()).toContain('Step 2 of 4')
    expect(indicator.text()).toContain('Work details')
  })

  it('renders mobile step progress bar', () => {
    const wrapper = mount(ReviewTimeline, {
      props: { steps, currentIndex: 1 },
    })

    const progressbar = wrapper.get('nav[aria-label="Current review step"] [role="progressbar"]')
    expect(progressbar.attributes('aria-valuenow')).toBe('2')
    expect(progressbar.attributes('aria-valuemax')).toBe('4')
  })

  it('guards empty steps and clamps out-of-range current indexes', () => {
    const empty = mount(ReviewTimeline, { props: { steps: [], currentIndex: 7 } })
    expect(empty.find('nav').exists()).toBe(false)

    const negative = mount(ReviewTimeline, { props: { steps, currentIndex: -4 } })
    expect(
      negative.get('nav[aria-label="Opportunity review sections"] [aria-current="step"]').text(),
    ).toContain('Overview')

    const tooLarge = mount(ReviewTimeline, { props: { steps, currentIndex: 99 } })
    expect(
      tooLarge.get('nav[aria-label="Opportunity review sections"] [aria-current="step"]').text(),
    ).toContain('Final review')
  })
})
