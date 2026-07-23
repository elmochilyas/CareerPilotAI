## Purpose

Provide a deterministic, testable percentage calculation of profile completeness based on populated areas with documented weights. The score is computed server-side and drives the frontend completion guidance.

## Requirements

### Requirement: PROF-COMP-001: Deterministic completion calculation
The system SHALL calculate profile_completion as a deterministic weighted percentage using documented rules.

#### Scenario: Empty profile returns 0
- **WHEN** a user has just authenticated and has no profile data
- **THEN** profile_completion SHALL be 0.00

#### Scenario: Fully completed profile returns 100
- **WHEN** a user has populated phone, city, country, headline, summary, at least one professional link, at least one target role, preferred locations, work modes, contract types, at least one language, at least one education, and at least one experience or project, but has no certification and no salary expectations
- **THEN** profile_completion SHALL be 100.00

#### Scenario: Partial completion reflects populated areas
- **WHEN** a user has only headline and summary filled
- **THEN** profile_completion SHALL reflect only the weighted contribution of those areas

### Requirement: PROF-COMP-002: Completion calculation weights
The system SHALL use these mandatory weights: basic personal information 10%, professional headline 10%, professional summary 15%, professional links 10%, target roles 15%, career preferences 10%, languages 10%, education 10%, and practical background 10%.

Basic personal information SHALL award 4 points for phone, 3 for city, and 3 for country. Career preferences SHALL award 4 points for at least one preferred location, 3 for work modes, and 3 for at least one contract type. Other areas SHALL award their full weight only when their documented condition is met. The mandatory weights SHALL total exactly 100%.

#### Scenario: Headline weight applied
- **WHEN** a user adds their first headline
- **THEN** profile_completion SHALL increase by the headline weight contribution

#### Scenario: Skills area excluded from calculation
- **WHEN** evaluating profile areas
- **THEN** skills SHALL NOT be considered because skills are outside this change scope

#### Scenario: Partial grouped areas are scored deterministically
- **WHEN** a profile contains phone and city but no country, and contains a work mode but no preferred location or contract type
- **THEN** basic personal information SHALL contribute 7 points and career preferences SHALL contribute 3 points

#### Scenario: Certifications and salary are optional
- **WHEN** a profile has no certifications and no salary expectations
- **THEN** neither absence SHALL reduce profile_completion or appear as a missing completion area

#### Scenario: Certifications do not exceed the maximum
- **WHEN** a profile already has profile_completion of 100 and a certification is added
- **THEN** profile_completion SHALL remain 100.00

### Requirement: PROF-COMP-003: Completion recalculation on profile changes
The system SHALL recalculate profile_completion after every relevant profile or profile-item change.

#### Scenario: Completion recalculated after profile update
- **WHEN** a user updates their professional_summary from null to a non-null value
- **THEN** the system SHALL recalculate and persist the updated profile_completion

#### Scenario: Completion recalculated after item creation
- **WHEN** a user creates a new education item
- **THEN** the system SHALL recalculate profile_completion

#### Scenario: Completion recalculated after item deletion
- **WHEN** a user deletes an experience item
- **THEN** the system SHALL recalculate profile_completion

#### Scenario: Item update recalculates completion
- **WHEN** a user updates a profile item in a way that may affect a qualifying area
- **THEN** the system SHALL recalculate and persist profile_completion

### Requirement: PROF-COMP-004: Completion guidance contract
The system SHALL return a server-calculated completion breakdown with the profile, including stable area keys, earned and available points, completion state, and an ordered `missing_areas` list with actionable guidance.

#### Scenario: Missing areas are returned to the frontend
- **WHEN** an authenticated candidate retrieves an incomplete profile
- **THEN** `completion_details.missing_areas` SHALL identify only incomplete mandatory areas and SHALL NOT include certifications or salary expectations

#### Scenario: Frontend does not calculate completion
- **WHEN** the profile UI displays completion guidance
- **THEN** it SHALL use the server-provided score and completion details without reproducing Laravel scoring rules

### Requirement: PROF-COMP-005: Completion is bounded
The system SHALL ensure profile_completion is always between 0 and 100.

#### Scenario: Completion never exceeds 100
- **WHEN** calculation produces a value above 100
- **THEN** the system SHALL cap it at 100.00

#### Scenario: Completion never falls below 0
- **WHEN** calculation produces a negative value
- **THEN** the system SHALL floor it at 0.00
