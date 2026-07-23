import { computed, ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { extractProblemDetail } from '@/api/client'
import type { ProfileItemType } from '../types'
import {
  createProfileItem,
  deleteProfileItem,
  fetchProfile,
  profileKeys,
  reorderProfileItems,
  updateProfile,
  updateProfileItem,
} from '../api'

function isConflictCode(error: unknown): boolean {
  const detail = extractProblemDetail(error as Parameters<typeof extractProblemDetail>[0])
  return detail?.code === 'profile_conflict'
}

function messageFor(error: unknown): string | null {
  const detail = extractProblemDetail(error as Parameters<typeof extractProblemDetail>[0])
  if (detail?.code === 'validation_error' && detail.errors) {
    const first = Object.values(detail.errors as Record<string, string[]>).flat()[0]
    if (first) return first
  }
  if (detail?.detail) return detail.detail
  return null
}

export function useProfile() {
  const queryClient = useQueryClient()
  const dirtySections = ref(new Set<string>())
  const announcement = ref('')
  const query = useQuery({ queryKey: profileKeys.detail(), queryFn: fetchProfile, retry: 1 })
  const refresh = () => queryClient.invalidateQueries({ queryKey: profileKeys.all })
  const conflictCount = ref(0)
  function handleConflictIfPresent(error: unknown): boolean {
    if (isConflictCode(error)) {
      conflictCount.value++
      announcement.value = 'Profile conflict detected. Reloading latest data.'
      setTimeout(() => refresh(), 500)
      return true
    }
    return false
  }
  function handleError(error: unknown, fallback: string): void {
    if (!handleConflictIfPresent(error)) {
      announcement.value = messageFor(error) ?? fallback
    }
  }
  const profileMutation = useMutation({
    mutationFn: updateProfile,
    onSuccess: async () => {
      announcement.value = 'Profile saved.'
      dirtySections.value.clear()
      await refresh()
    },
    onError: (error) => handleError(error, 'Save failed. Please try again.'),
  })
  const createItemMutation = useMutation({
    mutationFn: createProfileItem,
    onSuccess: async () => {
      announcement.value = 'Profile item added.'
      await refresh()
    },
    onError: (error) => handleError(error, 'Failed to add item. Please try again.'),
  })
  const updateItemMutation = useMutation({
    mutationFn: ({
      item,
      input,
    }: {
      item: Parameters<typeof updateProfileItem>[0]
      input: Parameters<typeof updateProfileItem>[1]
    }) => updateProfileItem(item, input),
    onSuccess: () => {
      announcement.value = 'Profile item updated.'
      refresh()
    },
    onError: (error) => handleError(error, 'Failed to update item. Please try again.'),
  })
  const deleteItemMutation = useMutation({
    mutationFn: deleteProfileItem,
    onSuccess: async () => {
      announcement.value = 'Profile item deleted.'
      await refresh()
    },
    onError: (error) => handleError(error, 'Failed to delete item. Please try again.'),
  })
  const reorderMutation = useMutation({
    mutationFn: ({ type, ids }: { type: ProfileItemType; ids: number[] }) =>
      reorderProfileItems(type, ids),
    onSuccess: () => {
      announcement.value = 'Items reordered.'
      refresh()
    },
    onError: (error) => handleError(error, 'Failed to reorder items. Please try again.'),
  })
  const hasUnsavedChanges = computed(() => dirtySections.value.size > 0)
  const markDirty = (section: string, dirty = true) => {
    const next = new Set(dirtySections.value)
    if (dirty) next.add(section)
    else next.delete(section)
    dirtySections.value = next
  }

  return {
    ...query,
    profile: query.data,
    hasUnsavedChanges,
    markDirty,
    announcement,
    conflictCount,
    profileMutation,
    createItemMutation,
    updateItemMutation,
    deleteItemMutation,
    reorderMutation,
    refresh,
  }
}
