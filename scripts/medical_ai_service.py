#!/usr/bin/env python3
"""
Medical AI Service - Python Microservice
Uses Azure OpenAI for intelligent medical terminology validation and field mapping
Can be called from Laravel app as REST API or run standalone
"""

import os
import json
import logging
import asyncio
from datetime import datetime
from typing import Dict, List, Optional, Any
from dataclasses import dataclass, asdict
from enum import Enum
from pathlib import Path
import re # Added for PHP config parsing

import uvicorn
from fastapi import FastAPI, HTTPException, Depends
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, validator
import openai
from openai import AzureOpenAI
import redis
from cachetools import TTLCache
import httpx

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Configuration
class Config:
    AZURE_OPENAI_ENDPOINT = os.getenv('AZURE_OPENAI_ENDPOINT')
    AZURE_OPENAI_API_KEY = os.getenv('AZURE_OPENAI_API_KEY')
    AZURE_OPENAI_DEPLOYMENT = os.getenv('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o')
    AZURE_OPENAI_API_VERSION = os.getenv('AZURE_OPENAI_API_VERSION', '2024-02-15-preview')
    
    REDIS_URL = os.getenv('REDIS_URL', 'redis://localhost:6379')
    CACHE_TTL = int(os.getenv('CACHE_TTL', 3600))  # 1 hour
    
    API_HOST = os.getenv('API_HOST', '0.0.0.0')
    API_PORT = int(os.getenv('API_PORT', 8080))
    
    # Medical terminology sources
    ENABLE_LOCAL_FALLBACK = os.getenv('ENABLE_LOCAL_FALLBACK', 'true').lower() == 'true'

# Data Models
class DocumentType(str, Enum):
    INSURANCE_CARD = "insurance_card"
    CLINICAL_NOTE = "clinical_note"
    WOUND_PHOTO = "wound_photo"
    PRESCRIPTION = "prescription"
    DEMOGRAPHICS = "demographics"
    GENERAL = "general"

class ValidationRequest(BaseModel):
    terms: List[str]
    context: DocumentType = DocumentType.GENERAL
    confidence_threshold: float = 0.7
    include_suggestions: bool = True

class FieldMappingRequest(BaseModel):
    ocr_data: Dict[str, Any]
    document_type: DocumentType
    target_schema: Optional[Dict[str, str]] = None
    manufacturer_name: Optional[str] = None
    include_confidence: bool = True

class TermValidationResult(BaseModel):
    term: str
    is_valid: bool
    confidence: float
    category: Optional[str] = None
    suggested_terms: List[str] = []
    medical_context: Optional[Dict] = None

class ValidationResponse(BaseModel):
    overall_confidence: float
    total_terms: int
    valid_terms: int
    results: List[TermValidationResult]
    processing_method: str
    timestamp: datetime

class FieldMappingResult(BaseModel):
    mapped_fields: Dict[str, Any]
    confidence_scores: Dict[str, float]
    quality_grade: str
    suggestions: List[str]
    processing_notes: List[str]

# Medical terminology dictionaries (comprehensive)
MEDICAL_TERMINOLOGIES = {
    'wound_care': {
        'wound_types': [
            'pressure_ulcer', 'pressure_sore', 'bed_sore', 'decubitus_ulcer',
            'diabetic_foot_ulcer', 'diabetic_wound', 'neuropathic_ulcer',
            'venous_stasis_ulcer', 'venous_leg_ulcer', 'venous_insufficiency_ulcer',
            'arterial_ulcer', 'ischemic_ulcer', 'mixed_ulcer',
            'surgical_wound', 'post_operative_wound', 'incision',
            'traumatic_wound', 'laceration', 'abrasion', 'contusion',
            'dehiscence', 'wound_separation', 'evisceration',
            'abscess', 'cellulitis', 'necrotizing_fasciitis',
            'osteomyelitis', 'bone_infection', 'deep_tissue_infection'
        ],
        'anatomical_locations': [
            'sacrum', 'sacral', 'coccyx', 'coccygeal', 'tailbone',
            'heel', 'calcaneus', 'ankle', 'malleolus', 'lateral_malleolus', 'medial_malleolus',
            'toe', 'digit', 'hallux', 'great_toe', 'lesser_toe',
            'forefoot', 'midfoot', 'hindfoot', 'metatarsal', 'tarsal',
            'plantar', 'dorsal', 'dorsum', 'sole',
            'lateral', 'medial', 'proximal', 'distal',
            'hip', 'trochanter', 'greater_trochanter', 'ischial_tuberosity',
            'elbow', 'olecranon', 'shoulder', 'scapula',
            'leg', 'lower_extremity', 'foot', 'lower_leg', 'calf'
        ],
        'characteristics': [
            'necrotic', 'necrosis', 'black', 'eschar',
            'slough', 'sloughy', 'yellow', 'fibrinous',
            'granulation', 'granulating', 'red', 'beefy_red',
            'epithelialization', 'epithelializing', 'pink',
            'macerated', 'maceration', 'white', 'soggy',
            'undermining', 'tunneling', 'sinus_tract', 'tracking',
            'erythema', 'erythematous', 'inflammation', 'inflamed',
            'induration', 'indurated', 'firm', 'hard',
            'fluctuance', 'fluctuant', 'soft', 'boggy',
            'purulent', 'pus', 'infected', 'suppurative',
            'serous', 'serosanguinous', 'bloody', 'drainage'
        ],
        'measurements': [
            'length', 'width', 'depth', 'area', 'volume',
            'circumference', 'diameter', 'size',
            'cm', 'centimeter', 'centimeters',
            'mm', 'millimeter', 'millimeters',
            'inch', 'inches', 'in'
        ],
        'staging': [
            'stage_1', 'stage_i', 'stage_one',
            'stage_2', 'stage_ii', 'stage_two',
            'stage_3', 'stage_iii', 'stage_three',
            'stage_4', 'stage_iv', 'stage_four',
            'unstageable', 'suspected_deep_tissue_injury',
            'sdti', 'deep_tissue_injury'
        ]
    },
    'insurance': {
        'member_terms': [
            'member_id', 'member_number', 'policy_number', 'policy_id',
            'subscriber_id', 'subscriber_number', 'group_number', 'group_id',
            'plan_number', 'plan_id', 'benefit_plan', 'coverage_id',
            'effective_date', 'start_date', 'termination_date', 'end_date',
            'dependent', 'subscriber', 'beneficiary', 'covered_person',
            'primary_insured', 'policy_holder', 'enrollee'
        ],
        'plan_types': [
            'hmo', 'health_maintenance_organization',
            'ppo', 'preferred_provider_organization',
            'epo', 'exclusive_provider_organization',
            'pos', 'point_of_service',
            'medicare', 'medicare_advantage', 'medicare_supplement',
            'medicaid', 'medicaid_managed_care',
            'tricare', 'military_health_plan',
            'commercial', 'private_insurance',
            'employer_sponsored', 'group_insurance',
            'individual', 'individual_market',
            'cobra', 'continuation_coverage'
        ],
        'financial_terms': [
            'copay', 'copayment', 'co_pay',
            'coinsurance', 'co_insurance',
            'deductible', 'annual_deductible',
            'out_of_pocket_max', 'out_of_pocket_maximum', 'oop_max',
            'premium', 'monthly_premium',
            'allowable_amount', 'allowed_amount',
            'covered_amount', 'benefit_amount',
            'prior_authorization', 'preauthorization', 'pa'
        ]
    },
    'clinical': {
        'vital_signs': [
            'blood_pressure', 'bp', 'systolic', 'diastolic',
            'heart_rate', 'hr', 'pulse', 'beats_per_minute', 'bpm',
            'respiratory_rate', 'rr', 'respirations', 'breaths_per_minute',
            'temperature', 'temp', 'fever', 'hypothermia',
            'oxygen_saturation', 'o2_sat', 'spo2', 'pulse_ox'
        ],
        'conditions': [
            'diabetes', 'diabetes_mellitus', 'dm', 'diabetic',
            'hypertension', 'high_blood_pressure', 'htn',
            'obesity', 'overweight', 'bmi',
            'peripheral_vascular_disease', 'pvd', 'pad',
            'chronic_kidney_disease', 'ckd', 'renal_failure',
            'heart_failure', 'chf', 'congestive_heart_failure',
            'copd', 'chronic_obstructive_pulmonary_disease',
            'cancer', 'malignancy', 'neoplasm', 'tumor',
            'stroke', 'cva', 'cerebrovascular_accident',
            'myocardial_infarction', 'mi', 'heart_attack',
            'sepsis', 'septicemia', 'infection',
            'pneumonia', 'respiratory_infection'
        ],
        'medications': [
            'insulin', 'metformin', 'aspirin', 'warfarin', 'coumadin',
            'lisinopril', 'enalapril', 'ace_inhibitor',
            'atorvastatin', 'simvastatin', 'statin',
            'omeprazole', 'pantoprazole', 'ppi',
            'levothyroxine', 'synthroid', 'thyroid',
            'amlodipine', 'calcium_channel_blocker',
            'antibiotic', 'antimicrobial', 'antifungal'
        ]
    }
}

class FormMappingKnowledgeBase:
    """Knowledge base for form field mappings and processing instructions"""
    
    def __init__(self):
        self.insurance_mappings = {}
        self.order_mappings = {}
        self.form_linkages = {}
        self.processing_instructions = {}
        self.load_knowledge_base()
    
    def load_knowledge_base(self):
        """Load form mappings from JSON files"""
        try:
            base_path = Path(__file__).parent.parent / "docs" / "mapping-final"
            
            # Load insurance form mappings
            insurance_file = base_path / "insurance_form_mappings.json"
            if insurance_file.exists():
                with open(insurance_file, 'r') as f:
                    data = json.load(f)
                    self.insurance_mappings = data.get('standardFieldMappings', {})
                    logger.info(f"Loaded insurance mappings for {len(self.insurance_mappings)} categories")
            
            # Load order form mappings  
            order_file = base_path / "order-form-mappings.json"
            if order_file.exists():
                with open(order_file, 'r') as f:
                    data = json.load(f)
                    self.order_mappings = data.get('orderFormFieldMappings', {}).get('standardFields', {})
                    self.form_linkages = data.get('orderFormFieldMappings', {}).get('formToIVRLinkage', {})
                    self.processing_instructions = data.get('orderFormFieldMappings', {}).get('processingInstructions', {})
                    logger.info(f"Loaded order mappings for {len(self.order_mappings)} categories")
            
        except Exception as e:
            logger.error(f"Failed to load knowledge base: {e}")
            # Continue with empty mappings if files don't exist
    
    def get_form_mapping(self, manufacturer_form_id: str, document_type: str) -> Dict:
        """Get field mappings for a specific manufacturer form"""
        if document_type.lower() == 'insurance':
            return self._get_insurance_mapping(manufacturer_form_id)
        elif document_type.lower() == 'order':
            return self._get_order_mapping(manufacturer_form_id)
        return {}
    
    def _get_insurance_mapping(self, form_id: str) -> Dict:
        """Get insurance form field mappings"""
        mappings = {}
        for category, fields in self.insurance_mappings.items():
            if 'canonicalFields' in fields:
                for field_name, field_info in fields['canonicalFields'].items():
                    if 'formMappings' in field_info and form_id in field_info['formMappings']:
                        form_field = field_info['formMappings'][form_id]
                        if form_field:  # Skip null mappings
                            mappings[field_name] = {
                                'form_field': form_field,
                                'description': field_info.get('description', ''),
                                'dataType': field_info.get('dataType', 'string'),
                                'required': field_info.get('required', False)
                            }
        return mappings
    
    def _get_order_mapping(self, form_id: str) -> Dict:
        """Get order form field mappings"""
        mappings = {}
        for category, fields in self.order_mappings.items():
            if 'canonicalFields' in fields:
                for field_name, field_info in fields['canonicalFields'].items():
                    if 'formMappings' in field_info and form_id in field_info['formMappings']:
                        form_field = field_info['formMappings'][form_id]
                        if form_field:  # Skip null mappings
                            mappings[field_name] = {
                                'form_field': form_field,
                                'description': field_info.get('description', ''),
                                'dataType': field_info.get('dataType', 'string'),
                                'required': field_info.get('required', False)
                            }
        return mappings
    
    def get_available_forms(self, document_type: str) -> List[str]:
        """Get list of available form IDs for a document type"""
        forms = set()
        
        if document_type.lower() == 'insurance':
            for category, fields in self.insurance_mappings.items():
                if 'canonicalFields' in fields:
                    for field_info in fields['canonicalFields'].values():
                        if 'formMappings' in field_info:
                            forms.update(field_info['formMappings'].keys())
        elif document_type.lower() == 'order':
            for category, fields in self.order_mappings.items():
                if 'canonicalFields' in fields:
                    for field_info in fields['canonicalFields'].values():
                        if 'formMappings' in field_info:
                            forms.update(field_info['formMappings'].keys())
        
        return list(forms)
    
    def get_processing_instructions(self, form_id: str) -> Dict:
        """Get processing instructions for a form"""
        instructions = {}
        
        if 'orderSubmissionEmails' in self.processing_instructions:
            if form_id in self.processing_instructions['orderSubmissionEmails']:
                instructions['submission_email'] = self.processing_instructions['orderSubmissionEmails'][form_id]
        
        if 'orderCutoffTimes' in self.processing_instructions:
            if form_id in self.processing_instructions['orderCutoffTimes']:
                instructions['cutoff_time'] = self.processing_instructions['orderCutoffTimes'][form_id]
        
        if 'specialRequirements' in self.processing_instructions:
            for key, requirement in self.processing_instructions['specialRequirements'].items():
                if key.lower() in form_id.lower():
                    instructions['special_requirements'] = requirement
        
        return instructions

class ManufacturerMappingKnowledgeBase:
    """Knowledge base for manufacturer-specific field mappings"""
    
    def __init__(self):
        self.manufacturer_configs = {}
        self.load_manufacturer_configs()
    
    def load_manufacturer_configs(self):
        """Load manufacturer configurations from PHP config files"""
        try:
            config_path = Path(__file__).parent.parent / "config" / "manufacturers"
            
            if not config_path.exists():
                logger.warning(f"Manufacturer config directory not found: {config_path}")
                return
            
            for php_file in config_path.glob("*.php"):
                try:
                    # Parse PHP config files to extract manufacturer data
                    config_data = self._parse_php_config(php_file)
                    if config_data:
                        manufacturer_name = config_data.get('name', php_file.stem)
                        self.manufacturer_configs[manufacturer_name] = config_data
                        logger.info(f"Loaded manufacturer config: {manufacturer_name}")
                except Exception as e:
                    logger.warning(f"Failed to load manufacturer config {php_file}: {e}")
            
            logger.info(f"Loaded {len(self.manufacturer_configs)} manufacturer configurations")
            
        except Exception as e:
            logger.error(f"Failed to load manufacturer configs: {e}")
    
    def _parse_php_config(self, php_file):
        """Parse PHP configuration file to extract manufacturer data"""
        try:
            with open(php_file, 'r') as f:
                content = f.read()
            
            # Basic PHP array parsing - extract key configuration data
            # This is a simplified parser for the specific PHP config format
            config_data = {}
            
            # Extract basic manufacturer info
            if "'name' => '" in content:
                name_match = re.search(r"'name'\s*=>\s*'([^']+)'", content)
                if name_match:
                    config_data['name'] = name_match.group(1)
            
            # Extract template IDs
            if "'docuseal_template_id' => '" in content:
                template_match = re.search(r"'docuseal_template_id'\s*=>\s*'([^']*)'", content)
                if template_match:
                    config_data['docuseal_template_id'] = template_match.group(1)
            
            # Extract field mappings
            config_data['field_mappings'] = self._extract_field_mappings(content)
            config_data['docuseal_field_names'] = self._extract_docuseal_field_names(content)
            
            # Extract other properties
            config_data['signature_required'] = "'signature_required' => true" in content
            config_data['has_order_form'] = "'has_order_form' => true" in content
            
            return config_data
            
        except Exception as e:
            logger.error(f"Error parsing PHP config {php_file}: {e}")
            return None
    
    def _extract_field_mappings(self, content):
        """Extract field mappings from PHP config content"""
        field_mappings = {}
        
        # Find the 'fields' array section
        fields_match = re.search(r"'fields'\s*=>\s*\[(.*?)\](?=\s*;)", content, re.DOTALL)
        if fields_match:
            fields_content = fields_match.group(1)
            
            # Extract individual field configurations
            field_pattern = r"'([^']+)'\s*=>\s*\[(.*?)\](?=\s*,\s*'|\s*$)"
            for match in re.finditer(field_pattern, fields_content, re.DOTALL):
                field_name = match.group(1)
                field_config = match.group(2)
                
                # Parse field configuration
                field_data = {}
                
                # Extract source
                source_match = re.search(r"'source'\s*=>\s*'([^']*)'", field_config)
                if source_match:
                    field_data['source'] = source_match.group(1)
                
                # Extract computation
                computation_match = re.search(r"'computation'\s*=>\s*'([^']*)'", field_config)
                if computation_match:
                    field_data['computation'] = computation_match.group(1)
                
                # Extract transform
                transform_match = re.search(r"'transform'\s*=>\s*'([^']*)'", field_config)
                if transform_match:
                    field_data['transform'] = transform_match.group(1)
                
                # Extract type
                type_match = re.search(r"'type'\s*=>\s*'([^']*)'", field_config)
                if type_match:
                    field_data['type'] = type_match.group(1)
                
                # Extract required
                field_data['required'] = "'required' => true" in field_config
                
                field_mappings[field_name] = field_data
        
        return field_mappings
    
    def _extract_docuseal_field_names(self, content):
        """Extract DocuSeal field name mappings from PHP config content"""
        docuseal_mappings = {}
        
        # Find the 'docuseal_field_names' array section
        field_names_match = re.search(r"'docuseal_field_names'\s*=>\s*\[(.*?)\]", content, re.DOTALL)
        if field_names_match:
            field_names_content = field_names_match.group(1)
            
            # Extract individual field name mappings
            mapping_pattern = r"'([^']+)'\s*=>\s*'([^']*)'(?=\s*,|\s*$)"
            for match in re.finditer(mapping_pattern, field_names_content):
                canonical_name = match.group(1)
                docuseal_name = match.group(2)
                docuseal_mappings[canonical_name] = docuseal_name
        
        return docuseal_mappings
    
    def get_manufacturer_config(self, manufacturer_name):
        """Get configuration for a specific manufacturer"""
        return self.manufacturer_configs.get(manufacturer_name)
    
    def get_field_mapping(self, manufacturer_name, field_name):
        """Get field mapping for a specific manufacturer and field"""
        config = self.get_manufacturer_config(manufacturer_name)
        if config and 'field_mappings' in config:
            return config['field_mappings'].get(field_name)
        return None
    
    def get_docuseal_field_name(self, manufacturer_name, canonical_name):
        """Get DocuSeal field name for a canonical field name"""
        config = self.get_manufacturer_config(manufacturer_name)
        if config and 'docuseal_field_names' in config:
            return config['docuseal_field_names'].get(canonical_name)
        return canonical_name  # Return original if no mapping found
    
    def list_manufacturers(self):
        """List all available manufacturers"""
        return list(self.manufacturer_configs.keys())
    
    def get_manufacturer_stats(self):
        """Get statistics about loaded manufacturers"""
        stats = {
            'total_manufacturers': len(self.manufacturer_configs),
            'manufacturers_with_templates': 0,
            'manufacturers_with_order_forms': 0,
            'total_field_mappings': 0
        }
        
        for config in self.manufacturer_configs.values():
            if config.get('docuseal_template_id'):
                stats['manufacturers_with_templates'] += 1
            if config.get('has_order_form'):
                stats['manufacturers_with_order_forms'] += 1
            if config.get('field_mappings'):
                stats['total_field_mappings'] += len(config['field_mappings'])
        
        return stats

# Initialize knowledge base
form_knowledge_base = FormMappingKnowledgeBase()
manufacturer_knowledge_base = ManufacturerMappingKnowledgeBase()

# Azure OpenAI Client
class AzureAIAgent:
    def __init__(self):
        if not Config.AZURE_OPENAI_ENDPOINT or not Config.AZURE_OPENAI_API_KEY:
            raise ValueError("Azure OpenAI configuration is required")
        
        self.client = AzureOpenAI(
            azure_endpoint=Config.AZURE_OPENAI_ENDPOINT,
            api_key=Config.AZURE_OPENAI_API_KEY,
            api_version=Config.AZURE_OPENAI_API_VERSION
        )
        
        # Cache for responses
        self.cache = TTLCache(maxsize=1000, ttl=Config.CACHE_TTL)
    
    async def validate_medical_terms(self, terms: List[str], context: DocumentType) -> Dict:
        """Validate medical terms using Azure OpenAI with medical knowledge"""
        cache_key = f"validate_{hash(str(terms))}_{context}"
        
        if cache_key in self.cache:
            logger.info(f"Cache hit for terms validation: {len(terms)} terms")
            return self.cache[cache_key]
        
        prompt = self._build_validation_prompt(terms, context)
        
        try:
            response = self.client.chat.completions.create(
                model=Config.AZURE_OPENAI_DEPLOYMENT,
                messages=[
                    {
                        "role": "system",
                        "content": self._get_medical_validation_system_prompt()
                    },
                    {
                        "role": "user",
                        "content": prompt
                    }
                ],
                temperature=0.1,
                max_tokens=2000,
                response_format={"type": "json_object"}
            )
            
            result = json.loads(response.choices[0].message.content)
            self.cache[cache_key] = result
            
            logger.info(f"Azure AI validated {len(terms)} terms with {result.get('overall_confidence', 0):.2f} confidence")
            return result
            
        except Exception as e:
            logger.error(f"Azure AI validation failed: {e}")
            return self._fallback_validation(terms, context)
    
    async def map_fields(self, ocr_data: Dict, document_type: DocumentType, target_schema: Optional[Dict] = None, manufacturer_name: Optional[str] = None) -> Dict:
        """Map OCR fields to target schema using Azure OpenAI with manufacturer-specific mappings"""
        cache_key = f"map_{hash(str(ocr_data))}_{document_type}_{manufacturer_name or 'generic'}"
        
        if cache_key in self.cache:
            logger.info(f"Cache hit for field mapping: {document_type} ({manufacturer_name})")
            return self.cache[cache_key]
        
        # Get manufacturer-specific mappings if available
        manufacturer_config = None
        if manufacturer_name:
            manufacturer_config = manufacturer_knowledge_base.get_manufacturer_config(manufacturer_name)
        
        prompt = self._build_mapping_prompt(ocr_data, document_type, target_schema, manufacturer_config)
        
        try:
            response = self.client.chat.completions.create(
                model=Config.AZURE_OPENAI_DEPLOYMENT,
                messages=[
                    {
                        "role": "system",
                        "content": self._get_field_mapping_system_prompt(manufacturer_config)
                    },
                    {
                        "role": "user",
                        "content": prompt
                    }
                ],
                temperature=0.1,
                max_tokens=3000,
                response_format={"type": "json_object"}
            )
            
            result = json.loads(response.choices[0].message.content)
            
            # Apply manufacturer-specific field name mappings
            if manufacturer_config and manufacturer_config.get('docuseal_field_names'):
                result = self._apply_manufacturer_field_mappings(result, manufacturer_config)
            
            self.cache[cache_key] = result
            
            logger.info(f"Azure AI mapped fields for {document_type} ({manufacturer_name or 'generic'}) with grade {result.get('quality_grade', 'N/A')}")
            return result
            
        except Exception as e:
            logger.error(f"Azure AI field mapping failed: {e}")
            return self._fallback_mapping(ocr_data, document_type, manufacturer_name)
    
    def _build_validation_prompt(self, terms: List[str], context: DocumentType) -> str:
        context_info = {
            DocumentType.WOUND_CARE: "wound care, pressure ulcers, diabetic foot ulcers, wound characteristics",
            DocumentType.INSURANCE_CARD: "health insurance, member information, plan types, copays, deductibles",
            DocumentType.CLINICAL_NOTE: "clinical documentation, diagnoses, treatments, medications, vital signs",
            DocumentType.PRESCRIPTION: "medications, dosages, prescribing information, pharmacy data"
        }.get(context, "general medical terminology")
        
        return f"""
Validate the following medical terms in the context of {context_info}:

Terms to validate: {json.dumps(terms)}

For each term, determine:
1. Is it a valid medical term?
2. Confidence level (0.0-1.0)
3. Medical category (anatomy, condition, medication, procedure, etc.)
4. Suggested alternatives if invalid or misspelled
5. Medical context and relationships

Consider common abbreviations, synonyms, and medical variations.
"""

    def _build_mapping_prompt(self, ocr_data: Dict, document_type: DocumentType, target_schema: Optional[Dict], manufacturer_config: Optional[Dict] = None) -> str:
        schema_info = target_schema or self._get_default_schema(document_type)
        
        manufacturer_context = ""
        if manufacturer_config:
            manufacturer_context = f"""
            
MANUFACTURER-SPECIFIC CONTEXT:
Manufacturer: {manufacturer_config.get('name', 'Unknown')}
Template ID: {manufacturer_config.get('docuseal_template_id', 'Not specified')}
Signature Required: {manufacturer_config.get('signature_required', False)}
Has Order Form: {manufacturer_config.get('has_order_form', False)}

Field Mappings Available: {len(manufacturer_config.get('field_mappings', {}))} custom mappings
DocuSeal Field Names: {len(manufacturer_config.get('docuseal_field_names', {}))} field name mappings

Use these manufacturer-specific mappings to ensure accurate field naming and processing.
"""
        
        return f"""
Map the following OCR data to the target schema for document type: {document_type}
{manufacturer_context}

OCR Data: {json.dumps(ocr_data, indent=2)}

Target Schema: {json.dumps(schema_info, indent=2)}

Requirements:
1. Map each OCR field to the most appropriate target field
2. Provide confidence scores for each mapping
3. Validate medical terminology
4. Suggest corrections for unclear or invalid data
5. Calculate overall quality grade (A-F)
6. Identify missing required fields
7. Apply manufacturer-specific field naming conventions if provided
8. Handle computed fields and transformations as specified

Consider medical context, common abbreviations, field relationships, and manufacturer-specific requirements.
"""

    def _get_medical_validation_system_prompt(self) -> str:
        return """You are a medical terminology expert with comprehensive knowledge of:
- ICD-10, CPT, HCPCS, SNOMED CT, LOINC codes
- Wound care terminology and staging
- Insurance and billing terminology
- Clinical documentation standards
- Anatomical terms and medical abbreviations

Validate medical terms with high accuracy, considering:
- Exact matches and common variations
- Medical abbreviations and synonyms
- Context-specific meanings
- Regional terminology differences

Return JSON format:
{
    "overall_confidence": float,
    "total_terms": int,
    "valid_terms": int,
    "results": [
        {
            "term": "string",
            "is_valid": boolean,
            "confidence": float,
            "category": "string",
            "suggested_terms": ["string"],
            "medical_context": {"key": "value"}
        }
    ],
    "processing_method": "azure_ai_agent"
}"""

    def _get_field_mapping_system_prompt(self) -> str:
        return """You are an expert in medical document processing and field mapping with expertise in:
- Insurance card processing and member information
- Clinical documentation and wound care records
- OCR data interpretation and correction
- Medical terminology validation
- HIPAA-compliant data handling

Map OCR fields intelligently, considering:
- Medical context and terminology
- Common OCR errors and corrections
- Field relationships and dependencies
- Data validation and quality scoring

Return JSON format:
{
    "mapped_fields": {"target_field": "mapped_value"},
    "confidence_scores": {"field": float},
    "quality_grade": "A-F",
    "suggestions": ["improvement suggestions"],
    "processing_notes": ["processing notes"]
}"""

    def _get_default_schema(self, document_type: DocumentType) -> Dict:
        schemas = {
            DocumentType.INSURANCE_CARD: {
                "member_id": "string",
                "member_name": "string",
                "insurance_company": "string",
                "group_number": "string",
                "plan_type": "string",
                "effective_date": "date",
                "copays": "object",
                "rx_info": "object"
            },
            DocumentType.CLINICAL_NOTE: {
                "patient_name": "string",
                "date_of_service": "date",
                "diagnosis": "string",
                "wound_location": "string",
                "wound_measurements": "object",
                "treatment_plan": "string"
            },
            DocumentType.WOUND_PHOTO: {
                "wound_location": "string",
                "length": "string",
                "width": "string",
                "depth": "string",
                "characteristics": "array",
                "staging": "string"
            }
        }
        return schemas.get(document_type, {})

    def _fallback_validation(self, terms: List[str], context: DocumentType) -> Dict:
        """Local fallback validation when Azure AI is unavailable"""
        if not Config.ENABLE_LOCAL_FALLBACK:
            raise HTTPException(status_code=503, detail="Azure AI unavailable and fallback disabled")
        
        logger.warning("Using local fallback validation")
        results = []
        valid_count = 0
        
        relevant_terms = self._get_relevant_terms(context)
        
        for term in terms:
            normalized_term = term.lower().replace(' ', '_').replace('-', '_')
            is_valid = any(normalized_term in category_terms 
                          for category_terms in relevant_terms.values())
            
            if is_valid:
                valid_count += 1
            
            results.append({
                "term": term,
                "is_valid": is_valid,
                "confidence": 0.8 if is_valid else 0.2,
                "category": "local_dictionary" if is_valid else None,
                "suggested_terms": [],
                "medical_context": None
            })
        
        return {
            "overall_confidence": valid_count / len(terms) if terms else 0,
            "total_terms": len(terms),
            "valid_terms": valid_count,
            "results": results,
            "processing_method": "local_fallback"
        }

    def _fallback_mapping(self, ocr_data: Dict, document_type: DocumentType) -> Dict:
        """Local fallback mapping when Azure AI is unavailable"""
        logger.warning("Using local fallback mapping")
        
        mapped_fields = {}
        confidence_scores = {}
        
        # Simple rule-based mapping
        for key, value in ocr_data.items():
            mapped_key = key.lower().replace(' ', '_')
            mapped_fields[mapped_key] = value
            confidence_scores[mapped_key] = 0.6
        
        return {
            "mapped_fields": mapped_fields,
            "confidence_scores": confidence_scores,
            "quality_grade": "C",
            "suggestions": ["Consider using Azure AI for better accuracy"],
            "processing_notes": ["Local fallback mapping used"]
        }

    def _get_relevant_terms(self, context: DocumentType) -> Dict:
        """Get relevant terminology based on context"""
        if context == DocumentType.WOUND_PHOTO:
            return MEDICAL_TERMINOLOGIES['wound_care']
        elif context == DocumentType.INSURANCE_CARD:
            return MEDICAL_TERMINOLOGIES['insurance']
        elif context == DocumentType.CLINICAL_NOTE:
            return {**MEDICAL_TERMINOLOGIES['clinical'], **MEDICAL_TERMINOLOGIES['wound_care']}
        else:
            return MEDICAL_TERMINOLOGIES['clinical']
    
    def _apply_manufacturer_field_mappings(self, result: Dict, manufacturer_config: Dict) -> Dict:
        """Apply manufacturer-specific field name mappings to the result"""
        if not manufacturer_config.get('docuseal_field_names'):
            return result
        
        docuseal_mappings = manufacturer_config['docuseal_field_names']
        mapped_fields = {}
        
        for canonical_name, value in result.get('mapped_fields', {}).items():
            # Get the manufacturer-specific DocuSeal field name
            docuseal_field_name = docuseal_mappings.get(canonical_name, canonical_name)
            mapped_fields[docuseal_field_name] = value
        
        # Update the result with manufacturer-specific field names
        result['mapped_fields'] = mapped_fields
        
        # Add processing note about manufacturer mapping
        if 'processing_notes' not in result:
            result['processing_notes'] = []
        result['processing_notes'].append(f"Applied {manufacturer_config.get('name')} field mappings")
        
        return result

# Local fallback functions (used when Azure AI is not available)
def _local_validate_terms(terms: List[str], context: DocumentType) -> Dict:
    """Enhanced local validation with better accuracy"""
    logger.info(f"Using enhanced local validation for {len(terms)} terms")
    
    results = []
    valid_count = 0
    
    relevant_terms = _get_relevant_terms_dict(context)
    all_terms = set()
    for category_terms in relevant_terms.values():
        all_terms.update(category_terms)
    
    for term in terms:
        normalized_term = term.lower().replace(' ', '_').replace('-', '_')
        variations = [
            normalized_term,
            normalized_term.replace('_', ''),
            term.lower().replace(' ', '').replace('-', ''),
            term.lower()
        ]
        
        is_valid = any(var in all_terms for var in variations)
        confidence = 0.85 if is_valid else 0.3
        
        # Check for partial matches for suggestions
        suggestions = []
        if not is_valid:
            for term_candidate in list(all_terms)[:10]:  # Limit for performance
                if normalized_term in term_candidate or term_candidate in normalized_term:
                    suggestions.append(term_candidate.replace('_', ' ').title())
        
        if is_valid:
            valid_count += 1
        
        results.append({
            "term": term,
            "is_valid": is_valid,
            "confidence": confidence,
            "category": "medical_terminology" if is_valid else "unknown",
            "suggested_terms": suggestions[:3],  # Limit suggestions
            "medical_context": {"source": "local_dictionary"}
        })
    
    return {
        "overall_confidence": (valid_count / len(terms)) * 0.85 if terms else 0,  # Cap at 85% for local
        "total_terms": len(terms),
        "valid_terms": valid_count,
        "results": results,
        "processing_method": "enhanced_local_fallback"
    }

def _local_map_fields(ocr_data: Dict, document_type: DocumentType, target_schema: Optional[Dict] = None) -> Dict:
    """Enhanced local field mapping with intelligent matching"""
    logger.info(f"Using enhanced local mapping for {document_type}")
    
    mapped_fields = {}
    confidence_scores = {}
    suggestions = []
    processing_notes = []
    
    # Get appropriate schema
    schema = target_schema or _get_default_schema_dict(document_type)
    
    # Enhanced field mapping logic
    for ocr_key, ocr_value in ocr_data.items():
        best_match = None
        best_confidence = 0.0
        
        ocr_key_normalized = ocr_key.lower().replace(' ', '_').replace('-', '_')
        
        # Try exact matches first
        for schema_key in schema.keys():
            schema_key_normalized = schema_key.lower().replace(' ', '_').replace('-', '_')
            
            if ocr_key_normalized == schema_key_normalized:
                best_match = schema_key
                best_confidence = 0.95
                break
            elif ocr_key_normalized in schema_key_normalized or schema_key_normalized in ocr_key_normalized:
                if best_confidence < 0.8:
                    best_match = schema_key
                    best_confidence = 0.8
        
        # Semantic matching for common fields
        if not best_match:
            field_mappings = {
                'member': ['member_id', 'member_name', 'member_number'],
                'insurance': ['insurance_company', 'insurance_plan', 'plan_type'],
                'group': ['group_number', 'group_id'],
                'effective': ['effective_date', 'start_date'],
                'copay': ['copay', 'copayment', 'primary_care_copay', 'specialist_copay'],
                'name': ['patient_name', 'member_name', 'first_name', 'last_name'],
                'date': ['date_of_birth', 'dob', 'effective_date', 'date_of_service'],
                'wound': ['wound_location', 'wound_type', 'wound_size'],
                'diagnosis': ['primary_diagnosis', 'diagnosis']
            }
            
            for keyword, possible_fields in field_mappings.items():
                if keyword in ocr_key_normalized:
                    for field in possible_fields:
                        if field in schema:
                            best_match = field
                            best_confidence = 0.7
                            break
                    if best_match:
                        break
        
        # Use the OCR key if no good match found
        if not best_match:
            best_match = ocr_key_normalized
            best_confidence = 0.5
            
        mapped_fields[best_match] = ocr_value
        confidence_scores[best_match] = best_confidence
    
    # Calculate quality grade
    avg_confidence = sum(confidence_scores.values()) / len(confidence_scores) if confidence_scores else 0
    if avg_confidence >= 0.9:
        quality_grade = "A"
    elif avg_confidence >= 0.8:
        quality_grade = "B" 
    elif avg_confidence >= 0.7:
        quality_grade = "B-"
    elif avg_confidence >= 0.6:
        quality_grade = "C"
    else:
        quality_grade = "D"
    
    processing_notes.append(f"Enhanced local mapping completed with {len(mapped_fields)} fields")
    processing_notes.append(f"Average confidence: {avg_confidence:.2f}")
    
    if avg_confidence < 0.8:
        suggestions.append("Consider enabling Azure AI for higher accuracy mapping")
    
    return {
        "mapped_fields": mapped_fields,
        "confidence_scores": confidence_scores,
        "quality_grade": quality_grade,
        "suggestions": suggestions,
        "processing_notes": processing_notes
    }

def _get_relevant_terms_dict(context: DocumentType) -> Dict:
    """Get relevant terminology based on context"""
    if context == DocumentType.WOUND_PHOTO:
        return MEDICAL_TERMINOLOGIES['wound_care']
    elif context == DocumentType.INSURANCE_CARD:
        return MEDICAL_TERMINOLOGIES['insurance']
    elif context == DocumentType.CLINICAL_NOTE:
        return {**MEDICAL_TERMINOLOGIES['clinical'], **MEDICAL_TERMINOLOGIES['wound_care']}
    else:
        return MEDICAL_TERMINOLOGIES['clinical']

def _get_default_schema_dict(document_type: DocumentType) -> Dict:
    """Get default schema for document type"""
    schemas = {
        DocumentType.INSURANCE_CARD: {
            "member_id": "string",
            "member_name": "string", 
            "insurance_company": "string",
            "group_number": "string",
            "plan_type": "string",
            "effective_date": "date",
            "primary_care_copay": "currency",
            "specialist_copay": "currency"
        },
        DocumentType.CLINICAL_NOTE: {
            "patient_name": "string",
            "date_of_service": "date",
            "primary_diagnosis": "string",
            "wound_location": "string",
            "wound_type": "string"
        },
        DocumentType.WOUND_PHOTO: {
            "wound_location": "string",
            "length": "measurement",
            "width": "measurement", 
            "depth": "measurement",
            "wound_characteristics": "array"
        }
    }
    return schemas.get(document_type, {})

# FastAPI Application
app = FastAPI(
    title="Medical AI Service",
    description="AI-powered medical terminology validation and field mapping",
    version="1.0.0"
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Global AI agent instance
ai_agent = None

@app.on_event("startup")
async def startup_event():
    global ai_agent
    try:
        ai_agent = AzureAIAgent()
        logger.info("Medical AI Service started successfully")
    except Exception as e:
        logger.error(f"Failed to initialize Azure AI Agent: {e}")
        if not Config.ENABLE_LOCAL_FALLBACK:
            raise

@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "timestamp": datetime.now().isoformat(),
        "azure_ai_available": ai_agent is not None,
        "local_fallback_enabled": Config.ENABLE_LOCAL_FALLBACK
    }

@app.post("/validate-terms", response_model=ValidationResponse)
async def validate_medical_terms(request: ValidationRequest):
    """Validate medical terminology"""
    try:
        if ai_agent:
            # Use Azure AI if available
            result = await ai_agent.validate_medical_terms(
                request.terms, 
                request.context
            )
        else:
            # Use local fallback processing
            result = _local_validate_terms(request.terms, request.context)
        
        return ValidationResponse(
            overall_confidence=result["overall_confidence"],
            total_terms=result["total_terms"],
            valid_terms=result["valid_terms"],
            results=[TermValidationResult(**r) for r in result["results"]],
            processing_method=result["processing_method"],
            timestamp=datetime.now()
        )
    
    except Exception as e:
        logger.error(f"Term validation failed: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/map-fields", response_model=FieldMappingResult)
async def map_document_fields(request: FieldMappingRequest):
    """Map OCR data to target schema with manufacturer-specific mappings"""
    try:
        if ai_agent:
            # Use Azure AI if available
            result = await ai_agent.map_fields(
                request.ocr_data,
                request.document_type,
                request.target_schema,
                request.manufacturer_name
            )
        else:
            # Use local fallback processing
            result = _local_map_fields(
                request.ocr_data,
                request.document_type,
                request.target_schema
            )
        
        return FieldMappingResult(**result)
    
    except Exception as e:
        logger.error(f"Field mapping failed: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/terminology-stats")
async def get_terminology_stats():
    """Get local terminology dictionary statistics"""
    stats = {}
    total_terms = 0
    
    for domain, categories in MEDICAL_TERMINOLOGIES.items():
        domain_stats = {}
        domain_total = 0
        
        for category, terms in categories.items():
            term_count = len(terms)
            domain_stats[category] = term_count
            domain_total += term_count
        
        domain_stats['total'] = domain_total
        stats[domain] = domain_stats
        total_terms += domain_total
    
    # Get manufacturer knowledge base stats
    manufacturer_stats = manufacturer_knowledge_base.get_manufacturer_stats()
    
    return {
        "domains": stats,
        "total_terms": total_terms,
        "manufacturer_knowledge_base": manufacturer_stats,
        "last_updated": datetime.now().isoformat()
    }

@app.get("/manufacturers")
async def get_manufacturers():
    """Get list of available manufacturers and their configurations"""
    try:
        manufacturers = manufacturer_knowledge_base.list_manufacturers()
        manufacturer_details = {}
        
        for manufacturer_name in manufacturers:
            config = manufacturer_knowledge_base.get_manufacturer_config(manufacturer_name)
            manufacturer_details[manufacturer_name] = {
                "name": config.get('name'),
                "docuseal_template_id": config.get('docuseal_template_id'),
                "signature_required": config.get('signature_required'),
                "has_order_form": config.get('has_order_form'),
                "field_mappings_count": len(config.get('field_mappings', {})),
                "docuseal_field_names_count": len(config.get('docuseal_field_names', {}))
            }
        
        return {
            "total_manufacturers": len(manufacturers),
            "manufacturers": manufacturer_details
        }
        
    except Exception as e:
        logger.error(f"Error getting manufacturers: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/manufacturers/{manufacturer_name}")
async def get_manufacturer_config(manufacturer_name: str):
    """Get detailed configuration for a specific manufacturer"""
    try:
        config = manufacturer_knowledge_base.get_manufacturer_config(manufacturer_name)
        if not config:
            raise HTTPException(status_code=404, detail=f"Manufacturer '{manufacturer_name}' not found")
        
        return config
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error getting manufacturer config: {e}")
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    # Run the service
    uvicorn.run(
        "medical_ai_service:app",
        host=Config.API_HOST,
        port=Config.API_PORT,
        reload=False,
        access_log=True
    ) 