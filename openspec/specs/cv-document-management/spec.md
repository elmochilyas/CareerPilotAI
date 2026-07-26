## Purpose

Manage candidate CV document lifecycle: upload, validate, list, view, download, retry, and delete CV documents with ownership enforcement and state machine validation.

## Requirements

### Requirement: Candidate uploads a CV file
The system SHALL accept CV uploads in PDF and DOCX formats. The system SHALL validate the upload server-side before accepting it.

#### Scenario: Successful PDF upload
- **GIVEN** the candidate is authenticated
- **WHEN** they upload a valid PDF file under 20MB with MIME type `application/pdf` and `%PDF` header
- **THEN** the system SHALL return HTTP 201 with a CvDocumentResource
- **AND** the document SHALL have status `pending`
- **AND** the file SHALL be stored in private storage with a generated opaque name
- **AND** a `ProcessCvDocumentJob` SHALL be dispatched to the `cv-ingestion` queue with a 3-second delay

#### Scenario: Successful DOCX upload
- **GIVEN** the candidate is authenticated
- **WHEN** they upload a valid DOCX file under 20MB with MIME type `application/vnd.openxmlformats-officedocument.wordprocessingml.document`
- **THEN** the system SHALL return HTTP 201 with a CvDocumentResource

#### Scenario: Reject unsupported file extension
- **GIVEN** the candidate is authenticated
- **WHEN** they upload a `.txt` file
- **THEN** the system SHALL return HTTP 422 with problem code `file_type_not_allowed`

#### Scenario: Reject MIME type mismatch
- **GIVEN** the candidate is authenticated
- **WHEN** they upload a file with `.pdf` extension but MIME type `image/png`
- **THEN** the system SHALL return HTTP 422 with problem code `file_type_mismatch`
- **AND** the file SHALL NOT be stored

#### Scenario: Reject binary signature mismatch
- **GIVEN** the candidate is authenticated
- **WHEN** they upload a file with `.pdf` extension and PDF MIME type but without `%PDF` header
- **THEN** the system SHALL return HTTP 422 with problem code `file_type_mismatch`

#### Scenario: Reject oversized file
- **GIVEN** the candidate is authenticated
- **WHEN** they upload a file exceeding 20MB
- **THEN** the system SHALL return HTTP 422 with problem code `file_too_large`

#### Scenario: Reject corrupt file
- **GIVEN** the candidate is authenticated
- **WHEN** they upload a truncated or unreadable file
- **THEN** the system SHALL return HTTP 422 with problem code `file_corrupt`

#### Scenario: Reject password-protected PDF
- **GIVEN** the candidate is authenticated
- **WHEN** they upload a PDF with encryption flags set
- **THEN** the system SHALL return HTTP 422 with problem code `file_protected`

#### Scenario: Reject empty document
- **GIVEN** the candidate is authenticated
- **WHEN** they upload a PDF or DOCX with zero content pages
- **THEN** the system SHALL return HTTP 422 with problem code `file_empty`

#### Scenario: Detect duplicate upload
- **GIVEN** the candidate has already uploaded a CV with a specific SHA-256 checksum
- **WHEN** they upload the same file again
- **THEN** the system SHALL return HTTP 409 with problem code `file_duplicate`
- **AND** the response SHALL reference the existing document ID

### Requirement: Candidate views CV document list
The system SHALL return a paginated list of the candidate's CV documents.

#### Scenario: List all documents
- **GIVEN** the candidate has 3 uploaded CV documents
- **WHEN** they call GET `/api/v1/cv`
- **THEN** the system SHALL return HTTP 200 with a paginated list of 3 CvDocumentResources

#### Scenario: Filter by status
- **GIVEN** the candidate has documents in various states
- **WHEN** they call GET `/api/v1/cv?status=failed`
- **THEN** the system SHALL return only documents with `failed` status

#### Scenario: Cross-user access returns 404
- **GIVEN** candidate A has a CV document with ID 5
- **WHEN** candidate B calls GET `/api/v1/cv/5`
- **THEN** the system SHALL return HTTP 404

### Requirement: Candidate views a single CV document
The system SHALL return detailed information about a specific CV document.

#### Scenario: View document details
- **GIVEN** the candidate has an uploaded CV document
- **WHEN** they call GET `/api/v1/cv/{cvDocument}`
- **THEN** the system SHALL return HTTP 200 with the CvDocumentResource including status, original name, size, MIME type, timestamps, and failure information if applicable

#### Scenario: View deleted document returns 404
- **GIVEN** the candidate has deleted a CV document
- **WHEN** they call GET `/api/v1/cv/{cvDocument}`
- **THEN** the system SHALL return HTTP 404

### Requirement: Candidate downloads a CV preview
The system SHALL serve the original CV file with authentication and ownership checks.

#### Scenario: Download own CV
- **GIVEN** the candidate has an uploaded CV document
- **WHEN** they call GET `/api/v1/cv/{cvDocument}/download`
- **THEN** the system SHALL serve the file with `Content-Disposition: inline`
- **AND** the response SHALL include the original filename and original MIME type

#### Scenario: Download deleted CV returns 410
- **GIVEN** the candidate has deleted a CV document
- **WHEN** they call GET `/api/v1/cv/{cvDocument}/download`
- **THEN** the system SHALL return HTTP 410

#### Scenario: Cross-user download returns 404
- **GIVEN** candidate A has a CV document
- **WHEN** candidate B calls GET `/api/v1/cv/{cvDocument}/download`
- **THEN** the system SHALL return HTTP 404

### Requirement: Candidate retries processing
The system SHALL allow retrying a failed CV document.

#### Scenario: Retry failed document
- **GIVEN** the candidate has a CV document with `failed` status
- **WHEN** they call POST `/api/v1/cv/{cvDocument}/retry`
- **THEN** the system SHALL reset status to `queued`
- **AND** dispatch a new `ProcessCvDocumentJob`

#### Scenario: Retry non-failed document returns 409
- **GIVEN** the candidate has a CV document with `ready_for_review` status
- **WHEN** they call POST `/api/v1/cv/{cvDocument}/retry`
- **THEN** the system SHALL return HTTP 409

### Requirement: Candidate deletes a CV document
The system SHALL delete the CV document, its private file, and derived suggestions while preserving imported profile data.

#### Scenario: Delete document before import
- **GIVEN** the candidate has a CV document with status `ready_for_review`
- **WHEN** they call DELETE `/api/v1/cv/{cvDocument}`
- **THEN** the system SHALL delete the private file from storage
- **AND** delete all `cv_suggestions` rows
- **AND** update document status to `deleted`
- **AND** return HTTP 200

#### Scenario: Delete during processing returns 409
- **GIVEN** the candidate has a CV document with status `processing`
- **WHEN** they call DELETE `/api/v1/cv/{cvDocument}`
- **THEN** the system SHALL return HTTP 409

#### Scenario: Delete after import preserves profile
- **GIVEN** the candidate has imported suggestions from a CV
- **WHEN** they delete the CV document
- **THEN** the imported profile data SHALL remain intact
- **AND** the processing runs SHALL remain for audit
- **AND** the document status SHALL be `deleted`

### Requirement: Document state machine enforcement
The system SHALL enforce valid state transitions for CV documents.

#### Scenario: Invalid transition returns 409
- **GIVEN** the candidate has a CV document with `failed` status
- **WHEN** the system attempts to transition directly to `imported`
- **THEN** the transition SHALL be rejected
- **AND** the document status SHALL remain `failed`

### Requirement: Rate limiting on upload
The system SHALL limit CV uploads to prevent abuse.

#### Scenario: Exceed upload rate limit
- **GIVEN** the candidate has uploaded 10 CVs in the last hour
- **WHEN** they attempt to upload another CV
- **THEN** the system SHALL return HTTP 429
