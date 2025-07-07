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
from contextlib import asynccontextmanager
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
        self.client = None
        self.cache = TTLCache(maxsize=1000, ttl=Config.CACHE_TTL)
        self.is_available = False
        
        # Validate Azure OpenAI configuration
        missing_vars = []
        if not Config.AZURE_OPENAI_ENDPOINT:
            missing_vars.append("AZURE_OPENAI_ENDPOINT")
        if not Config.AZURE_OPENAI_API_KEY:
            missing_vars.append("AZURE_OPENAI_API_KEY")
        
        if missing_vars:
            logger.warning(f"Azure OpenAI configuration incomplete. Missing: {', '.join(missing_vars)}")
            if not Config.ENABLE_LOCAL_FALLBACK:
                raise ValueError(f"Azure OpenAI configuration is required. Missing environment variables: {', '.join(missing_vars)}")
            logger.info("Continuing with local fallback mode enabled")
            return
        
        try:
            # Initialize Azure OpenAI client
            self.client = AzureOpenAI(
                azure_endpoint=Config.AZURE_OPENAI_ENDPOINT,
                api_key=Config.AZURE_OPENAI_API_KEY,
                api_version=Config.AZURE_OPENAI_API_VERSION
            )
            
            # Test the connection
            self._test_connection()
            self.is_available = True
            logger.info("✅ Azure OpenAI client initialized successfully")
            
        except Exception as e:
            logger.error(f"Failed to initialize Azure OpenAI client: {e}")
            if not Config.ENABLE_LOCAL_FALLBACK:
                raise
            logger.info("Continuing with local fallback mode")
    
    def _test_connection(self):
        """Test Azure OpenAI connection with a simple request"""
        try:
            response = self.client.chat.completions.create(
                model=Config.AZURE_OPENAI_DEPLOYMENT,
                messages=[{"role": "user", "content": "Test connection"}],
                max_tokens=1,
                temperature=0
            )
            logger.info("Azure OpenAI connection test successful")
        except Exception as e:
            logger.warning(f"Azure OpenAI connection test failed: {e}")
            raise
    
    async def validate_medical_terms(self, terms: List[str], context: DocumentType) -> Dict:
        """Validate medical terms using Azure OpenAI with medical knowledge"""
        # Use local fallback if Azure AI is not available
        if not self.is_available or not self.client:
            logger.info("Azure AI not available, using local fallback for validation")
            return self._fallback_validation(terms, context)
        
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
        # Use local fallback if Azure AI is not available
        if not self.is_available or not self.client:
            logger.info("Azure AI not available, using local fallback for field mapping")
            return self._fallback_mapping(ocr_data, document_type, manufacturer_name)
        
        cache_key = f"map_{hash(str(ocr_data))}_{document_type}_{manufacturer_name or 'generic'}"
        
        if cache_key in self.cache:
            logger.info(f"Cache hit for field mapping: {document_type} ({manufacturer_name})")
            return self.cache[cache_key]
        
        # Get manufacturer-specific mappings if available
        manufacturer_config = None
        if manufacturer_name:
            global manufacturer_knowledge_base
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

    def _fallback_mapping(self, ocr_data: Dict, document_type: DocumentType, manufacturer_name: Optional[str] = None) -> Dict:
        """Local fallback mapping when Azure AI is unavailable"""
        logger.warning("Using local fallback mapping")
        
        # Use enhanced local mapping instead of simple mapping
        result = _local_map_fields(ocr_data, document_type)
        
        # Add manufacturer-specific mappings if available
        if manufacturer_name:
            global manufacturer_knowledge_base
            manufacturer_config = manufacturer_knowledge_base.get_manufacturer_config(manufacturer_name)
            if manufacturer_config and manufacturer_config.get('docuseal_field_names'):
                result = self._apply_manufacturer_field_mappings(result, manufacturer_config)
        
        # Update processing notes to indicate fallback mode
        if 'processing_notes' not in result:
            result['processing_notes'] = []
        result['processing_notes'].append("Azure AI unavailable - used enhanced local fallback")
        
        return result

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
    """Enhanced local field mapping with intelligent matching and OCR error correction"""
    logger.info(f"Using enhanced local mapping for {document_type}")
    
    mapped_fields = {}
    confidence_scores = {}
    suggestions = []
    processing_notes = []
    
    # Get appropriate schema
    schema = target_schema or _get_default_schema_dict(document_type)
    
    # Enhanced field mapping logic with multiple passes
    for ocr_key, ocr_value in ocr_data.items():
        best_match = None
        best_confidence = 0.0
        mapping_method = "unknown"
        
        # Preprocess OCR key
        ocr_key_clean = _clean_ocr_text(ocr_key)
        ocr_key_normalized = ocr_key_clean.lower().replace(' ', '_').replace('-', '_')
        
        # Pass 1: Exact matches
        for schema_key in schema.keys():
            schema_key_normalized = schema_key.lower().replace(' ', '_').replace('-', '_')
            
            if ocr_key_normalized == schema_key_normalized:
                best_match = schema_key
                best_confidence = 0.95
                mapping_method = "exact_match"
                break
            elif ocr_key_normalized in schema_key_normalized or schema_key_normalized in ocr_key_normalized:
                if best_confidence < 0.85:
                    best_match = schema_key
                    best_confidence = 0.85
                    mapping_method = "substring_match"
        
        # Pass 2: Fuzzy string matching for OCR errors
        if not best_match or best_confidence < 0.8:
            for schema_key in schema.keys():
                similarity = _calculate_string_similarity(ocr_key_normalized, schema_key.lower().replace(' ', '_').replace('-', '_'))
                if similarity > 0.8 and similarity > best_confidence:
                    best_match = schema_key
                    best_confidence = similarity
                    mapping_method = "fuzzy_match"
        
        # Pass 3: Medical context-aware semantic matching
        if not best_match or best_confidence < 0.75:
            semantic_match, semantic_confidence = _semantic_field_matching(ocr_key_normalized, schema, document_type)
            if semantic_confidence > best_confidence:
                best_match = semantic_match
                best_confidence = semantic_confidence
                mapping_method = "semantic_match"
        
        # Pass 4: Pattern-based matching for specific document types
        if not best_match or best_confidence < 0.7:
            pattern_match, pattern_confidence = _pattern_based_matching(ocr_key, ocr_value, schema, document_type)
            if pattern_confidence > best_confidence:
                best_match = pattern_match
                best_confidence = pattern_confidence
                mapping_method = "pattern_match"
        
        # Pass 5: Medical terminology validation
        if best_match and ocr_value:
            # Validate medical content and adjust confidence
            medical_confidence = _validate_medical_content(best_match, ocr_value, document_type)
            best_confidence = (best_confidence + medical_confidence) / 2
        
        # Use the OCR key if no good match found
        if not best_match:
            best_match = ocr_key_normalized
            best_confidence = 0.4
            mapping_method = "fallback"
        
        # Apply OCR error correction to the value
        corrected_value = _correct_ocr_errors(ocr_value, best_match, document_type)
        
        mapped_fields[best_match] = corrected_value
        confidence_scores[best_match] = best_confidence
        
        # Add debug info for low confidence mappings
        if best_confidence < 0.6:
            processing_notes.append(f"Low confidence mapping: '{ocr_key}' → '{best_match}' ({mapping_method})")
    
    # Post-processing: Apply medical domain logic
    mapped_fields, confidence_scores = _apply_medical_domain_logic(mapped_fields, confidence_scores, document_type)
    
    # Quality scoring with enhanced criteria
    avg_confidence, quality_grade = _calculate_enhanced_quality_score(confidence_scores, mapped_fields, schema)
    
    processing_notes.append(f"Enhanced local mapping completed with {len(mapped_fields)} fields")
    processing_notes.append(f"Average confidence: {avg_confidence:.2f}")
    processing_notes.append(f"Field coverage: {len(mapped_fields)}/{len(schema)} schema fields")
    
    # Generate intelligent suggestions
    suggestions = _generate_mapping_suggestions(mapped_fields, confidence_scores, schema, avg_confidence)
    
    return {
        "mapped_fields": mapped_fields,
        "confidence_scores": confidence_scores,
        "quality_grade": quality_grade,
        "suggestions": suggestions,
        "processing_notes": processing_notes
    }

# Enhanced mapping helper functions
def _clean_ocr_text(text: str) -> str:
    """Clean and normalize OCR text for better matching"""
    if not text:
        return ""
    
    # Common OCR error corrections
    ocr_corrections = {
        'O': '0',  # Letter O to number 0
        'I': '1',  # Letter I to number 1
        'S': '5',  # Letter S to number 5 in numeric contexts
        'l': '1',  # Lowercase l to number 1
        '|': 'I',  # Pipe to letter I
        'rn': 'm', # Common OCR error
        'cl': 'd', # Common OCR error
    }
    
    # Remove extra whitespace
    cleaned = ' '.join(text.split())
    
    # Remove special characters that are likely OCR artifacts
    cleaned = cleaned.replace('°', '').replace('~', '').replace('`', "'")
    
    # Apply common corrections in non-alphabetic contexts
    if any(char.isdigit() for char in cleaned):
        for wrong, correct in ocr_corrections.items():
            if wrong in ['O', 'I', 'S', 'l']:
                cleaned = cleaned.replace(wrong, correct)
    
    return cleaned

def _calculate_string_similarity(str1: str, str2: str) -> float:
    """Calculate string similarity using a combination of metrics"""
    if not str1 or not str2:
        return 0.0
    
    # Exact match
    if str1 == str2:
        return 1.0
    
    # Levenshtein distance similarity
    def levenshtein_similarity(s1, s2):
        if len(s1) == 0 or len(s2) == 0:
            return 0.0
        
        matrix = [[0] * (len(s2) + 1) for _ in range(len(s1) + 1)]
        
        for i in range(len(s1) + 1):
            matrix[i][0] = i
        for j in range(len(s2) + 1):
            matrix[0][j] = j
        
        for i in range(1, len(s1) + 1):
            for j in range(1, len(s2) + 1):
                if s1[i-1] == s2[j-1]:
                    matrix[i][j] = matrix[i-1][j-1]
                else:
                    matrix[i][j] = min(
                        matrix[i-1][j] + 1,    # deletion
                        matrix[i][j-1] + 1,    # insertion
                        matrix[i-1][j-1] + 1   # substitution
                    )
        
        distance = matrix[len(s1)][len(s2)]
        max_len = max(len(s1), len(s2))
        return 1 - (distance / max_len)
    
    # Jaccard similarity for sets of characters
    def jaccard_similarity(s1, s2):
        set1, set2 = set(s1), set(s2)
        intersection = len(set1 & set2)
        union = len(set1 | set2)
        return intersection / union if union > 0 else 0
    
    # Combine metrics
    levenshtein_sim = levenshtein_similarity(str1, str2)
    jaccard_sim = jaccard_similarity(str1, str2)
    
    # Weighted average (Levenshtein is more important for field names)
    return 0.7 * levenshtein_sim + 0.3 * jaccard_sim

def _semantic_field_matching(ocr_key: str, schema: Dict, document_type: DocumentType) -> tuple:
    """Perform semantic field matching based on medical context"""
    best_match = None
    best_confidence = 0.0
    
    # Medical field semantic mappings
    field_mappings = {
        DocumentType.INSURANCE_CARD: {
            'member': ['member_id', 'member_name', 'member_number', 'subscriber_id'],
            'insurance': ['insurance_company', 'insurance_plan', 'plan_type', 'payer_name'],
            'group': ['group_number', 'group_id', 'employer_group'],
            'effective': ['effective_date', 'start_date', 'coverage_start'],
            'copay': ['copay', 'copayment', 'primary_care_copay', 'specialist_copay'],
            'deductible': ['deductible', 'annual_deductible', 'family_deductible'],
            'plan': ['plan_type', 'plan_name', 'benefit_plan'],
            'id': ['member_id', 'policy_id', 'subscriber_id'],
            'phone': ['phone', 'phone_number', 'contact_phone'],
            'address': ['address', 'member_address', 'billing_address']
        },
        DocumentType.CLINICAL_NOTE: {
            'patient': ['patient_name', 'patient_first_name', 'patient_last_name'],
            'name': ['patient_name', 'member_name', 'first_name', 'last_name'],
            'date': ['date_of_service', 'visit_date', 'appointment_date'],
            'diagnosis': ['primary_diagnosis', 'diagnosis', 'icd10_code'],
            'wound': ['wound_location', 'wound_type', 'wound_size', 'wound_description'],
            'treatment': ['treatment_plan', 'treatment', 'intervention'],
            'medication': ['medications', 'prescribed_medications', 'current_medications'],
            'physician': ['physician_name', 'provider_name', 'attending_physician']
        },
        DocumentType.WOUND_PHOTO: {
            'location': ['wound_location', 'anatomical_location', 'body_site'],
            'size': ['wound_size', 'dimensions', 'measurements'],
            'length': ['length', 'wound_length', 'longest_dimension'],
            'width': ['width', 'wound_width', 'widest_dimension'],
            'depth': ['depth', 'wound_depth', 'deepest_measurement'],
            'stage': ['staging', 'wound_stage', 'pressure_ulcer_stage'],
            'characteristics': ['wound_characteristics', 'appearance', 'description']
        }
    }
    
    # Get mappings for document type
    type_mappings = field_mappings.get(document_type, {})
    
    # Check semantic matches
    for keyword, possible_fields in type_mappings.items():
        if keyword in ocr_key:
            for field in possible_fields:
                if field in schema:
                    confidence = 0.8 - (0.1 * len(keyword))  # Longer keywords get higher confidence
                    if confidence > best_confidence:
                        best_match = field
                        best_confidence = min(confidence, 0.9)  # Cap at 90%
                        break
    
    return best_match, best_confidence

def _pattern_based_matching(ocr_key: str, ocr_value: str, schema: Dict, document_type: DocumentType) -> tuple:
    """Pattern-based field matching using value patterns"""
    best_match = None
    best_confidence = 0.0
    
    # Analyze the value to infer field type
    if ocr_value:
        # Date patterns
        date_patterns = [
            r'\d{1,2}[/-]\d{1,2}[/-]\d{2,4}',
            r'\d{4}[/-]\d{1,2}[/-]\d{1,2}',
            r'[A-Za-z]{3}\s+\d{1,2},?\s+\d{4}'
        ]
        
        # Currency patterns
        currency_patterns = [
            r'\$\d+\.?\d*',
            r'\d+\.\d{2}\s*\$?'
        ]
        
        # Phone patterns
        phone_patterns = [
            r'\(\d{3}\)\s*\d{3}-\d{4}',
            r'\d{3}-\d{3}-\d{4}',
            r'\d{10}'
        ]
        
        # ID patterns
        id_patterns = [
            r'[A-Z]{2,3}\d{6,}',
            r'\d{8,12}',
            r'[A-Z]\d{7,}'
        ]
        
        import re
        
        # Check patterns and suggest fields
        if any(re.search(pattern, ocr_value) for pattern in date_patterns):
            date_fields = [k for k in schema.keys() if 'date' in k.lower() or 'dob' in k.lower()]
            if date_fields:
                best_match = date_fields[0]
                best_confidence = 0.8
        
        elif any(re.search(pattern, ocr_value) for pattern in currency_patterns):
            currency_fields = [k for k in schema.keys() if 'copay' in k.lower() or 'deductible' in k.lower() or 'cost' in k.lower()]
            if currency_fields:
                best_match = currency_fields[0]
                best_confidence = 0.75
        
        elif any(re.search(pattern, ocr_value) for pattern in phone_patterns):
            phone_fields = [k for k in schema.keys() if 'phone' in k.lower()]
            if phone_fields:
                best_match = phone_fields[0]
                best_confidence = 0.85
        
        elif any(re.search(pattern, ocr_value) for pattern in id_patterns):
            id_fields = [k for k in schema.keys() if 'id' in k.lower() or 'number' in k.lower()]
            if id_fields:
                best_match = id_fields[0]
                best_confidence = 0.7
    
    return best_match, best_confidence

def _validate_medical_content(field_name: str, field_value: str, document_type: DocumentType) -> float:
    """Validate medical content and return confidence adjustment"""
    if not field_value:
        return 0.5
    
    confidence = 0.5  # Base confidence
    
    # Get relevant medical terms for validation
    relevant_terms = _get_relevant_terms_dict(document_type)
    all_terms = set()
    for category_terms in relevant_terms.values():
        all_terms.update(category_terms)
    
    # Check if field value contains medical terms
    value_lower = field_value.lower().replace(' ', '_').replace('-', '_')
    
    # Higher confidence for recognized medical terms
    if any(term in value_lower or value_lower in term for term in all_terms):
        confidence = 0.9
    
    # Field-specific validation
    if 'name' in field_name.lower() and len(field_value.split()) >= 2:
        confidence = 0.8  # Names should have multiple parts
    elif 'date' in field_name.lower():
        # Basic date validation
        if any(char.isdigit() for char in field_value) and ('/' in field_value or '-' in field_value):
            confidence = 0.9
    elif 'id' in field_name.lower() or 'number' in field_name.lower():
        # IDs should contain alphanumeric characters
        if field_value.replace('-', '').replace(' ', '').isalnum():
            confidence = 0.8
    
    return confidence

def _correct_ocr_errors(text: str, field_name: str, document_type: DocumentType) -> str:
    """Apply OCR error corrections based on field context"""
    if not text:
        return text
    
    corrected = text
    
    # Field-specific corrections
    if 'date' in field_name.lower():
        # Common date OCR errors
        corrected = corrected.replace('O', '0').replace('l', '1').replace('I', '1')
        
    elif 'phone' in field_name.lower():
        # Phone number corrections
        corrected = ''.join(c if c.isdigit() or c in '()-. ' else '' for c in corrected)
        
    elif 'id' in field_name.lower() or 'number' in field_name.lower():
        # ID/Number corrections
        corrected = corrected.replace('O', '0').replace('l', '1').replace('I', '1')
        
    elif 'name' in field_name.lower():
        # Name corrections - capitalize properly
        words = corrected.split()
        corrected = ' '.join(word.capitalize() for word in words if word.isalpha())
    
    return corrected

def _apply_medical_domain_logic(mapped_fields: Dict, confidence_scores: Dict, document_type: DocumentType) -> tuple:
    """Apply medical domain-specific logic and validation"""
    
    # Domain-specific field validation
    if document_type == DocumentType.INSURANCE_CARD:
        # Ensure member ID exists and is reasonable
        if 'member_id' in mapped_fields:
            member_id = mapped_fields['member_id']
            if len(str(member_id)) < 3:  # Too short for a member ID
                confidence_scores['member_id'] *= 0.5
        
        # Cross-validate dates
        if 'effective_date' in mapped_fields and 'date_of_birth' in mapped_fields:
            # Effective date should be after DOB (basic sanity check)
            pass  # Could implement actual date comparison
    
    elif document_type == DocumentType.WOUND_PHOTO:
        # Validate wound measurements
        measurement_fields = ['length', 'width', 'depth']
        for field in measurement_fields:
            if field in mapped_fields:
                value = str(mapped_fields[field])
                # Should contain numbers and possibly units
                if not any(c.isdigit() for c in value):
                    confidence_scores[field] *= 0.3
    
    return mapped_fields, confidence_scores

def _calculate_enhanced_quality_score(confidence_scores: Dict, mapped_fields: Dict, schema: Dict) -> tuple:
    """Calculate enhanced quality score with multiple criteria"""
    if not confidence_scores:
        return 0.0, "F"
    
    # Calculate average confidence
    avg_confidence = sum(confidence_scores.values()) / len(confidence_scores)
    
    # Calculate field coverage (how many schema fields were mapped)
    coverage_ratio = len(mapped_fields) / len(schema) if schema else 0
    
    # Calculate quality adjustments
    high_confidence_fields = sum(1 for conf in confidence_scores.values() if conf >= 0.8)
    high_confidence_ratio = high_confidence_fields / len(confidence_scores)
    
    # Composite score
    composite_score = (
        0.5 * avg_confidence +
        0.3 * coverage_ratio +
        0.2 * high_confidence_ratio
    )
    
    # Letter grade assignment
    if composite_score >= 0.9:
        grade = "A+"
    elif composite_score >= 0.85:
        grade = "A"
    elif composite_score >= 0.8:
        grade = "A-"
    elif composite_score >= 0.75:
        grade = "B+"
    elif composite_score >= 0.7:
        grade = "B"
    elif composite_score >= 0.65:
        grade = "B-"
    elif composite_score >= 0.6:
        grade = "C+"
    elif composite_score >= 0.55:
        grade = "C"
    elif composite_score >= 0.5:
        grade = "C-"
    elif composite_score >= 0.4:
        grade = "D"
    else:
        grade = "F"
    
    return composite_score, grade

def _generate_mapping_suggestions(mapped_fields: Dict, confidence_scores: Dict, schema: Dict, avg_confidence: float) -> List[str]:
    """Generate intelligent suggestions for improving mapping quality"""
    suggestions = []
    
    # Overall quality suggestions
    if avg_confidence < 0.6:
        suggestions.append("⚠️  Low overall confidence - consider manual review of field mappings")
    elif avg_confidence < 0.8:
        suggestions.append("Consider enabling Azure AI for higher accuracy mapping")
    
    # Missing field suggestions
    unmapped_fields = set(schema.keys()) - set(mapped_fields.keys())
    if unmapped_fields:
        suggestions.append(f"📋 Missing {len(unmapped_fields)} schema fields: {', '.join(list(unmapped_fields)[:3])}")
    
    # Low confidence field suggestions
    low_confidence_fields = [field for field, conf in confidence_scores.items() if conf < 0.5]
    if low_confidence_fields:
        suggestions.append(f"🔍 Review low confidence fields: {', '.join(low_confidence_fields[:3])}")
    
    # Document-specific suggestions
    if len(mapped_fields) < 3:
        suggestions.append("📄 Very few fields detected - check document quality and OCR accuracy")
    
    return suggestions

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

# Global instances
ai_agent = None
form_mapping_knowledge_base = FormMappingKnowledgeBase()
manufacturer_knowledge_base = ManufacturerMappingKnowledgeBase()

@asynccontextmanager
async def lifespan(app: FastAPI):
    # Startup
    global ai_agent
    try:
        ai_agent = AzureAIAgent()
        logger.info("Medical AI Service started successfully")
    except Exception as e:
        logger.error(f"Failed to initialize Azure AI Agent: {e}")
        if not Config.ENABLE_LOCAL_FALLBACK:
            raise
    
    yield
    
    # Shutdown
    logger.info("Medical AI Service shutting down")

# FastAPI Application
app = FastAPI(
    title="Medical AI Service",
    description="AI-powered medical terminology validation and field mapping",
    version="1.0.0",
    lifespan=lifespan
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Global instances are now managed by lifespan

@app.get("/health")
async def health_check():
    """Health check endpoint"""
    azure_ai_status = "unavailable"
    if ai_agent:
        if ai_agent.is_available:
            azure_ai_status = "available"
        else:
            azure_ai_status = "configured_but_failed"
    
    return {
        "status": "healthy",
        "timestamp": datetime.now().isoformat(),
        "azure_ai_status": azure_ai_status,
        "azure_ai_available": ai_agent is not None and ai_agent.is_available,
        "local_fallback_enabled": Config.ENABLE_LOCAL_FALLBACK,
        "services": {
            "ai_agent": "running" if ai_agent else "not_initialized",
            "knowledge_base": "loaded",
            "manufacturer_configs": len(manufacturer_knowledge_base.list_manufacturers()) if manufacturer_knowledge_base else 0
        }
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
    global manufacturer_knowledge_base
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
        global manufacturer_knowledge_base
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
        global manufacturer_knowledge_base
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