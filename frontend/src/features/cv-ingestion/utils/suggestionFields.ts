export function fieldOf(value: unknown, keys: string[]): string {
  const record = value as Record<string, unknown> | null
  for (const key of keys) {
    const fieldValue = record?.[key]
    if (typeof fieldValue === 'string' && fieldValue !== '') return fieldValue
  }
  return ''
}
