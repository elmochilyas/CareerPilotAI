param(
  [switch]$Wait
)

$ErrorActionPreference = "Stop"
$CollectionRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$ResultsDir = Join-Path $CollectionRoot "results"
$null = New-Item -ItemType Directory -Path $ResultsDir -Force

# -- Pre-run: Clean up stale CV documents for test user (user_id=2)
Write-Host "Cleaning stale CV documents for test user..."
php "$CollectionRoot\..\backend\artisan" tinker --execute "DB::delete('delete cs from cv_suggestions cs inner join cv_documents cd on cs.cv_document_id = cd.id where cd.user_id = 2'); DB::delete('delete ib from cv_import_batches ib inner join cv_documents cd on ib.cv_document_id = cd.id where cd.user_id = 2'); DB::delete('delete pr from cv_processing_runs pr inner join cv_documents cd on pr.cv_document_id = cd.id where cd.user_id = 2'); DB::table('cv_documents')->where('user_id',2)->delete();" 2>$null

# -- Wait if needed (rate limit cool-down)
if ($Wait) {
  Write-Host "Waiting 95s for rate-limit reset..."
  Start-Sleep -Seconds 95
}

# -- Build the ordered file list for bru run
$files = @(
  # Section 1: Auth bootstrap (CV-relevant only)
  "01 - Authentication/01 - Health.yml"
  "01 - Authentication/02 - CSRF Cookie.yml"
  "01 - Authentication/03 - Login.yml"
  "01 - Authentication/05 - Current User.yml"

  # Section 4: CV Ingestion - Documents
  "04 - CV Ingestion/01 - Documents/01 - List CV Documents.yml"
  "04 - CV Ingestion/01 - Documents/02 - Upload Valid PDF.yml"
  "04 - CV Ingestion/01 - Documents/03 - View CV Document.yml"
  "04 - CV Ingestion/01 - Documents/04 - List And Verify Uploaded.yml"
  "04 - CV Ingestion/01 - Documents/05 - Download CV Document.yml"
  "04 - CV Ingestion/01 - Documents/06 - Delete Temporary Document.yml"
  "04 - CV Ingestion/01 - Documents/07 - Verify Deleted Document.yml"

  # CV Ingestion - Upload Validation
  "04 - CV Ingestion/02 - Upload Validation/01 - Reject Unsupported Extension.yml"
  "04 - CV Ingestion/02 - Upload Validation/02 - Reject Corrupt PDF.yml"
  "04 - CV Ingestion/02 - Upload Validation/03 - Reject Fake DOCX ZIP.yml"
  "04 - CV Ingestion/02 - Upload Validation/04 - Reject Empty File.yml"
  "04 - CV Ingestion/02 - Upload Validation/05 - Reject Missing File Field.yml"

  # CV Ingestion - Processing
  "04 - CV Ingestion/03 - Processing/01 - Upload CV For Processing.yml"
  "04 - CV Ingestion/03 - Processing/02 - Poll Status 1.yml"
  "04 - CV Ingestion/03 - Processing/03 - Poll Status 2.yml"
  "04 - CV Ingestion/03 - Processing/04 - Poll Status 3.yml"
  "04 - CV Ingestion/03 - Processing/05 - Poll Status 4.yml"
  "04 - CV Ingestion/03 - Processing/06 - Poll Status 5.yml"
  "04 - CV Ingestion/03 - Processing/07 - Poll Status 6.yml"
  "04 - CV Ingestion/03 - Processing/08 - Poll Status 7.yml"
  "04 - CV Ingestion/03 - Processing/09 - Poll Status 8.yml"
  "04 - CV Ingestion/03 - Processing/10 - Poll Status 9.yml"
  "04 - CV Ingestion/03 - Processing/11 - Poll Status 10.yml"

  # CV Ingestion - Suggestions
  "04 - CV Ingestion/04 - Suggestions/01 - List Suggestions.yml"
  "04 - CV Ingestion/04 - Suggestions/02 - Verify Suggestion Structure.yml"

  # CV Ingestion - Review Decisions
  "04 - CV Ingestion/05 - Review Decisions/01 - Accept Headline Suggestion.yml"
  "04 - CV Ingestion/05 - Review Decisions/02 - Edit And Accept Summary.yml"
  "04 - CV Ingestion/05 - Review Decisions/03 - Reject Duplicate.yml"
  "04 - CV Ingestion/05 - Review Decisions/04 - Keep Existing Value.yml"
  "04 - CV Ingestion/05 - Review Decisions/05 - Reject Invalid Decision.yml"
  "04 - CV Ingestion/05 - Review Decisions/06 - Batch Save Skill Decisions.yml"
  "04 - CV Ingestion/05 - Review Decisions/07 - Verify Decisions Persist.yml"

  # CV Ingestion - Import Preview
  "04 - CV Ingestion/06 - Import Preview/01 - Generate Import Preview.yml"
  "04 - CV Ingestion/06 - Import Preview/02 - Verify Preview Summary.yml"

  # CV Ingestion - Apply Import
  "04 - CV Ingestion/07 - Apply Import/01 - Capture Profile Before.yml"
  "04 - CV Ingestion/07 - Apply Import/02 - Apply Reviewed Import.yml"
  "04 - CV Ingestion/07 - Apply Import/03 - Verify Import Result.yml"
  "04 - CV Ingestion/07 - Apply Import/04 - Repeat Apply With Same Key.yml"
  "04 - CV Ingestion/07 - Apply Import/05 - Capture Profile After And Verify.yml"

  # CV Ingestion - Retry and Recovery
  "04 - CV Ingestion/08 - Retry and Recovery/01 - Reject Retry On Non Failed.yml"
  "04 - CV Ingestion/08 - Retry and Recovery/02 - Import Result Returns Batch.yml"

  # CV Ingestion - Ownership and Not Found
  "04 - CV Ingestion/09 - Ownership and Not Found/01 - Non-existent Document.yml"
  "04 - CV Ingestion/09 - Ownership and Not Found/02 - Non-existent Suggestion.yml"
  "04 - CV Ingestion/09 - Ownership and Not Found/03 - Non-existent Import Result.yml"
  "04 - CV Ingestion/09 - Ownership and Not Found/04 - Delete Non-existent Document.yml"
  "04 - CV Ingestion/09 - Ownership and Not Found/05 - Download Non-existent Document.yml"

  # CV Ingestion - Concurrency and Idempotency
  "04 - CV Ingestion/10 - Concurrency and Idempotency/01 - Apply After Import.yml"
  "04 - CV Ingestion/10 - Concurrency and Idempotency/02 - Suggest After Import.yml"
  "04 - CV Ingestion/10 - Concurrency and Idempotency/03 - Preview After Import.yml"

  # CV Ingestion - Profile Completion
  "04 - CV Ingestion/11 - Profile Completion (Post-Import)/01 - Verify Profile Completeness.yml"

  # CV Ingestion - Cleanup Tear Down
  "04 - CV Ingestion/12 - Cleanup Tear Down/01 - Delete Processed Document.yml"
  "04 - CV Ingestion/12 - Cleanup Tear Down/02 - Delete Upload Validation Doc.yml"
  "04 - CV Ingestion/12 - Cleanup Tear Down/03 - Delete Extra Document.yml"
  "04 - CV Ingestion/12 - Cleanup Tear Down/04 - Verify Documents Deleted.yml"
  "04 - CV Ingestion/12 - Cleanup Tear Down/05 - Get Fresh Token For Any Followup.yml"
  "04 - CV Ingestion/12 - Cleanup Tear Down/06 - Delete Imported Profile Items.yml"
)

# -- Run bru
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$outputFile = Join-Path $ResultsDir "cv-ingestion-$timestamp.json"

Write-Host "Running CareerPilot CV Ingestion tests..."
Write-Host "Output: $outputFile"
Write-Host ""

$result = & bru run @files --env-file "environments/local.yml" --output $outputFile --format json 2>&1

# -- Display results
$result | ForEach-Object { Write-Host $_ }

# -- Parse and summarize
if (Test-Path $outputFile) {
  $data = Get-Content $outputFile -Raw | ConvertFrom-Json
  Write-Host ""
  Write-Host "=== SUMMARY ==="
  Write-Host "Passed:   $($data.runStats.passed)"
  Write-Host "Failed:   $($data.runStats.failed)"
  Write-Host "Skipped:  $($data.runStats.skipped)"
  Write-Host "Duration: $($data.runStats.duration)ms"
  Write-Host "Results saved to: $outputFile"
}
