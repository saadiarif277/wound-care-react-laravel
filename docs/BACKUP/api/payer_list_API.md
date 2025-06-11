# Availity Payer List API Integration

**API Documentation**: [Availity Payer List 1.0.5](https://developer.availity.com/partner/product/257664/api/223440#/AvailityPayerList_105/overview)

## Overview
Provides a real-time list of payers connected to Availity and their supported transaction types (eligibility, pre-auth, claims).

## Integration into Med.Exchange

### Modules Impacted:
- **Med.Check**: Dynamically decide whether to use Availity vs. Optum/Office Ally.
- **Med.Flow**: Configure payer-specific workflows based on transaction availability.

## Use Cases
- **Eligibility Cascade Optimization**: Route traffic to the most capable API per payer.
- **Automation Logic**: Skip redundant steps when Availity provides complete coverage.

