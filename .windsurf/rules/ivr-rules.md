---
trigger: model_decision
description: Step7DocusealIVR.tsx to be used when working with pre-fill for IVR forms.
globs: 
---
# IVR Tips

## Exact Field Name Matching

Docuseal requires character-perfect field name matches
Even extra spaces or punctuation differences cause "Unknown field" errors

## String vs Numeric Comparisons

Form values are stored as strings ('31', '32') not numbers (31, 32)
Computed field logic must use proper string comparisons: == "31"

## Field Separation Strategy

Physician fields: Individual provider data (Physician NPI, Physician PTAN)
Facility fields: Practice/clinic data (Practice NPI, Practice PTAN)
Clear naming prevents PTAN confusion

## Default Value Handling

Boolean-style fields should default to explicit "No" unless conditions are met
Place of Service checkboxes now properly activate based on selected option

