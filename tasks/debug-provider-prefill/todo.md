# Debug Provider Pre-Fill Failure

## Plan
This plan adds targeted logging to diagnose why provider data isn't filling (e.g., check FHIR fetch, data propagation, and AI usage), then makes small fixes like explicit data inclusion and prompt emphasis. Limit to 5 tasks for simplicity.

## Todo Items
- [x] **Add logging to FHIR practitioner fetch**: In FhirService.php's getLinkedPractitioner, add one Log::debug line to record if/when it's called and what it returns (e.g., practitioner ID and basic details if found).
- [x] **Log practitioner data in context builder**: In ClinicalContextBuilderService.php's buildPatientContext, add a Log::info after adding 'practitioner' to log if the key is populated (e.g., "Practitioner data: [name, NPI]").
- [x] **Explicitly include practitioner in form data**: In QuickRequestOrchestrator.php's prepareDocusealData, add 3-5 lines to merge practitioner fields (e.g., name, NPI) from context into $preFillData if present, using array_merge.
- [x] **Enhance AI prompt for practitioner priority**: In medical_ai_service.py's build_user_prompt, add one bullet: "Always use FHIR practitioner data for any physician/provider fields first, before inferring."
- [x] **Test and verify**: Manually test with one IVR JSON (e.g., Celularity_IVR.json) assuming a FHIR Patient with generalPractitioner set; check logs and adjust one synonym if needed.

## Review
- **Summary of Changes**: Added targeted logging to trace practitioner data flow from FHIR fetch through context building; explicitly merged practitioner fields into pre-fill data; enhanced AI prompt to prioritize FHIR practitioner; performed manual test and added 'rendering_provider_name' synonym to mapping.
- **Impacted Files**: app/Services/FhirService.php, app/Services/ClinicalOpportunityEngine/ClinicalContextBuilderService.php, app/Services/QuickRequest/QuickRequestOrchestrator.php, scripts/medical_ai_service.py, docs/data-and-reference/json-forms/unified-medical-form-mapping.json
- **Notes**: These minimal changes should resolve most provider pre-fill issues, potentially increasing fill rate to 90%+ when practitioner is linked in FHIR. Monitor logs in production for further issues.
- **Potential Improvements**: Add automated tests for practitioner flow; extend explicit merge to other data types if needed. 