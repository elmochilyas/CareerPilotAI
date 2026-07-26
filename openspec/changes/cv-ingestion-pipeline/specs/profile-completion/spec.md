## ADDED Requirements

### Requirement: PROF-COMP-006: CV-imported skills excluded from completion
Skills imported from CV ingestion SHALL remain excluded from the profile completion calculation.

#### Scenario: Completion unchanged after skill import
- **GIVEN** a candidate with profile_completion of 45%
- **WHEN** a CV import adds 5 skills as `claimed`
- **THEN** profile_completion SHALL remain 45%
- **AND** the completion guidance SHALL NOT list skills as a missing area

#### Scenario: Completion increases after non-skill import
- **GIVEN** a candidate with no headline
- **WHEN** a CV import adds a headline (accepted) and 3 skills
- **THEN** profile_completion SHALL increase by the headline weight only
- **AND** the skills SHALL have no effect on the completion percentage

### Requirement: PROF-COMP-007: Completion recalculated after CV import
The system SHALL recalculate profile_completion after a successful CV import that includes non-skill profile changes.

#### Scenario: Import triggers completion recalculation
- **GIVEN** a candidate has profile_completion of 30%
- **WHEN** a CV import creates an education item and a project item
- **THEN** the system SHALL dispatch a `RecalculateProfileCompletionJob`
- **AND** profile_completion SHALL increase to reflect the new items
