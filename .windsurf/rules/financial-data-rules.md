---
trigger: model_decision
description: Anytime you see a $ sign or pricing/money is involved
globs:
---
# Financial Data Permissions System

## The system applies permissions to any UI element containing "price" or "$" and includes:

### "common-financial-data" - National ASP & default MSC pricing (40% off ASP)

### "my-financial-data" - Provider-specific pricing, what they owe, commissions

### "view-all-financial-data" - Admin access to all financial information

###"no-financial-data" - Office managers see NO pricing info (headers/titles hidden entirely)

This rule will help me consistently apply the correct financial data visibility whenever I'm working on:

Product catalogs
Order forms
Commission tracking
Provider dashboards
Any component with pricing information

The key distinction is that "no-financial-data" users don't just see empty values - they don't see pricing-related UI elements at all, ensuring a clean interface appropriate for their role.