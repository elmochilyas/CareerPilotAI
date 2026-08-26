# CareerPilot AI — Database Design Reference

This directory is the **agent-readable source of truth** for the CareerPilot AI database design.

## Files

- `MCD.md`: entities, conceptual attributes, associations, cardinalities, and business rules.
- `MLD.md`: tables, columns, SQL types, PK/FK, constraints, indexes, and delete rules.
- `IMPLEMENTATION_PLAN.md`: migration order and OpenSpec-to-table ownership.

## Authority order

1. The currently approved OpenSpec change
2. `docs/database/MLD.md`
3. `docs/database/MCD.md`
4. Existing Laravel migrations
5. Visual MCD/MLD images

A mismatch must be reported before implementation. Do not silently choose one interpretation.

## Mandatory agent workflow

Before creating or modifying a migration, model, factory, seeder, policy, request, resource, or database test:

1. Read this file.
2. Read the relevant section in `MCD.md`.
3. Read the relevant tables in `MLD.md`.
4. Read `IMPLEMENTATION_PLAN.md`.
5. Inspect existing migrations and the current database schema.
6. Implement only the schema belonging to the active OpenSpec change.
7. Do not add speculative tables, columns, pivots, versioning, or relationships.
8. Update these references in the same Pull Request when an approved schema decision changes.

## Core decisions

- MySQL with Laravel migrations and Eloquent.
- Trusted candidate data remains relational.
- JSON is limited to bounded snapshots, temporary AI extraction data, document content, sources, and provider metadata.
- AI never updates trusted profile data without validation and required candidate confirmation.
- Job-offer descriptions are not versioned in the MVP.
- Match runs, resume versions, and application status history are retained.
- Match analysis snapshots are immutable once completed; recalculating creates a new snapshot, never mutating the old one. At most one active (queued/processing) analysis exists per profile and opportunity. See the `match_analyses`, `match_scores`, and `match_findings` tables in `MLD.md`.
- A user can have at most one candidate profile.
- A candidate can have at most one application for one saved opportunity.

## Phase A decisions — core-hardening-baseline (2026-08-25)

### File/storage architecture — Option B chosen (purpose-specific entities)

- The generic `FILE` entity modelled in `MCD.md` / `files` table in `MLD.md` is **formally deprecated** as of this change. No `files` table SHALL be created.
- Rationale: implemented reality is already purpose-specific (`cv_documents` owns upload lifecycle, scan status, and extraction linkage; `resumes` stores structured content as JSON). Future needs (application documents, PDF/DOCX exports, account export bundles) share no meaningful structure beyond bytes+mime and have stronger ownership/provenance when modelled per purpose (e.g., `application_documents` FK to `applications` + `uploader` + `scan_status`).
- Ownership/provenance: application documents FK directly to their aggregate (application or profile) and uploader; exports store artifact metadata on their owning rows; quarantine/scan-status abstraction is reused per entity, not centralized.
- Migration implications: no data migration in Phase A. Existing `resumes.file_id` (nullable BIGINT, no FK, referencing a `files` table that will never exist) is a dangling column and SHALL be removed in the next change that touches `resumes` schema (the Application Documents phase). Until then it is ignored (never written, never read, no FK, no index). Future phases MUST NOT introduce a generic `files` table or reuse `file_id`.
- See `MCD.md` (§ FILE) and `MLD.md` (§ `files`, § `resumes.file_id`) for the deprecation markers and the same guidance.

### Interview model — Option C recommended for Phase D

- For Phase D **Tasks / Reminders / Interviews**, the recommended model is **Option C — task/reminder entry + dedicated `interviews` record** (1:0..1 link from `tasks` to `interviews`).
- Rationale: interview history is analytical (stage/type, scheduled time, interviewer/contact, notes, feedback, outcome) that outlives any reminder; overloading `tasks.type = interview` would bury lifecycle transitions in JSON and pollute task lists. Option A alone loses the natural reminder surface; Option B conflates domains. Option C keeps both clean with a clear link (task may reference its interview).
- MCD today models `TASK` with `type ∈ {task, reminder, interview, follow_up}` (see `MCD.md` § TASK). That model is retained for Phase A's hardening, but the Phase D recommendation deprecates `interview` as a `TASK.type` value in favor of the dedicated `interviews` table. `TASK` will then cover `task | reminder | follow_up` only.
- MLD today models `tasks` only (see `MLD.md` § `tasks`). Phase D SHALL introduce `interviews` with FKs to `applications` (or to `tasks` where the interview was scheduled from), plus indexes on `candidate_profile_id` and on `(application_id, scheduled_at)`.
- No interview tables or migrations are created in Phase A; this decision is documentation-only and is stable for Application Tracking / Application Documents work that follows.
