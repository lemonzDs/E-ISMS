# UX Contract — mockup review

Source: `.briefing`, user selection of Penyelaras ISMS, `PRODUCT.md`. All records and workflow decisions shown are synthetic demonstrations. No production permission or legal policy is inferred.

## Canonical UI Map
| Capability | Canonical owner | Source of truth | Allowed variants | Verification |
| --- | --- | --- | --- | --- |
| Select/Listbox | native select via field/filter helpers | this contract | native OS popup accepted | browser keyboard |
| Date | displayDate and text field | this contract | display / typed DD/MM/YYYY | validation |
| Form | field and risk form handlers, app.js | this contract | create | invalid and valid submission |
| Scrollbar | styles.css global baseline | DESIGN.md | document/table | narrow browser |
| Toast | toast, app.js | this contract | success/info | aria live |
| CRUD | in-memory data and delegated handlers | PRODUCT.md | create/read; simulated task transition | browser flow |

## Navigation and datasets
Hash routes: overview, risks, documents, actions, controls, reports, history. Shared table bounded to 6 records per page; hash parameters restore search, filter and page. Details open a native dialog; Escape and close restore focus. Browser back restores route state. Unknown routes show a recovery page. App title reflects current route.

The v0.2 portal provides a synthetic attention table and module directory. Record links use `?record=<id>` to open the exact native detail dialog; closing removes the record parameter with replaceState. Portal examples are static and do not reflect in-session workspace mutations. The topbar Portal return link is available on desktop and mobile. The portal register includes a mobile horizontal-scroll hint.

## Forms
Visible labels; novalidate; app-owned inline errors with aria-invalid and aria-describedby; focus first invalid input. Search has explicit reset. Risk creation returns to owning list, clears filters to make new record visible, announces success, focuses heading. Demo data lives in memory only. Reload resets it. Closing an edited form keeps a draft in memory for reopening and announces this; reload may discard it as stated on screen.

## Interactions
Risk matrix cells navigate to corresponding risk filter. Document details display synthetic version history and sample content. Task submission simulates sending to review, never real approval or acceptance of risk. Reports export only synthetic records. No destructive actions, real uploads, actual auth or external side effects. No network data loads; remote error/offline/conflict states belong to Laravel implementation.

## Accessibility
Target WCAG 2.2 AA; semantic navigation/table/forms, visible focus, skip link, text status, dialog title and live regions. Tables scroll in labelled regions; mobile navigation remains visible. Native popup locale may follow Windows. All product copy is Malay; date display ms-MY. Reference date fixed 2026-10-02 for reproducible demo.

## Production follow-up
Persisted create/edit success returns to owning list with previous list state; server pagination and enforced permissions. Real transition authority and retention remain pending. No client-side behavior in this demo is a security boundary.
