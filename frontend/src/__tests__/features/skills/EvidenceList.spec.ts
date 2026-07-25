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

describe('EvidenceSelector inline list', () => {
  it('shows evidence entries', () => {
    const evidence = [makeEvidence()]
    const wrapper = mount(EvidenceSelector, { props: { evidence, saving: false } })
    expect(wrapper.text()).toContain('Certificate')
  })

  it('shows evidence value when no label', () => {
    const evidence = [makeEvidence({ label: null })]
    const wrapper = mount(EvidenceSelector, { props: { evidence, saving: false } })
    expect(wrapper.text()).toContain('https://example.com/cert')
  })

  it('shows add button when no evidence', () => {
    const wrapper = mount(EvidenceSelector, { props: { evidence: [], saving: false } })
    expect(wrapper.text()).toContain('Add Evidence')
  })

  it('emits remove with key on delete click', async () => {
    const evidence = [makeEvidence()]
    const wrapper = mount(EvidenceSelector, { props: { evidence, saving: false } })
    const removeBtn = wrapper.find('[aria-label="Remove Certificate"]')
    await removeBtn.trigger('click')
    expect(wrapper.emitted('remove')).toBeTruthy()
    expect(wrapper.emitted('remove')![0]).toEqual(['evt_1'])
  })

  it('disables remove button while saving', () => {
    const evidence = [makeEvidence()]
    const wrapper = mount(EvidenceSelector, { props: { evidence, saving: true } })
    const removeBtn = wrapper.find('[aria-label="Remove Certificate"]')
    expect(removeBtn.attributes('disabled')).toBeDefined()
  })

  it('shows profile_item evidence value when no label', () => {
    const evidence = [
      makeEvidence({
        type: 'profile_item',
        value: '42',
        label: null,
      }),
    ]
    const wrapper = mount(EvidenceSelector, { props: { evidence, saving: false } })
    expect(wrapper.text()).toContain('42')
  })

  it('renders multiple evidence entries', () => {
    const evidence = [
      makeEvidence({ key: 'e1', label: 'Cert 1' }),
      makeEvidence({ key: 'e2', label: 'Cert 2' }),
    ]
    const wrapper = mount(EvidenceSelector, { props: { evidence, saving: false } })
    expect(wrapper.text()).toContain('Cert 1')
    expect(wrapper.text()).toContain('Cert 2')
  })
})
