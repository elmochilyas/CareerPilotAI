import { ref } from 'vue'
import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

const { useTailoringWorkspaceMock } = vi.hoisted(() => ({
  useTailoringWorkspaceMock: vi.fn<(...args: unknown[]) => unknown>(),
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: '1' } }),
}))

vi.mock('@/features/cv-tailoring/composables/useTailoringWorkspace', () => ({
  useTailoringWorkspace: useTailoringWorkspaceMock,
}))

import TailoringWorkspacePage from '@/features/cv-tailoring/pages/TailoringWorkspacePage.vue'

describe('TailoringWorkspacePage', () => {
  it('does not treat the nested isError ref object as an active error', () => {
    useTailoringWorkspaceMock.mockReturnValue({
      resume: ref(null),
      loadErrorDetail: ref(null),
      preview: ref(null),
      isCreating: ref(false),
      isDraft: ref(false),
      currentStep: ref('generate'),
      currentStepIndex: ref(0),
      steps: [],
      goToStep: vi.fn<(...args: unknown[]) => unknown>(),
      nextStep: vi.fn<(...args: unknown[]) => unknown>(),
      prevStep: vi.fn<(...args: unknown[]) => unknown>(),
      resumeQuery: {
        isError: ref(false),
        refetch: vi.fn<(...args: unknown[]) => unknown>(),
      },
      previewQuery: { isLoading: ref(false) },
      createMutation: {
        mutate: vi.fn<(...args: unknown[]) => unknown>(),
        isPending: ref(false),
        isError: ref(false),
      },
      tailorMutation: {
        mutate: vi.fn<(...args: unknown[]) => unknown>(),
        isPending: ref(false),
        isError: ref(false),
      },
      updateMutation: {
        mutateAsync: vi.fn<(...args: unknown[]) => unknown>(),
        isPending: ref(false),
        error: ref(null),
      },
      approveMutation: {
        mutate: vi.fn<(...args: unknown[]) => unknown>(),
        isPending: ref(false),
        isError: ref(false),
      },
    })

    const wrapper = shallowMount(TailoringWorkspacePage)

    expect(wrapper.text()).toContain('Generate Your Tailored CV')
    expect(wrapper.text()).not.toContain('Failed to load resume')
  })
})
