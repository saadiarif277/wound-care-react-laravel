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
import re

# Load environment variables from .env file if it exists
try:
    from dotenv import load_dotenv
    # Load from .env file in the parent directory (Laravel root)
    env_path = Path(__file__).parent.parent / '.env'
    if env_path.exists():
        load_dotenv(env_path)
        print(f"Loaded environment variables from {env_path}")
except ImportError:
    print("python-dotenv not installed, skipping .env file loading")

import uvicorn
from fastapi import FastAPI, HTTPException, Depends
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from openai import AzureOpenAI
from cachetools import TTLCache

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Configuration
class Config:
    AZURE_OPENAI_ENDPOINT = os.getenv('AZURE_OPENAI_ENDPOINT')
    AZURE_OPENAI_API_KEY = os.getenv('AZURE_OPENAI_API_KEY')
    AZURE_OPENAI_DEPLOYMENT = os.getenv('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o')
    AZURE_OPENAI_API_VERSION = os.getenv('AZURE_OPENAI_API_VERSION', '2024-02-15-preview')
    
    # Service configuration
    PORT = int(os.getenv('MEDICAL_AI_SERVICE_PORT', 8081))
    DEBUG = os.getenv('MEDICAL_AI_DEBUG', 'false').lower() == 'true'
    CACHE_TTL = int(os.getenv('MEDICAL_AI_CACHE_TTL', 300))
    
    @classmethod
    def is_azure_configured(cls) -> bool:
        return all([
            cls.AZURE_OPENAI_ENDPOINT,
            cls.AZURE_OPENAI_API_KEY,
            cls.AZURE_OPENAI_DEPLOYMENT
        ])

# Data Models
class FieldMappingRequest(BaseModel):
    context: Dict[str, Any]
    optimization_level: str = Field(default="standard", description="Optimization level: standard, high")
    confidence_threshold: float = Field(default=0.7, description="Minimum confidence threshold")

class EnhanceMappingResponse(BaseModel):
    enhanced_fields: Dict[str, Any]
    confidence: float
    method: str
    recommendations: List[str]
    field_confidence: Dict[str, float] = Field(default_factory=dict)

class HealthResponse(BaseModel):
    status: str
    azure_configured: bool
    knowledge_base_loaded: bool
    timestamp: str

class TestResponse(BaseModel):
    status: str
    version: str
    features: List[str]
    azure_configured: bool

# Global cache
cache = TTLCache(maxsize=1000, ttl=Config.CACHE_TTL)

# Initialize Azure OpenAI client
azure_client = None
if Config.is_azure_configured():
    try:
        from typing import cast
        azure_client = AzureOpenAI(
            azure_endpoint=cast(str, Config.AZURE_OPENAI_ENDPOINT),
            api_key=Config.AZURE_OPENAI_API_KEY,
            api_version=Config.AZURE_OPENAI_API_VERSION
        )
        logger.info("Azure OpenAI client initialized successfully")
    except Exception as e:
        logger.error(f"Failed to initialize Azure OpenAI client: {e}")
else:
    logger.warning("Azure OpenAI not configured - service will run in fallback mode")

# FastAPI app
app = FastAPI(
    title="Medical AI Service",
    description="AI-powered medical form field mapping and validation service",
    version="1.0.0"
)

# Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/health", response_model=HealthResponse)
async def health_check():
    """Health check endpoint"""
    return HealthResponse(
        status="healthy",
        azure_configured=Config.is_azure_configured(),
        knowledge_base_loaded=True,  # Always true for now
        timestamp=datetime.now().isoformat()
    )

@app.get("/api/v1/test", response_model=TestResponse)
async def test_endpoint():
    """Test endpoint for connectivity"""
    return TestResponse(
        status="ok",
        version="1.0.0",
        features=["field_mapping", "medical_validation", "fhir_integration"],
        azure_configured=Config.is_azure_configured()
    )

@app.post("/api/v1/enhance-mapping", response_model=EnhanceMappingResponse)
async def enhance_mapping(request: FieldMappingRequest):
    """Enhanced field mapping using AI"""
    try:
        logger.info(f"Received enhance-mapping request with optimization level: {request.optimization_level}")
        
        # Create cache key
        cache_key = f"enhance_mapping_{hash(json.dumps(request.context, sort_keys=True))}"
        
        # Check cache first
        if cache_key in cache:
            logger.info("Returning cached result")
            return cache[cache_key]
        
        if not azure_client:
            logger.warning("Azure OpenAI not available, using fallback")
            return get_fallback_enhancement(request.context)
        
        # Extract context data
        episode_data = request.context.get('episode', {})
        fhir_context = request.context.get('fhir_context', {})
        base_data = request.context.get('base_data', {})
        template_structure = request.context.get('template_structure', {})
        manufacturer_context = request.context.get('manufacturer_context', {})
        
        # Build AI prompt
        system_prompt = build_system_prompt(manufacturer_context, template_structure)
        user_prompt = build_user_prompt(base_data, fhir_context, episode_data, template_structure)
        
        # Call Azure OpenAI
        response = azure_client.chat.completions.create(
            model=Config.AZURE_OPENAI_DEPLOYMENT,
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt}
            ],
            temperature=0.1,
            max_tokens=2000
        )
        
        # Parse AI response
        ai_content = response.choices[0].message.content
        enhanced_fields, confidence, field_confidence = parse_ai_response(ai_content, base_data, template_structure)
        
        result = EnhanceMappingResponse(
            enhanced_fields=enhanced_fields,
            confidence=confidence,
            method="ai_enhanced",
            recommendations=generate_recommendations(enhanced_fields, fhir_context),
            field_confidence=field_confidence
        )
        
        # Cache the result
        cache[cache_key] = result
        
        logger.info(f"AI enhancement completed with confidence: {confidence}")
        return result
        
    except Exception as e:
        logger.error(f"Error in enhance-mapping: {e}")
        # Return fallback on error
        return get_fallback_enhancement(request.context)

def build_system_prompt(manufacturer_context: Dict, template_structure: Dict) -> str:
    """Build system prompt for AI with dynamic template support"""
    manufacturer_name = manufacturer_context.get('name', 'Unknown')
    
    # Check if we have dynamic template fields
    template_fields = template_structure.get('template_fields', {})
    if template_fields and 'field_names' in template_fields:
        # Dynamic template mode
        field_names = template_fields['field_names']
        required_fields = template_fields.get('required_fields', [])
        field_types = template_fields.get('field_types', {})
        
        template_info = f"""
TEMPLATE FIELDS (Exact names from DocuSeal):
{', '.join(field_names)}

REQUIRED FIELDS: {', '.join(required_fields) if required_fields else 'None specified'}

FIELD TYPES: {json.dumps(field_types, indent=2) if field_types else 'Not specified'}"""
    else:
        # Fallback to old mode
        required_fields = manufacturer_context.get('requirements', {}).get('required_fields', [])
        template_info = f"REQUIRED FIELDS: {', '.join(required_fields) if required_fields else 'None specified'}"
    
    return f"""You are an expert medical AI assistant specializing in insurance verification request (IVR) forms for wound care products.

Your task is to intelligently map and enhance form fields using clinical data, patient information, and FHIR resources.

MANUFACTURER: {manufacturer_name}
{template_info}

FIELD MAPPING RULES:
1. **USE EXACT FIELD NAMES**: Only use field names that exactly match the template field names provided above
2. Prioritize patient safety and clinical accuracy
3. Use FHIR data when available and reliable
4. Only fill fields you're confident about (>70% confidence)
5. Preserve existing data unless clearly incorrect
6. Use standard medical coding (ICD-10, CPT, HCPCS)
7. Format dates as YYYY-MM-DD
8. Format phone numbers as (XXX) XXX-XXXX
9. Use proper case for names and addresses

RESPONSE FORMAT:
Return a JSON object with:
- "enhanced_fields": object with field mappings using EXACT template field names
- "confidence": overall confidence score (0.0-1.0)
- "field_confidence": object with per-field confidence scores
- "recommendations": array of improvement suggestions

CRITICAL: Only use field names that exist in the template. Do not invent or modify field names."""

def build_user_prompt(base_data: Dict, fhir_context: Dict, episode_data: Dict, template_structure: Dict = None) -> str:
    """Build user prompt with context data and template information"""
    
    prompt_parts = [
        "Please enhance the following medical form fields using the provided context:",
        "",
        "CURRENT FORM DATA:",
        json.dumps(base_data, indent=2),
        "",
        "FHIR CONTEXT:",
        json.dumps(fhir_context, indent=2),
        "",
        "EPISODE INFORMATION:",
        json.dumps(episode_data, indent=2),
    ]
    
    # Add template field information if available
    template_fields = template_structure.get('template_fields', {}) if template_structure else {}
    if template_fields and 'field_names' in template_fields:
        prompt_parts.extend([
            "",
            "TEMPLATE FIELD MAPPING GUIDANCE:",
            f"Available template fields: {', '.join(template_fields['field_names'])}",
            f"Required fields: {', '.join(template_fields.get('required_fields', []))}",
            "",
            "IMPORTANT: Map data to the exact field names listed above. Do not create new field names."
        ])
    
    prompt_parts.extend([
        "",
        "Please provide enhanced field mappings with high confidence, focusing on:",
        "1. Patient demographics and contact information",
        "2. Provider and facility information", 
        "3. Clinical details and diagnosis codes",
        "4. Insurance and coverage information",
        "5. Wound assessment and treatment details",
        "",
        "Remember: Use only the exact template field names provided above."
    ])
    
    return "\n".join(prompt_parts)

def parse_ai_response(ai_content: str, base_data: Dict, template_structure: Dict) -> tuple:
    """Parse AI response and extract field mappings"""
    try:
        # Try to extract JSON from AI response
        json_start = ai_content.find('{')
        json_end = ai_content.rfind('}') + 1
        
        if json_start >= 0 and json_end > json_start:
            json_str = ai_content[json_start:json_end]
            ai_data = json.loads(json_str)
            
            enhanced_fields = ai_data.get('enhanced_fields', {})
            confidence = ai_data.get('confidence', 0.5)
            field_confidence = ai_data.get('field_confidence', {})
            
            # Validate enhanced fields with template support
            validated_fields = validate_enhanced_fields(enhanced_fields, base_data, template_structure)
            
            return validated_fields, confidence, field_confidence
            
    except Exception as e:
        logger.warning(f"Failed to parse AI response: {e}")
    
    # Fallback to basic enhancement
    return perform_basic_enhancement(base_data), 0.3, {}

def validate_enhanced_fields(enhanced_fields: Dict, base_data: Dict, template_structure: Dict = None) -> Dict:
    """Validate and sanitize enhanced fields with template support"""
    validated = {}
    
    # Get template field names if available
    template_fields = template_structure.get('template_fields', {}) if template_structure else {}
    valid_field_names = template_fields.get('field_names', []) if template_fields else []
    field_types = template_fields.get('field_types', {}) if template_fields else {}
    
    for field, value in enhanced_fields.items():
        if value is None or value == "":
            continue
        
        # Check if field name is valid (if we have template info)
        if valid_field_names and field not in valid_field_names:
            logger.warning(f"Skipping invalid field name: {field} (not in template)")
            continue
            
        # Get field type from template or infer from field name
        field_type = field_types.get(field, 'text').lower()
        
        # Validate based on field type
        if field_type in ['date'] or 'date' in field.lower() or 'dob' in field.lower():
            validated_value = validate_date(value)
        elif field_type in ['phone', 'tel'] or 'phone' in field.lower():
            validated_value = validate_phone(value)
        elif field_type in ['email'] or 'email' in field.lower():
            validated_value = validate_email(value)
        elif field_type in ['checkbox', 'radio']:
            validated_value = validate_checkbox_value(value)
        else:
            validated_value = sanitize_text_value(value)
        
        if validated_value is not None:
            validated[field] = validated_value
    
    return validated

def validate_checkbox_value(value) -> bool:
    """Validate checkbox/radio button values"""
    if isinstance(value, bool):
        return value
    if isinstance(value, str):
        return value.lower() in ['true', 'yes', '1', 'on', 'checked']
    return bool(value)

def sanitize_text_value(value) -> str:
    """Sanitize text values"""
    if value is None:
        return ""
    return str(value).strip()

def validate_date(date_str: str) -> Optional[str]:
    """Validate and format date"""
    if not date_str:
        return None
    
    try:
        # Try parsing various date formats
        for fmt in ['%Y-%m-%d', '%m/%d/%Y', '%m-%d-%Y', '%Y/%m/%d']:
            try:
                dt = datetime.strptime(str(date_str), fmt)
                return dt.strftime('%Y-%m-%d')
            except ValueError:
                continue
        return None
    except:
        return None

def validate_phone(phone_str: str) -> Optional[str]:
    """Validate and format phone number"""
    if not phone_str:
        return None
    
    # Remove all non-numeric characters
    digits = re.sub(r'\D', '', str(phone_str))
    
    if len(digits) == 10:
        return f"({digits[:3]}) {digits[3:6]}-{digits[6:]}"
    elif len(digits) == 11 and digits[0] == '1':
        return f"({digits[1:4]}) {digits[4:7]}-{digits[7:]}"
    
    return None

def validate_email(email_str: str) -> Optional[str]:
    """Validate email address"""
    if not email_str:
        return None
    
    email_pattern = r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'
    if re.match(email_pattern, str(email_str)):
        return str(email_str).lower()
    
    return None

def perform_basic_enhancement(base_data: Dict) -> Dict:
    """Perform basic field enhancement without AI"""
    enhanced = {}
    
    # Basic field mappings
    field_mappings = {
        'patient_first_name': ['first_name', 'firstname', 'fname'],
        'patient_last_name': ['last_name', 'lastname', 'lname'],
        'patient_dob': ['dob', 'date_of_birth', 'birthdate'],
        'patient_phone': ['phone', 'phone_number', 'telephone'],
        'patient_email': ['email', 'email_address'],
    }
    
    for target_field, source_fields in field_mappings.items():
        for source_field in source_fields:
            if source_field in base_data and base_data[source_field]:
                enhanced[target_field] = base_data[source_field]
                break
    
    return enhanced

def generate_recommendations(enhanced_fields: Dict, fhir_context: Dict) -> List[str]:
    """Generate recommendations for form completion"""
    recommendations = []
    
    # Check for missing critical fields
    critical_fields = ['patient_first_name', 'patient_last_name', 'patient_dob']
    missing_critical = [field for field in critical_fields if field not in enhanced_fields]
    
    if missing_critical:
        recommendations.append(f"Missing critical patient information: {', '.join(missing_critical)}")
    
    # Check for FHIR data utilization
    if fhir_context.get('patient'):
        recommendations.append("FHIR patient data available - consider manual review")
    
    return recommendations

def get_fallback_enhancement(context: Dict) -> EnhanceMappingResponse:
    """Get fallback enhancement when AI is not available"""
    base_data = context.get('base_data', {})
    enhanced_fields = perform_basic_enhancement(base_data)
    
    return EnhanceMappingResponse(
        enhanced_fields=enhanced_fields,
        confidence=0.4,
        method="fallback",
        recommendations=["AI service unavailable - using basic field mapping"],
        field_confidence={}
    )

if __name__ == "__main__":
    logger.info(f"Starting Medical AI Service on port {Config.PORT}")
    logger.info(f"Azure OpenAI configured: {Config.is_azure_configured()}")
    
    uvicorn.run(
        "medical_ai_service:app",
        host="127.0.0.1",
        port=Config.PORT,
        reload=Config.DEBUG,
        log_level="info" if not Config.DEBUG else "debug"
    )
