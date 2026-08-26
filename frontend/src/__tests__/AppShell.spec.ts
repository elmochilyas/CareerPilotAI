import { afterEach, describe, expect, it } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import AppShell from '@/app/layouts/AppShell.vue'

const originalInnerWidth = window.innerWidth

let wrapper: VueWrapper | undefined

function setViewportWidth(width: number): void {
  Object.defineProperty(window, 'innerWidth', {
    configurable: true,
    value: width,
  })
}

async function mountAppShell(): Promise<VueWrapper> {
  const page = { template: '<div>Page</div>' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: page },
      { path: '/profile', component: page },
      { path: '/cv', component: page },
      { path: '/opportunities', component: page },
    ],
  })

  await router.push('/')
  await router.isReady()

  wrapper = mount(AppShell, {
    global: {
      plugins: [createPinia(), router],
    },
  })
  await flushPromises()

  return wrapper
}

afterEach(() => {
  wrapper?.unmount()
  wrapper = undefined
  setViewportWidth(originalInnerWidth)
})

describe('AppShell responsive sidebar', () => {
  it('reserves the padded desktop rail width when the sidebar is collapsed', async () => {
    setViewportWidth(1280)
    const shell = await mountAppShell()
    const sidebar = shell.get('aside[aria-label="Main navigation"]')

    await sidebar.get('button[aria-label="Collapse sidebar"]').trigger('click')
    await flushPromises()

    const rail = sidebar.element.parentElement as HTMLElement

    expect(sidebar.attributes('aria-label')).toBe('Navigation (collapsed)')
    expect(rail.classList).toContain('p-3')
    expect(rail.style.width).toBe('calc(var(--sidebar-collapsed-width) + 1.5rem)')
  })

  it('keeps the drawer trigger mobile-only so it cannot overlap the tablet rail', async () => {
    setViewportWidth(800)
    const shell = await mountAppShell()
    const drawerTrigger = shell.get('button[aria-label="Toggle sidebar"]')

    expect(drawerTrigger.classes()).toContain('md:hidden')
    expect(drawerTrigger.classes()).not.toContain('lg:hidden')
  })

  it('preserves the sidebar vertical slots when it collapses', async () => {
    setViewportWidth(1280)
    const shell = await mountAppShell()
    const expandedSidebar = shell.get('aside[aria-label="Main navigation"]')

    expect(expandedSidebar.get('.sidebar-brand').classes()).toContain('h-[10.75rem]')
    expect(expandedSidebar.get('.sidebar-brand').classes()).not.toContain('-mt-3')
    expect(expandedSidebar.get('.sidebar-eyebrow').classes()).toContain('opacity-100')
    expect(expandedSidebar.get('.sidebar-footer').classes()).not.toContain('translate-y-3')
    expect(expandedSidebar.get('button[aria-label="Collapse sidebar"]').classes()).toContain(
      'top-5',
    )

    await expandedSidebar.get('button[aria-label="Collapse sidebar"]').trigger('click')
    await flushPromises()

    const collapsedSidebar = shell.get('aside[aria-label="Navigation (collapsed)"]')

    expect(collapsedSidebar.get('.sidebar-brand').classes()).toContain('h-[10.75rem]')
    expect(collapsedSidebar.get('.sidebar-brand').classes()).toContain('-mt-3')
    expect(collapsedSidebar.get('.sidebar-eyebrow').classes()).toContain('opacity-0')
    expect(collapsedSidebar.get('.sidebar-eyebrow').attributes('aria-hidden')).toBe('true')
    expect(collapsedSidebar.get('.sidebar-footer').classes()).toContain('translate-y-3')
    expect(collapsedSidebar.get('button[aria-label="Expand sidebar"]').classes()).toContain('top-2')
  })

  it('coordinates the rail padding and sidebar frame with the width transition', async () => {
    setViewportWidth(1280)
    const shell = await mountAppShell()
    const sidebar = shell.get('aside[aria-label="Main navigation"]')
    const rail = sidebar.element.parentElement as HTMLElement

    expect(rail.classList).toContain('transition-[width,padding]')
    expect(rail.classList).toContain('duration-300')
    expect(rail.classList).toContain('p-0')
    expect(sidebar.classes()).toContain('h-full')
    expect(sidebar.classes()).toContain('transition-[border-radius]')
    expect(sidebar.classes()).toContain('duration-300')

    await sidebar.get('button[aria-label="Collapse sidebar"]').trigger('click')
    await flushPromises()

    expect(rail.classList).toContain('p-3')
    expect(rail.classList).not.toContain('p-0')
  })

  it('keeps sidebar content mounted while it crossfades', async () => {
    setViewportWidth(1280)
    const shell = await mountAppShell()
    const expandedSidebar = shell.get('aside[aria-label="Main navigation"]')

    expect(expandedSidebar.find('img.sidebar-logo').exists()).toBe(true)
    expect(expandedSidebar.find('.sidebar-monogram').exists()).toBe(true)
    expect(expandedSidebar.findAll('.sidebar-nav-label')).toHaveLength(4)
    expect(expandedSidebar.find('.sidebar-user-details').exists()).toBe(true)
    expect(expandedSidebar.find('.sidebar-logout-label').exists()).toBe(true)

    await expandedSidebar.get('button[aria-label="Collapse sidebar"]').trigger('click')
    await flushPromises()

    const collapsedSidebar = shell.get('aside[aria-label="Navigation (collapsed)"]')

    expect(collapsedSidebar.find('img.sidebar-logo').exists()).toBe(true)
    expect(collapsedSidebar.find('.sidebar-monogram').exists()).toBe(true)
    expect(collapsedSidebar.findAll('.sidebar-nav-label')).toHaveLength(4)
    expect(
      collapsedSidebar
        .findAll('.sidebar-nav-label')
        .every((label) => label.classes().includes('opacity-0')),
    ).toBe(true)
    expect(collapsedSidebar.get('.sidebar-user-details').classes()).toContain('opacity-0')
    expect(collapsedSidebar.get('.sidebar-logout-label').classes()).toContain('opacity-0')
    expect(collapsedSidebar.get('.sidebar-eyebrow').classes()).toContain('opacity-0')
  })
})
