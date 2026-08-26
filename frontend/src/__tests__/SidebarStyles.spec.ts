import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

import { compileStyle, parse } from '@vue/compiler-sfc'
import { describe, expect, it } from 'vitest'

const sidebarPath = resolve('src/components/navigation/Sidebar.vue')

function compileSidebarScopedStyles(): string {
  const source = readFileSync(sidebarPath, 'utf8')
  const { descriptor, errors } = parse(source, { filename: sidebarPath })

  if (errors.length > 0) {
    throw new Error(`Failed to parse Sidebar.vue: ${errors.map(String).join(', ')}`)
  }

  const scopedStyle = descriptor.styles.find((style) => style.scoped)

  if (!scopedStyle) {
    throw new Error('Sidebar.vue must include a scoped style block')
  }

  const result = compileStyle({
    id: 'data-v-sidebar-test',
    filename: sidebarPath,
    source: scopedStyle.content,
    scoped: true,
  })

  if (result.errors.length > 0) {
    throw new Error(`Failed to compile Sidebar.vue styles: ${result.errors.map(String).join(', ')}`)
  }

  return result.code
}

describe('Sidebar scoped styles', () => {
  it('does not apply runway offsets to the collapsed sidebar itself', () => {
    const styleElement = document.createElement('style')
    styleElement.textContent = compileSidebarScopedStyles()

    const sidebar = document.createElement('aside')
    sidebar.className = 'sidebar'
    sidebar.setAttribute('aria-label', 'Navigation (collapsed)')
    sidebar.setAttribute('data-v-sidebar-test', '')

    document.head.append(styleElement)
    document.body.append(sidebar)

    try {
      const styles = getComputedStyle(sidebar)

      expect(styles.left).not.toBe('50%')
      expect(styles.top).not.toBe('88px')
      expect(styles.bottom).not.toBe('140px')
      expect(styles.opacity).not.toBe('0.7')
    } finally {
      styleElement.remove()
      sidebar.remove()
    }
  })
})
