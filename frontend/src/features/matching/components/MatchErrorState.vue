<script setup lang="ts">
withDefaults(
  defineProps<{
    title?: string
    detail?: string | null
    busy?: boolean
  }>(),
  {
    title: 'Could not load the match analysis',
    detail: null,
    busy: false,
  },
)

const emit = defineEmits<{
  retry: []
}>()
</script>

<template>
  <div
    role="alert"
    class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-6 text-center"
  >
    <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
    <p v-if="detail" class="mt-2 text-sm leading-relaxed text-slate-600">{{ detail }}</p>
    <button
      type="button"
      :disabled="busy"
      class="mt-5 inline-flex min-h-10 items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
      @click="emit('retry')"
    >
      {{ busy ? 'Retrying…' : 'Try again' }}
    </button>
  </div>
</template>
