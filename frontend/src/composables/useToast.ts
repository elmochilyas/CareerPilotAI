import { ref } from 'vue'

export type ToastVariant = 'success' | 'error' | 'warning' | 'info'

export interface ToastItem {
  id: number
  message: string
  variant: ToastVariant
  duration: number
}

let counter = 0

const toasts = ref<ToastItem[]>([])

function add(message: string, variant: ToastVariant, duration = 3500): void {
  const id = ++counter
  toasts.value.push({ id, message, variant, duration })
}

function dismiss(id: number): void {
  toasts.value = toasts.value.filter((t) => t.id !== id)
}

function toast(message: string, opts?: { variant?: ToastVariant; duration?: number }) {
  add(message, opts?.variant ?? 'success', opts?.duration)
}

toast.success = (message: string, duration?: number) => add(message, 'success', duration)
toast.error = (message: string, duration?: number) => add(message, 'error', duration)
toast.warning = (message: string, duration?: number) => add(message, 'warning', duration)
toast.info = (message: string, duration?: number) => add(message, 'info', duration)
toast.dismiss = dismiss

export function useToast() {
  return { toasts, toast, dismiss }
}
