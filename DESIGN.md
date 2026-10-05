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
  cp-navy: '#142F41'
  cp-gold: '#D8B66B'
  cp-muted: '#52616A'
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
  cp-control: '3px'
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
User-approved direction: **Formal, ringkas dan berorientasikan kerja**, avoiding generic AI-generated decoration. The historical mockup portal and coordinator workspace use Operate mode: registers, ownership, dates and next actions provide the hierarchy.

**Creative North Star: "Meja urus setia"** describes the proposed working environment; it is not an approved official identity. A plain e-ISMS wordmark replaces the decorative monogram. The portal presents an attention register of synthetic records and a module directory. The workspace uses a quiet light sidebar and a ledger summary.

The Laravel public landing is a scoped corporate variant, extracted from `application/resources/css/portal.css` and `application/resources/views/landing.blade.php` on 3 October 2026. The direction contract in `docs/landing-direction.md` establishes an institutional front door anchored by Pahang's actual crest and PPSAS architecture, one access destination and no simulated statistics. Workspace and historical mockup tokens remain intact.

**Key Characteristics:**
- Plain wordmark, light sidebar and ledger summaries in the workspace.
- Official crest, white masthead and deep blue architectural hero on the public portal.
- Restrained brass access action and rectangular public portal controls.

Asset provenance from `docs/landing-direction.md`: `application/public/images/landing/jata-pahang.png` is the unchanged official crest from https://www.pahang.gov.my/pahang/resources/jataphg.png, retrieved 2026-10-03. `application/public/images/landing/ppsas.jpg` is the unchanged architectural illustration from https://triumphantgallery.com.my/wp-content/uploads/2026/05/citiesurbandesign_PPSAS_004_new-1536x864.jpg, retrieved 2026-10-03. Project source: https://triumphantgallery.com.my/government-institution/. The image alternative text identifies it as an illustration; provenance is retained in docs/landing-direction.md. Visible caption and credit removed as requested on 2026-10-05; no government endorsement is claimed. The detector engine was unavailable; documentation uses source inspection.

## Colors
Frontmatter mirrors runtime tokens in `mockup/styles.css :root`; CSS remains the source of truth. Navy identifies active navigation, primary actions and the portal register heading; blue marks links and information. Gold is a small contextual marker. Cool neutral surfaces, ink text and dividers keep records legible. Status combines a text label with semantic foreground/background colors.

The scoped cp-navy, cp-gold and cp-muted primitives come from the corporate portal custom properties. Navy frames the government strip, hero and footer; brass marks the hero access action; muted text supports white content sections.

**The Scoped Portal Rule.** Corporate portal tokens apply only to the public landing; workspace tokens remain canonical for authenticated work.

## Typography
Segoe UI is the local body and heading stack with a sans-serif fallback, appropriate to the office Operate direction. Body is 14px/1.55; workspace headings are 28px and section headings 18px. Metadata is generally 12px; status and matrix context use 11px. Some mobile contextual notes also use 11px. Tables use 13px body and 12px headers.

Historical mockup portal headline is 53px/1.15, 44px at <=1100px, 45px at <=800px and 35px at <=580px. Intro copy is 16px/1.8, reduced to 14px on small screens. Tabular numerals support dates and counts.

The public landing retains the chosen Segoe UI office continuity. Body is 16px/1.6, supporting copy 15px/1.8, workflow text 14px/1.75 and navigation 14px/600. The shipped hero heading uses 600 weight, clamp(42px,4.25vw,62px), 1.12 line height and 43px on mobile. This observed surface treatment does not create a new global display-face recommendation. About, workflow and access headings use 34px, 27px and 22px.

## Layout
Workspace sidebar is 228px, reduced to 210px at <=1200px. Main content has a 1540px maximum and 34px side padding. A border-separated ledger strip groups four summaries. Dashboard columns use 1.7fr and a minimum 290px supporting column with a 28px gap; they stack at <=1050px. At <=760px navigation wraps above content. The topbar Portal link remains available at every width.

Historical mockup portal uses a 1280px outer container with 36px side padding. Intro columns are 1.8fr/1fr, followed by an actual semantic attention table, module directory and workflow. The directory has two columns. At <=800px intro and section layouts stack; at <=580px padding becomes 20px and directory/workflow become one column. Tables retain horizontal scrolling in labelled regions. Reports form one divided list with export actions, rather than separate cards.

The corporate portal container caps at 1240px with 56px side margins, reduced to 32px at <=1000px and 20px at <=700px. A 116px masthead precedes a 594px hero (660px at >=1700px). Desktop copy is left aligned with architecture visible at right. At <=700px the 99px masthead keeps access while hiding section links; the 690px hero places copy above the building. About columns stack, four workflow steps become two columns, and access/footer rows stack.

## Elevation & Depth
Records are grouped by borders, spacing and restrained tonal backgrounds. Panels and portal register have no shadows. Dialog uses `0 18px 60px #142D4033`; toast uses `0 5px 25px #142D4026`. Matrix counts are flat.

The public portal has no component shadows. Image overlays supply text contrast; pale section backgrounds and fine rules separate content. Image provenance is recorded in project documentation.

## Shapes
Panels use the default radius; controls, badges and dialogs use their respective frontmatter tokens. Wordmarks carry the application identity. Authored SVG line icons supplement text labels, usually at 16–20px. No decorative monogram or stacked card treatment defines this version.

Corporate portal controls use the scoped cp-control radius. Architectural imagery is full bleed, with straight section edges. Workflow stages share a horizontal desktop rule and use individual rules on mobile.

## Components
Primary buttons are navy with white text and a darker hover response. Quiet buttons use neutral text and a pale informational hover surface. Fields have visible labels, four-pixel corners, inline validation and native select popups. Focus uses a three-pixel outline. Status badges always contain text.

Attention-table links open exact synthetic records via the record query parameter; portal examples do not update with in-session workspace mutations. The register includes a mobile horizontal-scroll hint. Module rows provide simple directory links. Workspace actions use delegated click, submit and change handlers. Native dialogs restore focus on closing. Button feedback lasts 150ms; reduced motion disables transitions. Shared scrollbar, selection and focus styles live in the runtime stylesheet.

### Corporate public portal
Dark access buttons sit on white and the brass variant sits on the hero. Both use minimum 48px height, 12px 22px padding and 14px/600 text; the mobile header button has minimum 44px height. Background feedback lasts 150ms; reduced motion removes transitions. Links have a three-pixel focus outline offset five pixels, white on hero/footer surfaces. Navigation uses semantic links; the skip link targets main. All access links share the role-aware destination: login for guests, user administration for admins and documents for other signed-in users.

**The Honest Image Rule.** Identify the PPSAS architectural illustration in alternative text and retain provenance in project documentation.

## Do's and Don'ts
- Do show ownership, status and target dates together.
- Do retain text labels for statuses and meaningful action destinations.
- Do distinguish synthetic records from official operational evidence.
- Don't add decorative identity marks, card grids or promotional claims to routine work surfaces.
- Don't imply live authentication in historical mockups, official brand approval or compliance certification.
- Do keep corporate portal styles scoped to the public landing.
- Do retain crest and architectural illustration provenance in project documentation.
- Don't present simulated statistics as live organisational results.

Visual direction is user-selected; the v0.2 implementation remains a review prototype. Real workflow authority, data and production security remain pending.

### Laravel pilot extension

`application/resources/css/app.css` imports `mockup/styles.css` through Vite; the approved palette remains canonical there. Laravel adds work-shell, forms, document registers, status labels and server feedback without independent token copies. Blade labels and errors are Malay, timestamps display Asia/Kuala_Lumpur, and authenticated views show the actor/role/department. Routes now contain real local auth/persistence, while operational approval authority is still pending. The app has no remote fonts or font dependency; Segoe UI remains the local stack.
