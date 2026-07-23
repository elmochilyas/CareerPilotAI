<script setup lang="ts">
import { Briefcase, GraduationCap, Folder, Award, Plus } from '@lucide/vue'
import { ref, watch } from 'vue'
import type { ProfileItem, ProfileItemInput, ProfileItemType } from '../types'
import DeleteConfirmDialog from './DeleteConfirmDialog.vue'
import ProfileItemCard from './ProfileItemCard.vue'
import ProfileItemForm from './ProfileItemForm.vue'
import ReorderControls from './ReorderControls.vue'

const props = defineProps<{
  title: string
  type: ProfileItemType
  items: ProfileItem[]
  busy?: boolean
  serverError?: unknown
  variant?: 'default' | 'timeline' | 'academic' | 'grid' | 'achievement'
}>()

const emit = defineEmits<{
  create: [value: ProfileItemInput]
  update: [item: ProfileItem, value: ProfileItemInput]
  delete: [item: ProfileItem]
  reorder: [ids: number[]]
  dirty: [value: boolean]
}>()

const formOpen = ref(false)
const editing = ref<ProfileItem | null>(null)
const deleting = ref<ProfileItem | null>(null)
const savingLocally = ref(false)

watch(
  () => props.items.map((i) => i.id).join(','),
  () => {
    if (savingLocally.value && formOpen.value) {
      savingLocally.value = false
      formOpen.value = false
      editing.value = null
    }
  },
)

function move(index: number, offset: number) {
  const next = [...props.items]
  const [item] = next.splice(index, 1)
  if (!item) return
  next.splice(index + offset, 0, item)
  emit(
    'reorder',
    next.map((v) => v.id),
  )
}

function save(value: ProfileItemInput) {
  if (savingLocally.value) return
  savingLocally.value = true
  if (editing.value) emit('update', editing.value, value)
  else emit('create', value)
}

function openEdit(item: ProfileItem) {
  editing.value = item
  formOpen.value = true
}

function cancelEdit() {
  formOpen.value = false
  editing.value = null
  savingLocally.value = false
  emit('dirty', false)
}

function confirmDelete() {
  if (!deleting.value) return
  emit('delete', deleting.value)
  deleting.value = null
}

const sectionIcon: Record<string, object> = {
  experience: Briefcase,
  education: GraduationCap,
  project: Folder,
  certification: Award,
}

const sectionGradient: Record<string, string> = {
  experience: 'from-blue-500 to-blue-600',
  education: 'from-indigo-500 to-indigo-600',
  project: 'from-violet-500 to-violet-600',
  certification: 'from-amber-500 to-amber-600',
}

const emptyStates: Record<string, { title: string; description: string }> = {
  experience: {
    title: 'Build your professional journey',
    description: 'Add your roles, responsibilities and achievements.',
  },
  education: {
    title: 'Add your education',
    description: 'Share your academic background, degrees and qualifications.',
  },
  project: {
    title: 'Show what you have built',
    description: 'Add projects that demonstrate your skills and experience.',
  },
  certification: {
    title: 'Add your certifications',
    description: 'Highlight your professional certifications and achievements.',
  },
}

const variant = props.variant ?? 'default'
const isTimeline = variant === 'timeline' || variant === 'academic'
const isGrid = variant === 'grid'
const isAchievement = variant === 'achievement'

const timelineLineGradient =
  variant === 'academic' ? 'from-indigo-200 to-indigo-100' : 'from-blue-200 to-blue-100'
</script>

<template>
  <section
    class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition-all duration-300 hover:shadow-md"
    :aria-labelledby="`${type}-heading`"
  >
    <header
      class="flex items-center justify-between gap-3 border-b border-slate-100/80 bg-gradient-to-b from-slate-50/50 to-white px-5 py-4 sm:px-6"
    >
      <div class="flex items-center gap-3">
        <div
          class="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br text-white shadow-sm"
          :class="sectionGradient[type] ?? 'from-slate-500 to-slate-600'"
        >
          <component :is="sectionIcon[type]" :size="16" stroke-width="1.5" />
        </div>
        <h2 :id="`${type}-heading`" class="text-base font-bold text-slate-900">
          {{ title }}
        </h2>
      </div>
      <button
        type="button"
        class="min-h-10 cursor-pointer rounded-xl bg-gradient-to-br from-primary-600 to-primary-500 px-4 text-sm font-semibold text-white shadow-md shadow-primary-500/20 transition-all hover:shadow-lg hover:shadow-primary-500/30 active:scale-[0.97]"
        @click="formOpen = true"
      >
        <Plus :size="14" stroke-width="2.5" class="inline-block -ml-0.5 mr-1" />
        Add {{ type }}
      </button>
    </header>

    <div class="px-5 pb-5 sm:px-6 sm:pb-6">
      <!-- Grid variant (projects) -->
      <div v-if="isGrid && items.length" class="grid gap-5 pt-4 sm:grid-cols-2">
        <ProfileItemCard v-for="(item, index) in items" :key="item.id" :item="item" variant="grid">
          <template #actions>
            <ReorderControls
              :first="index === 0"
              :last="index === items.length - 1"
              @up="move(index, -1)"
              @down="move(index, 1)"
            />
            <button
              type="button"
              class="min-h-9 cursor-pointer rounded-lg px-2.5 text-xs font-medium text-primary-600 transition-all hover:bg-primary-50 active:scale-[0.97]"
              @click="openEdit(item)"
            >
              Edit
            </button>
            <button
              type="button"
              class="min-h-9 cursor-pointer rounded-lg px-2.5 text-xs font-medium text-red-600 transition-all hover:bg-red-50 active:scale-[0.97]"
              @click="deleting = item"
            >
              Delete
            </button>
          </template>
        </ProfileItemCard>
      </div>

      <!-- Achievement variant (certifications) -->
      <div v-else-if="isAchievement && items.length" class="grid gap-3 pt-4">
        <ProfileItemCard
          v-for="(item, index) in items"
          :key="item.id"
          :item="item"
          variant="achievement"
        >
          <template #actions>
            <ReorderControls
              :first="index === 0"
              :last="index === items.length - 1"
              @up="move(index, -1)"
              @down="move(index, 1)"
            />
            <button
              type="button"
              class="min-h-9 cursor-pointer rounded-lg px-2.5 text-xs font-medium text-primary-600 transition-all hover:bg-primary-50 active:scale-[0.97]"
              @click="openEdit(item)"
            >
              Edit
            </button>
            <button
              type="button"
              class="min-h-9 cursor-pointer rounded-lg px-2.5 text-xs font-medium text-red-600 transition-all hover:bg-red-50 active:scale-[0.97]"
              @click="deleting = item"
            >
              Delete
            </button>
          </template>
        </ProfileItemCard>
      </div>

      <!-- Timeline variant (experience / education) -->
      <div v-else-if="isTimeline && items.length" class="relative pt-4">
        <div
          class="absolute left-[21px] top-6 h-[calc(100%-3rem)] w-0.5 bg-gradient-to-b opacity-60"
          :class="timelineLineGradient"
        />
        <div class="space-y-6">
          <ProfileItemCard
            v-for="(item, index) in items"
            :key="item.id"
            :item="item"
            :variant="variant"
          >
            <template #actions>
              <div class="flex items-center gap-1">
                <ReorderControls
                  :first="index === 0"
                  :last="index === items.length - 1"
                  @up="move(index, -1)"
                  @down="move(index, 1)"
                />
                <button
                  type="button"
                  class="min-h-9 cursor-pointer rounded-lg px-2.5 text-xs font-medium text-primary-600 transition-all hover:bg-primary-50 active:scale-[0.97]"
                  @click="openEdit(item)"
                >
                  Edit
                </button>
                <button
                  type="button"
                  class="min-h-9 cursor-pointer rounded-lg px-2.5 text-xs font-medium text-red-600 transition-all hover:bg-red-50 active:scale-[0.97]"
                  @click="deleting = item"
                >
                  Delete
                </button>
              </div>
            </template>
          </ProfileItemCard>
        </div>
      </div>

      <!-- Default list variant -->
      <div v-else-if="items.length" class="grid gap-3 pt-4">
        <ProfileItemCard v-for="(item, index) in items" :key="item.id" :item="item">
          <template #actions>
            <ReorderControls
              :first="index === 0"
              :last="index === items.length - 1"
              @up="move(index, -1)"
              @down="move(index, 1)"
            />
            <button
              type="button"
              class="min-h-9 cursor-pointer rounded-lg px-2.5 text-xs font-medium text-primary-600 transition-all hover:bg-primary-50 active:scale-[0.97]"
              @click="openEdit(item)"
            >
              Edit
            </button>
            <button
              type="button"
              class="min-h-9 cursor-pointer rounded-lg px-2.5 text-xs font-medium text-red-600 transition-all hover:bg-red-50 active:scale-[0.97]"
              @click="deleting = item"
            >
              Delete
            </button>
          </template>
        </ProfileItemCard>
      </div>

      <!-- Empty state -->
      <div v-else class="flex flex-col items-center py-12 text-center">
        <div
          class="flex size-14 items-center justify-center rounded-full bg-gradient-to-br shadow-inner"
          :class="sectionGradient[type] ? 'from-white to-white bg-opacity-10' : 'bg-slate-50'"
        >
          <div
            class="flex size-11 items-center justify-center rounded-full bg-gradient-to-br text-white shadow-sm"
            :class="sectionGradient[type] ?? 'from-slate-500 to-slate-600'"
          >
            <component :is="sectionIcon[type]" :size="20" stroke-width="1.5" />
          </div>
        </div>
        <h3 class="mt-4 text-sm font-bold text-slate-900">
          {{ emptyStates[type]?.title ?? title }}
        </h3>
        <p class="mt-1.5 max-w-xs text-xs leading-relaxed text-slate-500">
          {{ emptyStates[type]?.description ?? '' }}
        </p>
        <button
          type="button"
          class="mt-5 min-h-10 cursor-pointer rounded-xl bg-gradient-to-br from-primary-600 to-primary-500 px-5 text-sm font-semibold text-white shadow-md shadow-primary-500/20 transition-all hover:shadow-lg hover:shadow-primary-500/30 active:scale-[0.97]"
          @click="formOpen = true"
        >
          Add {{ type }}
        </button>
      </div>
    </div>

    <ProfileItemForm
      :open="formOpen"
      :type="type"
      :item="editing"
      :saving="busy"
      :server-error="serverError"
      @save="save"
      @dirty="$emit('dirty', $event)"
      @cancel="cancelEdit"
    />
    <DeleteConfirmDialog
      :open="!!deleting"
      :busy="busy"
      @cancel="deleting = null"
      @confirm="confirmDelete"
    />
  </section>
</template>
