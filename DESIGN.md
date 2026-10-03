---
version: alpha
name: e-ISMS SUK Pahang
description: Formal, ringkas dan berorientasikan kerja — mockup v0.2.
colors:
  navy: '#163A50'
  blue: '#245971'
  gold: '#BC9746'
  canvas: '#F4F6F7'
  surface: '#FFFFFF'
  ink: '#1C2E3A'
  muted: '#546570'
  line: '#D4DEE4'
  blue-soft: '#EDF3F6'
  danger: '#A53730'
  danger-soft: '#FAEEEC'
  warning: '#765515'
  warning-soft: '#F8F2E5'
  success: '#28604B'
  success-soft: '#EBF3EE'
  focus: '#2177AD'
typography:
  sans:
    fontFamily: 'Segoe UI, sans-serif'
    fontSize: '14px'
    lineHeight: 1.55
  display:
    fontFamily: 'Segoe UI, sans-serif'
    fontWeight: 600
rounded:
  DEFAULT: '8px'
  control: '4px'
  badge: '3px'
  dialog: '10px'
spacing:
  page-inline: '34px'
  dashboard-gap: '28px'
components:
  button-primary:
    backgroundColor: '{colors.navy}'
    textColor: '{colors.surface}'
    rounded: '{rounded.control}'
    padding: '9px 14px'
    height: '40px'
---

# Design system — mockup v0.2 untuk review

## Overview
User-approved direction: **Formal, ringkas dan berorientasikan kerja**, avoiding generic AI-generated decoration. Both portal and coordinator workspace use Operate mode: registers, ownership, dates and next actions provide the hierarchy.

**Creative North Star: "Meja urus setia"** describes the proposed working environment; it is not an approved official identity. A plain e-ISMS wordmark replaces the decorative monogram. The portal presents an attention register of synthetic records and a module directory. The workspace uses a quiet light sidebar and a ledger summary.

## Colors
Frontmatter mirrors runtime tokens in `mockup/styles.css :root`; CSS remains the source of truth. Navy identifies active navigation, primary actions and the portal register heading; blue marks links and information. Gold is a small contextual marker. Cool neutral surfaces, ink text and dividers keep records legible. Status combines a text label with semantic foreground/background colors.

## Typography
Segoe UI is the local body and heading stack with a sans-serif fallback, appropriate to the office Operate direction. Body is 14px/1.55; workspace headings are 28px and section headings 18px. Metadata is generally 12px; status and matrix context use 11px. Some mobile contextual notes also use 11px. Tables use 13px body and 12px headers.

Portal headline is 53px/1.15, 44px at <=1100px, 45px at <=800px and 35px at <=580px. Intro copy is 16px/1.8, reduced to 14px on small screens. Tabular numerals support dates and counts.

## Layout
Workspace sidebar is 228px, reduced to 210px at <=1200px. Main content has a 1540px maximum and 34px side padding. A border-separated ledger strip groups four summaries. Dashboard columns use 1.7fr and a minimum 290px supporting column with a 28px gap; they stack at <=1050px. At <=760px navigation wraps above content. The topbar Portal link remains available at every width.

Portal uses a 1280px outer container with 36px side padding. Intro columns are 1.8fr/1fr, followed by an actual semantic attention table, module directory and workflow. The directory has two columns. At <=800px intro and section layouts stack; at <=580px padding becomes 20px and directory/workflow become one column. Tables retain horizontal scrolling in labelled regions. Reports form one divided list with export actions, rather than separate cards.

## Elevation & Depth
Records are grouped by borders, spacing and restrained tonal backgrounds. Panels and portal register have no shadows. Dialog uses `0 18px 60px #142D4033`; toast uses `0 5px 25px #142D4026`. Matrix counts are flat.

## Shapes
Panels use the default radius; controls, badges and dialogs use their respective frontmatter tokens. Wordmarks carry the application identity. Authored SVG line icons supplement text labels, usually at 16–20px. No decorative monogram or stacked card treatment defines this version.

## Components
Primary buttons are navy with white text and a darker hover response. Quiet buttons use neutral text and a pale informational hover surface. Fields have visible labels, four-pixel corners, inline validation and native select popups. Focus uses a three-pixel outline. Status badges always contain text.

Attention-table links open exact synthetic records via the record query parameter; portal examples do not update with in-session workspace mutations. The register includes a mobile horizontal-scroll hint. Module rows provide simple directory links. Workspace actions use delegated click, submit and change handlers. Native dialogs restore focus on closing. Button feedback lasts 150ms; reduced motion disables transitions. Shared scrollbar, selection and focus styles live in the runtime stylesheet.

## Do's and Don'ts
- Do show ownership, status and target dates together.
- Do retain text labels for statuses and meaningful action destinations.
- Do distinguish synthetic records from official operational evidence.
- Don't add decorative identity marks, card grids or promotional claims to routine work surfaces.
- Don't imply live authentication, official brand approval or compliance certification.

Visual direction is user-selected; the v0.2 implementation remains a review prototype. Real workflow authority, data and production security remain pending.

## Laravel pilot extension

`application/resources/css/app.css` imports `mockup/styles.css` through Vite; the approved palette remains canonical there. Laravel adds work-shell, forms, document registers, status labels and server feedback without independent token copies. Blade labels and errors are Malay, timestamps display Asia/Kuala_Lumpur, and authenticated views show the actor/role/department. Routes now contain real local auth/persistence, while operational approval authority is still pending. The app has no remote fonts or font dependency; Segoe UI remains the local stack.
