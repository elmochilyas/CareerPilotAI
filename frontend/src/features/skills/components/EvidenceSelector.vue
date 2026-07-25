<script setup lang="ts">
import { computed, ref } from 'vue'
import { ExternalLink, Trash2, Plus } from '@lucide/vue'
import EvidenceUrlInput from './EvidenceUrlInput.vue'
import type { SkillEvidenceEntry, EvidenceInput } from '../types'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'

export interface ProfileItemOption {
  id: number
  type: string
  title: string
  organization: string | null
  start_date: string | null
  end_date: string | null
}

const props = withDefaults(
  defineProps<{
    evidence: SkillEvidenceEntry[]
    saving: boolean
    profileItems?: Record<string, ProfileItemOption[]>
    profileItemsLoading?: boolean
    profileItemsError?: boolean
  }>(),
  {
    profileItems: () => ({}),
  },
)
const emit = defineEmits<{
  add: [value: EvidenceInput]
  remove: [value: string]
}>()

const isOpen = ref(false)
const activeTab = ref<'profile' | 'url'>('profile')

const groupEntries = computed(() => {
  return Object.entries(props.profileItems).filter(([, items]) => items.length > 0) as [
    string,
    ProfileItemOption[],
  ][]
})

function addProfileItem(item: ProfileItemOption) {
  emit('add', { type: 'profile_item', value: String(item.id), label: item.title })
  isOpen.value = false
}

const urlForm = ref({ value: '', label: '' })
const urlValid = ref(false)

function addUrlEvidence() {
  if (!urlValid.value) return
  emit('add', { type: 'url', value: urlForm.value.value, label: urlForm.value.label || null })
  urlForm.value = { value: '', label: '' }
  isOpen.value = false
}
</script>

<template>
  <div>
    <Button variant="outline" size="sm" @click="isOpen = true">
      <Plus class="size-4" aria-hidden="true" />
      Add Evidence
    </Button>

    <div v-if="evidence.length > 0" class="mt-3 space-y-2">
      <div
        v-for="entry in evidence"
        :key="entry.key"
        class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
      >
        <div class="flex items-center gap-2 text-sm">
          <ExternalLink
            v-if="entry.type === 'url'"
            class="size-4 text-slate-400"
            aria-hidden="true"
          />
          <span v-else class="size-4 text-slate-400">{{
            entry.type === 'profile_item' ? 'PI' : 'TX'
          }}</span>
          <span class="text-slate-900">{{ entry.label || entry.value }}</span>
        </div>
        <button
          type="button"
          class="text-slate-400 hover:text-red-600"
          :disabled="saving"
          :aria-label="'Remove ' + (entry.label || 'evidence')"
          @click="emit('remove', entry.key)"
        >
          <Trash2 class="size-4" aria-hidden="true" />
        </button>
      </div>
    </div>

    <Modal :open="isOpen" title="Add Evidence" size="md" @close="isOpen = false">
      <div class="p-6">
        <div class="flex gap-2 border-b border-slate-200">
          <button
            type="button"
            :class="[
              'px-4 py-2 text-sm font-medium transition-all',
              activeTab === 'profile'
                ? 'border-b-2 border-primary-500 text-primary-700'
                : 'text-slate-500 hover:text-slate-700',
            ]"
            @click="activeTab = 'profile'"
          >
            Profile Items
          </button>
          <button
            type="button"
            :class="[
              'px-4 py-2 text-sm font-medium transition-all',
              activeTab === 'url'
                ? 'border-b-2 border-primary-500 text-primary-700'
                : 'text-slate-500 hover:text-slate-700',
            ]"
            @click="activeTab = 'url'"
          >
            URL
          </button>
        </div>

        <div v-if="activeTab === 'profile'" class="mt-4 max-h-64 space-y-3 overflow-y-auto">
          <div v-for="[type, items] in groupEntries" :key="type">
            <h4 class="mb-1 text-xs font-semibold uppercase text-slate-500">{{ type }}</h4>
            <button
              v-for="item in items"
              :key="item.id"
              type="button"
              class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left hover:bg-primary-50"
              @click="addProfileItem(item)"
            >
              <div>
                <div class="text-sm font-medium text-slate-900">{{ item.title }}</div>
                <div v-if="item.organization" class="text-xs text-slate-500">
                  {{ item.organization }}
                </div>
              </div>
              <Plus class="size-4 text-primary-500" aria-hidden="true" />
            </button>
          </div>
          <div v-if="profileItemsLoading" class="py-4 text-center text-sm text-slate-400">
            Loading profile items...
          </div>
          <div
            v-else-if="profileItemsError"
            class="rounded-lg bg-red-50 p-3 text-center text-sm text-red-600"
          >
            Failed to load profile items.
          </div>
          <p v-else-if="groupEntries.length === 0" class="text-sm text-slate-500">
            No profile items available.
          </p>
        </div>

        <div v-if="activeTab === 'url'" class="mt-4">
          <EvidenceUrlInput
            :model-value="urlForm"
            @update:model-value="urlForm = $event"
            @valid="urlValid = $event"
          />
          <div class="mt-4">
            <Button class="w-full" :disabled="!urlValid || saving" @click="addUrlEvidence">
              {{ saving ? 'Adding...' : 'Add URL' }}
            </Button>
          </div>
        </div>
      </div>
    </Modal>
  </div>
</template>
