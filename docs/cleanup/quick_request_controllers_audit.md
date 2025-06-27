# Quick Request & Related Controllers / Services Cleanup Audit

_Last updated: 2025-06-25 19:57 CST_

This document records an initial audit of the **Quick Request / DocuSeal / Insurance** related PHP classes that appear potentially redundant.  For each class I checked:

1. **Route bindings** (web.php / api.php)
2. **Service container bindings** (service providers, `app()->make()` etc.)
3. **Direct code references** (grep search)
4. Purpose and current production relevance

The column **Safe to Remove?** reflects the current findings.  Treat anything flagged **⚠ REVIEW** with caution – further testing/QA is recommended before deletion.

| File | Purpose | Route / Code Usage | Safe to Remove? | Notes |
|------|---------|-------------------|-----------------|-------|
| `app/Http/Controllers/QuickRequestController.php` | Browser (Inertia) controller for legacy Quick-Request UI | Many web routes (create, store, DocuSeal interactions) | **NO** | Core of legacy UI – still hit by multiple routes. |
| `app/Http/Controllers/Api/V1/QuickRequestController.php` | API wrapper for new RSC/SPA flow | Multiple `api.php` & `web.php` routes (`generateBuilderToken`, episode helpers) | **NO** | Actively used by new frontend.
| `app/Http/Controllers/Api/V1/QuickRequestEpisodeController.php` | REST endpoints for Episode CRUD | Mounted under `/api/v1/quick-request/episodes` | **NO** | Essential for episode engine.
| `app/Http/Controllers/Api/V1/QuickRequestOrderController.php` | REST endpoints for Orders within episode | `/episodes/{episode}/orders` routes | **NO** | Essential for order creation/follow-up.
| `app/Http/Controllers/Api/V1/DocuSealTemplateController.php` | Field-mapping & template sync endpoints | Heavy usage in both `web.php` & `api.php` | **NO** | Core field-mapping engine.
| `app/Http/Controllers/DocuSealDebugController.php` | Dev-only debug endpoints (`/docuseal/test*`) | Two web debug routes; not shipped to prod | ⚠ REVIEW | Safe to delete from production build; keep for local dev or move behind `APP_DEBUG` flag.
| `app/Http/Controllers/FhirController.php` | Full FHIR R4 façade over Azure HDS | Extensive routes under `/fhir/*` (both `api.php` & `web.php`) | **NO** | Critical for PHI-separated FHIR API.
| `app/Http/Controllers/InsuranceController.php` | Ad-hoc Insurance OCR & eligibility sandbox | **No route bindings found** | **YES – candidate** | Appears unused; functions duplicated inside `InsuranceIntegrationService` called elsewhere.
| `app/Services/QuickRequest/QuickRequestOrchestrator.php` | High-level workflow orchestrator (episode, product request) | Instantiated in `QuickRequestController` constructor | **NO** | Required by QuickRequestController.
| `app/Services/QuickRequestService.php` | Stateless helper service used by API controllers | Injected in multiple controllers | **NO** | Core.
| `app/Services/Insurance/InsuranceIntegrationService.php` | Handles Azure OCR & eligibility logic | Used by `InsuranceController` AND various Jobs | **NO** | Still referenced by jobs; keep.
| `app/Jobs/QuickRequest/*` (ProcessEpisodeCreation, VerifyInsuranceEligibility, …) | Asynchronous steps of workflow | Dispatched by QuickRequestController | **NO** | Required.
| `app/Providers/QuickRequestServiceProvider.php` | Binds QuickRequest services | Auto-loaded in `config/app.php` | **NO** | Keep.
| `app/Http/Middleware/HandleQuickRequestErrors.php` | Converts QuickRequest exceptions to JSON | Globally registered in `Kernel.php` | **NO** | Keep.

## Recommended Actions

1. **Remove or archive `InsuranceController.php`**
   • No active routes; all logic lives in `InsuranceIntegrationService`.  Grep shows no other direct references.

2. **Move `DocuSealDebugController` routes under debug-only gate**
   • Add `->middleware('debug')` or guard behind `APP_DEBUG`.  Optionally delete from production.

3. **Document legacy vs. new API**
   • The legacy Blade/Inertia QuickRequestController could be deprecated when all clients migrate to the new API flow.

4. **Unit Tests**
   • After removal, add regression tests ensuring endpoints still pass.

---

### How to proceed
1. PR removing `InsuranceController.php` and its unused import in any file.
2. Update `routes/web.php` debug block to wrap DocuSeal debug routes:
   ```php
   if (app()->environment('local') || config('app.debug')) {
       Route::get('/test', [DocuSealDebugController::class, 'debug']);
       Route::get('/test-submission', [DocuSealDebugController::class, 'testSubmission']);
   }
   ```
3. Run full test suite & manual smoke test of Quick Request flow.

_This audit only covers the classes shown in the initial screenshot.  A broader search may reveal additional orphaned controllers not yet flagged._
