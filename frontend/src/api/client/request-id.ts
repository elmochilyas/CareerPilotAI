export function nextRequestId(): string {
  return `req_${crypto.randomUUID()}`
}
