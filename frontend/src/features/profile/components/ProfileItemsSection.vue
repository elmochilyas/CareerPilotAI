<script setup lang="ts">
import { Briefcase, GraduationCap, Folder, Award, Plus } from '@lucide/vue'
import { ref, watch } from 'vue'
import type { ProfileItem, ProfileItemInput, ProfileItemType } from '../types'
import DeleteConfirmDialog from './DeleteConfirmDialog.vue'
import ProfileItemCard from './ProfileItemCard.vue'
import ProfileItemForm from './ProfileItemForm.vue'
import ReorderControls from './ReorderControls.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

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

const sectionColor: Record<string, string> = {
  experience: 'bg-[var(--color-primary-100)] text-[var(--color-primary-600)]',
  education: 'bg-[var(--color-primary-100)] text-[var(--color-primary-600)]',
  project: 'bg-[var(--color-primary-100)] text-[var(--color-primary-600)]',
  certification: 'bg-[var(--color-primary-100)] text-[var(--color-primary-600)]',
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

const vnt = props.variant ?? 'default'
const isTimeline = vnt === 'timeline' || vnt === 'academic'
const isGrid = vnt === 'grid'
const isAchievement = vnt === 'achievement'
</script>

<template>
  <section :aria-labelledby="`${type}-heading`">
    <header class="flex items-center justify-between gap-3 py-4">
      <div class="flex items-center gap-3">
        <div
          class="flex size-9 items-center justify-center rounded-[var(--radius-lg)]"
          :class="
            sectionColor[type] ?? 'bg-[var(--color-neutral-100)] text-[var(--color-neutral-600)]'
          "
        >
          <component :is="sectionIcon[type]" :size="16" stroke-width="1.5" />
        </div>
        <h2
          :id="`${type}-heading`"
          class="text-[var(--text-base)] font-semibold text-[var(--text-primary)]"
        >
          {{ title }}
        </h2>
      </div>
      <Button size="sm" @click="formOpen = true">
        <Plus :size="14" stroke-width="2.5" />
        Add {{ type }}
      </Button>
    </header>

    <div>
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
            <Button variant="ghost" size="sm" @click="openEdit(item)">Edit</Button>
            <Button
              variant="ghost"
              size="sm"
              class="text-[var(--color-error-600)] hover:bg-[var(--color-error-50)] hover:text-[var(--color-error-700)]"
              @click="deleting = item"
              >Delete</Button
            >
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
            <Button variant="ghost" size="sm" @click="openEdit(item)">Edit</Button>
            <Button
              variant="ghost"
              size="sm"
              class="text-[var(--color-error-600)] hover:bg-[var(--color-error-50)] hover:text-[var(--color-error-700)]"
              @click="deleting = item"
              >Delete</Button
            >
          </template>
        </ProfileItemCard>
      </div>

      <!-- Timeline variant (experience / education) -->
      <div v-else-if="isTimeline && items.length" class="relative pt-4">
        <div
          class="absolute left-[21px] top-6 h-[calc(100%-3rem)] w-0.5 bg-[var(--border-subtle)]"
        />
        <div class="space-y-6">
          <ProfileItemCard
            v-for="(item, index) in items"
            :key="item.id"
            :item="item"
            :variant="vnt"
          >
            <template #actions>
              <div class="flex items-center gap-1">
                <ReorderControls
                  :first="index === 0"
                  :last="index === items.length - 1"
                  @up="move(index, -1)"
                  @down="move(index, 1)"
                />
                <Button variant="ghost" size="sm" @click="openEdit(item)">Edit</Button>
                <Button
                  variant="ghost"
                  size="sm"
                  class="text-[var(--color-error-600)] hover:bg-[var(--color-error-50)] hover:text-[var(--color-error-700)]"
                  @click="deleting = item"
                  >Delete</Button
                >
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
            <Button variant="ghost" size="sm" @click="openEdit(item)">Edit</Button>
            <Button
              variant="ghost"
              size="sm"
              class="text-[var(--color-error-600)] hover:bg-[var(--color-error-50)] hover:text-[var(--color-error-700)]"
              @click="deleting = item"
              >Delete</Button
            >
          </template>
        </ProfileItemCard>
      </div>

      <!-- Empty state -->
      <EmptyState
        v-else
        :icon="sectionIcon[type] as any"
        :title="emptyStates[type]?.title ?? title"
        :description="emptyStates[type]?.description ?? ''"
      >
        <Button size="sm" @click="formOpen = true">
          <Plus :size="14" stroke-width="2.5" />
          Add {{ type }}
        </Button>
      </EmptyState>
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
