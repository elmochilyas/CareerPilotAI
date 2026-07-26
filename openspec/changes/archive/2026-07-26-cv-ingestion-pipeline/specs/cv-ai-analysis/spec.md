## ADDED Requirements

### Requirement: AI analysis is queued and asynchronous
The system SHALL analyze extracted CV text using an AI provider asynchronously.

#### Scenario: AI analysis dispatched after text extraction
- **GIVEN** a CV document has extracted text
- **WHEN** `ExtractTextJob` completes successfully
- **THEN** `AnalyzeCvJob` SHALL be dispatched to the `cv-ingestion` queue
- **AND** the document status SHALL transition to `analyzing`

#### Scenario: Successful AI analysis
- **GIVEN** extracted CV text
- **WHEN** `AnalyzeCvJob` runs
- **THEN** the system SHALL call the `CvAnalyzer` interface with the extracted text
- **AND** the AI provider SHALL receive a prompt with delimited CV text
- **AND** the system SHALL validate the AI output against `CvAnalysisSchema`
- **AND** create `cv_suggestions` rows for each extracted item
- **AND** transition document status to `ready_for_review`

### Requirement: AI output is strictly validated
The system SHALL validate all AI output against a server-side schema before persistence.

#### Scenario: Schema validation passes
- **GIVEN** the AI returns a structure matching `CvAnalysisSchema`
- **WHEN** validation runs
- **THEN** all valid suggestions SHALL be persisted as `cv_suggestions` rows

#### Scenario: Schema validation fails
- **GIVEN** the AI returns values with invalid types or unknown enums
- **WHEN** validation runs
- **THEN** the system SHALL reject the output
- **AND** mark the processing run as `failed`
- **AND** NOT persist any suggestions
- **AND** the document SHALL remain in `failed` state for retry

#### Scenario: AI output contains hallucinated fields
- **GIVEN** the AI returns fields not in the analysis schema
- **WHEN** validation runs
- **THEN** the unexpected fields SHALL be silently dropped
- **AND** valid fields SHALL still be persisted

### Requirement: Prompt injection protection
The system SHALL protect against prompt injection in CV text.

#### Scenario: CV text with embedded instructions
- **GIVEN** the CV text contains instructions like "ignore previous instructions"
- **WHEN** the AI prompt is constructed
- **THEN** the CV text SHALL be delimited with clear boundaries
- **AND** the prompt SHALL include an instruction to ignore embedded instructions
- **AND** the AI output SHALL still follow the schema

### Requirement: Provenance recording
Every suggestion SHALL record its source for traceability.

#### Scenario: Suggestion includes provenance
- **GIVEN** the AI extracts a skill with source page 2 and supporting text "Proficient in Laravel"
- **WHEN** the suggestion is persisted
- **THEN** the `cv_suggestion` SHALL store `source_page: 2`, `source_text: "Proficient in Laravel"`, and `extraction_method: "ai_extraction"`

### Requirement: AI provider abstraction
The system SHALL use a `CvAnalyzer` interface rather than coupling directly to OpenAI.

#### Scenario: Analyzer interface contract
- **GIVEN** the system needs CV analysis
- **WHEN** calling the analyzer
- **THEN** the `CvAnalyzer` interface SHALL accept `string $extractedText` and return a structured `CvAnalysisResult`
- **AND** the interface SHALL allow swapping implementations (e.g., mock for tests)

### Requirement: AI analysis fails gracefully
The system SHALL handle AI provider failures with clear error states.

#### Scenario: AI provider timeout
- **GIVEN** the AI provider times out during analysis
- **WHEN** `AnalyzeCvJob` runs and catches the timeout
- **THEN** the job SHALL be retried up to 3 times with 10s/30s/60s backoff
- **AND** the document SHALL remain in `analyzing` state

#### Scenario: All retries exhausted
- **GIVEN** the AI provider fails repeatedly
- **WHEN** retries are exhausted
- **THEN** the document SHALL transition to `failed`
- **AND** the processing run SHALL record the provider error

### Requirement: No sensitive data extracted
The system SHALL NOT extract sensitive personal information.

#### Scenario: AI returns a national ID number
- **GIVEN** the CV contains a CIN or passport number
- **WHEN** the AI returns it as part of the analysis
- **THEN** the schema validator SHALL reject the unexpected field
- **AND** it SHALL NOT be persisted as a suggestion
