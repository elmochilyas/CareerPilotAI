<script setup lang="ts">
import { X } from '@lucide/vue'
import { computed, nextTick, reactive, ref, watch } from 'vue'
import type { ProfileItem, ProfileItemInput, ProfileItemType } from '../types'
import { serverFieldErrors, validateNativeForm } from '../utils/validation'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  open: boolean
  type: ProfileItemType
  item?: ProfileItem | null
  saving?: boolean
  serverError?: unknown
}>()

const emit = defineEmits<{ save: [value: ProfileItemInput]; cancel: []; dirty: [value: boolean] }>()

const form = ref<HTMLFormElement | null>(null)
const errors = ref<Record<string, string>>({})

const values = reactive({
  title: '',
  organization: '',
  location: '',
  start_date: '',
  end_date: '',
  description: '',
  is_current: false,
  degree: '',
  field: '',
  role: '',
  technologies: [] as string[],
  project_url: '',
  repository_url: '',
  issuer: '',
  credential_id: '',
  credential_url: '',
  expiry_date: '',
  does_not_expire: false,
})

function reset() {
  const m = props.item?.metadata ?? {}
  const endDate = props.item?.end_date ?? ''
  const isCurrent =
    props.type === 'experience' || props.type === 'education' || props.type === 'project'
      ? !props.item?.end_date && !!props.item?.start_date
      : false
  const doesNotExpire = props.type === 'certification' && !m.expiry_date && !!props.item?.start_date
  Object.assign(values, {
    title: props.item?.title ?? '',
    organization: props.item?.organization ?? '',
    location: props.item?.location ?? '',
    start_date: props.item?.start_date ?? '',
    end_date: endDate,
    description: props.item?.description ?? '',
    is_current: isCurrent,
    degree: m.degree ?? '',
    field: m.field ?? '',
    role: m.role ?? '',
    technologies: (m.technologies as string[]) ?? [],
    project_url: m.project_url ?? '',
    repository_url: m.repository_url ?? '',
    issuer: m.issuer ?? '',
    credential_id: m.credential_id ?? '',
    credential_url: m.credential_url ?? '',
    expiry_date: m.expiry_date ?? '',
    does_not_expire: doesNotExpire,
  })
  errors.value = {}
}

watch(
  () => [props.open, props.item],
  async () => {
    reset()
    if (props.open) {
      await nextTick()
      form.value?.querySelector<HTMLInputElement>('input')?.focus()
    }
  },
  { immediate: true, deep: true },
)

watch(
  () => props.serverError,
  (v) => {
    if (v) errors.value = serverFieldErrors(v)
  },
)

function metadataPayload(): Record<string, unknown> {
  const meta: Record<string, unknown> = {}
  if (props.type === 'education') {
    if (values.degree) meta.degree = values.degree
    if (values.field) meta.field = values.field
  }
  if (props.type === 'experience') {
    if (values.degree) meta.employment_type = values.degree
  }
  if (props.type === 'project') {
    if (values.role) meta.role = values.role
    if (values.project_url) meta.project_url = values.project_url
    if (values.repository_url) meta.repository_url = values.repository_url
    if (values.technologies.length) meta.technologies = values.technologies
  }
  if (props.type === 'certification') {
    if (values.issuer) meta.issuer = values.issuer
    if (values.credential_id) meta.credential_id = values.credential_id
    if (values.credential_url) meta.credential_url = values.credential_url
    if (values.expiry_date && !values.does_not_expire) meta.expiry_date = values.expiry_date
  }
  return Object.keys(meta).length ? meta : (null as unknown as Record<string, unknown>)
}

function submit() {
  if (!form.value) return
  const result = validateNativeForm(form.value)
  errors.value = result.errors
  if (!result.valid) {
    const field = form.value.elements.namedItem(result.firstInvalidField!)
    if (field instanceof HTMLElement) field.focus()
    return
  }
  emit('save', {
    type: props.type,
    title: values.title.trim(),
    organization: values.organization.trim() || null,
    location: values.location.trim() || null,
    start_date: values.start_date || null,
    end_date: values.is_current || values.does_not_expire ? null : values.end_date || null,
    description: values.description || null,
    is_current: values.is_current,
    metadata: metadataPayload() as Record<string, string> | undefined,
  })
}

const typeLabel = computed(() => (props.item ? `Edit ${props.type}` : `Add ${props.type}`))

function addTechnology(value: string) {
  const trimmed = value.trim()
  if (trimmed && !values.technologies.includes(trimmed)) {
    values.technologies = [...values.technologies, trimmed]
  }
}

function removeTechnology(index: number) {
  values.technologies = values.technologies.filter((_, i) => i !== index)
}

const showEndDate = computed(() => {
  if (props.type === 'experience' || props.type === 'education' || props.type === 'project') {
    return !values.is_current
  }
  if (props.type === 'certification') {
    return !values.does_not_expire
  }
  return true
})

const techInput = ref('')

function onTechKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter') {
    e.preventDefault()
    addTechnology(techInput.value)
    techInput.value = ''
  }
  if (e.key === 'Backspace' && techInput.value === '' && values.technologies.length > 0) {
    removeTechnology(values.technologies.length - 1)
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="open"
        class="fixed inset-0 z-40 grid place-items-center overflow-y-auto bg-[var(--color-neutral-950)]/40 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`${type}-form-title`"
        @keydown.escape="emit('cancel')"
        @click.self="emit('cancel')"
      >
        <form
          ref="form"
          class="my-4 w-full max-w-xl overflow-hidden rounded-2xl bg-[var(--surface-primary)] shadow-[var(--shadow-neo-raised)]"
          novalidate
          @submit.prevent="submit"
          @input="emit('dirty', true)"
        >
          <div
            class="sticky top-0 z-10 border-b border-[var(--border-subtle)]/80 bg-[var(--surface-primary)]/95 px-6 py-4 backdrop-blur-sm"
          >
            <div class="flex items-center justify-between">
              <h2
                :id="`${type}-form-title`"
                class="text-[var(--text-lg)] font-bold capitalize text-[var(--text-primary)]"
              >
                {{ typeLabel }}
              </h2>
              <button
                type="button"
                class="flex size-8 cursor-pointer items-center justify-center rounded-[var(--radius-lg)] text-[var(--text-muted)] transition-all hover:bg-[var(--color-neutral-100)] hover:text-[var(--text-secondary)] hover:rotate-90"
                aria-label="Close"
                @click="emit('cancel')"
              >
                <X :size="16" stroke-width="2" />
              </button>
            </div>
          </div>

          <div class="overflow-y-auto px-6 py-5">
            <div class="grid gap-5 sm:grid-cols-2">
              <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold sm:col-span-2">
                {{
                  type === 'experience'
                    ? 'Job title'
                    : type === 'education'
                      ? 'Degree / qualification'
                      : type === 'project'
                        ? 'Project name'
                        : 'Certification name'
                }}
                <input
                  v-model="values.title"
                  name="title"
                  required
                  maxlength="255"
                  class="min-h-11 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                  :placeholder="
                    type === 'experience'
                      ? 'e.g. Senior Software Engineer'
                      : type === 'education'
                        ? 'e.g. Bachelor of Science'
                        : ''
                  "
                />
                <span
                  v-if="errors.title"
                  class="text-[var(--text-xs)] font-medium text-[var(--color-error-600)]"
                  >{{ errors.title }}</span
                >
              </label>

              <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold">
                {{
                  type === 'experience'
                    ? 'Company'
                    : type === 'education'
                      ? 'Institution'
                      : type === 'project'
                        ? 'Client / context'
                        : 'Issuing organization'
                }}
                <input
                  v-model="values.organization"
                  name="organization"
                  maxlength="255"
                  class="min-h-11 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                  :placeholder="type === 'experience' ? 'e.g. Acme Corp' : ''"
                />
              </label>

              <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold">
                Location
                <input
                  v-model="values.location"
                  name="location"
                  maxlength="255"
                  class="min-h-11 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                />
              </label>

              <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold">
                {{ type === 'certification' ? 'Issue date' : 'Start date' }}
                <input
                  v-model="values.start_date"
                  name="start_date"
                  type="date"
                  class="min-h-11 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                />
              </label>

              <label v-if="showEndDate" class="grid gap-1.5 text-[var(--text-sm)] font-semibold">
                {{ type === 'certification' ? 'Expiration date' : 'End date' }}
                <input
                  v-model="values.end_date"
                  name="end_date"
                  type="date"
                  :required="!values.is_current && !values.does_not_expire && !!values.start_date"
                  class="min-h-11 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                />
                <span
                  v-if="errors.end_date"
                  class="text-[var(--text-xs)] font-medium text-[var(--color-error-600)]"
                  >{{ errors.end_date }}</span
                >
              </label>

              <!-- Current / ongoing / does not expire checkbox -->
              <div v-if="type !== 'certification'" class="flex items-center gap-2.5 sm:col-span-2">
                <div class="relative flex items-center justify-center">
                  <input
                    :id="`is-current-${type}`"
                    v-model="values.is_current"
                    type="checkbox"
                    class="size-4 cursor-pointer appearance-none rounded-[var(--radius-md)] border-2 border-[var(--border-default)] bg-[var(--surface-primary)] transition-all checked:border-[var(--color-primary-600)] checked:bg-[var(--color-primary-600)] focus:ring-2 focus:ring-[var(--color-primary-500)]/30 focus:ring-offset-0"
                  />
                  <svg
                    v-if="values.is_current"
                    class="pointer-events-none absolute size-3.5 text-white"
                    viewBox="0 0 16 16"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <polyline points="3 8 6 11 13 4" class="animate-checkmark-draw" />
                  </svg>
                </div>
                <label
                  :for="`is-current-${type}`"
                  class="cursor-pointer select-none text-[var(--text-sm)] text-[var(--text-secondary)]"
                >
                  {{
                    type === 'experience'
                      ? 'I currently work in this role'
                      : type === 'education'
                        ? 'I currently study here'
                        : 'This project is ongoing'
                  }}
                </label>
              </div>

              <!-- Certification: does not expire -->
              <div v-if="type === 'certification'" class="flex items-center gap-2.5 sm:col-span-2">
                <div class="relative flex items-center justify-center">
                  <input
                    id="does-not-expire"
                    v-model="values.does_not_expire"
                    type="checkbox"
                    class="size-4 cursor-pointer appearance-none rounded-[var(--radius-md)] border-2 border-[var(--border-default)] bg-[var(--surface-primary)] transition-all checked:border-[var(--color-primary-600)] checked:bg-[var(--color-primary-600)] focus:ring-2 focus:ring-[var(--color-primary-500)]/30 focus:ring-offset-0"
                  />
                  <svg
                    v-if="values.does_not_expire"
                    class="pointer-events-none absolute size-3.5 text-white"
                    viewBox="0 0 16 16"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <polyline points="3 8 6 11 13 4" class="animate-checkmark-draw" />
                  </svg>
                </div>
                <label
                  for="does-not-expire"
                  class="cursor-pointer select-none text-[var(--text-sm)] text-[var(--text-secondary)]"
                >
                  This certification does not expire
                </label>
              </div>

              <!-- Type-specific metadata -->
              <template v-if="type === 'education'">
                <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold">
                  Degree
                  <input
                    v-model="values.degree"
                    name="degree"
                    maxlength="255"
                    class="min-h-11 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                    placeholder="e.g. Bachelor's"
                  />
                </label>
                <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold">
                  Field of study
                  <input
                    v-model="values.field"
                    name="field"
                    maxlength="255"
                    class="min-h-11 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                    placeholder="e.g. Computer Science"
                  />
                </label>
              </template>

              <template v-if="type === 'experience'">
                <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold">
                  Employment type
                  <input
                    v-model="values.degree"
                    name="employment_type"
                    maxlength="255"
                    class="min-h-11 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                    placeholder="e.g. Full-time, Contract"
                  />
                </label>
              </template>

              <template v-if="type === 'project'">
                <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold">
                  Your role
                  <input
                    v-model="values.role"
                    name="role"
                    maxlength="255"
                    class="min-h-11 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                    placeholder="e.g. Frontend Developer"
                  />
                </label>

                <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold sm:col-span-2">
                  Technologies
                  <div
                    class="flex min-h-11 flex-wrap items-center gap-1.5 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-2.5 py-1.5 shadow-[var(--shadow-neo-inset)] has-focus:ring-2 has-focus:ring-[var(--color-primary-500)]/30"
                    @click="(form?.querySelector('#tech-input') as HTMLInputElement)?.focus()"
                  >
                    <span
                      v-for="(tech, i) in values.technologies"
                      :key="i"
                      class="inline-flex select-none items-center gap-1 rounded-[var(--radius-lg)] bg-gradient-to-r from-violet-50 to-violet-100 px-2.5 py-1 text-[var(--text-sm)] font-medium text-violet-800 shadow-sm"
                    >
                      {{ tech }}
                      <button
                        type="button"
                        class="-mr-0.5 inline-flex size-4 cursor-pointer items-center justify-center rounded-full text-violet-500 transition-all hover:bg-violet-200 hover:text-violet-900"
                        :aria-label="`Remove ${tech}`"
                        @click.stop="removeTechnology(i)"
                      >
                        <X :size="12" stroke-width="2" />
                      </button>
                    </span>
                    <input
                      id="tech-input"
                      ref="techInput"
                      v-model="techInput"
                      placeholder="Type a technology and press Enter"
                      class="min-w-[120px] flex-1 border-none bg-transparent px-1 py-1 text-[var(--text-sm)] outline-none placeholder:text-[var(--text-muted)]"
                      @keydown="onTechKeydown"
                    />
                  </div>
                </label>

                <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold">
                  Project URL
                  <input
                    v-model="values.project_url"
                    name="project_url"
                    type="url"
                    maxlength="500"
                    class="min-h-11 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                    placeholder="https://…"
                  />
                </label>
                <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold">
                  Repository URL
                  <input
                    v-model="values.repository_url"
                    name="repository_url"
                    type="url"
                    maxlength="500"
                    class="min-h-11 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                    placeholder="https://github.com/…"
                  />
                </label>
              </template>

              <template v-if="type === 'certification'">
                <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold">
                  Issuer
                  <input
                    v-model="values.issuer"
                    name="issuer"
                    maxlength="255"
                    class="min-h-11 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                    placeholder="e.g. AWS, Google"
                  />
                </label>
                <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold">
                  Credential ID
                  <input
                    v-model="values.credential_id"
                    name="credential_id"
                    maxlength="255"
                    class="min-h-11 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                    placeholder="e.g. ABC123XYZ"
                  />
                </label>
                <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold sm:col-span-2">
                  Credential URL
                  <input
                    v-model="values.credential_url"
                    name="credential_url"
                    type="url"
                    maxlength="500"
                    class="min-h-11 rounded-[var(--radius-xl)] bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                    placeholder="https://…"
                  />
                </label>
              </template>

              <label class="grid gap-1.5 text-[var(--text-sm)] font-semibold sm:col-span-2">
                Description
                <textarea
                  v-model="values.description"
                  name="description"
                  maxlength="5000"
                  rows="5"
                  class="rounded-[var(--radius-xl)] bg-[var(--surface-inset)] p-3.5 shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
                  :placeholder="
                    type === 'experience' ? 'Describe your responsibilities and achievements…' : ''
                  "
                />
                <span class="text-right text-[var(--text-xs)] text-[var(--text-muted)] font-medium"
                  >{{ values.description.length }}/5000</span
                >
              </label>
            </div>
          </div>

          <div
            class="sticky bottom-0 rounded-b-2xl border-t border-[var(--border-subtle)]/80 bg-[var(--surface-primary)]/95 px-6 py-4 backdrop-blur-sm"
          >
            <div class="flex justify-end gap-2">
              <Button variant="secondary" @click="emit('cancel')"> Cancel </Button>
              <Button variant="primary" :disabled="saving" :loading="saving"> Saving… </Button>
            </div>
          </div>
        </form>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active {
  transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-enter-active > form {
  transition:
    transform 0.25s cubic-bezier(0.16, 1, 0.3, 1),
    opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-leave-active {
  transition: opacity 0.2s ease-in;
}
.modal-leave-active > form {
  transition:
    transform 0.2s ease-in,
    opacity 0.2s ease-in;
}
.modal-enter-from {
  opacity: 0;
}
.modal-enter-from > form {
  transform: scale(0.92) translateY(8px);
  opacity: 0;
}
.modal-leave-to {
  opacity: 0;
}
.modal-leave-to > form {
  transform: scale(0.95);
  opacity: 0;
}
</style>
