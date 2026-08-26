## Context

Approved resumes already contain canonical, trusted, immutable content. Export currently delegates to browser printing and provides no direct file response.

## Goals / Non-Goals

- Generate and download a valid PDF directly from an approved owned resume.
- Preserve source content without AI calls or mutation.
- Keep the demo synchronous and small.
- Do not implement DOCX, export history, templates, queues, or storage in this slice.

## Decisions

### Server-side DOMPDF rendering

Use DOMPDF to render the same escaped Blade document returned by an authenticated HTML preview endpoint. A single document rendering action and source-CV-derived Blade template therefore own section order, typography, spacing, compact skills, and pagination for both screen and PDF. The template follows the current uploaded CV's single-column A4 composition and blue/navy hierarchy; it never reads extracted CV text as tailored content.

### Source template association

New resumes select `source-cv-classic` when the candidate has an imported CV document. Existing resumes without a template key use this safe source-derived default at render time, so approved immutable content is not mutated. Trusted resume/profile data supplies content; the upload supplies presentation reference only.

### Authorization and state

The existing ResumePolicy view check protects the endpoint. Export requires `approved`; drafts return a stable 409 conflict. The renderer receives only canonical preview arrays and escapes all candidate content through Blade interpolation.

### Synchronous MVP

PDF generation is synchronous because the current single-resume document is bounded and demo scale is small. A future stored/queued export can replace it without changing the download UX.

## Risks / Trade-offs

- DOMPDF supports a limited CSS subset, so the first source-derived template preserves the dominant layout rather than every font metric from the uploaded PDF.
- Large future documents may require queued persisted exports.

## Rollback

Remove the route, controller action, Blade template, frontend action, and Composer dependency. No migration or stored data is involved.
