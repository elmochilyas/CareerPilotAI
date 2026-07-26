## ADDED Requirements

### Requirement: Text extraction is queued and asynchronous
The system SHALL extract text from CV documents asynchronously using Laravel queues.

#### Scenario: Text extraction dispatched after validation
- **GIVEN** a CV document passes initial validation
- **WHEN** `ProcessCvDocumentJob` completes validation
- **THEN** `ExtractTextJob` SHALL be dispatched to the `cv-ingestion` queue
- **AND** the document status SHALL transition to `extracting`

#### Scenario: Successful PDF text extraction
- **GIVEN** a text-based PDF CV document
- **WHEN** `ExtractTextJob` runs
- **THEN** the system SHALL extract plain text content using the PDF parser
- **AND** record page count as metadata
- **AND** transition document status to `analyzing`
- **AND** dispatch `AnalyzeCvJob`

#### Scenario: Successful DOCX text extraction
- **GIVEN** a DOCX CV document
- **WHEN** `ExtractTextJob` runs
- **THEN** the system SHALL extract plain text content using the DOCX parser
- **AND** transition document status to `analyzing`
- **AND** dispatch `AnalyzeCvJob`

#### Scenario: Image-only PDF detected
- **GIVEN** a PDF with no extractable text (scanned document)
- **WHEN** `ExtractTextJob` runs
- **THEN** the system SHALL set document status to `failed`
- **AND** record `file_no_text` failure code
- **AND** NOT dispatch `AnalyzeCvJob`

#### Scenario: Transient extraction failure retries
- **GIVEN** a CV document where extraction fails due to a transient IO error
- **WHEN** `ExtractTextJob` runs and fails
- **THEN** the job SHALL be retried up to 3 times with backoff intervals
- **AND** the document SHALL remain in `extracting` state during retries

#### Scenario: Permanent extraction failure
- **GIVEN** a CV document where extraction fails due to a corrupt file
- **WHEN** `ExtractTextJob` exhausts retries
- **THEN** the system SHALL set document status to `failed`
- **AND** record the failure reason and code

### Requirement: Extraction uses a provider abstraction
The system SHALL use a `TextExtractor` interface with separate implementations for PDF and DOCX.

#### Scenario: Parser selected by MIME type
- **GIVEN** a CV document with MIME type `application/pdf`
- **WHEN** text extraction begins
- **THEN** the system SHALL select the `PdfTextExtractor` implementation

#### Scenario: DOCX parser validates ZIP structure
- **GIVEN** a DOCX CV document
- **WHEN** `DocxTextExtractor` runs
- **THEN** it SHALL validate the ZIP archive contains expected DOCX parts (`[Content_Types].xml`, `word/document.xml`)
- **AND** extract text from paragraph elements

### Requirement: Extraction is safe after document deletion
The system SHALL handle document deletion during extraction gracefully.

#### Scenario: Extraction job after deletion exits silently
- **GIVEN** a CV document is deleted during processing
- **WHEN** `ExtractTextJob` runs for that document
- **THEN** the job SHALL exit without error
- **AND** NOT transition any state
- **AND** NOT create any orphan records
