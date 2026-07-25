import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import EvidenceSelector from '@/features/skills/components/EvidenceSelector.vue'
import type { SkillEvidenceEntry } from '@/features/skills/types'

function makeEvidence(overrides: Partial<SkillEvidenceEntry> = {}): SkillEvidenceEntry {
  return {
    key: 'evt_1',
    type: 'url',
    value: 'https://example.com/cert',
    label: 'Certificate',
    ...overrides,
  }
}

const stubs = {
  Teleport: { template: '<div><slot/></div>' },
  Transition: false,
}

describe('EvidenceSelector', () => {
  it('shows Add Evidence button', () => {
    const wrapper = mount(EvidenceSelector, {
      props: { evidence: [], saving: false },
      global: { stubs },
    })
    expect(wrapper.text()).toContain('Add Evidence')
  })

  it('shows evidence entries', () => {
    const evidence = [makeEvidence()]
    const wrapper = mount(EvidenceSelector, {
      props: { evidence, saving: false },
      global: { stubs },
    })
    expect(wrapper.text()).toContain('Certificate')
  })

  it('emits remove when delete button clicked', async () => {
    const evidence = [makeEvidence()]
    const wrapper = mount(EvidenceSelector, {
      props: { evidence, saving: false },
      global: { stubs },
    })
    const removeBtn = wrapper.find('[aria-label="Remove Certificate"]')
    await removeBtn.trigger('click')
    expect(wrapper.emitted('remove')).toBeTruthy()
    expect(wrapper.emitted('remove')![0]).toEqual(['evt_1'])
  })

  it('opens modal when Add Evidence clicked', async () => {
    const wrapper = mount(EvidenceSelector, {
      props: { evidence: [], saving: false },
      global: { stubs },
    })
    const addBtn = wrapper.findAll('button').find((b) => b.text().includes('Add Evidence'))
    await addBtn!.trigger('click')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
  })

  it('shows profile items tab in modal', async () => {
    const wrapper = mount(EvidenceSelector, {
      props: { evidence: [], saving: false },
      global: { stubs },
    })
    const addBtn = wrapper.findAll('button').find((b) => b.text().includes('Add Evidence'))
    await addBtn!.trigger('click')
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Profile Items')
    expect(wrapper.text()).toContain('URL')
  })

  it('shows URL input tab', async () => {
    const wrapper = mount(EvidenceSelector, {
      props: { evidence: [], saving: false },
      global: { stubs },
    })
    const addBtn = wrapper.findAll('button').find((b) => b.text().includes('Add Evidence'))
    await addBtn!.trigger('click')
    await wrapper.vm.$nextTick()
    const urlTab = wrapper.findAll('button').find((b) => b.text().includes('URL'))
    await urlTab!.trigger('click')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('input[type="url"]').exists()).toBe(true)
  })

  it('shows "No profile items available" when profile has no items', async () => {
    const wrapper = mount(EvidenceSelector, {
      props: { evidence: [], saving: false, profileItems: {} },
      global: { stubs },
    })
    const addBtn = wrapper.findAll('button').find((b) => b.text().includes('Add Evidence'))
    await addBtn!.trigger('click')
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('No profile items available')
  })

  it('disables remove buttons while saving', () => {
    const evidence = [makeEvidence()]
    const wrapper = mount(EvidenceSelector, {
      props: { evidence, saving: true },
      global: { stubs },
    })
    const removeBtn = wrapper.find('[aria-label="Remove Certificate"]')
    expect(removeBtn.attributes('disabled')).toBeDefined()
  })
})
