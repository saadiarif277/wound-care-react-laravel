# Data and Reference Files

This directory contains reference data, CSV forms, JSON forms, and other resources for the wound care platform.

## Adding New Field Variations
To improve form fill rates, add synonyms to the `alternateKeys` array in unified-medical-form-mapping.json for canonical fields. For example, for `patientName`, add variations like `pt_name` or `full_name`. Test the mapping after updates. 