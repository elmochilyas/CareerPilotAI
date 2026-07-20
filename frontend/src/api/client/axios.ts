import axios from 'axios'
import type { AxiosError } from 'axios'

const client = axios.create({
  baseURL: '/',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

let isRefreshingCsrf = false
let failedQueue: Array<{
  resolve: (value: unknown) => void
  reject: (reason: unknown) => void
}> = []

let onUnauthorized: (() => void) | null = null

export function setUnauthorizedHandler(handler: () => void): void {
  onUnauthorized = handler
}

function processQueue(error: unknown): void {
  failedQueue.forEach(({ reject }) => reject(error))
  failedQueue = []
}

client.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    if (!error.response) {
      return Promise.reject(error)
    }

    if (error.response.status === 419) {
      const originalRequest = error.config
      if (!originalRequest) {
        return Promise.reject(error)
      }

      if (isRefreshingCsrf) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject })
        }).then(() => client(originalRequest))
      }

      isRefreshingCsrf = true

      try {
        await client.get('/sanctum/csrf-cookie')
        processQueue(null)
        return client(originalRequest)
      } catch (csrfError) {
        processQueue(csrfError)
        return Promise.reject(csrfError)
      } finally {
        isRefreshingCsrf = false
      }
    }

    if (error.response.status === 401) {
      onUnauthorized?.()
    }

    return Promise.reject(error)
  },
)

export default client
