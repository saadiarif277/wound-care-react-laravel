#!/usr/bin/env python3
"""
Comprehensive test script for AI Service and DocuSeal integration
Tests the full pipeline from form data to DocuSeal submission
"""

import asyncio
import httpx
import json
from datetime import datetime
from typing import Dict, Any
import sys

# ANSI color codes
GREEN = '\033[92m'
RED = '\033[91m'
YELLOW = '\033[93m'
BLUE = '\033[94m'
RESET = '\033[0m'

def print_status(message: str, status: str = "info"):
    """Print colored status messages"""
    colors = {
        "success": GREEN,
        "error": RED,
        "warning": YELLOW,
        "info": BLUE
    }
    color = colors.get(status, RESET)
    print(f"{color}[{status.upper()}]{RESET} {message}")

# Test data that mimics real form submissions
TEST_FORM_DATA = {
    # Patient Information
    "patient_first_name": "John",
    "patient_last_name": "Doe",
    "patient_name": "John Doe",
    "patient_dob": "01/01/1970",
    "patient_gender": "M",
    "patient_member_id": "ABC123456",
    "patient_address_line1": "123 Main Street",
    "patient_city": "Anytown",
    "patient_state": "CA",
    "patient_zip": "12345",
    "patient_phone": "(555) 123-4567",
    "patient_email": "johndoe@example.com",
    
    # Provider Information
    "provider_name": "Dr. Jane Smith",
    "provider_npi": "1234567890",
    "provider_email": "drsmith@clinic.com",
    "facility_name": "General Hospital",
    "facility_address": "456 Hospital Way",
    "facility_city": "Medical City",
    "facility_state": "CA",
    "facility_zip": "54321",
    
    # Clinical Information
    "wound_type": "Pressure Ulcer",
    "wound_location": "Sacral",
    "wound_size_length": "3",
    "wound_size_width": "4",
    "wound_size_depth": "0.5",
    "primary_diagnosis_code": "L89.152",
    "secondary_diagnosis_code": "E11.622",
    
    # Insurance Information
    "primary_insurance_name": "Medicare",
    "primary_member_id": "ABC123456",
    "group_number": "GRP001",
    "payer_phone": "(800) 555-1234",
    
    # Additional Fields
    "wound_duration_weeks": "4",
    "prior_applications": "No",
    "hospice_status": False,
}

async def test_health_check(client: httpx.AsyncClient, base_url: str) -> bool:
    """Test the health endpoint"""
    try:
        response = await client.get(f"{base_url}/health")
        if response.status_code == 200:
            data = response.json()
            print_status("Health check passed", "success")
            print(f"  - Azure configured: {data.get('azure_configured')}")
            print(f"  - DocuSeal configured: {data.get('docuseal_configured')}")
            print(f"  - DocuSeal available: {data.get('docuseal_available')}")
            return True
        else:
            print_status(f"Health check failed: {response.status_code}", "error")
            return False
    except Exception as e:
        print_status(f"Health check error: {str(e)}", "error")
        return False

async def test_docuseal_connection(client: httpx.AsyncClient, base_url: str) -> bool:
    """Test DocuSeal API connectivity"""
    try:
        response = await client.get(f"{base_url}/api/v1/docuseal/test")
        data = response.json()
        if data.get('success'):
            print_status("DocuSeal connection test passed", "success")
            return True
        else:
            print_status(f"DocuSeal connection failed: {data.get('message')}", "error")
            return False
    except Exception as e:
        print_status(f"DocuSeal test error: {str(e)}", "error")
        return False

async def test_field_enhancement(client: httpx.AsyncClient, base_url: str, form_data: Dict[str, Any]) -> Dict[str, Any]:
    """Test AI field enhancement"""
    payload = {
        "context": {
            "base_data": form_data,
            "manufacturer_context": {
                "name": "BioWound"
            },
            "template_structure": {
                "template_fields": {
                    "field_names": [
                        "patient_full_name", "date_of_birth", "member_number",
                        "primary_insurance", "diagnosis_codes", "wound_description"
                    ]
                }
            }
        },
        "optimization_level": "high",
        "confidence_threshold": 0.7
    }
    
    try:
        print_status("Testing AI field enhancement...", "info")
        response = await client.post(
            f"{base_url}/api/v1/enhance-mapping",
            json=payload,
            timeout=30.0
        )
        
        if response.status_code == 200:
            data = response.json()
            print_status("AI enhancement successful", "success")
            print(f"  - Confidence: {data.get('confidence', 0):.2%}")
            print(f"  - Method: {data.get('method')}")
            print(f"  - Fields enhanced: {len(data.get('enhanced_fields', {}))}")
            
            # Show some enhanced fields
            enhanced = data.get('enhanced_fields', {})
            print("\n  Enhanced fields sample:")
            for i, (key, value) in enumerate(enhanced.items()):
                if i < 5:
                    print(f"    - {key}: {value}")
            
            return data
        else:
            print_status(f"Enhancement failed: {response.status_code}", "error")
            print(f"  Response: {response.text}")
            return {}
            
    except Exception as e:
        print_status(f"Enhancement error: {str(e)}", "error")
        return {}

async def test_docuseal_submission(client: httpx.AsyncClient, base_url: str, enhanced_data: Dict[str, Any]) -> bool:
    """Test creating a DocuSeal submission"""
    
    # Use a real template ID (you'll need to provide this)
    template_id = "123456"  # Replace with actual template ID
    
    payload = {
        "template_id": template_id,
        "submitter_email": "provider@example.com",
        "submitter_name": "Dr. Jane Smith",
        "field_values": enhanced_data.get('enhanced_fields', TEST_FORM_DATA),
        "metadata": {
            "test": True,
            "timestamp": datetime.now().isoformat(),
            "ai_enhanced": True
        }
    }
    
    try:
        print_status(f"Creating DocuSeal submission for template {template_id}...", "info")
        response = await client.post(
            f"{base_url}/api/v1/docuseal/submissions",
            json=payload,
            timeout=30.0
        )
        
        if response.status_code == 200:
            data = response.json()
            print_status("DocuSeal submission created successfully", "success")
            print(f"  - Submission ID: {data.get('submission_id')}")
            print(f"  - Status: {data.get('status')}")
            print(f"  - Form URL: {data.get('form_url')}")
            print(f"  - Fields submitted: {data.get('fields_submitted')}")
            return True
        else:
            print_status(f"Submission failed: {response.status_code}", "error")
            print(f"  Response: {response.text}")
            return False
            
    except Exception as e:
        print_status(f"Submission error: {str(e)}", "error")
        return False

async def test_end_to_end_workflow(client: httpx.AsyncClient, base_url: str) -> bool:
    """Test the complete workflow from form data to DocuSeal"""
    
    template_id = "123456"  # Replace with actual template ID
    
    payload = {
        "template_id": template_id,
        "base_data": TEST_FORM_DATA,
        "submitter_email": "provider@example.com",
        "submitter_name": "Dr. Jane Smith",
        "manufacturer_name": "BioWound",
        "metadata": {
            "test": True,
            "workflow": "end_to_end_test"
        }
    }
    
    try:
        print_status("Testing end-to-end workflow...", "info")
        response = await client.post(
            f"{base_url}/api/v1/docuseal/enhance-and-submit",
            json=payload,
            timeout=60.0
        )
        
        if response.status_code == 200:
            data = response.json()
            print_status("End-to-end workflow successful", "success")
            
            # Template info
            template_info = data.get('template_info', {})
            print("\n  Template Information:")
            print(f"    - Template ID: {template_info.get('template_id')}")
            print(f"    - Total fields: {template_info.get('total_fields')}")
            
            # Enhancement results
            enhancement = data.get('enhancement_result', {})
            print("\n  AI Enhancement Results:")
            print(f"    - Confidence: {enhancement.get('confidence', 0):.2%}")
            print(f"    - Method: {enhancement.get('method')}")
            print(f"    - Fields enhanced: {enhancement.get('fields_enhanced')}")
            
            # Submission results
            submission = data.get('submission_result', {})
            print("\n  DocuSeal Submission:")
            print(f"    - Submission ID: {submission.get('submission_id')}")
            print(f"    - Status: {submission.get('status')}")
            print(f"    - Form URL: {submission.get('form_url')}")
            print(f"    - Fields submitted: {submission.get('fields_submitted')}")
            
            return True
        else:
            print_status(f"Workflow failed: {response.status_code}", "error")
            print(f"  Response: {response.text}")
            return False
            
    except Exception as e:
        print_status(f"Workflow error: {str(e)}", "error")
        return False

async def main():
    """Main test function"""
    base_url = "http://localhost:8081"
    
    print("\n" + "="*70)
    print("MEDICAL AI SERVICE & DOCUSEAL INTEGRATION TEST")
    print("="*70)
    print(f"Testing service at: {base_url}")
    print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*70 + "\n")
    
    # Note about template ID
    print_status("NOTE: Update the template_id variable with a real DocuSeal template ID", "warning")
    print("")
    
    async with httpx.AsyncClient() as client:
        # Test 1: Health check
        print_status("Test 1: Health Check", "info")
        health_ok = await test_health_check(client, base_url)
        print("")
        
        if not health_ok:
            print_status("Service is not healthy. Please check if the service is running.", "error")
            print_status("Try running: cd scripts && python medical_ai_service.py", "info")
            return
        
        # Test 2: DocuSeal connectivity
        print_status("Test 2: DocuSeal Connection", "info")
        docuseal_ok = await test_docuseal_connection(client, base_url)
        print("")
        
        # Test 3: AI field enhancement
        print_status("Test 3: AI Field Enhancement", "info")
        enhancement_result = await test_field_enhancement(client, base_url, TEST_FORM_DATA)
        print("")
        
        # Test 4: DocuSeal submission (optional - requires real template ID)
        if False:  # Set to True and update template_id to test
            print_status("Test 4: DocuSeal Submission", "info")
            submission_ok = await test_docuseal_submission(client, base_url, enhancement_result)
            print("")
        
        # Test 5: End-to-end workflow (optional - requires real template ID)
        if False:  # Set to True and update template_id to test
            print_status("Test 5: End-to-End Workflow", "info")
            workflow_ok = await test_end_to_end_workflow(client, base_url)
            print("")
        
        # Summary
        print("\n" + "="*70)
        print("TEST SUMMARY")
        print("="*70)
        
        if health_ok and docuseal_ok and enhancement_result:
            print_status("Core tests passed! The AI service is working correctly.", "success")
        else:
            print_status("Some tests failed. Please check the logs above.", "error")
        
        print("\nNext steps:")
        print("1. Update template_id with a real DocuSeal template ID")
        print("2. Set the test flags to True to run submission tests")
        print("3. Check Laravel integration at /quick-requests/docuseal/generate-submission-slug")
        print("4. Monitor logs: tail -f storage/logs/laravel.log")

if __name__ == "__main__":
    asyncio.run(main())
