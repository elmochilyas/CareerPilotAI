# CareerPilot AI — Database Design Reference

This directory is the **agent-readable source of truth** for the CareerPilot AI database design.

## Files

- `MCD.md`: entities, conceptual attributes, associations, cardinalities, and business rules.
- `MLD.md`: tables, columns, SQL types, PK/FK, constraints, indexes, and delete rules.
- `IMPLEMENTATION_PLAN.md`: migration order and OpenSpec-to-table ownership.

## Authority order

1. The currently approved OpenSpec change
2. `docs/database/MLD.md`
3. `docs/database/MCD.md`
4. Existing Laravel migrations
5. Visual MCD/MLD images

A mismatch must be reported before implementation. Do not silently choose one interpretation.

## Mandatory agent workflow

Before creating or modifying a migration, model, factory, seeder, policy, request, resource, or database test:

1. Read this file.
2. Read the relevant section in `MCD.md`.
3. Read the relevant tables in `MLD.md`.
4. Read `IMPLEMENTATION_PLAN.md`.
5. Inspect existing migrations and the current database schema.
6. Implement only the schema belonging to the active OpenSpec change.
7. Do not add speculative tables, columns, pivots, versioning, or relationships.
8. Update these references in the same Pull Request when an approved schema decision changes.

## Core decisions

- MySQL with Laravel migrations and Eloquent.
- Trusted candidate data remains relational.
- JSON is limited to bounded snapshots, temporary AI extraction data, document content, sources, and provider metadata.
- AI never updates trusted profile data without validation and required candidate confirmation.
- Job-offer descriptions are not versioned in the MVP.
- Match runs, resume versions, and application status history are retained.
- A user can have at most one candidate profile.
- A candidate can have at most one application for one saved opportunity.
