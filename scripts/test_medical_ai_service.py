import unittest
import json
from unittest.mock import patch, MagicMock

# Assume medical_ai_service is in the same directory or in python path
import medical_ai_service

class TestMedicalAIService(unittest.TestCase):

    def setUp(self):
        """Set up test data for all test cases."""
        self.manufacturer_context = {
            'name': 'Test Manufacturer',
            'field_mappings': {
                'patient_name': {'source': 'patient.name'}
            }
        }
        self.template_structure = {
            'template_fields': {
                'field_names': ['patient_name', 'patient_dob', 'visit_date', 'is_urgent'],
                'required_fields': ['patient_name', 'visit_date'],
                'field_types': {
                    'patient_name': 'text',
                    'patient_dob': 'date',
                    'visit_date': 'date',
                    'is_urgent': 'checkbox'
                }
            }
        }
        self.base_data = {
            'first_name': 'John',
            'last_name': 'Doe'
        }
        self.fhir_context = {
            'patient': {
                'birthDate': '1990-01-15'
            }
        }
        self.episode_data = {
            'visit_date': '2023-10-26'
        }

    def test_build_system_prompt(self):
        """Test that the system prompt is built correctly and contains all necessary sections."""
        prompt = medical_ai_service.build_system_prompt(self.manufacturer_context, self.template_structure)
        
        self.assertIn('You are a highly intelligent AI assistant', prompt)
        self.assertIn('Test Manufacturer', prompt)
        self.assertIn('patient_name', prompt)
        self.assertIn('patient_dob', prompt)
        self.assertIn('is_urgent', prompt)
        self.assertIn('## Rules', prompt)
        self.assertIn('## Output Format', prompt)

    def test_build_user_prompt(self):
        """Test that the user prompt correctly formats the data without instructions."""
        prompt = medical_ai_service.build_user_prompt(self.base_data, self.fhir_context, self.episode_data)

        self.assertIn('### 1. Base Data', prompt)
        self.assertIn(json.dumps(self.base_data, indent=2), prompt)
        self.assertIn('### 2. FHIR Context', prompt)
        self.assertIn(json.dumps(self.fhir_context, indent=2), prompt)
        self.assertIn('### 3. Episode Data', prompt)
        self.assertIn(json.dumps(self.episode_data, indent=2), prompt)
        self.assertNotIn('SPECIFIC MAPPING INSTRUCTIONS', prompt)

    def test_parse_ai_response_success(self):
        """Test successful parsing of a clean AI response."""
        ai_content = f'''Here is the JSON output:\n\n```json
{{
    "enhanced_fields": {{
        "patient_name": "John Doe",
        "visit_date": "10/26/2023"
    }},
    "confidence": 0.95
}}
```'''
        
        validated_fields, confidence, _ = medical_ai_service.parse_ai_response(
            ai_content, self.base_data, self.template_structure
        )
        
        self.assertEqual(confidence, 0.95)
        self.assertIn('patient_name', validated_fields)
        self.assertEqual(validated_fields['patient_name'], 'John Doe')

    def test_parse_ai_response_failure_and_fallback(self):
        """Test that parsing failure triggers the fallback mechanism."""
        ai_content = "Sorry, I could not process the request."
        
        with patch('medical_ai_service.perform_basic_enhancement') as mock_fallback:
            mock_fallback.return_value = {'patient_name': 'John Doe'}
            
            validated_fields, confidence, _ = medical_ai_service.parse_ai_response(
                ai_content, self.base_data, self.template_structure
            )
            
            mock_fallback.assert_called_once_with(self.base_data, None)
            self.assertEqual(confidence, 0.3)
            self.assertEqual(validated_fields, {'patient_name': 'John Doe'})

    def test_validate_enhanced_fields_happy_path(self):
        """Test validation with all valid fields."""
        enhanced_fields = {
            'patient_name': 'Jane Doe',
            'visit_date': '2023-11-01',
            'patient_dob': '1992-05-21',
            'is_urgent': 'true'
        }
        
        validated = medical_ai_service.validate_enhanced_fields(
            enhanced_fields, self.base_data, self.template_structure
        )
        
        self.assertEqual(len(validated), 4)
        self.assertEqual(validated['visit_date'], '11/01/2023') # Check date formatting
        self.assertEqual(validated['is_urgent'], True) # Check boolean conversion

    def test_validate_enhanced_fields_with_invalid_data(self):
        """Test validation with invalid field names and data formats."""
        enhanced_fields = {
            'patient_name': 'Jane Doe',
            'visit_date': 'invalid-date-format',
            'patient_dob': '1992/05/21',
            'is_urgent': 'maybe',
            'extra_field_not_in_template': 'some value'
        }
        
        validated = medical_ai_service.validate_enhanced_fields(
            enhanced_fields, self.base_data, self.template_structure
        )
        
        self.assertIn('patient_name', validated)
        self.assertIn('patient_dob', validated) # yyyy/mm/dd is a valid format
        self.assertNotIn('visit_date', validated) # Invalid date format
        self.assertNotIn('is_urgent', validated) # Invalid boolean value
        self.assertNotIn('extra_field_not_in_template', validated)
        self.assertEqual(len(validated), 2)

if __name__ == '__main__':
    unittest.main()
