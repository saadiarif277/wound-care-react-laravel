#!/usr/bin/env python3
"""
Test script for ML Field Mapping System
Validates field extraction and mapping capabilities
"""

import sys
import os
import json
import time
from datetime import datetime

# Add the current directory to Python path
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

try:
    from ml_field_mapping import FieldMappingMLSystem, initialize_ml_system
    print("✅ Successfully imported ML system")
except ImportError as e:
    print(f"❌ Failed to import ML system: {e}")
    print("Please install required dependencies:")
    print("pip3 install -r requirements.txt")
    sys.exit(1)

def test_ml_system():
    """Test the ML field mapping system"""
    print("\n🧠 Testing ML Field Mapping System")
    print("=" * 50)
    
    # Initialize ML system
    print("\n1. Initializing ML System...")
    try:
        ml_system = initialize_ml_system()
        print("✅ ML system initialized successfully")
    except Exception as e:
        print(f"❌ Failed to initialize ML system: {e}")
        return False
    
    # Test field mapping predictions
    print("\n2. Testing Field Mapping Predictions...")
    test_cases = [
        {
            "source_field": "patient_full_name",
            "manufacturer": "CELULARITY", 
            "document_type": "IVR",
            "expected": "patient_name"
        },
        {
            "source_field": "date_of_birth", 
            "manufacturer": "ADVANCED SOLUTION",
            "document_type": "IVR",
            "expected": "patient_dob"
        },
        {
            "source_field": "insurance_member_id",
            "manufacturer": "BIOWOUND SOLUTIONS", 
            "document_type": "IVR",
            "expected": "insurance_id"
        },
        {
            "source_field": "physician_name",
            "manufacturer": "LEGACY MEDICAL CONSULTANTS",
            "document_type": "IVR", 
            "expected": "provider_name"
        },
        {
            "source_field": "wound_location",
            "manufacturer": "CENTURION THERAPEUTICS",
            "document_type": "IVR",
            "expected": "anatomical_location"
        }
    ]
    
    successful_predictions = 0
    
    for i, test_case in enumerate(test_cases, 1):
        print(f"\n   Test {i}: {test_case['source_field']} ({test_case['manufacturer']})")
        
        try:
            prediction = ml_system.predict_field_mapping(
                source_field=test_case["source_field"],
                manufacturer=test_case["manufacturer"],
                document_type=test_case["document_type"]
            )
            
            print(f"      ✅ Predicted: {prediction.predicted_field}")
            print(f"      📊 Confidence: {prediction.confidence:.2f}")
            print(f"      🤖 Model: {prediction.model_used}")
            
            if prediction.alternative_suggestions:
                print(f"      🔄 Alternatives: {prediction.alternative_suggestions[:2]}")
            
            successful_predictions += 1
            
        except Exception as e:
            print(f"      ❌ Prediction failed: {e}")
    
    print(f"\n   Results: {successful_predictions}/{len(test_cases)} predictions successful")
    
    # Test recording mapping results
    print("\n3. Testing Mapping Result Recording...")
    try:
        ml_system.record_mapping_result(
            source_field="patient_name_test",
            target_field="patient_full_name",
            manufacturer="TEST_MANUFACTURER",
            document_type="IVR",
            confidence=0.95,
            success=True,
            mapping_method="test",
            user_feedback="Test feedback"
        )
        print("✅ Successfully recorded mapping result")
    except Exception as e:
        print(f"❌ Failed to record mapping result: {e}")
    
    # Test analytics
    print("\n4. Testing Analytics...")
    try:
        analytics = ml_system.get_analytics()
        print("✅ Analytics retrieved successfully:")
        print(f"   📊 Total mappings: {analytics.get('total_mappings', 0)}")
        print(f"   📈 Success rate: {analytics.get('success_rate', 0):.1%}")
        print(f"   🎯 Average confidence: {analytics.get('avg_confidence', 0):.2f}")
        print(f"   🏭 Top manufacturers: {len(analytics.get('top_manufacturers', []))}")
    except Exception as e:
        print(f"❌ Failed to get analytics: {e}")
    
    # Test model training
    print("\n5. Testing Model Training...")
    try:
        training_results = ml_system.train_models(force=True)
        print("✅ Model training completed successfully:")
        for model_name, metrics in training_results.items():
            print(f"   🤖 {model_name}: accuracy={metrics.get('accuracy', 0):.2f}")
    except Exception as e:
        print(f"❌ Model training failed: {e}")
    
    print("\n🎉 ML System Test Complete!")
    return True

def test_field_extraction_samples():
    """Test field extraction with sample IVR forms"""
    print("\n📋 Testing Field Extraction Samples")
    print("=" * 50)
    
    # Sample IVR form fields from different manufacturers
    sample_forms = {
        "Celularity": [
            "patient_full_name", "patient_first_name", "patient_last_name",
            "date_of_birth", "social_security_number", "insurance_company",
            "policy_number", "member_id", "group_number", "physician_name",
            "physician_npi", "diagnosis_code", "wound_description",
            "wound_location", "treatment_start_date"
        ],
        "Advanced Solutions": [
            "patient_name", "patient_dob", "patient_ssn", "insurance_carrier",
            "insurance_id", "insurance_group", "provider_name", "provider_npi",
            "icd_code", "clinical_notes", "anatomical_site", "service_date"
        ],
        "BioWound Solutions": [
            "full_name", "birth_date", "ssn", "primary_insurance",
            "member_number", "group_id", "ordering_physician", "npi_number",
            "diagnosis", "wound_assessment", "body_location", "dos"
        ]
    }
    
    # Initialize ML system
    try:
        ml_system = initialize_ml_system()
    except Exception as e:
        print(f"❌ Failed to initialize ML system: {e}")
        return False
    
    # Test field mapping for each manufacturer
    for manufacturer, fields in sample_forms.items():
        print(f"\n🏭 Testing {manufacturer} IVR Form:")
        print(f"   Fields to map: {len(fields)}")
        
        mapped_fields = {}
        confidence_scores = []
        
        for field in fields:
            try:
                prediction = ml_system.predict_field_mapping(
                    source_field=field,
                    manufacturer=manufacturer.upper(),
                    document_type="IVR"
                )
                
                mapped_fields[field] = {
                    "target": prediction.predicted_field,
                    "confidence": prediction.confidence,
                    "model": prediction.model_used
                }
                confidence_scores.append(prediction.confidence)
                
            except Exception as e:
                print(f"   ❌ Failed to map {field}: {e}")
                mapped_fields[field] = {"target": "unmapped", "confidence": 0.0}
        
        # Summary for this manufacturer
        avg_confidence = sum(confidence_scores) / len(confidence_scores) if confidence_scores else 0
        successful_mappings = len([f for f in mapped_fields.values() if f["confidence"] > 0.5])
        
        print(f"   ✅ Successfully mapped: {successful_mappings}/{len(fields)} fields")
        print(f"   📊 Average confidence: {avg_confidence:.2f}")
        print(f"   🎯 High confidence (>0.8): {len([f for f in mapped_fields.values() if f['confidence'] > 0.8])}")
        
        # Show some examples
        print(f"   📝 Sample mappings:")
        for field, mapping in list(mapped_fields.items())[:3]:
            print(f"      {field} → {mapping['target']} (confidence: {mapping['confidence']:.2f})")
    
    return True

def main():
    """Main test function"""
    print("🚀 ML Field Mapping System Test Suite")
    print("Starting at:", datetime.now().strftime("%Y-%m-%d %H:%M:%S"))
    
    # Run basic ML system tests
    ml_test_success = test_ml_system()
    
    if ml_test_success:
        # Run field extraction tests
        test_field_extraction_samples()
    
    print("\n" + "=" * 50)
    print("🏁 Test Suite Complete!")
    
    if ml_test_success:
        print("✅ ML system is ready for field mapping!")
        print("\nNext steps:")
        print("1. Start ML API server: python3 ml_api_server.py")
        print("2. Test with Laravel integration")
        print("3. Upload IVR forms and test field mapping")
    else:
        print("❌ ML system needs troubleshooting")
        print("Check dependencies and error messages above")

if __name__ == "__main__":
    main() 