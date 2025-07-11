#!/usr/bin/env python3
"""
Test script for MedLife Solutions (Amnio AMP) field mapping
Tests why only 8-9 fields are being filled instead of all available fields
"""

import asyncio
import httpx
import json
from datetime import datetime

# ANSI colors
GREEN = '\033[92m'
RED = '\033[91m'
YELLOW = '\033[93m'
BLUE = '\033[94m'
RESET = '\033[0m'

def print_status(message: str, status: str = "info"):
    colors = {"success": GREEN, "error": RED, "warning": YELLOW, "info": BLUE}
    color = colors.get(status, RESET)
    print(f"{color}[{status.upper()}]{RESET} {message}")

# Comprehensive test data matching what Quick Request form collects
MEDLIFE_TEST_DATA = {
    # Basic Contact Information
    "contact_name": "John Smith",
    "contact_email": "john.smith@clinic.com",
    "contact_phone": "(555) 123-4567",
    "distributor_company": "MSC Wound Care",
    "sales_rep_name": "Jane Doe",
    "organization_name": "General Hospital",
    
    # Patient Information
    "patient_first_name": "Robert",
    "patient_last_name": "Johnson",
    "patient_name": "Robert Johnson",
    "patient_dob": "03/15/1965",
    "patient_gender": "M",
    "patient_member_id": "MED789456123",
    "patient_address_line1": "456 Oak Street",
    "patient_city": "Houston",
    "patient_state": "TX",
    "patient_zip": "77001",
    "patient_phone": "(555) 987-6543",
    "patient_email": "rjohnson@email.com",
    
    # Provider Information
    "provider_name": "Dr. Emily Williams",
    "provider_first_name": "Emily",
    "provider_last_name": "Williams", 
    "provider_npi": "1234567890",
    "provider_ptan": "PTAN123456",
    "provider_email": "ewilliams@clinic.com",
    "provider_phone": "(555) 234-5678",
    "provider_specialty": "Wound Care",
    "provider_tax_id": "12-3456789",
    
    # Facility Information
    "facility_name": "Houston Medical Center",
    "facility_address": "789 Medical Drive",
    "facility_address_line1": "789 Medical Drive",
    "facility_city": "Houston",
    "facility_state": "TX",
    "facility_zip_code": "77002",
    "facility_phone": "(555) 345-6789",
    "facility_fax": "(555) 345-6790",
    "facility_npi": "9876543210",
    "facility_ptan": "FAC789456",
    "facility_tax_id": "98-7654321",
    "place_of_service": "11",
    
    # Insurance Information
    "primary_insurance_name": "Medicare",
    "primary_member_id": "MED789456123",
    "primary_payer_phone": "(800) 633-4227",
    "secondary_insurance_name": "Blue Cross Blue Shield",
    "secondary_member_id": "BCBS456789",
    "secondary_payer_phone": "(800) 262-2583",
    
    # Clinical Information
    "wound_type": "diabetic_foot_ulcer",
    "wound_location": "Right foot, plantar surface",
    "wound_size_length": "3.5",
    "wound_size_width": "2.8",
    "wound_size_depth": "0.4",
    "wound_size_total": "9.8",
    "primary_diagnosis_code": "E11.621",
    "secondary_diagnosis_code": "L97.519",
    "wound_duration_weeks": "8",
    "graft_size_requested": "4x4",
    "procedure_date": "07/15/2025",
    
    # ICD-10 and CPT codes
    "icd10_code_1": "E11.621",
    "icd10_code_2": "L97.519",
    "cpt_code_1": "15271",
    "cpt_code_2": "97597",
    "application_cpt_codes": ["15271", "97597"],
    
    # SNF/Post-op status
    "patient_snf_yes": False,
    "patient_snf_no": True,
    "snf_days": "",
    "post_op_status": False,
    "global_period_status": False,
    
    # Additional required fields
    "failed_conservative_treatment": True,
    "information_accurate": True,
    "medical_necessity_established": True,
    "maintain_documentation": True,
    "authorize_prior_auth": True,
    
    # Office contact
    "office_contact_name": "Mary Johnson",
    "office_contact_email": "mjohnson@clinic.com"
}

async def test_get_template_fields(client: httpx.AsyncClient, base_url: str, template_id: str):
    """Get and display all fields from the DocuSeal template"""
    try:
        print_status(f"Getting template fields for template {template_id}...")
        response = await client.get(f"{base_url}/api/v1/docuseal/template/{template_id}/fields")
        
        if response.status_code == 200:
            data = response.json()
            print_status(f"Template has {data.get('total_fields', 0)} fields", "success")
            
            # Display field names
            field_names = data.get('field_names', [])
            print("\nTemplate Fields:")
            for i, field in enumerate(field_names, 1):
                print(f"  {i}. {field}")
            
            return data
        else:
            print_status(f"Failed to get template fields: {response.status_code}", "error")
            return None
    except Exception as e:
        print_status(f"Error getting template fields: {str(e)}", "error")
        return None

async def test_field_mapping(client: httpx.AsyncClient, base_url: str):
    """Test AI field mapping with comprehensive data"""
    
    # MedLife Solutions template ID
    template_id = "1233913"
    
    payload = {
        "context": {
            "base_data": MEDLIFE_TEST_DATA,
            "manufacturer_context": {
                "name": "MedLife Solutions"
            },
            "template_structure": {
                "template_fields": {
                    "field_names": [
                        # All MedLife fields from config
                        "Name", "Email", "Phone", "Distributor/Company",
                        "Physician Name", "Physician PTAN", "Physician NPI",
                        "Practice Name", "Practice PTAN", "Practice NPI", "TAX ID",
                        "Office Contact Name", "Office Contact Email",
                        "Patient Name", "Patient DOB",
                        "Primary Insurance", "Member ID", "Secondary Insurance", "Secondary Member ID",
                        "Office: POS-11", "Home: POS 12", "Assisted Living: POS-13", "Other",
                        "Is the patient currently residing in a Nursing Home OR Skilled Nursing Facility",
                        "If yes, has it been over 100 days",
                        "Is this patient currently under a post-op period",
                        "If yes please list CPT codes of previous surgery", "Surgery Date",
                        "Procedure Date", "L", "W", "Wound Size Total", "Wound location",
                        "Size of Graft Requested",
                        "ICD-10 #1", "ICD-10 #2", "ICD-10 #3", "ICD-10 #4",
                        "CPT #1", "CPT #2", "CPT #3", "CPT #4",
                        "HCPCS #1", "HCPCS #2", "HCPCS #3", "HCPCS #4"
                    ]
                }
            }
        },
        "optimization_level": "high",
        "confidence_threshold": 0.6
    }
    
    try:
        print_status("Testing AI field enhancement for MedLife Solutions...")
        print_status(f"Sending {len(MEDLIFE_TEST_DATA)} source fields")
        
        response = await client.post(
            f"{base_url}/api/v1/enhance-mapping",
            json=payload,
            timeout=30.0
        )
        
        if response.status_code == 200:
            data = response.json()
            enhanced_fields = data.get('enhanced_fields', {})
            
            print_status("AI enhancement response received", "success")
            print(f"\n  Original fields: {len(MEDLIFE_TEST_DATA)}")
            print(f"  Enhanced fields: {len(enhanced_fields)}")
            print(f"  Confidence: {data.get('confidence', 0):.2%}")
            print(f"  Method: {data.get('method')}")
            
            # Show which fields were mapped
            print("\nMapped Fields:")
            for i, (key, value) in enumerate(enhanced_fields.items(), 1):
                print(f"  {i}. {key}: {value}")
            
            # Check for missing critical fields
            critical_fields = [
                "Name", "Email", "Phone", "Patient Name", "Patient DOB",
                "Physician Name", "Physician NPI", "Practice Name",
                "Primary Insurance", "Member ID", "Procedure Date",
                "L", "W", "Wound location", "ICD-10 #1", "CPT #1"
            ]
            
            print("\nCritical Field Check:")
            missing_fields = []
            for field in critical_fields:
                if field in enhanced_fields:
                    print(f"  ✓ {field}: {enhanced_fields[field]}")
                else:
                    print(f"  ✗ {field}: MISSING")
                    missing_fields.append(field)
            
            if missing_fields:
                print_status(f"\nMissing {len(missing_fields)} critical fields!", "warning")
                print("Missing fields:", ", ".join(missing_fields))
            
            return data
        else:
            print_status(f"Enhancement failed: {response.status_code}", "error")
            print(f"Response: {response.text}")
            return None
            
    except Exception as e:
        print_status(f"Enhancement error: {str(e)}", "error")
        return None

async def test_direct_submission(client: httpx.AsyncClient, base_url: str):
    """Test creating a DocuSeal submission directly with all fields"""
    
    template_id = "1233913"  # MedLife IVR template
    
    # Map our test data to exact DocuSeal field names
    docuseal_fields = {
        "Name": MEDLIFE_TEST_DATA["contact_name"],
        "Email": MEDLIFE_TEST_DATA["contact_email"],
        "Phone": MEDLIFE_TEST_DATA["contact_phone"],
        "Distributor/Company": MEDLIFE_TEST_DATA["distributor_company"],
        "Physician Name": MEDLIFE_TEST_DATA["provider_name"],
        "Physician PTAN": MEDLIFE_TEST_DATA["provider_ptan"],
        "Physician NPI": MEDLIFE_TEST_DATA["provider_npi"],
        "Practice Name": MEDLIFE_TEST_DATA["facility_name"],
        "Practice NPI": MEDLIFE_TEST_DATA["facility_npi"],
        "Practice PTAN": MEDLIFE_TEST_DATA["facility_ptan"],
        "TAX ID": MEDLIFE_TEST_DATA["facility_tax_id"],
        "Office Contact Name": MEDLIFE_TEST_DATA["office_contact_name"],
        "Office Contact Email": MEDLIFE_TEST_DATA["office_contact_email"],
        "Patient Name": MEDLIFE_TEST_DATA["patient_name"],
        "Patient DOB": MEDLIFE_TEST_DATA["patient_dob"],
        "Primary Insurance": MEDLIFE_TEST_DATA["primary_insurance_name"],
        "Member ID": MEDLIFE_TEST_DATA["primary_member_id"],
        "Secondary Insurance": MEDLIFE_TEST_DATA["secondary_insurance_name"],
        "Secondary Member ID": MEDLIFE_TEST_DATA["secondary_member_id"],
        "Office: POS-11": True,  # Since place_of_service is "11"
        "Procedure Date": MEDLIFE_TEST_DATA["procedure_date"],
        "L": MEDLIFE_TEST_DATA["wound_size_length"],
        "W": MEDLIFE_TEST_DATA["wound_size_width"],
        "Wound Size Total": MEDLIFE_TEST_DATA["wound_size_total"],
        "Wound location": MEDLIFE_TEST_DATA["wound_location"],
        "Size of Graft Requested": MEDLIFE_TEST_DATA["graft_size_requested"],
        "ICD-10 #1": MEDLIFE_TEST_DATA["icd10_code_1"],
        "ICD-10 #2": MEDLIFE_TEST_DATA["icd10_code_2"],
        "CPT #1": MEDLIFE_TEST_DATA["cpt_code_1"],
        "CPT #2": MEDLIFE_TEST_DATA["cpt_code_2"]
    }
    
    payload = {
        "template_id": template_id,
        "submitter_email": "provider@example.com",
        "submitter_name": "Dr. Emily Williams",
        "field_values": docuseal_fields,
        "metadata": {
            "test": True,
            "manufacturer": "MedLife Solutions",
            "product": "Amnio AMP"
        }
    }
    
    try:
        print_status(f"Creating DocuSeal submission with {len(docuseal_fields)} fields...")
        
        # Uncomment to actually create submission
        # response = await client.post(
        #     f"{base_url}/api/v1/docuseal/submissions",
        #     json=payload,
        #     timeout=30.0
        # )
        
        print("\nWould submit these fields:")
        for field, value in docuseal_fields.items():
            print(f"  - {field}: {value}")
        
        return True
        
    except Exception as e:
        print_status(f"Submission error: {str(e)}", "error")
        return False

async def main():
    """Main test function"""
    base_url = "http://localhost:8081"
    
    print("\n" + "="*70)
    print("MEDLIFE SOLUTIONS (AMNIO AMP) FIELD MAPPING TEST")
    print("="*70)
    print(f"Testing service at: {base_url}")
    print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*70 + "\n")
    
    async with httpx.AsyncClient() as client:
        # Test 1: Get template fields
        print_status("Test 1: Get DocuSeal Template Fields", "info")
        template_data = await test_get_template_fields(client, base_url, "1233913")
        print("")
        
        # Test 2: AI field enhancement
        print_status("Test 2: AI Field Enhancement", "info")
        enhancement_result = await test_field_mapping(client, base_url)
        print("")
        
        # Test 3: Show what direct submission would look like
        print_status("Test 3: Direct Submission (Dry Run)", "info")
        await test_direct_submission(client, base_url)
        
        # Summary
        print("\n" + "="*70)
        print("TEST SUMMARY")
        print("="*70)
        
        if enhancement_result:
            enhanced_count = len(enhancement_result.get('enhanced_fields', {}))
            expected_count = 30  # Approximate expected fields for MedLife
            
            if enhanced_count < expected_count / 2:
                print_status(f"Only {enhanced_count} fields mapped out of ~{expected_count} expected!", "error")
                print("\nPossible issues:")
                print("1. AI service not using manufacturer config properly")
                print("2. Template field names not matching config")
                print("3. Field transformation rules need adjustment")
            else:
                print_status(f"Mapped {enhanced_count} fields successfully", "success")

if __name__ == "__main__":
    asyncio.run(main())
