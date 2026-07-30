import { describe, it, expect, vi, beforeAll, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { VueQueryPlugin } from '@tanstack/vue-query'
import SkillSearchCombobox from '@/features/skills/components/SkillSearchCombobox.vue'
import type { Skill } from '@/features/skills/types'

const mockResults: Skill[] = [
  {
    id: 1,
    name: 'TypeScript',
    normalized_name: 'typescript',
    category: 'Language',
    is_active: true,
    aliases: [],
    created_at: '',
    updated_at: '',
  },
  {
    id: 2,
    name: 'TypeORM',
    normalized_name: 'typeorm',
    category: 'Database',
    is_active: true,
    aliases: [],
    created_at: '',
    updated_at: '',
  },
]

const mockQuery = vi.hoisted(() => ({
  data: { value: undefined as Skill[] | undefined },
  isPending: { value: false },
  isFetching: { value: false },
  isError: { value: false },
  error: { value: null },
}))

beforeAll(() => {
  Element.prototype.scrollIntoView = vi.fn<() => void>()
})

vi.mock('@tanstack/vue-query', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@tanstack/vue-query')>()
  return {
    ...actual,
    useQuery: () => mockQuery,
  }
})

describe('SkillSearchCombobox', () => {
  beforeEach(() => {
    mockQuery.data.value = undefined
    mockQuery.isPending.value = false
  })

  function createWrapper() {
    return mount(SkillSearchCombobox, {
      props: { modelValue: null },
      global: { plugins: [VueQueryPlugin] },
      attachTo: document.body,
    })
  }

  it('renders search input', () => {
    const wrapper = createWrapper()
    expect(wrapper.find('input').exists()).toBe(true)
  })

  it('shows placeholder text', () => {
    const wrapper = createWrapper()
    expect(wrapper.find('input').attributes('placeholder')).toBe('Search for a skill…')
  })

  it('has combobox role', () => {
    const wrapper = createWrapper()
    expect(wrapper.find('input').attributes('role')).toBe('combobox')
  })

  it('does not show results when query is too short', async () => {
    vi.useFakeTimers()
    const wrapper = createWrapper()
    const input = wrapper.find('input')
    input.value = ''
    await input.setValue('T')
    await vi.advanceTimersByTimeAsync(350)
    expect(wrapper.find('ul').exists()).toBe(false)
    vi.useRealTimers()
  })

  it('shows results when query is at least 2 characters', async () => {
    vi.useFakeTimers()
    mockQuery.data.value = mockResults
    const wrapper = createWrapper()
    const input = wrapper.find('input')
    await input.setValue('Ty')
    await vi.advanceTimersByTimeAsync(350)
    await wrapper.vm.$nextTick()
    const list = wrapper.find('ul')
    expect(list.exists()).toBe(true)
    expect(list.text()).toContain('TypeScript')
    expect(list.text()).toContain('TypeORM')
    vi.useRealTimers()
  })

  it('shows "No skills found" when results are empty', async () => {
    vi.useFakeTimers()
    mockQuery.data.value = []
    const wrapper = createWrapper()
    const input = wrapper.find('input')
    await input.setValue('Xyz')
    await vi.advanceTimersByTimeAsync(350)
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('No skills found')
    vi.useRealTimers()
  })

  it('emits select when a result is clicked', async () => {
    vi.useFakeTimers()
    mockQuery.data.value = mockResults
    const wrapper = createWrapper()
    const input = wrapper.find('input')
    await input.setValue('Ty')
    await vi.advanceTimersByTimeAsync(350)
    await wrapper.vm.$nextTick()
    const items = wrapper.findAll('li')
    await items[0].trigger('mousedown')
    expect(wrapper.emitted('select')).toBeTruthy()
    expect(wrapper.emitted('select')![0]).toEqual([mockResults[0]])
    vi.useRealTimers()
  })

  it('navigates with arrow keys', async () => {
    vi.useFakeTimers()
    mockQuery.data.value = mockResults
    const wrapper = createWrapper()
    const input = wrapper.find('input')
    await input.setValue('Ty')
    await vi.advanceTimersByTimeAsync(350)
    await wrapper.vm.$nextTick()
    await input.trigger('keydown', { key: 'ArrowDown' })
    const firstOption = wrapper.find('[aria-selected="true"]')
    expect(firstOption.text()).toContain('TypeScript')
    vi.useRealTimers()
  })

  it('selects on Enter', async () => {
    vi.useFakeTimers()
    mockQuery.data.value = mockResults
    const wrapper = createWrapper()
    const input = wrapper.find('input')
    await input.setValue('Ty')
    await vi.advanceTimersByTimeAsync(350)
    await wrapper.vm.$nextTick()
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('select')).toBeTruthy()
    expect(wrapper.emitted('select')![0]).toEqual([mockResults[0]])
    vi.useRealTimers()
  })

  it('closes on Escape', async () => {
    vi.useFakeTimers()
    mockQuery.data.value = mockResults
    const wrapper = createWrapper()
    const input = wrapper.find('input')
    await input.setValue('Ty')
    await vi.advanceTimersByTimeAsync(350)
    await wrapper.vm.$nextTick()
    expect(wrapper.find('ul').exists()).toBe(true)
    await input.trigger('keydown', { key: 'Escape' })
    expect(wrapper.find('ul').exists()).toBe(false)
    vi.useRealTimers()
  })
})
