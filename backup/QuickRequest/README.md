# QuickRequest Components Backup

This folder contains unused step components that were moved from the active QuickRequest workflow to clean up the codebase.

## Backup Date
Created: 2025-07-03

## Components Moved to Backup

### Legacy Step Components
- `Step1PatientInfoNew.tsx` - Alternative patient info collection component
- `Step2ProductSelection.tsx` - Old product selection implementation
- `Step2PatientInsuranceUpdated.tsx` - Updated version of patient insurance component
- `Step3Documentation.tsx` - Original documentation step
- `Step3DocumentationUpdated.tsx` - Updated documentation step
- `Step4Confirmation.tsx` - Confirmation step component
- `Step7DocusealIVRUpdated.tsx` - Updated version of Docuseal IVR component
- `Step7FinalSubmission.tsx` - Final submission step component
- `Step8OrderFormPending.tsx` - Pending order form component

## Currently Active Components (Not Moved)
The following components remain active in the current QuickRequest workflow:

- `Step2PatientInsurance.tsx` - Section 0: Patient & Insurance
- `Step4ClinicalBilling.tsx` - Section 1: Clinical Validation
- `Step5ProductSelection.tsx` - Section 2: Select Products
- `Step7DocusealIVR.tsx` - Section 3: Complete IVR Form
- `Step8OrderFormApproval.tsx` - Section 4: Order Form Review (conditional)
- `Step6ReviewSubmit.tsx` - Section 4/5: Review & Confirm

## DocumentUploadForm Error Fix
Also fixed ReferenceError by removing DocumentUploadForm component references from Step7DocusealIVR.tsx, as document upload functionality will be moved to the review page where it chronologically belongs after the submission is created.

## Restoration
If any of these components need to be restored, simply move them back to:
`/resources/js/Pages/QuickRequest/Components/`