import { beforeEach, describe, it, expect, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ImportJobPage from '@/features/opportunities/pages/ImportJobPage.vue'

const mocks = vi.hoisted(() => ({
  push: vi.fn<(to: string | Record<string, unknown>) => void>(),
  createIngestion: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  extractProblemDetail: vi.fn<(...args: unknown[]) => unknown>(),
}))

vi.mock('vue-router', async () => {
  const { defineComponent, h } = await import('vue')
  return {
    RouterLink: defineComponent({
      name: 'RouterLink',
      props: ['to'],
      setup(_, { slots }) {
        return () => h('a', slots.default?.())
      },
    }),
    useRouter: () => ({ push: mocks.push }),
    useRoute: () => ({ params: {} }),
  }
})

vi.mock('@/api/client', () => ({
  extractProblemDetail: mocks.extractProblemDetail,
}))

vi.mock('@/features/opportunities/api', () => ({
  createIngestion: mocks.createIngestion,
}))

describe('ImportJobPage', () => {
  beforeEach(() => {
    mocks.push.mockReset()
    mocks.createIngestion.mockReset()
    mocks.createIngestion.mockResolvedValue({ id: 1 })
    mocks.extractProblemDetail.mockReset()
    mocks.extractProblemDetail.mockReturnValue(null)
  })

  it('returns to opportunities without depending on browser history', () => {
    const wrapper = mount(ImportJobPage)

    const link = wrapper.findAll('a').find((a) => a.text() === 'Back to opportunities')
    expect(link).toBeDefined()

    const routerLink = wrapper.findComponent({ name: 'RouterLink' })
    expect(routerLink.props('to')).toEqual({ name: 'opportunities' })
  })

  it('renders the form title', () => {
    const wrapper = mount(ImportJobPage)
    expect(wrapper.text()).toContain('Import job description')
  })

  it('shows the info box explaining review process', () => {
    const wrapper = mount(ImportJobPage)
    expect(wrapper.text()).toContain('review and edit before saving')
  })

  it('has a textarea for job description', () => {
    const wrapper = mount(ImportJobPage)
    const textarea = wrapper.find('textarea')
    expect(textarea.exists()).toBe(true)
  })

  it('has a submit button labeled Start analysis', () => {
    const wrapper = mount(ImportJobPage)
    expect(wrapper.text()).toContain('Start analysis')
  })

  it('shows minimum character hint when empty', () => {
    const wrapper = mount(ImportJobPage)
    expect(wrapper.text()).toContain('Minimum 50 characters')
  })

  it('has source URL input', () => {
    const wrapper = mount(ImportJobPage)
    const input = wrapper.find('input[type="url"]')
    expect(input.exists()).toBe(true)
  })

  it('has personal label input', () => {
    const wrapper = mount(ImportJobPage)
    const input = wrapper.find('input#personal_label')
    expect(input.exists()).toBe(true)
  })

  it('shows an actionable status-aware message for a cancelled duplicate', async () => {
    mocks.createIngestion.mockRejectedValue(new Error('Conflict'))
    mocks.extractProblemDetail.mockReturnValue({
      code: 'duplicate_ingestion',
      errors: {
        existing_ingestion_id: 42,
        existing_status: 'cancelled',
      },
    })

    const wrapper = mount(ImportJobPage)
    await wrapper.find('textarea').setValue('A'.repeat(60))
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('already in your history as a cancelled analysis')
    expect(wrapper.text()).toContain('View existing analysis')
    expect(wrapper.text()).toContain('Import a different job')

    const viewButton = wrapper
      .findAll('button')
      .find((button) => button.text() === 'View existing analysis')
    await viewButton!.trigger('click')

    expect(mocks.push).toHaveBeenCalledWith({
      name: 'opportunities-processing',
      params: { id: 42 },
    })
  })

  it('never renders internal details for an unexpected server error', async () => {
    mocks.createIngestion.mockRejectedValue(new Error('Request failed'))
    mocks.extractProblemDetail.mockReturnValue({
      status: 500,
      code: 'internal_error',
      detail: 'SQLSTATE[23000]: Integrity constraint violation',
    })

    const wrapper = mount(ImportJobPage)
    await wrapper.find('textarea').setValue('A'.repeat(60))
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('We couldn’t start the analysis')
    expect(wrapper.text()).not.toContain('SQLSTATE')
    expect(wrapper.text()).not.toContain('Integrity constraint')
  })

  it('clears the form and focuses the description when importing a different job', async () => {
    mocks.createIngestion.mockRejectedValue(new Error('Conflict'))
    mocks.extractProblemDetail.mockReturnValue({
      code: 'duplicate_ingestion',
      errors: {
        existing_ingestion_id: 42,
        existing_status: 'cancelled',
      },
    })

    const wrapper = mount(ImportJobPage, { attachTo: document.body })
    const textarea = wrapper.find('textarea')
    await textarea.setValue('A'.repeat(60))
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const resetButton = wrapper
      .findAll('button')
      .find((button) => button.text() === 'Import a different job')
    await resetButton!.trigger('click')

    expect((textarea.element as HTMLTextAreaElement).value).toBe('')
    expect(document.activeElement).toBe(textarea.element)
    expect(wrapper.text()).not.toContain('This job was already imported')

    wrapper.unmount()
  })
})
