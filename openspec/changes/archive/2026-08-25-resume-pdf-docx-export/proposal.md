## Why

Candidates can approve and view a tailored CV but cannot download a file, leaving the MVP acceptance flow incomplete. A direct, authorization-checked PDF download is required now for the demo and RES-005.

## What Changes

- Add an ATS-safe server-generated PDF for approved tailored CVs.
- Reproduce the candidate's imported CV presentation as a reusable document template, with the same renderer used by the in-app preview and PDF export.
- Add an authenticated download endpoint and direct Download PDF action.
- Generate from persisted approved resume content only; no AI or factual transformation occurs during export.
- Return safe problem details for non-approved, missing, or cross-user resources.
- In scope: one source-CV-derived template, authenticated HTML preview, and synchronous PDF download for the MVP. Out of scope: arbitrary pixel-perfect template extraction, DOCX, background export history, email/share, and application attachment.

## Capabilities

### New Capabilities
- `resume-document-export`: Direct PDF generation and download for an owned approved tailored CV.

### Modified Capabilities

## Impact

- Backend Resumes domain, API route/controller, PDF renderer, feature tests, and OpenAPI.
- Frontend cv-tailoring API and Export action.
- Adds `dompdf/dompdf` 3.1.6, a mature framework-independent PDF renderer compatible with Laravel 13; no new infrastructure or database migration.
- Requirement: RES-005. Acceptance: an owner downloads a valid PDF; drafts and cross-user requests are rejected safely.
