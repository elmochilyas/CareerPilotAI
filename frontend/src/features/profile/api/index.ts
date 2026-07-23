import client from '@/api/client/axios'
import type {
  ApiData,
  CandidateProfile,
  ProfileItem,
  ProfileItemInput,
  ProfileItemType,
  ProfileUpdate,
} from '../types'

export const profileKeys = {
  all: ['profile'] as const,
  detail: () => [...profileKeys.all, 'detail'] as const,
}

export async function fetchProfile(): Promise<CandidateProfile> {
  return (await client.get<ApiData<CandidateProfile>>('/api/v1/profile')).data.data
}

export async function updateProfile(input: ProfileUpdate): Promise<CandidateProfile> {
  return (await client.patch<ApiData<CandidateProfile>>('/api/v1/profile', input)).data.data
}

export async function createProfileItem(input: ProfileItemInput): Promise<ProfileItem> {
  return (await client.post<ApiData<ProfileItem>>('/api/v1/profile/items', input)).data.data
}

export async function updateProfileItem(
  item: ProfileItem,
  input: Partial<ProfileItemInput>,
): Promise<ProfileItem> {
  return (
    await client.patch<ApiData<ProfileItem>>(`/api/v1/profile/items/${item.id}`, {
      ...input,
      updated_at: item.updated_at,
    })
  ).data.data
}

export async function deleteProfileItem(item: ProfileItem): Promise<void> {
  await client.delete(`/api/v1/profile/items/${item.id}`)
}

export async function reorderProfileItems(
  type: ProfileItemType,
  itemIds: number[],
): Promise<ProfileItem[]> {
  return (
    await client.patch<ApiData<ProfileItem[]>>('/api/v1/profile/items/reorder', {
      type,
      item_ids: itemIds,
    })
  ).data.data
}
