## Purpose

Export approved tailored resumes as ATS-safe PDF documents through a shared source-CV document renderer used by both the browser preview and file export.

## Requirements

### Requirement: Direct approved-resume PDF download

The system SHALL allow the authenticated owner to directly download an ATS-safe PDF generated exclusively from the persisted approved tailored-resume preview. Export SHALL NOT add, rewrite, or infer candidate facts.

#### Scenario: Owner downloads approved resume
- **WHEN** the owner requests PDF export for an approved resume
- **THEN** the API returns HTTP 200 with `application/pdf`, an attachment filename, and a valid PDF document containing the saved resume content

#### Scenario: Draft export is blocked
- **WHEN** the owner requests PDF export for a draft resume
- **THEN** the API returns HTTP 409 with a stable problem code and no partial file

#### Scenario: Cross-user export is hidden
- **WHEN** another user requests the resume PDF
- **THEN** the API returns HTTP 404 without revealing the resource

#### Scenario: Download is visible in Export
- **WHEN** an approved resume is shown in the frontend Export step
- **THEN** a clear Download PDF action starts the authenticated file download directly without opening the print dialog

### Requirement: Shared source-CV document renderer

The system SHALL render tailored content through one escaped document template derived from the candidate's imported CV presentation. The authenticated browser preview and PDF export SHALL use that same renderer and section order. Skills SHALL be compact, and long content SHALL paginate without overlapping or clipping.

#### Scenario: Preview matches the exported document
- **WHEN** an owner reviews a generated tailored resume and downloads its PDF
- **THEN** both representations use the source-derived header, hierarchy, spacing, section order, and content

#### Scenario: Existing resume has no template association
- **WHEN** an existing resume with no template key is previewed or exported for a candidate with an imported CV
- **THEN** the renderer safely selects the source-derived template without mutating the approved resume
