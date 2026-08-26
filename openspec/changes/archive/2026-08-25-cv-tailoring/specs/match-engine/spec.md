## MODIFIED Requirements

### Requirement: Match findings expose tailoring relevance
Match findings SHALL include a `tailoring_relevance` field with values `high`, `medium`, `low`, or `none`, computed from the match state and importance. Findings with `match_state = matched` and `importance = required` SHALL have `tailoring_relevance = high`. Findings with `match_state = partial` SHALL have `tailoring_relevance = medium`. Gaps SHALL have `tailoring_relevance = none`. This field is read-only and computed deterministically from existing finding attributes.

#### Scenario: Matched required finding has high tailoring relevance
- **WHEN** a match finding has `match_state = matched` and `importance = required`
- **THEN** the finding includes `tailoring_relevance = high`

#### Scenario: Partial finding has medium tailoring relevance
- **WHEN** a match finding has `match_state = partial`
- **THEN** the finding includes `tailoring_relevance = medium`

#### Scenario: Gap finding has no tailoring relevance
- **WHEN** a match finding has `match_state = gap`
- **THEN** the finding includes `tailoring_relevance = none`
