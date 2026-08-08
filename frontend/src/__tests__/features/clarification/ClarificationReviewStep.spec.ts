import { afterEach, describe, expect, it } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import ClarificationReviewStep from '@/features/clarification/components/ClarificationReviewStep.vue'
import type {
  ClarificationAnswer,
  ClarificationProposal,
  ClarificationQuestion,
} from '@/features/clarification/types'

function makeAnswer(overrides: Partial<ClarificationAnswer> = {}): ClarificationAnswer {
  return {
    id: 11,
    question_id: 1,
    answer_type: 'yes',
    value: 'https://example.com/cert',
    acknowledged_no_evidence: false,
    status: 'pending',
    proposal: null,
    ...overrides,
  }
}

function makeProposal(overrides: Partial<ClarificationProposal> = {}): ClarificationProposal {
  return {
    id: 21,
    answer_id: 11,
    target: { type: 'candidate_skill', id: 7 },
    field: 'evidence',
    before_value: [],
    after_value: { url: 'https://example.com/cert' },
    status: 'proposed',
    ...overrides,
  }
}

function makeQuestion(overrides: Partial<ClarificationQuestion> = {}): ClarificationQuestion {
  return {
    id: 1,
    question_no: 1,
    question_type: 'yes_no_with_details',
    prompt: 'Can you confirm your Laravel experience?',
    detail: null,
    template_key: 'skill_evidence_confirm',
    options: null,
    unit: null,
    status: 'answered',
    requirement: { text: 'Laravel (required)', label: 'Required skill' },
    answer: makeAnswer(),
    ...overrides,
  }
}

function mountStep(
  question: ClarificationQuestion,
  proposal: ClarificationProposal,
  props: Partial<Record<string, unknown>> = {},
) {
  return mount(ClarificationReviewStep, {
    props: {
      question,
      proposal,
      busy: false,
      error: null,
      canGoBack: false,
      ...props,
    },
    attachTo: document.body,
  })
}

function lastEdit(wrapper: VueWrapper): string {
  const emits = wrapper.emitted('edit')
  expect(emits).toBeTruthy()
  return emits!.at(-1)![0] as string
}

describe('ClarificationReviewStep', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('summarises the before/after change with the evidence basis', () => {
    const wrapper = mountStep(makeQuestion(), makeProposal())

    expect(wrapper.text()).toContain('Review the proposed change')
    expect(wrapper.text()).toContain('candidate skill #7')
    expect(wrapper.text()).toContain('Evidence')
    expect(wrapper.text()).toContain('Current value')
    expect(wrapper.text()).toContain('None')
    expect(wrapper.text()).toContain('Proposed value')
    expect(wrapper.text()).toContain('https://example.com/cert')
    expect(wrapper.text()).toContain('Evidence basis:')
  })

  it('emits accept, skip, and back', async () => {
    const wrapper = mountStep(makeQuestion(), makeProposal(), { canGoBack: true })

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Back')!
      .trigger('click')
    expect(wrapper.emitted('back')).toHaveLength(1)

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Skip')!
      .trigger('click')
    expect(wrapper.emitted('skip')).toHaveLength(1)

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Accept change')!
      .trigger('click')
    expect(wrapper.emitted('accept')).toHaveLength(1)
  })

  it('renders a years-experience proposal as human text, not raw JSON', () => {
    const wrapper = mountStep(
      makeQuestion({ question_type: 'number' }),
      makeProposal({
        field: 'state',
        before_value: { state: 'claimed' },
        after_value: { years_experience: 2, skill_id: 59 },
      }),
    )

    expect(wrapper.text()).toContain('Claimed')
    expect(wrapper.text()).toContain('2 years')
    expect(wrapper.text()).not.toContain('skill_id')
    expect(wrapper.text()).not.toContain('{')
  })

  it('labels evidence as acknowledged when no evidence was shared', () => {
    const wrapper = mountStep(
      makeQuestion({ answer: makeAnswer({ acknowledged_no_evidence: true, value: '' }) }),
      makeProposal(),
    )

    expect(wrapper.text()).toContain('No evidence shared — acknowledged by the candidate.')
  })

  it('edits the proposed value and emits the trimmed edit', async () => {
    const wrapper = mountStep(makeQuestion(), makeProposal())

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Edit value')!
      .trigger('click')

    const input = wrapper.find('input[name="edited-value"]')
    expect((input.element as HTMLInputElement).value).toBe('https://example.com/cert')

    await input.setValue('  https://example.com/project  ')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Save edit')!
      .trigger('click')

    expect(lastEdit(wrapper)).toBe('https://example.com/project')
  })

  it('rejects an empty edit and validates years experience range', async () => {
    const proposal = makeProposal({
      field: 'years_experience',
      before_value: null,
      after_value: 42,
    })
    const wrapper = mountStep(makeQuestion({ question_type: 'number' }), proposal)

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Edit value')!
      .trigger('click')

    const input = wrapper.find('input[name="edited-value"]')
    expect((input.element as HTMLInputElement).value).toBe('42')

    await input.setValue('   ')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Save edit')!
      .trigger('click')
    expect(wrapper.find('[role="alert"]').text()).toContain('Enter a value before saving.')

    await input.setValue('abc')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Save edit')!
      .trigger('click')
    expect(wrapper.find('[role="alert"]').text()).toContain('Enter a number between 0 and 100.')

    await input.setValue('150')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Save edit')!
      .trigger('click')
    expect(wrapper.find('[role="alert"]').text()).toContain('Enter a number between 0 and 100.')

    await input.setValue('7')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Save edit')!
      .trigger('click')
    expect(lastEdit(wrapper)).toBe('7')
  })

  it('never offers to edit a skill-state proposal', () => {
    const wrapper = mountStep(
      makeQuestion(),
      makeProposal({
        field: 'state',
        before_value: { state: 'claimed' },
        after_value: { state: 'verified' },
      }),
    )

    expect(wrapper.findAll('button').some((button) => button.text() === 'Edit value')).toBe(false)
    expect(wrapper.text()).toContain('Accept change')
  })

  it('disables the actions while a request is in flight and renders the server error', () => {
    const wrapper = mountStep(makeQuestion(), makeProposal(), {
      busy: true,
      error: 'The proposal could not be applied.',
    })

    const buttons = wrapper.findAll('button')
    const textButtons = buttons.filter((button) => button.text() !== '')
    for (const button of textButtons) {
      expect((button.element as HTMLButtonElement).disabled).toBe(true)
    }
    expect(wrapper.find('[role="alert"]').text()).toContain('The proposal could not be applied.')
  })
})
