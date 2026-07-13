export interface ProblemDetail {
  type: string
  title: string
  status: number
  detail: string
  instance: string
  code: string
  errors: Record<string, string[]>
  request_id: string
  debug?: {
    exception: string
    message: string
    file: string
    line: number
  }
}
