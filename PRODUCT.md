# e-ISMS SUK Pahang
<!-- impeccable:product-schema 1 -->

## Platform
web

## Stack
User requirement: Laravel, latest stable supported specifications, initially local on Laragon. `application/` now runs Laravel 13.34.0, PHP 8.3.30, MySQL 8.4.3 and Blade/Vite. Composer/npm dependencies are locked. Dedicated development/test databases are configured and tested.

## Users
User confirmed Penyelaras ISMS as the first review persona. Other roles in `.briefing` remain proposed.

## Product Purpose
Digitise manual ISMS records, ownership, reviews, approvals and evidence for SUK Pahang. Source: `.briefing`.

## Operating Context
On 3 October 2026 the user approved continuing with Laravel foundations and a complete document workflow. `application/` persists accounts, departments, documents, versions, reviews, approvals and audit. Pilot role/approval rules are proposed defaults for synthetic local data, not approved SUK authority. See `docs/phase-1-design.md` and `docs/laragon-setup.md`. Risk/actions/SoA remain mockups.

Initial work is an interactive mockup for user review before real-case implementation. Fictional records only. No authentication, database, file uploads or real approvals in this prototype. Changes last only in browser memory and reset on reload.

For v0.2 the user selected **Formal, ringkas dan berorientasikan kerja** and requested avoidance of generic AI-generated visual decoration. Portal and workspace prioritise registers, clear ownership and direct actions. The implementation remains proposed for review.

The user also requested next phases. `docs/roadmap.md` proposes Laravel/access foundations, controlled documents, risk/treatment/evidence, then pilot/reporting/reminders and later modules. This is a development proposal, not an approved organisational schedule.

## Capabilities and Constraints
Mockup: overview, risk list/detail/create, controlled document list/detail, action tracking, sample SoA, reports and audit history. Bahasa Melayu; reference date 2 October 2026; Asia/Kuala_Lumpur for future timestamps. Production scope, permissions, risk methodology, retention, official brand and actual forms remain open.

User additionally requested a landing page and all continued work in the existing Laragon project folder. `mockup/landing.html` is an institutional portal explaining the system and linking to the demonstration workspace. It does not claim live login or operational deployment.

## Evidence on Hand
User-supplied purpose and `.briefing`; no actual departmental records or approved logo. Do not imply synthetic records, names or counts are real organisational findings.

## Product Principles
- Show ownership, next action and due date together.
- Keep evidence and decisions traceable.
- Separate technical administration from business approval.
- A complete record does not establish compliance.
