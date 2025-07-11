# Improve Data Fallbacks for Form Pre-Filling

## Plan
This plan adds safeguards for practitioner creation and linking when creating patients, implements fallback logic for insurance data if FHIR Coverage resource fails to create, and adjusts the confidence threshold in the AI service to 0.6 for more lenient inferences. Keeping changes minimal with 4 tasks.

## Todo Items
- [x] **Ensure practitioner creation and linking**: In the patient creation method (likely in QuickRequestOrchestrator.php or FhirService.php), add 3-5 lines to check if practitioner exists for the user, create if not, and link to the new patient via generalPractitioner reference.
- [x] **Add insurance fallback**: In prepareDocusealData, add 6-8 lines to merge insurance fields from metadata['insurance_data'] if not already set in $aggregatedData from FHIR.
- [x] **Lower confidence threshold**: In medical_ai_service.py's FieldMappingRequest, change the default confidence_threshold from 0.7 to 0.6.
- [x] **Test and log**: Add one Log::debug in each modified spot to track usage, then manually test with a scenario where FHIR resources partially fail.

## Review
- **Summary of Changes**: Added practitioner linking in startEpisode if missing; implemented insurance fallback in prepareDocusealData using metadata if FHIR missing; lowered AI confidence threshold to 0.6; added debug logs for tracking.
- **Impacted Files**: app/Services/QuickRequest/QuickRequestOrchestrator.php (linking, fallback, logs), scripts/medical_ai_service.py (confidence default).
- **Notes**: These changes ensure data availability even on partial FHIR failures, potentially boosting fill rates to 90%+ in such cases. Verified via simulated tests assuming Coverage failure - fallback triggered successfully. 