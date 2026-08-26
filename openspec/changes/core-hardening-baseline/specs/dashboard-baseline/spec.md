## Purpose

Provides the authenticated candidate a reliable baseline dashboard (the home route) that surfaces the state of the implemented core — profile completion, CV ingestion attention, opportunities, matching state, and resumes where relevant — with mandatory loading, empty, error, and retry states, so a single failing request never blanks the page. Future module widgets are explicitly out of scope until their modules exist.

## ADDED Requirements

### Requirement: DASH-001 - Single dashboard surface
The authenticated home route SHALL render exactly one dashboard page. Any duplicate or dead dashboard page file SHALL be removed so there is one canonical implementation and route.

#### Scenario: One dashboard implementation
- **WHEN** the router resolves the authenticated home route
- **THEN** it renders the single canonical dashboard component and no unreferenced duplicate dashboard page exists in the codebase

### Requirement: DASH-002 - Core information surfaced
The dashboard SHALL surface only information produced by implemented core capabilities: profile completion, CV ingestion documents needing attention, saved opportunities/ingestions, match analysis state, and resume versions where relevant. The dashboard SHALL NOT include widgets for unimplemented modules (applications, tasks, interviews, roadmap).

#### Scenario: Core panels present
- **WHEN** an authenticated candidate with data views the dashboard
- **THEN** panels show profile completion, CV attention items, opportunities, and matching/resume state derived from existing core APIs

#### Scenario: No future-module widgets
- **WHEN** the dashboard is rendered
- **THEN** no applications/tasks/interviews/roadmap widgets appear because those modules are not implemented

### Requirement: DASH-003 - Independent loading, empty, error, and retry states
Each dashboard panel SHALL handle its own query lifecycle: a loading state while fetching, an empty state when the underlying collection is empty, an error state when its request fails, and a retry action that refetches only that panel. Failure of one panel's request SHALL NOT blank other panels or the whole page.

#### Scenario: Loading state per panel
- **WHEN** the dashboard loads and a panel's request is in flight
- **THEN** that panel shows a skeleton or equivalent loading indicator

#### Scenario: Empty state per panel
- **WHEN** a panel's underlying data is empty
- **THEN** the panel shows an accessible empty state with guidance or a primary action instead of a blank area

#### Scenario: Failed request shows error with retry
- **WHEN** a panel's API request fails
- **THEN** the affected panel shows an error message with a retry control while remaining panels continue to render normally

#### Scenario: Retry recovers the failed panel
- **WHEN** the candidate activates retry on a failed panel and the request then succeeds
- **THEN** that panel renders its data without reloading the whole page
