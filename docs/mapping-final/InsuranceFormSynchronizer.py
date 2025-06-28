import json
import pandas as pd
from typing import Dict, List, Any, Optional
from dataclasses import dataclass, field
import re
from datetime import datetime
import logging

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@dataclass
class FieldMapping:
    """Represents a mapping between canonical field and form-specific fields"""
    canonical_name: str
    description: str
    data_type: str
    required: bool
    form_mappings: Dict[str, Optional[str]]
    validation_rules: List[str] = field(default_factory=list)

@dataclass
class FormData:
    """Represents data extracted from a single form"""
    form_id: str
    raw_data: Dict[str, Any]
    processed_data: Dict[str, Any] = field(default_factory=dict)
    validation_errors: List[str] = field(default_factory=list)

class InsuranceFormSynchronizer:
    """Synchronizes metadata across multiple insurance verification forms"""
    
    def __init__(self, mapping_config_path: str):
        """
        Initialize the synchronizer with a mapping configuration
        
        Args:
            mapping_config_path: Path to JSON file containing field mappings
        """
        with open(mapping_config_path, 'r') as f:
            self.config = json.load(f)
        
        self.field_mappings = self._parse_field_mappings()
        self.transformation_rules = self.config.get('transformationRules', {})
        
    def _parse_field_mappings(self) -> Dict[str, FieldMapping]:
        """Parse the configuration into FieldMapping objects"""
        mappings = {}
        
        for section, section_data in self.config['standardFieldMappings'].items():
            # Handle nested structure for insurance information
            if section == 'insuranceInformation':
                for insurance_type, insurance_data in section_data.items():
                    for field_name, field_data in insurance_data['canonicalFields'].items():
                        canonical_name = f"{section}.{insurance_type}.{field_name}"
                        mappings[canonical_name] = FieldMapping(
                            canonical_name=canonical_name,
                            description=field_data['description'],
                            data_type=field_data['dataType'],
                            required=field_data.get('required', False),
                            form_mappings=field_data['formMappings']
                        )
            else:
                for field_name, field_data in section_data['canonicalFields'].items():
                    canonical_name = f"{section}.{field_name}"
                    mappings[canonical_name] = FieldMapping(
                        canonical_name=canonical_name,
                        description=field_data['description'],
                        data_type=field_data['dataType'],
                        required=field_data.get('required', False),
                        form_mappings=field_data['formMappings']
                    )
        
        return mappings
    
    def extract_form_data(self, form_id: str, raw_form_data: Dict[str, Any]) -> FormData:
        """
        Extract data from a form using the configured mappings
        
        Args:
            form_id: Identifier for the form (e.g., 'form1_ACZ')
            raw_form_data: Dictionary of field names to values from the form
            
        Returns:
            FormData object with processed data
        """
        form_data = FormData(form_id=form_id, raw_data=raw_form_data)
        
        # Process each canonical field
        for canonical_name, field_mapping in self.field_mappings.items():
            form_field_name = field_mapping.form_mappings.get(form_id)
            
            if form_field_name and form_field_name in raw_form_data:
                raw_value = raw_form_data[form_field_name]
                
                # Apply transformations
                processed_value = self._transform_value(
                    raw_value, 
                    field_mapping.data_type,
                    canonical_name
                )
                
                # Validate the value
                if self._validate_value(processed_value, field_mapping):
                    form_data.processed_data[canonical_name] = processed_value
                else:
                    form_data.validation_errors.append(
                        f"Invalid value for {canonical_name}: {processed_value}"
                    )
            elif field_mapping.required:
                form_data.validation_errors.append(
                    f"Required field {canonical_name} not found in form"
                )
        
        return form_data
    
    def _transform_value(self, value: Any, data_type: str, field_name: str) -> Any:
        """Apply transformation rules to standardize values"""
        if value is None:
            return None
        
        # Apply specific transformations based on field name
        if 'placeOfService' in field_name:
            pos_mappings = self.transformation_rules.get('placeOfServiceCodes', {}).get('mappings', {})
            for key, code in pos_mappings.items():
                if key in str(value):
                    return code
        
        # Apply data type transformations
        if data_type == 'boolean':
            bool_mappings = self.transformation_rules.get('booleanMapping', {}).get('mappings', {})
            str_value = str(value).strip()
            return bool_mappings.get(str_value, value)
        
        elif data_type == 'date':
            # Try to parse various date formats
            if isinstance(value, str):
                for fmt in ['%m/%d/%Y', '%Y-%m-%d', '%m-%d-%Y']:
                    try:
                        return datetime.strptime(value, fmt).strftime('%Y-%m-%d')
                    except ValueError:
                        continue
        
        elif data_type == 'string':
            # Clean and standardize string values
            return str(value).strip()
        
        return value
    
    def _validate_value(self, value: Any, field_mapping: FieldMapping) -> bool:
        """Validate a value according to field rules"""
        if field_mapping.required and (value is None or value == ''):
            return False
        
        # Add specific validation rules based on field name
        if 'NPI' in field_mapping.canonical_name and value:
            # NPI should be 10 digits
            return bool(re.match(r'^\d{10}$', str(value).replace('-', '')))
        
        if 'taxId' in field_mapping.canonical_name and value:
            # Tax ID format: XX-XXXXXXX
            return bool(re.match(r'^\d{2}-?\d{7}$', str(value)))
        
        if 'Phone' in field_mapping.canonical_name and value:
            # Phone number validation
            digits = re.sub(r'\D', '', str(value))
            return len(digits) == 10
        
        return True
    
    def synchronize_forms(self, forms_data: List[Dict[str, Any]]) -> pd.DataFrame:
        """
        Synchronize data from multiple forms into a unified structure
        
        Args:
            forms_data: List of dictionaries, each containing 'form_id' and 'data'
            
        Returns:
            DataFrame with synchronized data
        """
        all_form_data = []
        
        for form_info in forms_data:
            form_id = form_info['form_id']
            raw_data = form_info['data']
            
            logger.info(f"Processing form: {form_id}")
            
            form_data = self.extract_form_data(form_id, raw_data)
            
            if form_data.validation_errors:
                logger.warning(f"Validation errors for {form_id}: {form_data.validation_errors}")
            
            # Add form_id to the processed data
            form_data.processed_data['_form_id'] = form_id
            form_data.processed_data['_validation_errors'] = form_data.validation_errors
            
            all_form_data.append(form_data.processed_data)
        
        # Create DataFrame with all canonical fields as columns
        df = pd.DataFrame(all_form_data)
        
        # Reorder columns to put metadata first
        meta_cols = ['_form_id', '_validation_errors']
        other_cols = [col for col in df.columns if col not in meta_cols]
        df = df[meta_cols + sorted(other_cols)]
        
        return df
    
    def generate_unified_record(self, forms_data: List[Dict[str, Any]], 
                               conflict_strategy: str = 'most_recent') -> Dict[str, Any]:
        """
        Generate a single unified record from multiple forms
        
        Args:
            forms_data: List of form data dictionaries
            conflict_strategy: How to handle conflicts ('most_recent', 'most_complete', 'manual')
            
        Returns:
            Dictionary with unified data
        """
        df = self.synchronize_forms(forms_data)
        
        # Remove metadata columns for unification
        data_df = df.drop(columns=['_form_id', '_validation_errors'], errors='ignore')
        
        unified = {}
        
        for col in data_df.columns:
            values = data_df[col].dropna()
            
            if len(values) == 0:
                unified[col] = None
            elif len(values) == 1:
                unified[col] = values.iloc[0]
            else:
                # Handle conflicts based on strategy
                if conflict_strategy == 'most_recent':
                    # Assume last form is most recent
                    unified[col] = values.iloc[-1]
                elif conflict_strategy == 'most_complete':
                    # Use the longest/most detailed value
                    unified[col] = max(values, key=lambda x: len(str(x)))
                else:
                    # Flag for manual review
                    unified[col] = {
                        'conflict': True,
                        'values': list(values.unique())
                    }
        
        return unified
    
    def export_mapping_report(self, output_path: str):
        """Export a human-readable mapping report"""
        report_lines = ["Insurance Form Field Mapping Report", "=" * 50, ""]
        
        for section in self.config['standardFieldMappings']:
            report_lines.append(f"\n{section.upper()}")
            report_lines.append("-" * len(section))
            
            section_data = self.config['standardFieldMappings'][section]
            
            # Handle nested insurance information
            if section == 'insuranceInformation':
                for insurance_type in section_data:
                    report_lines.append(f"\n  {insurance_type}:")
                    for field_name, field_data in section_data[insurance_type]['canonicalFields'].items():
                        report_lines.append(f"\n    {field_name}:")
                        report_lines.append(f"      Description: {field_data['description']}")
                        report_lines.append(f"      Required: {field_data.get('required', False)}")
                        report_lines.append("      Form Mappings:")
                        for form_id, form_field in field_data['formMappings'].items():
                            if form_field:
                                report_lines.append(f"        {form_id}: {form_field}")
            else:
                for field_name, field_data in section_data['canonicalFields'].items():
                    report_lines.append(f"\n  {field_name}:")
                    report_lines.append(f"    Description: {field_data['description']}")
                    report_lines.append(f"    Required: {field_data.get('required', False)}")
                    report_lines.append("    Form Mappings:")
                    for form_id, form_field in field_data['formMappings'].items():
                        if form_field:
                            report_lines.append(f"      {form_id}: {form_field}")
        
        with open(output_path, 'w') as f:
            f.write('\n'.join(report_lines))


# Example usage
if __name__ == "__main__":
    # Create synchronizer with the mapping configuration
    synchronizer = InsuranceFormSynchronizer('insurance_form_mappings.json')
    
    # Example form data (you would extract this from your actual forms)
    sample_forms = [
        {
            'form_id': 'form1_ACZ',
            'data': {
                'PHYSICIAN NAME': 'Dr. John Smith',
                'NPI': '1234567890',
                'TAX ID': '12-3456789',
                'PATIENT NAME': 'Jane Doe',
                'PATIENT DOB': '01/15/1970',
                'INSURANCE NAME': 'Blue Cross Blue Shield',
                'POLICY NUMBER': 'BC123456',
                'PHYSICIAN OFFICE (POS 11)': True,
                'IS THE PATIENT CURRENTLY IN HOSPICE?': 'NO'
            }
        },
        {
            'form_id': 'form2_IVR',
            'data': {
                'Physician Name': 'Dr. John Smith',
                'Physician NPI': '1234567890',
                'Patient Name': 'Jane Doe',
                'Patient DOB': '01/15/1970',
                'Primary Insurance': 'Blue Cross Blue Shield',
                'Member ID': 'BC123456',
                'Place of Service': '(11) Office'
            }
        },
        {
            'form_id': 'form6_ImbedBio',
            'data': {
                'Name': 'Dr. John Smith',
                'NPI': '1234567890',
                'Tax ID': '12-3456789',
                'Practice Name': 'Smith Medical Practice',
                'Facility Name': 'Regional Medical Center',
                'Patient Name': 'Jane Doe',
                'Date of Birth': '01/15/1970',
                'Primary': 'Blue Cross Blue Shield',
                'Does patient reside in nursing home?': 'No'
            }
        },
        {
            'form_id': 'form7_ExtremityCare_FT',
            'data': {
                '*Provider Name': 'Dr. John Smith',
                '*Provider ID #\'s NPI': '1234567890',
                'Tax ID#': '12-3456789',
                '*Facility Name': 'Regional Medical Center',
                '*Patient Name': 'Jane Doe',
                '*DOB': '01/15/1970',
                'Primary Insurance': 'Blue Cross Blue Shield',
                'Policy Number': 'BC123456',
                '*Is this patient currently in a skilled nursing facility or nursing home?': 'q No'
            }
        }
    ]
    
    # Synchronize the forms
    synchronized_df = synchronizer.synchronize_forms(sample_forms)
    print("Synchronized Data:")
    print(synchronized_df.to_string())
    
    # Generate unified record
    unified_data = synchronizer.generate_unified_record(sample_forms)
    print("\nUnified Record:")
    print(json.dumps(unified_data, indent=2))
    
    # Export mapping report
    synchronizer.export_mapping_report('form_mapping_report.txt')
    print("\nMapping report exported to form_mapping_report.txt")