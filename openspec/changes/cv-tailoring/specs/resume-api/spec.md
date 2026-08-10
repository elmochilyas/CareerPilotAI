## ADDED Requirements

### Requirement: Resume CRUD API endpoints
The system SHALL expose REST endpoints under `/api/v1` for tailored CV management:
- `POST /api/v1/opportunities/{opportunity}/resumes` — create a new tailored CV (returns 201)
- `GET /api/v1/opportunities/{opportunity}/resumes` — list all resume versions for an opportunity
- `GET /api/v1/resumes/{resume}` — show a single resume with full content
- `PATCH /api/v1/resumes/{resume}` — update draft resume content (returns 200)
- `POST /api/v1/resumes/{resume}/approve` — approve and immutabilize (returns 200)
- `GET /api/v1/resumes/{resume}/preview` — preview finalized CV (returns 200)
- `DELETE /api/v1/resumes/{resume}` — delete a draft resume (returns 204)

All endpoints require `auth:sanctum` + CSRF. All lookups are ownership-scoped.

#### Scenario: Create resume from opportunity
- **WHEN** POST `/api/v1/opportunities/{id}/resumes` with valid ownership
- **THEN** system returns 201 with the created resume resource

#### Scenario: List resumes for opportunity
- **WHEN** GET `/api/v1/opportunities/{id}/resumes`
- **THEN** system returns 200 with a collection of resume versions ordered by `version_no` desc

#### Scenario: Show resume detail
- **WHEN** GET `/api/v1/resumes/{id}` with valid ownership
- **THEN** system returns 200 with the resume resource including full content and source refs

#### Scenario: Update draft resume
- **WHEN** PATCH `/api/v1/resumes/{id}` with valid ownership and status `draft`
- **THEN** system returns 200 with the updated resume resource

#### Scenario: Update approved resume rejected
- **WHEN** PATCH `/api/v1/resumes/{id}` with status `approved`
- **THEN** system returns 409 with problem code `resume_immutable`

#### Scenario: Approve resume
- **WHEN** POST `/api/v1/resumes/{id}/approve` with valid ownership and status `draft`
- **THEN** system sets `status = approved`, `approved_at = now()`, returns 200

#### Scenario: Preview resume
- **WHEN** GET `/api/v1/resumes/{id}/preview` with valid ownership
- **THEN** system returns 200 with the finalized CV layout

#### Scenario: Delete draft resume
- **WHEN** DELETE `/api/v1/resumes/{id}` with status `draft`
- **THEN** system returns 204 and soft-deletes or hard-deletes per policy

#### Scenario: Delete approved resume rejected
- **WHEN** DELETE `/api/v1/resumes/{id}` with status `approved`
- **THEN** system returns 409 with problem code `resume_immutable`

### Requirement: Resume API resource structure
The `ResumeResource` SHALL include: `id`, `candidate_profile_id`, `opportunity_id`, `title`, `template_key`, `content` (structured JSON with sections, skills, experience, education, summary), `status`, `generated_by`, `stale`, `stale_reason`, `version_no`, `approved_at`, `created_at`, `updated_at`. The `content` structure SHALL follow a defined schema with `sections` array, each section having `type`, `title`, `items` array, and each item having `source_ref`, `original_text`, `current_text`, `ai_proposals` (if any).

#### Scenario: Resource includes source references
- **WHEN** the client receives a resume resource
- **THEN** each content item has a `source_ref` field (e.g., `profile_item:123`)

#### Scenario: Resource includes AI proposals
- **WHEN** a content item has pending AI wording proposals
- **THEN** the item includes `ai_proposals` array with `proposal_id`, `original_text`, `proposed_text`, `status`

### Requirement: Resume policy enforcement
The `ResumePolicy` SHALL enforce: `viewAny`/`view` require ownership via `candidate_profile_id`; `create` requires ownership of the linked opportunity; `update` requires ownership and status `draft`; `delete` requires ownership and status `draft`; `approve` requires ownership and status `draft`.

#### Scenario: Policy allows owner to create
- **WHEN** the authenticated user owns the linked opportunity
- **THEN** `create` returns true

#### Scenario: Policy blocks non-owner
- **WHEN** the authenticated user does not own the resume
- **THEN** `view` returns false (controller returns 404)

### Requirement: Rate limiting
Resume creation endpoints SHALL respect the general-write rate limit (60/minute/user). Approve and preview endpoints SHALL respect the general-read rate limit (120/minute/user).

#### Scenario: Rate limit on create
- **WHEN** the user exceeds 60 write requests in one minute
- **THEN** the system returns 429

### Requirement: OpenAPI definition
The system SHALL document all resume endpoints in OpenAPI 3.1 with request/response schemas, problem codes, and authentication requirements.

#### Scenario: OpenAPI includes resume endpoints
- **WHEN** the OpenAPI spec is generated
- **THEN** all resume endpoints are documented with schemas
