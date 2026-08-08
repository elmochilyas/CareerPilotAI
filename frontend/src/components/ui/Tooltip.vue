<script setup lang="ts">
import { ref } from 'vue'

const props = withDefaults(
  defineProps<{
    text: string
    position?: 'top' | 'bottom' | 'left' | 'right'
    delay?: number
  }>(),
  { position: 'top', delay: 300 },
)

const visible = ref(false)
let timeout: ReturnType<typeof setTimeout> | null = null

function show() {
  timeout = setTimeout(() => {
    visible.value = true
  }, props.delay)
}

function hide() {
  if (timeout) clearTimeout(timeout)
  visible.value = false
}

type TooltipPosition = 'top' | 'bottom' | 'left' | 'right'
const placementMap: Record<TooltipPosition, string> = {
  top: 'bottom-full left-1/2 -translate-x-1/2 mb-2',
  bottom: 'top-full left-1/2 -translate-x-1/2 mt-2',
  left: 'right-full top-1/2 -translate-y-1/2 mr-2',
  right: 'left-full top-1/2 -translate-y-1/2 ml-2',
}
const arrowMap: Record<TooltipPosition, string> = {
  top: 'left-1/2 -translate-x-1/2 -translate-y-full border-b-slate-800 border-x-transparent border-t-0 border-[5px]',
  bottom:
    'left-1/2 -translate-x-1/2 translate-y-full border-t-slate-800 border-x-transparent border-b-0 border-[5px]',
  left: 'top-1/2 -translate-y-1/2 -translate-x-full border-r-slate-800 border-y-transparent border-l-0 border-[5px]',
  right:
    'top-1/2 -translate-y-1/2 translate-x-full border-l-slate-800 border-y-transparent border-r-0 border-[5px]',
}

function getPlacement(pos: string): string {
  return placementMap[pos as TooltipPosition] ?? placementMap.top
}

function getArrow(pos: string): string {
  return arrowMap[pos as TooltipPosition] ?? arrowMap.top
}

function onMouseenter() {
  if (timeout) clearTimeout(timeout)
  timeout = setTimeout(() => {
    visible.value = true
  }, props.delay)
}

function onMouseleave() {
  if (timeout) clearTimeout(timeout)
  visible.value = false
}
</script>

<template>
  <div
    class="relative inline-flex"
    @mouseenter="onMouseenter"
    @mouseleave="onMouseleave"
    @focusin="show"
    @focusout="hide"
  >
    <slot />
    <Transition name="tooltip">
      <div
        v-if="visible && text"
        class="absolute whitespace-nowrap rounded-md bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-white shadow-lg pointer-events-none"
        :class="getPlacement(position)"
        role="tooltip"
        style="z-index: var(--z-tooltip)"
      >
        {{ text }}
        <span
          class="absolute border-5 border-solid"
          :class="getArrow(position)"
          aria-hidden="true"
        />
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.tooltip-enter-active {
  transition: opacity var(--duration-fast) var(--ease-out);
}
.tooltip-leave-active {
  transition: opacity var(--duration-fast) var(--ease-in);
}
.tooltip-enter-from,
.tooltip-leave-to {
  opacity: 0;
}
</style>
