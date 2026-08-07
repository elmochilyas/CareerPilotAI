import { afterEach, describe, expect, it } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import ClarificationQuestionStep from '@/features/clarification/components/ClarificationQuestionStep.vue'
import type { ClarificationQuestion } from '@/features/clarification/types'

function makeQuestion(overrides: Partial<ClarificationQuestion> = {}): ClarificationQuestion {
  return {
    id: 1,
    question_no: 1,
    question_type: 'yes_no',
    prompt: 'Do you have professional Laravel experience?',
    detail: 'We ask this to verify a required skill for the job.',
    template_key: 'skill_evidence_confirm',
    options: null,
    unit: null,
    status: 'pending',
    requirement: { text: 'Laravel (required)', label: 'Required skill' },
    answer: null,
    ...overrides,
  }
}

function mountStep(question: ClarificationQuestion, props: Partial<Record<string, unknown>> = {}) {
  return mount(ClarificationQuestionStep, {
    props: {
      question,
      busy: false,
      error: null,
      canGoBack: false,
      ...props,
    },
    attachTo: document.body,
  })
}

function lastSubmit(wrapper: VueWrapper): Record<string, unknown> {
  const emits = wrapper.emitted('submit')
  expect(emits).toBeTruthy()
  return (emits!.at(-1)![0] as Record<string, unknown>) ?? {}
}

describe('ClarificationQuestionStep', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('shows the requirement context, prompt, and the evidence disclosure', () => {
    const wrapper = mountStep(makeQuestion())

    expect(wrapper.text()).toContain('Required skill')
    expect(wrapper.text()).toContain('Laravel (required)')
    expect(wrapper.text()).toContain('Do you have professional Laravel experience?')
    expect(wrapper.find('summary').text()).toContain('Why is this asked?')
  })

  it('submits a plain yes/no answer once a choice is made', async () => {
    const wrapper = mountStep(makeQuestion({ question_type: 'yes_no' }))

    await wrapper.find('input[value="no"]').setValue(true)
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')

    expect(lastSubmit(wrapper)).toEqual({
      answer_type: 'no',
      value: '',
      acknowledged_no_evidence: false,
    })
  })

  it('validates that a yes/no choice is required and focuses the first radio', async () => {
    const wrapper = mountStep(makeQuestion({ question_type: 'yes_no' }))

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')

    expect(wrapper.find('[role="alert"]').text()).toContain('Choose an answer before submitting.')
    expect(document.activeElement).toBe(wrapper.find('input[type="radio"]').element)
  })

  it('requires an HTTPS evidence URL when answering yes with details', async () => {
    const wrapper = mountStep(makeQuestion({ question_type: 'yes_no_with_details' }))

    await wrapper.find('input[value="yes"]').setValue(true)
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')
    expect(wrapper.find('[role="alert"]').text()).toContain('Evidence URL is required.')

    const url = wrapper.find('input[name="evidence-url"]')
    await url.setValue('javascript:alert(1)')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')
    expect(wrapper.find('[role="alert"]').text()).toContain('This URL scheme is not allowed.')

    await url.setValue('http://example.com/cert')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')
    expect(wrapper.find('[role="alert"]').text()).toContain(
      'Only HTTPS URLs are accepted as evidence.',
    )

    await url.setValue('https://example.com/cert')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')

    expect(lastSubmit(wrapper)).toEqual({
      answer_type: 'yes',
      value: 'https://example.com/cert',
      acknowledged_no_evidence: false,
    })
  })

  it('accepts a yes answer without evidence when acknowledged', async () => {
    const wrapper = mountStep(makeQuestion({ question_type: 'yes_no_with_details' }))

    await wrapper.find('input[value="yes"]').setValue(true)
    await wrapper.find('input[type="checkbox"]').setValue(true)

    const url = wrapper.find('input[name="evidence-url"]')
    expect((url.element as HTMLInputElement).disabled).toBe(true)

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')

    expect(lastSubmit(wrapper)).toEqual({
      answer_type: 'yes',
      value: '',
      acknowledged_no_evidence: true,
    })
  })

  it('records a no answer as an acknowledged missing item', async () => {
    const wrapper = mountStep(makeQuestion({ question_type: 'yes_no_with_details' }))

    await wrapper.find('input[value="no"]').setValue(true)
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')

    expect(lastSubmit(wrapper)).toEqual({
      answer_type: 'no_with_ack',
      value: '',
      acknowledged_no_evidence: true,
    })
  })

  it('validates and submits a free-text answer', async () => {
    const wrapper = mountStep(makeQuestion({ question_type: 'text' }))

    const textarea = wrapper.find('textarea[name="clarification-text"]')
    expect(textarea.attributes('maxlength')).toBe('2000')

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')
    expect(wrapper.find('[role="alert"]').text()).toContain('Enter a value before submitting.')

    await textarea.setValue('  Managed a team of five  ')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')

    expect(lastSubmit(wrapper)).toEqual({
      answer_type: 'text',
      value: 'Managed a team of five',
      acknowledged_no_evidence: false,
    })
  })

  it('renders select options as a labelled radio group and submits the choice', async () => {
    const wrapper = mountStep(
      makeQuestion({
        question_type: 'select',
        options: ['1–2 years', '3–5 years'],
      }),
    )

    const radios = wrapper.findAll('input[name="select-option"]')
    expect(radios.length).toBe(2)
    expect(wrapper.find('p[id="select-option-label"]').text()).toBe('Choose one')

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')
    expect(wrapper.find('[role="alert"]').text()).toContain('Choose an option before submitting.')

    await wrapper.find('input[value="3–5 years"]').setValue(true)
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')

    expect(lastSubmit(wrapper)).toEqual({
      answer_type: 'select_option',
      value: '3–5 years',
      acknowledged_no_evidence: false,
    })
  })

  it('validates a number between 0 and 100 and submits it', async () => {
    const wrapper = mountStep(
      makeQuestion({
        question_type: 'number',
        prompt: 'How many years of Laravel experience?',
        unit: 'years',
      }),
    )

    expect(wrapper.find('label[for="clarification-number"]').text()).toContain('Value (years)')

    const number = wrapper.find('input[name="clarification-number"]')

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')
    expect(wrapper.find('[role="alert"]').text()).toContain('Enter a value before submitting.')

    await number.setValue('150')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')
    expect(wrapper.find('[role="alert"]').text()).toContain('Enter a number between 0 and 100.')

    await number.setValue('-1')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')
    expect(wrapper.find('[role="alert"]').text()).toContain('Enter a number between 0 and 100.')

    await number.setValue('7')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Submit answer')!
      .trigger('click')

    expect(lastSubmit(wrapper)).toEqual({
      answer_type: 'number',
      value: '7',
      acknowledged_no_evidence: false,
    })
  })

  it('emits skip and back, and hides back when it is the first question', async () => {
    const first = mountStep(makeQuestion())
    expect(first.findAll('button').some((button) => button.text() === 'Back')).toBe(false)

    const later = mountStep(makeQuestion(), { canGoBack: true })
    await later
      .findAll('button')
      .find((button) => button.text() === 'Back')!
      .trigger('click')
    expect(later.emitted('back')).toHaveLength(1)

    await later
      .findAll('button')
      .find((button) => button.text() === 'Skip question')!
      .trigger('click')
    expect(later.emitted('skip')).toHaveLength(1)
  })

  it('disables the actions while a request is in flight', async () => {
    const wrapper = mountStep(makeQuestion({ question_type: 'text' }), { busy: true })

    for (const button of wrapper.findAll('button')) {
      expect((button.element as HTMLButtonElement).disabled).toBe(true)
    }
  })

  it('renders a server error and the field labels accessibly', async () => {
    const wrapper = mountStep(makeQuestion({ question_type: 'yes_no_with_details' }), {
      error: 'The answer could not be saved.',
    })

    await wrapper.find('input[value="yes"]').setValue(true)

    expect(wrapper.find('[role="alert"]').text()).toContain('The answer could not be saved.')
    expect(wrapper.find('div[role="radiogroup"]').attributes('aria-labelledby')).toBe(
      'answer-choice-label',
    )
    expect(wrapper.find('label[for="evidence-url"]').text()).toContain('Evidence URL')
  })
})
