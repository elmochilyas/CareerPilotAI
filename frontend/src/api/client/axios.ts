import axios from 'axios'

const client = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://careerpilot-api.test',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 419) {
      return client.get('/sanctum/csrf-cookie').then(() => Promise.reject(error))
    }
    return Promise.reject(error)
  },
)

export default client
