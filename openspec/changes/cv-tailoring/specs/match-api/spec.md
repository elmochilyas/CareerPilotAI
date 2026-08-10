## MODIFIED Requirements

### Requirement: Match analysis resource includes tailoring fields
The `MatchAnalysisResource` SHALL include a `tailorable` boolean field indicating whether the analysis is completed and eligible for CV tailoring. The `MatchFindingResource` SHALL include the `tailoring_relevance` field from the match engine. These fields are additive and do not change existing match endpoint behavior.

#### Scenario: Completed analysis is tailorable
- **WHEN** the match analysis status is `completed`
- **THEN** the resource includes `tailorable = true`

#### Scenario: Incomplete analysis is not tailorable
- **WHEN** the match analysis status is `queued` or `processing`
- **THEN** the resource includes `tailorable = false`
