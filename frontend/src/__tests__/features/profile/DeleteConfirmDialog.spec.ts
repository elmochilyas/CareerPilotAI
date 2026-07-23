import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import DeleteConfirmDialog from '@/features/profile/components/DeleteConfirmDialog.vue'

const stubs = { Teleport: true }

describe('DeleteConfirmDialog', () => {
  it('renders nothing when closed', () => {
    const wrapper = mount(DeleteConfirmDialog, {
      props: { open: false, title: 'Test', busy: false },
      global: { stubs },
    })
    expect(wrapper.find('[role="alertdialog"]').exists()).toBe(false)
  })

  it('renders dialog when open', () => {
    const wrapper = mount(DeleteConfirmDialog, {
      props: { open: true, busy: false },
      global: { stubs },
    })
    const dialog = wrapper.find('[role="dialog"]')
    expect(dialog.exists()).toBe(true)
    expect(dialog.text()).toContain('Delete this item?')
  })

  it('emits confirm when delete button clicked', async () => {
    const wrapper = mount(DeleteConfirmDialog, {
      props: { open: true, busy: false },
      global: { stubs },
    })
    const deleteBtn = wrapper.find('[role="dialog"] button:last-child')
    await deleteBtn.trigger('click')
    expect(wrapper.emitted('confirm')).toBeTruthy()
  })

  it('emits cancel when cancel button clicked', async () => {
    const wrapper = mount(DeleteConfirmDialog, {
      props: { open: true, title: 'Delete', busy: false },
      global: { stubs },
    })
    await wrapper.findAll('button')[0].trigger('click')
    expect(wrapper.emitted('cancel')).toBeTruthy()
  })

  it('disables delete button while busy', () => {
    const wrapper = mount(DeleteConfirmDialog, {
      props: { open: true, title: 'Delete', busy: true },
      global: { stubs },
    })
    const deleteBtn = wrapper.find('[role="dialog"] button:last-child')
    expect(deleteBtn.attributes('disabled')).toBeDefined()
  })
})
