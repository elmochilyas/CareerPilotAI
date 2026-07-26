import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CvReviewSkills from '@/features/cv-ingestion/components/CvReviewSkills.vue'
import type { CvSuggestion } from '@/features/cv-ingestion/types'

function makeSkill(overrides: Partial<CvSuggestion<'skill'>> = {}): CvSuggestion<'skill'> {
  return {
    id: Math.random(),
    cv_document_id: 1,
    type: 'skill',
    category: 'Backend',
    field_name: null,
    current_value: null,
    suggested_value: { name: 'Laravel', category: 'Backend' },
    source_page: null,
    source_text: null,
    extraction_method: 'ai_extraction',
    confidence: 0.95,
    review_status: 'pending',
    reviewed_decision: null,
    reviewed_at: null,
    ...overrides,
  } as CvSuggestion<'skill'>
}

function makeLanguage(overrides: Partial<CvSuggestion<'language'>> = {}): CvSuggestion<'language'> {
  return {
    id: Math.random(),
    cv_document_id: 1,
    type: 'language',
    category: null,
    field_name: null,
    current_value: null,
    suggested_value: { language: 'Arabic', proficiency: 'native' },
    source_page: null,
    source_text: null,
    extraction_method: 'ai_extraction',
    confidence: 0.95,
    review_status: 'pending',
    reviewed_decision: null,
    reviewed_at: null,
    ...overrides,
  } as CvSuggestion<'language'>
}

describe('CvReviewSkills', () => {
  it('groups skills by category', () => {
    const suggestions: CvSuggestion[] = [
      makeSkill({ suggested_value: { name: 'PHP', category: 'Backend' } }),
      makeSkill({ suggested_value: { name: 'Vue.js', category: 'Frontend' } }),
      makeSkill({ suggested_value: { name: 'Laravel', category: 'Backend' } }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions } })
    expect(wrapper.text()).toContain('Backend')
    expect(wrapper.text()).toContain('Frontend')
    expect(wrapper.text()).toContain('PHP')
    expect(wrapper.text()).toContain('Laravel')
    expect(wrapper.text()).toContain('Vue.js')
  })

  it('shows languages separately from skills', () => {
    const suggestions: CvSuggestion[] = [
      makeSkill({ suggested_value: { name: 'PHP', category: 'Backend' } }),
      makeLanguage({ suggested_value: { language: 'Arabic', proficiency: 'native' } }),
      makeLanguage({ suggested_value: { language: 'French', proficiency: 'fluent' } }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions } })
    expect(wrapper.text()).toContain('Technical skills')
    expect(wrapper.text()).toContain('Languages')
    expect(wrapper.text()).toContain('Arabic')
    expect(wrapper.text()).toContain('French')
  })

  it('shows language proficiency label', () => {
    const suggestions: CvSuggestion[] = [
      makeLanguage({ suggested_value: { language: 'Arabic', proficiency: 'native' } }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions } })
    expect(wrapper.text()).toContain('Native')
  })

  it('has remove button on skill chips', () => {
    const suggestions: CvSuggestion[] = [
      makeSkill({ suggested_value: { name: 'PHP', category: 'Backend' } }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions } })
    expect(wrapper.find('button[aria-label*="Remove"]').exists()).toBe(true)
  })

  it('shows undo after removing a skill', async () => {
    const suggestions: CvSuggestion[] = [
      makeSkill({ id: 42, suggested_value: { name: 'PHP', category: 'Backend' } }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions } })
    const removeBtn = wrapper.find('button[aria-label*="Remove"]')
    await removeBtn.trigger('click')
    expect(wrapper.text()).toContain('Undo')
    expect(wrapper.text()).toContain('PHP')
  })

  it('emits unsavedChange when skills are removed', async () => {
    const suggestions: CvSuggestion[] = [
      makeSkill({ id: 42, suggested_value: { name: 'PHP', category: 'Backend' } }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions } })
    const removeBtn = wrapper.find('button[aria-label*="Remove"]')
    await removeBtn.trigger('click')
    expect(wrapper.emitted('unsavedChange')).toBeTruthy()
    expect(wrapper.emitted('unsavedChange')![0]).toEqual([true])
  })

  it('shows existing badge for duplicate skills', () => {
    const suggestions: CvSuggestion[] = [
      makeSkill({
        current_value: { name: 'PHP' },
        suggested_value: { name: 'PHP', category: 'Backend' },
      }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions } })
    expect(wrapper.text()).toContain('In profile')
  })

  it('shows archived badge for archived skills', () => {
    const suggestions: CvSuggestion[] = [
      makeSkill({
        current_value: { name: 'PHP', state: 'archived' },
        suggested_value: { name: 'PHP', category: 'Backend' },
      }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions } })
    expect(wrapper.text()).toContain('Archived')
  })

  it('shows keep all / remove all for categories with >1 skill', () => {
    const suggestions: CvSuggestion[] = [
      makeSkill({ id: 1, suggested_value: { name: 'PHP', category: 'Backend' } }),
      makeSkill({ id: 2, suggested_value: { name: 'Laravel', category: 'Backend' } }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions } })
    expect(wrapper.text()).toContain('Keep all')
    expect(wrapper.text()).toContain('Remove all')
  })

  it('hides keep all / remove all for categories with 1 skill', () => {
    const suggestions: CvSuggestion[] = [
      makeSkill({ id: 1, suggested_value: { name: 'PHP', category: 'Backend' } }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions } })
    expect(wrapper.text()).not.toContain('Keep all')
    expect(wrapper.text()).not.toContain('Remove all')
  })

  it('hides remove buttons in readonly mode', () => {
    const suggestions: CvSuggestion[] = [
      makeSkill({ suggested_value: { name: 'PHP', category: 'Backend' } }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions, readonly: true } })
    expect(wrapper.find('button[aria-label*="Remove"]').exists()).toBe(false)
  })

  it('hides Keep/Remove buttons for languages in readonly mode', () => {
    const suggestions: CvSuggestion[] = [
      makeLanguage({ suggested_value: { language: 'Arabic', proficiency: 'native' } }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions, readonly: true } })
    const buttons = wrapper.findAll('button')
    const buttonTexts = buttons.map((b) => b.text())
    expect(buttonTexts.every((t) => !t.includes('Keep'))).toBe(true)
    expect(buttonTexts.every((t) => !t.includes('Remove'))).toBe(true)
  })

  it('shows decision label for reviewed language', () => {
    const suggestions: CvSuggestion[] = [
      makeLanguage({
        id: 1,
        suggested_value: { language: 'Arabic', proficiency: 'native' },
        review_status: 'accepted',
      }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions } })
    expect(wrapper.text()).toContain('Accepted')
  })

  it('restores chips and dismisses toast on keep all after remove all', async () => {
    const suggestions: CvSuggestion[] = [
      makeSkill({ id: 1, suggested_value: { name: 'PHP', category: 'Backend' } }),
      makeSkill({ id: 2, suggested_value: { name: 'Laravel', category: 'Backend' } }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions } })

    const buttons = () => wrapper.findAll('button')
    function btnByText(text: string) {
      return buttons().find((b) => b.text().includes(text))
    }

    const removeAll = btnByText('Remove all')
    expect(removeAll).toBeTruthy()
    await removeAll!.trigger('click')

    expect(btnByText('Undo')).toBeTruthy()

    const keepAll = btnByText('Keep all')
    await keepAll!.trigger('click')

    expect(btnByText('Undo')).toBeFalsy()
    expect(wrapper.text()).not.toContain('removed')
  })

  it('shows accepted badge and hides buttons after keep all', async () => {
    const suggestions: CvSuggestion[] = [
      makeSkill({ id: 1, suggested_value: { name: 'PHP', category: 'Backend' } }),
      makeSkill({ id: 2, suggested_value: { name: 'Laravel', category: 'Backend' } }),
      makeSkill({ id: 3, suggested_value: { name: 'Vue.js', category: 'Frontend' } }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions } })

    const keepAll = wrapper.findAll('button').find((b) => b.text().includes('Keep all'))
    expect(keepAll).toBeTruthy()
    await keepAll!.trigger('click')

    const decisions = wrapper.emitted('decision') as unknown[][]
    const accepted = decisions.filter(
      ([, d]) => (d as { decision: string }).decision === 'accepted',
    )
    expect(accepted.length).toBe(2)
    expect(accepted[0][0]).toBe(1)
    expect(accepted[1][0]).toBe(2)

    // Chips show Accepted badge
    expect(wrapper.text()).toContain('Accepted')

    // Category buttons are hidden because all skills in category are now accepted
    const keepAllBtnAfter = wrapper.findAll('button').find((b) => b.text().includes('Keep all'))
    expect(keepAllBtnAfter).toBeFalsy()
  })

  it('emits decision only once per skill on rapid keep all clicks', async () => {
    const suggestions: CvSuggestion[] = [
      makeSkill({ id: 1, suggested_value: { name: 'PHP', category: 'Backend' } }),
      makeSkill({ id: 2, suggested_value: { name: 'Laravel', category: 'Backend' } }),
    ]
    const wrapper = mount(CvReviewSkills, { props: { suggestions } })

    function btnByText(text: string) {
      return wrapper.findAll('button').find((b) => b.text().includes(text))
    }

    const keepAll = btnByText('Keep all')!
    await keepAll.trigger('click')
    await keepAll.trigger('click')
    await keepAll.trigger('click')

    const decisions = wrapper.emitted('decision') as unknown[][]
    expect(decisions.length).toBe(2)
  })
})
