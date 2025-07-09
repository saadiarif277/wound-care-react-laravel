#!/usr/bin/env python3
"""
Debug script for testing the AI field mapping service end-to-end
"""

import asyncio
import httpx
import json
from datetime import datetime
import sys
from typing import Dict, Any

# ANSI color codes for output
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

async def test_health_endpoint(client: httpx.AsyncClient, base_url: str) -> bool:
    """Test the health endpoint"""
    try:
        response = await client.get(f"{base_url}/health")
        if response.status_code == 200:
            data = response.json()
            print_status(f"Health check passed: {json.dumps(data, indent=2)}", "success")
            return True
        else:
            print_status(f"Health check failed with status {response.status_code}", "error")
            return False
    except Exception as e:
        print_status(f"Health check error: {str(e)}", "error")
        return False

async def test_enhance_mapping(client: httpx.AsyncClient, base_url: str) -> bool:
    """Test the enhance mapping endpoint with sample data"""
    
    # Sample test payload mimicking real FHIR data
    test_payload = {
        "context": {
            "base_data": {
            "patient_name": "John Doe",
            "patient_first_name": "John",
            "patient_last_name": "Doe",
            "dob": "1970-01-01",
            "patient_dob": "01/01/1970",
            "phone": "555-0123",
            "patient_phone": "555-0123",
            "insurance": "Medicare",
            "insurance_name": "Medicare",
            "member_id": "ABC123456",
            "group_number": "GRP001",
            "diagnosis_1": "L89.152",
            "diagnosis_2": "E11.622",
            "wound_location": "sacral",
            "wound_size": "3x4cm",
            "provider_name": "Dr. Smith",
            "provider_npi": "1234567890",
            "facility_name": "General Hospital",
            "facility_address": "123 Main St, City, ST 12345"
        },
        "fhir_context": {
            "patient": {
                "resourceType": "Patient",
                "id": "patient-123",
                "name": [{
                    "family": "Doe",
                    "given": ["John"]
                }],
                "birthDate": "1970-01-01",
                "telecom": [{
                    "system": "phone",
                    "value": "555-0123"
                }]
            },
            "coverage": {
                "resourceType": "Coverage",
                "id": "coverage-123",
                "payor": [{
                    "display": "Medicare"
                }],
                "subscriberId": "ABC123456",
                "class": [{
                    "type": {
                        "text": "group"
                    },
                    "value": "GRP001"
                }]
            },
            "conditions": [
                {
                    "resourceType": "Condition",
                    "code": {
                        "coding": [{
                            "system": "http://hl7.org/fhir/sid/icd-10-cm",
                            "code": "L89.152",
                            "display": "Pressure ulcer of sacral region, stage 2"
                        }]
                    }
                },
                {
                    "resourceType": "Condition",
                    "code": {
                        "coding": [{
                            "system": "http://hl7.org/fhir/sid/icd-10-cm",
                            "code": "E11.622",
                            "display": "Type 2 diabetes mellitus with other skin ulcer"
                        }]
                    }
                }
            ]
        },
        "manufacturer_config": {
            "name": "BioWound",
            "template_id": "docuseal_template_biowound_ivr",
            "field_mappings": {
                "patient_full_name": ["patient_name", "patient_full_name"],
                "date_of_birth": ["dob", "patient_dob", "birthDate"],
                "primary_insurance": ["insurance", "insurance_name", "primary_insurance_name"],
                "member_number": ["member_id", "insurance_member_id", "subscriber_id"],
                "diagnosis_codes": ["diagnosis_1", "diagnosis_2", "icd10_codes"],
                "wound_description": ["wound_location", "wound_site", "wound_area"],
                "wound_measurements": ["wound_size", "wound_dimensions"]
            }
        }
        },
        "optimization_level": "high"
    }
    
    try:
        print_status("Testing enhance mapping endpoint...", "info")
        print_status(f"Sending {len(test_payload['context']['base_data'])} base fields", "info")
        
        response = await client.post(
            f"{base_url}/api/v1/enhance-mapping",
            json=test_payload,
            timeout=30.0
        )
        
        if response.status_code == 200:
            data = response.json()
            print_status(f"Mapping successful!", "success")
            
            # Display results
            print("\n" + "="*50)
            print("ENHANCED MAPPING RESULTS")
            print("="*50)
            
            enhanced_data = data.get('enhanced_fields', {})
            print(f"\nOriginal fields: {len(test_payload['context']['base_data'])}")
            print(f"Enhanced fields: {len(enhanced_data)}")
            print(f"Confidence score: {data.get('confidence', 'N/A')}")
            print(f"AI model: {data.get('model_used', 'N/A')}")
            
            # Show sample of enhanced fields
            print("\nSample enhanced fields:")
            for i, (key, value) in enumerate(enhanced_data.items()):
                if i < 10:  # Show first 10 fields
                    print(f"  - {key}: {value}")
                else:
                    print(f"  ... and {len(enhanced_data) - 10} more fields")
                    break
            
            # Check for critical fields
            critical_fields = [
                'patient_full_name', 'date_of_birth', 'primary_insurance',
                'member_number', 'diagnosis_codes', 'wound_description'
            ]
            
            print("\nCritical field validation:")
            for field in critical_fields:
                if field in enhanced_data:
                    print_status(f"   {field}: {enhanced_data[field]}", "success")
                else:
                    print_status(f"   {field}: MISSING", "warning")
            
            return True
            
        else:
            print_status(f"Mapping failed with status {response.status_code}", "error")
            print_status(f"Response: {response.text}", "error")
            return False
            
    except Exception as e:
        print_status(f"Mapping error: {str(e)}", "error")
        return False

async def test_manufacturers_endpoint(client: httpx.AsyncClient, base_url: str) -> bool:
    """Test the manufacturers endpoint"""
    try:
        response = await client.get(f"{base_url}/manufacturers")
        if response.status_code == 200:
            data = response.json()
            print_status(f"Manufacturers endpoint working. Found {len(data.get('manufacturers', []))} manufacturers", "success")
            return True
        else:
            print_status(f"Manufacturers endpoint failed with status {response.status_code}", "warning")
            return False
    except Exception as e:
        print_status(f"Manufacturers endpoint error: {str(e)}", "warning")
        return False

async def test_terminology_stats(client: httpx.AsyncClient, base_url: str) -> bool:
    """Test the terminology stats endpoint"""
    try:
        response = await client.get(f"{base_url}/terminology-stats")
        if response.status_code == 200:
            data = response.json()
            print_status("Terminology stats endpoint working", "success")
            return True
        else:
            print_status(f"Terminology stats endpoint failed with status {response.status_code}", "warning")
            return False
    except Exception as e:
        print_status(f"Terminology stats endpoint error: {str(e)}", "warning")
        return False

async def main():
    """Main test function"""
    base_url = "http://localhost:8081"
    
    print("\n" + "="*60)
    print("MEDICAL AI SERVICE DEBUG SCRIPT")
    print("="*60)
    print(f"Testing service at: {base_url}")
    print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*60 + "\n")
    
    async with httpx.AsyncClient() as client:
        # Test health endpoint
        print_status("Testing health endpoint...", "info")
        health_ok = await test_health_endpoint(client, base_url)
        
        if not health_ok:
            print_status("Service is not healthy. Please check if the service is running.", "error")
            print_status("Try running: cd scripts && python medical_ai_service.py", "info")
            return
        
        print("\n" + "-"*40 + "\n")
        
        # Test enhance mapping
        mapping_ok = await test_enhance_mapping(client, base_url)
        
        print("\n" + "-"*40 + "\n")
        
        # Test other endpoints
        print_status("Testing additional endpoints...", "info")
        await test_manufacturers_endpoint(client, base_url)
        await test_terminology_stats(client, base_url)
        
        # Summary
        print("\n" + "="*60)
        print("TEST SUMMARY")
        print("="*60)
        
        if health_ok and mapping_ok:
            print_status("All critical tests passed! The AI service is working correctly.", "success")
        else:
            print_status("Some tests failed. Please check the logs above.", "error")
        
        print("\nNext steps:")
        print("1. If service is not running: cd scripts && python medical_ai_service.py")
        print("2. Check Laravel logs: tail -f storage/logs/laravel.log")
        print("3. Check if Azure OpenAI credentials are set in .env")
        print("4. Verify port 8080 is not blocked by firewall")

if __name__ == "__main__":
    asyncio.run(main())