export interface ProblemDetail {
  type: string
  title: string
  status: number
  detail: string
  instance: string
  code: string
  errors: Record<string, unknown>
  request_id: string
}
