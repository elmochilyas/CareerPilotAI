## ADDED Requirements

### Requirement: Backend-driven clarification entry in the brief
The system SHALL render a clarification entry card in the Career Intelligence Brief whose state is driven entirely by the clarification session resource, and SHALL NEVER offer an action that leads to an empty or dead-end flow.

#### Scenario: Entry rendered with open questions
- **GIVEN** a completed analysis with open clarification questions
- **WHEN** the candidate opens the brief page
- **THEN** the page SHALL show a clarification entry card
- **AND** SHALL indicate how many questions remain actionable
- **AND** the card action SHALL navigate to the clarification page

#### Scenario: Entry rendered with reviewable answers only
- **GIVEN** a session with answered questions pending review and no unanswered questions
- **WHEN** the candidate opens the brief page
- **THEN** the card SHALL offer to continue the review
- **AND** SHALL NOT offer to answer questions

#### Scenario: Entry offers generation when questions can still be created
- **GIVEN** a session with no actionable questions but `generable_count` greater than 0
- **WHEN** the candidate opens the brief page
- **THEN** the card SHALL offer to generate questions
- **AND** the action SHALL lead to the clarification page's generate flow

#### Scenario: Entry hidden when nothing remains
- **GIVEN** a session with no actionable questions and `generable_count` equal to 0
- **WHEN** the candidate opens the brief page
- **THEN** the page SHALL NOT show a clarification entry

### Requirement: Dedicated clarification page and flow states
The system SHALL expose the clarification flow on a dedicated route `opportunities/:id/match/clarifications` that reuses the match brief's analysis loading, gates, and state handling, and SHALL render one question at a time, proposal review, and completion states.

#### Scenario: Clarification page renders for a completed analysis
- **GIVEN** the candidate navigates to the clarification route for an analysis they own
- **WHEN** the page loads
- **THEN** it SHALL show the match header with a back link to the match brief
- **AND** SHALL render the clarification flow (questions, review, or empty/generate state)

#### Scenario: Flow completes and brief refreshes
- **GIVEN** the candidate accepted a clarification proposal
- **WHEN** the flow completes
- **THEN** the brief SHALL refresh the affected data
- **AND** SHALL show the analysis as stale with a recalculate action

#### Scenario: Flow failure shows retry
- **GIVEN** a network or API failure during the clarification flow
- **WHEN** the flow cannot continue
- **THEN** the page SHALL show an error state with a retry action
- **AND** SHALL NOT enter an infinite retry loop

#### Scenario: Stale analysis entry remains visible
- **GIVEN** an analysis flagged stale
- **WHEN** the candidate opens the brief page
- **THEN** the stale notice and recalculate action SHALL remain visible alongside any clarification entry

#### Scenario: Empty session offers generation
- **GIVEN** a session with no questions and `generable_count` greater than 0
- **WHEN** the candidate opens the clarification page
- **THEN** the page SHALL offer a generate action that creates the questions and refreshes the session

#### Scenario: Insufficient profile gate on the clarification page
- **GIVEN** a candidate with an insufficient or missing profile
- **WHEN** they open the clarification route
- **THEN** the page SHALL show the same insufficient-profile gate as the match brief
