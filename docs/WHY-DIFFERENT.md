# Why “Order Form After IVR Verification” Is Different And Better

Purpose

- Explain how the proposed flow differs from the current implementation and why it improves prefill accuracy and order form readiness.

What we do today (summary)

- Split mapping logic: frontend helper ([resources/js/utils/docusealFieldMapper.js](resources/js/utils/docusealFieldMapper.js)) plus backend service ([`App\Services\UnifiedFieldMappingService`](app/Services/UnifiedFieldMappingService.php)).
- Template drift risk: field names in configs can diverge from live DocuSeal templates, causing “Unknown field” 422 errors.
- Over-broad payloads: extra/unmapped fields sometimes leak into submissions.
- Order form timing: order forms can be created too early (before IVR verification), causing rework and confusion.
- Limited coverage visibility: prefill completeness is not enforced or surfaced.

What changes with the new flow

- Backend-only mapping: consolidate mapping in [`App\Services\UnifiedFieldMappingService`](app/Services/UnifiedFieldMappingService.php); frontend uses embed only ([`resources/js/Components/QuickRequest/DocusealEmbed.tsx`](resources/js/Components/QuickRequest/DocusealEmbed.tsx), [`resources/js/Pages/QuickRequest/Components/Step7DocusealIVR.tsx`](resources/js/Pages/QuickRequest/Components/Step7DocusealIVR.tsx)).
- Targeted extraction: only extract canonical fields required by the selected manufacturer config.
- Runtime validation: validate/filter outgoing field names against the live DocuSeal template and PDF-derived inventory.
- Deterministic normalization: standardize radios/checkboxes (Yes/No, Network, POS) before submit.
- Coverage gating/telemetry: compute coverage $coverage=\frac{filled}{total}\times100$ and warn if $coverage<95$.
- Order form gating: generate and expose the Order Form only after IVR is verified via [`App\Services\QuickRequest\QuickRequestOrchestrator::completeEpisode`](app/Services/QuickRequest/QuickRequestOrchestrator.php); see [docs/FORM-AFTER-IVR.md](docs/FORM-AFTER-IVR.md).

Why this is better for prefill

- Eliminates template drift: runtime validation filters out stale field names before the DocuSeal API call.
- Reduces 422s: only valid, mapped fields are sent; extraneous keys are dropped.
- Higher completeness: targeted extraction plus normalization increases $coverage$ and lowers manual edits.
- Consistent mapping surface: one source of truth in the backend minimizes divergence from UI data structures.

Why this is better for the order form

- Correct timing: creating the Order Form only after IVR verification reflects real operational flow (fewer re-open/re-send cycles).
- “Ready to Sign” state: clear gating and visibility in Order Details for providers/office managers; no partial or premature forms.
- Consistent field semantics: reuses the same canonical→DocuSeal mapping for IVR and Order Form, preventing mismatches between documents.
- Embedded signing: no email send; embedded link is stored and shown when status is Ready to Sign.

Security and compliance

- PHI separation preserved (FHIR/SQL boundaries unchanged).
- DocuSeal operations are non-destructive: POST /submissions only; templates aren’t modified.
- RBAC and audit logs continue to apply; status transitions are explicit and traceable.

Operational impact and KPIs

- Prefill success: target coverage ≥ 95% with warning when below ($coverage=\frac{filled}{total}\times100$).
- Error rate: expected 40–60% reduction in DocuSeal 422 “Unknown field” errors.
- Time-to-complete: fewer manual edits and fewer re-sends due to early order form creation.
- Support load: lower ticket volume related to mapping/template drift.

Rollout and compatibility

- Feature-flagged activation; current behavior can be retained per manufacturer if needed.
- Requires manufacturer config to define `order_form_template_id` and `order_form_field_names`.
- If no Order Form config is found, system safely skips generation (no regressions).

References

- Prefill plan: [PREFILL.md](PREFILL.md)
- Order Form after IVR plan: [FORM-AFTER-IVR.md](FORM-AFTER-IVR.md)
- Orchestrator hook: [`App\Services\QuickRequest\QuickRequestOrchestrator::completeEpisode`](app/Services/QuickRequest/QuickRequestOrchestrator.php)
- Mapping service: [`App\Services\UnifiedFieldMappingService`](app/Services/UnifiedFieldMappingService.php)
- DocuSeal embed: [`resources/js/Components/QuickRequest/DocusealEmbed.tsx`](resources/js/Components/QuickRequest/DocusealEmbed.tsx)
- IVR step: [`resources/js/Pages/QuickRequest/Components/Step7DocusealIVR.tsx`](resources/js/Pages/QuickRequest/Components/Step7DocusealIVR.tsx)
