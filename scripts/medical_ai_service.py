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
import subprocess
import csv  # Added for CSV loading

# Load environment variables from .env file if it exists
try:
    from dotenv import load_dotenv
    # Load from .env file in the .venv directory
    env_path = Path(__file__).parent / '.venv' / '.env'
    if env_path.exists():
        load_dotenv(env_path)
        print(f"Loaded environment variables from {env_path}")
    else:
        # Fallback to parent directory (Laravel root)
        env_path = Path(__file__).parent.parent / '.env'
        if env_path.exists():
            load_dotenv(env_path)
            print(f"Loaded environment variables from {env_path}")
except ImportError:
    print("python-dotenv not installed, skipping .env file loading")

# DocuSeal integration
try:
    import docuseal
    DOCUSEAL_AVAILABLE = True
    print("DocuSeal package imported successfully")
except ImportError:
    DOCUSEAL_AVAILABLE = False
    print("DocuSeal package not installed")

import uvicorn
from fastapi import FastAPI, HTTPException, Depends, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.middleware.trustedhost import TrustedHostMiddleware
from pydantic import BaseModel, Field, validator
from openai import AzureOpenAI
from cachetools import TTLCache
from slowapi import Limiter, _rate_limit_exceeded_handler
from slowapi.util import get_remote_address
from slowapi.errors import RateLimitExceeded
from starlette.responses import JSONResponse
from starlette.middleware.base import BaseHTTPMiddleware, RequestResponseCall
import time

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Configuration
class Config:
    AZURE_OPENAI_ENDPOINT = os.getenv('AZURE_OPENAI_ENDPOINT')
    AZURE_OPENAI_API_KEY = os.getenv('AZURE_OPENAI_API_KEY')
    AZURE_OPENAI_DEPLOYMENT = os.getenv('AZURE_OPENAI_DEPLOYMENT_NAME', os.getenv('AZURE_OPENAI_DEPLOYMENT', 'gpt-4'))
    AZURE_OPENAI_API_VERSION = os.getenv('AZURE_OPENAI_API_VERSION', '2024-02-15-preview')
    
    # DocuSeal configuration
    DOCUSEAL_API_KEY = os.getenv('DOCUSEAL_API_KEY')
    DOCUSEAL_BASE_URL = os.getenv('DOCUSEAL_BASE_URL', 'https://api.docuseal.com')
    
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
    
    @classmethod
    def is_docuseal_configured(cls) -> bool:
        return cls.DOCUSEAL_API_KEY is not None

# Initialize DocuSeal client
docuseal_client = None
if DOCUSEAL_AVAILABLE and Config.is_docuseal_configured():
    try:
        docuseal_client = docuseal.Client(
            api_key=Config.DOCUSEAL_API_KEY,
            base_url=Config.DOCUSEAL_BASE_URL
        )
        logger.info("DocuSeal client initialized successfully")
    except Exception as e:
        logger.error(f"Failed to initialize DocuSeal client: {e}")
else:
    logger.warning("DocuSeal not configured - DocuSeal features will be unavailable")

# Data Models
class FieldMappingRequest(BaseModel):
    context: Dict[str, Any]
    optimization_level: str = Field(default="standard", description="Optimization level: standard, high")
    confidence_threshold: float = Field(default=0.6, description="Minimum confidence threshold")

class EnhanceMappingResponse(BaseModel):
    enhanced_fields: Dict[str, Any]
    confidence: float
    method: str
    recommendations: List[str]
    field_confidence: Dict[str, float] = Field(default_factory=dict)

class HealthResponse(BaseModel):
    status: str
    azure_configured: bool
    docuseal_configured: bool
    docuseal_available: bool
    knowledge_base_loaded: bool
    timestamp: str

class TestResponse(BaseModel):
    status: str
    version: str
    features: List[str]
    azure_configured: bool
    docuseal_configured: bool

class DocuSealTemplateRequest(BaseModel):
    template_id: str

class DocuSealTemplateResponse(BaseModel):
    template_id: str
    template_name: str
    field_names: List[str]
    field_details: Dict[str, Any]
    total_fields: int

class DocuSealSubmissionRequest(BaseModel):
    template_id: str
    submitter_email: str
    submitter_name: str
    field_values: Dict[str, Any]
    metadata: Optional[Dict[str, Any]] = None

class DocuSealSubmissionResponse(BaseModel):
    submission_id: str
    status: str
    form_url: Optional[str]
    created_at: str
    fields_submitted: int

# Custom exception for API input errors
class ApiInputError(Exception):
    def __init__(self, message: str, status_code: int = 422):
        self.message = message
        self.status_code = status_code
        super().__init__(self.message)

# Error handler for API input errors
async def api_error_handler(request: Request, exc: ApiInputError):
    return JSONResponse(
        status_code=exc.status_code,
        content={"detail": exc.message}
    )

# Global cache
cache = TTLCache(maxsize=1000, ttl=Config.CACHE_TTL)

# Manufacturer config cache
manufacturer_configs_cache = {}

# Initialize rate limiter
limiter = Limiter(key_func=get_remote_address)

def load_manufacturer_config(manufacturer_name: str) -> Optional[Dict[str, Any]]:
    """Load manufacturer config from Laravel config files"""
    if manufacturer_name in manufacturer_configs_cache:
        return manufacturer_configs_cache[manufacturer_name]
    
    try:
        # Convert manufacturer name to filename format
        filename = manufacturer_name.lower().replace(' ', '-').replace('_', '-')
        config_path = Path(__file__).parent.parent / 'config' / 'manufacturers' / f'{filename}.php'
        
        if not config_path.exists():
            logger.warning(f"Manufacturer config not found: {config_path}")
            return None
        
        # Use PHP to parse the config file
        php_command = f"php -r \"echo json_encode(require '{config_path}');\""
        result = subprocess.run(php_command, shell=True, capture_output=True, text=True)
        
        if result.returncode != 0:
            logger.error(f"Failed to parse manufacturer config: {result.stderr}")
            return None
        
        config = json.loads(result.stdout)
        manufacturer_configs_cache[manufacturer_name] = config
        
        logger.info(f"Loaded manufacturer config for {manufacturer_name}", {
            'fields_count': len(config.get('fields', {})),
            'docuseal_fields_count': len(config.get('docuseal_field_names', {}))
        })
        
        return config
        
    except Exception as e:
        logger.error(f"Error loading manufacturer config for {manufacturer_name}: {e}")
        return None

def get_manufacturer_field_mappings(manufacturer_name: str) -> tuple[Dict[str, str], Dict[str, Any]]:
    """Get field mappings for a specific manufacturer"""
    config = load_manufacturer_config(manufacturer_name)
    if not config:
        return {}, {}
    
    # Get both docuseal field names and field mappings
    docuseal_field_names = config.get('docuseal_field_names', {})
    field_mappings = config.get('fields', {})
    
    return docuseal_field_names, field_mappings

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

# Add rate limit error handler
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)
app.add_exception_handler(ApiInputError, api_error_handler)

# Error handling middleware for unhandled exceptions
class ErrorHandlerMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next: RequestResponseCall):
        try:
            return await call_next(request)
        except Exception as e:
            logger.error(f"Unhandled exception: {e}", exc_info=True)
            return JSONResponse(
                status_code=500,
                content={"detail": "An internal server error occurred."}
            )

app.add_middleware(ErrorHandlerMiddleware)

# Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Add trusted host middleware for security
if Config.AZURE_OPENAI_ENDPOINT:
    app.add_middleware(
        TrustedHostMiddleware,
        allowed_hosts=["localhost", "127.0.0.1", "*.mscwoundcare.com"]
    )

# Request logging middleware
@app.middleware("http")
async def log_requests(request: Request, call_next):
    start_time = time.time()
    response = await call_next(request)
    process_time = time.time() - start_time
    logger.info(f"{request.method} {request.url.path} - {response.status_code} - {process_time:.3f}s")
    return response

@app.get("/health", response_model=HealthResponse)
async def health_check():
    """Health check endpoint"""
    return HealthResponse(
        status="healthy",
        azure_configured=Config.is_azure_configured(),
        docuseal_configured=Config.is_docuseal_configured(),
        docuseal_available=DOCUSEAL_AVAILABLE,
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
        azure_configured=Config.is_azure_configured(),
        docuseal_configured=Config.is_docuseal_configured()
    )

@app.post("/api/v1/enhance-mapping", response_model=EnhanceMappingResponse)
@limiter.limit("10/minute")
async def enhance_mapping(request: FieldMappingRequest, req: Request):
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

        # Validate required context
        if not base_data:
            raise ApiInputError("Missing 'base_data' in request context.")
        if not template_structure:
            raise ApiInputError("Missing 'template_structure' in request context.")
        if not manufacturer_context:
            raise ApiInputError("Missing 'manufacturer_context' in request context.")
        
        # Build AI prompt
        system_prompt = build_system_prompt(manufacturer_context, template_structure)
        user_prompt = build_user_prompt(base_data, fhir_context, episode_data, template_structure, manufacturer_context.get('name'))
        
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
        enhanced_fields, confidence, field_confidence = parse_ai_response(
            ai_content, base_data, template_structure, manufacturer_context.get('name')
        )
        
        result = EnhanceMappingResponse(
            enhanced_fields=enhanced_fields,
            confidence=confidence,
            method="ai_enhanced",
            recommendations=generate_recommendations(enhanced_fields, fhir_context),
            field_confidence=field_confidence
        )
        
        # Cache the result
        cache[cache_key] = result
        
        # Log detailed field mapping results
        logger.info("AI field mapping completed", {
            'manufacturer': manufacturer_context.get('name', 'Unknown'),
            'confidence': confidence,
            'method': result.method,
            'total_fields': len(enhanced_fields),
            'base_fields': len(base_data),
            'enhanced_fields': len(enhanced_fields) - len(base_data),
            'field_confidence_avg': sum(field_confidence.values()) / len(field_confidence) if field_confidence else 0,
            'sample_mappings': list(enhanced_fields.items())[:5],
            'optimization_level': request.optimization_level
        })
        
        return result
        
    except Exception as e:
        logger.error(f"Error in enhance-mapping: {e}")
        # Return fallback on error
        return get_fallback_enhancement(request.context)

@app.get("/api/v1/docuseal/template/{template_id}/fields", response_model=DocuSealTemplateResponse)
async def get_docuseal_template_fields(template_id: str):
    """Get field names and structure from a DocuSeal template"""
    if not docuseal_client:
        raise HTTPException(status_code=503, detail="DocuSeal client not available")
    
    try:
        # Get template using DocuSeal Python package
        template = docuseal_client.get_template(template_id)
        
        field_names = []
        field_details = {}
        
        # Extract field information from template
        for field in template.get('fields', []):
            field_name = field.get('name')
            if field_name:
                field_names.append(field_name)
                field_details[field_name] = {
                    'type': field.get('type', 'text'),
                    'required': field.get('required', False),
                    'uuid': field.get('uuid'),
                    'options': field.get('options', [])
                }
        
        logger.info(f"Retrieved {len(field_names)} fields from DocuSeal template {template_id}")
        
        return DocuSealTemplateResponse(
            template_id=template_id,
            template_name=template.get('name', 'Unknown'),
            field_names=field_names,
            field_details=field_details,
            total_fields=len(field_names)
        )
        
    except Exception as e:
        logger.error(f"Error getting DocuSeal template fields: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to get template fields: {str(e)}")

@app.post("/api/v1/docuseal/submissions", response_model=DocuSealSubmissionResponse)
async def create_docuseal_submission(request: DocuSealSubmissionRequest):
    """Create a DocuSeal submission with pre-filled data"""
    if not docuseal_client:
        raise HTTPException(status_code=503, detail="DocuSeal client not available")
    
    try:
        # Clean field values - remove None/empty values
        cleaned_fields = {}
        for field_name, value in request.field_values.items():
            if value is not None and value != '':
                # Convert boolean values for DocuSeal checkboxes
                if isinstance(value, bool):
                    cleaned_fields[field_name] = value
                else:
                    cleaned_fields[field_name] = str(value)
        
        # Create submission using DocuSeal Python package
        submission_data = {
            'template_id': int(request.template_id),
            'submitters': [{
                'email': request.submitter_email,
                'name': request.submitter_name,
                'role': 'First Party'
            }]
        }
        
        # Add field values directly to submitters
        if cleaned_fields:
            submission_data['submitters'][0]['fields'] = [
                {
                    'name': field_name,
                    'default_value': field_value
                } for field_name, field_value in cleaned_fields.items()
            ]
        
        # Add metadata if provided
        if request.metadata:
            submission_data['metadata'] = request.metadata
        
        # Try the correct method based on DocuSeal Python SDK
        try:
            # First try create_submission method (newer SDK)
            result = docuseal_client.create_submission(submission_data)
        except AttributeError:
            # Fallback to submissions.create if using different SDK version
            try:
                result = docuseal_client.submissions.create(submission_data)
            except Exception as e:
                logger.error(f"Failed with both submission methods: {e}")
                # Try direct API call as last resort
                import requests
                headers = {
                    'Authorization': f'Bearer {Config.DOCUSEAL_API_KEY}',
                    'Content-Type': 'application/json'
                }
                response = requests.post(
                    f'{Config.DOCUSEAL_BASE_URL}/api/submissions',
                    json=submission_data,
                    headers=headers
                )
                response.raise_for_status()
                result = response.json()
        
        logger.info(f"DocuSeal submission created successfully: {result.get('id')}")
        
        # Extract submission details
        submission_id = result.get('id', '')
        submitters = result.get('submitters', [])
        form_url = submitters[0].get('url') if submitters else None
        
        return DocuSealSubmissionResponse(
            submission_id=str(submission_id),
            status=result.get('status', 'created'),
            form_url=form_url,
            created_at=result.get('created_at', datetime.now().isoformat()),
            fields_submitted=len(cleaned_fields)
        )
        
    except Exception as e:
        logger.error(f"Error creating DocuSeal submission: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to create submission: {str(e)}")

@app.get("/api/v1/docuseal/test")
async def test_docuseal_connection():
    """Test DocuSeal API connectivity"""
    if not docuseal_client:
        return {
            'success': False,
            'message': 'DocuSeal client not available',
            'docuseal_available': DOCUSEAL_AVAILABLE,
            'docuseal_configured': Config.is_docuseal_configured()
        }
    
    try:
        # Test connection by listing templates
        templates = docuseal_client.list_submissions({'limit': 1})
        
        return {
            'success': True,
            'message': 'DocuSeal API connection successful',
            'docuseal_available': DOCUSEAL_AVAILABLE,
            'docuseal_configured': Config.is_docuseal_configured(),
            'templates_accessible': True
        }
        
    except Exception as e:
        return {
            'success': False,
            'message': f'DocuSeal API connection failed: {str(e)}',
            'docuseal_available': DOCUSEAL_AVAILABLE,
            'docuseal_configured': Config.is_docuseal_configured(),
            'error': str(e)
        }

@app.post("/api/v1/docuseal/enhance-and-submit")
async def enhance_and_submit_to_docuseal(
    template_id: str,
    base_data: Dict[str, Any],
    submitter_email: str,
    submitter_name: str,
    manufacturer_name: Optional[str] = None,
    metadata: Optional[Dict[str, Any]] = None
):
    """Enhanced workflow: AI enhance fields then submit to DocuSeal"""
    if not docuseal_client:
        raise HTTPException(status_code=503, detail="DocuSeal client not available")
    
    try:
        # First, get template fields from DocuSeal
        template_fields_response = await get_docuseal_template_fields(template_id)
        template_fields = template_fields_response.field_names
        
        # Build context for AI enhancement
        context = {
            'base_data': base_data,
            'template_structure': {
                'template_fields': {
                    'field_names': template_fields,
                    'field_types': template_fields_response.field_details
                }
            },
            'manufacturer_context': {
                'name': manufacturer_name or 'Unknown'
            }
        }
        
        # Use AI to enhance field mapping
        enhance_request = FieldMappingRequest(
            context=context,
            optimization_level="high",
            confidence_threshold=0.6
        )
        
        enhancement_result = await enhance_mapping(enhance_request)
        
        # Create DocuSeal submission with enhanced fields
        submission_request = DocuSealSubmissionRequest(
            template_id=template_id,
            submitter_email=submitter_email,
            submitter_name=submitter_name,
            field_values=enhancement_result.enhanced_fields,
            metadata={
                **(metadata or {}),
                'ai_enhanced': True,
                'ai_confidence': enhancement_result.confidence,
                'ai_method': enhancement_result.method,
                'manufacturer': manufacturer_name,
                'enhanced_at': datetime.now().isoformat()
            }
        )
        
        submission_result = await create_docuseal_submission(submission_request)
        
        return {
            'template_info': {
                'template_id': template_id,
                'total_fields': template_fields_response.total_fields,
                'field_names': template_fields
            },
            'enhancement_result': {
                'enhanced_fields': enhancement_result.enhanced_fields,
                'confidence': enhancement_result.confidence,
                'method': enhancement_result.method,
                'recommendations': enhancement_result.recommendations,
                'fields_enhanced': len(enhancement_result.enhanced_fields)
            },
            'submission_result': {
                'submission_id': submission_result.submission_id,
                'status': submission_result.status,
                'form_url': submission_result.form_url,
                'fields_submitted': submission_result.fields_submitted
            },
            'success': True
        }
        
    except Exception as e:
        logger.error(f"Error in enhance-and-submit workflow: {e}")
        raise HTTPException(status_code=500, detail=f"Workflow failed: {str(e)}")

def build_system_prompt(manufacturer_context: Dict, template_structure: Dict) -> str:
    """Build a more structured and clearer system prompt for the AI."""
    manufacturer_name = manufacturer_context.get('name', 'Unknown')
    
    # Extract template information
    template_fields = template_structure.get('template_fields', {})
    field_names = template_fields.get('field_names', [])
    required_fields = template_fields.get('required_fields', [])
    field_types = template_fields.get('field_types', {})
    
    # Load manufacturer-specific mapping guide if available
    docuseal_field_names, _ = get_manufacturer_field_mappings(manufacturer_name)
    
    field_mapping_guide = ""
    if docuseal_field_names:
        field_mapping_guide = f"""
### Manufacturer Field Mapping Guide
This guide shows how your internal system fields map to the official DocuSeal fields. Use it to ensure correct mapping.
{json.dumps(docuseal_field_names, indent=2)}
"""

    return f"""You are a meticulous and highly accurate AI data processor for a wound care company. Your purpose is to pre-fill Insurance Verification Request (IVR) forms for the manufacturer '{manufacturer_name}'.

### Primary Directive
Accurately map the provided data (base, FHIR, episode) to the official manufacturer form fields. Precision is critical.

### Data Hierarchy (Sources of Truth)
1.  **FHIR Context**: Highest priority. Use this for patient demographics, clinical data, and provider information.
2.  **Episode Data**: Contains specific details about the current request.
3.  **Base Data**: The initial data provided by the user. Use this as a fallback.

### Official Form Fields
This is the definitive list of fields you can populate. **DO NOT use any field name not on this list.**

**Available Fields**:
{json.dumps(field_names, indent=2)}

**Required Fields**:
{json.dumps(required_fields, indent=2)}

**Field Data Types**:
{json.dumps(field_types, indent=2)}

{field_mapping_guide}
### Core Rules
1.  **Exact Field Names**: You MUST use the exact field names from the "Available Fields" list.
2.  **Data Formatting**: Adhere strictly to these formats:
    -   **Dates**: `MM/DD/YYYY`
    -   **Phone Numbers**: `(XXX) XXX-XXXX`
    -   **Checkboxes**: `true` or `false` (boolean, not string).
3.  **Confidence**: Only fill a field if you are at least 80% confident. Do not guess.
4.  **No Invention**: Do not invent data. If a value is not available in the source data, leave the field null.
5.  **Calculations**: If a field requires a calculation (e.g., combining wound dimensions), perform it accurately.

### Output Format
Your final output MUST be a single, valid JSON object with the following structure:
{{
  "enhanced_fields": {{ ... }},
  "confidence": 0.0-1.0,
  "field_confidence": {{ ... }},
  "recommendations": [ ... ]
}}
"""

def build_user_prompt(base_data: Dict, fhir_context: Dict, episode_data: Dict, template_structure: Optional[Dict] = None, manufacturer_name: Optional[str] = None) -> str:
    """Build a clear and concise user prompt focused on providing data."""
    
    # The user prompt's only job is to provide the data clearly.
    # All instructions are now in the system prompt.
    return f"""Please process the following data to pre-fill the IVR form.

### 1. Base Data (from user input)
{json.dumps(base_data, indent=2)}

### 2. FHIR Context (from health records)
{json.dumps(fhir_context, indent=2)}

### 3. Episode Data (specific to this request)
{json.dumps(episode_data, indent=2)}

Based on the instructions and form fields provided in the system prompt, generate the final JSON output.
"""

def parse_ai_response(ai_content: str, base_data: Dict, template_structure: Dict, manufacturer_name: Optional[str] = None) -> tuple:
    """Parse AI response and extract field mappings using a robust regex method."""
    try:
        # Use regex to find the JSON object within the AI's response
        match = re.search(r'\{.*\}', ai_content, re.DOTALL)
        if not match:
            logger.warning("No JSON object found in AI response.", {'ai_response_snippet': ai_content[:200]})
            raise ValueError("No JSON object found in AI response.")

        json_str = match.group(0)
        ai_data = json.loads(json_str)

        enhanced_fields = ai_data.get('enhanced_fields')
        if not isinstance(enhanced_fields, dict):
            raise TypeError("'enhanced_fields' is not a dictionary.")

        confidence = ai_data.get('confidence', 0.5)
        field_confidence = ai_data.get('field_confidence', {})

        logger.info("AI response parsed successfully", {
            'manufacturer': manufacturer_name,
            'fields_count': len(enhanced_fields),
            'confidence': confidence
        })

        validated_fields = validate_enhanced_fields(enhanced_fields, base_data, template_structure, manufacturer_name)
        return validated_fields, confidence, field_confidence

    except (json.JSONDecodeError, ValueError, TypeError) as e:
        logger.error(f"Failed to parse or validate AI response: {e}", {
            'manufacturer': manufacturer_name,
            'ai_response_snippet': ai_content[:500] # Log a larger snippet on error
        })
    
    # Fallback to basic enhancement on any parsing/validation error
    logger.info("Falling back to basic enhancement due to parsing error.")
    return perform_basic_enhancement(base_data, manufacturer_name), 0.3, {}

def validate_enhanced_fields(enhanced_fields: Dict, base_data: Dict, template_structure: Optional[Dict] = None, manufacturer_name: Optional[str] = None) -> Dict:
    """Validate and sanitize enhanced fields using explicit type definitions from the template."""
    validated = {}
    stats = {'total': 0, 'valid': 0, 'invalid': 0, 'skipped': 0, 'mismatch': 0}
    invalid_details = []

    template = template_structure.get('template_fields', {})
    valid_field_names = set(template.get('field_names', []))
    field_types = template.get('field_types', {})

    if not valid_field_names:
        logger.warning("No valid field names found in template structure for validation.")
        # In this case, we can't validate field names, so we trust the AI's output.
        valid_field_names = set(enhanced_fields.keys()) 

    for field, value in enhanced_fields.items():
        stats['total'] += 1
        if value is None or str(value).strip() == "":
            stats['skipped'] += 1
            continue

        if field not in valid_field_names:
            stats['mismatch'] += 1
            invalid_details.append({'field': field, 'value': value, 'reason': 'Field name not in template'})
            continue

        # Get the explicit field type; default to 'text'
        field_type = field_types.get(field, 'text').lower()
        validated_value = None

        try:
            if field_type == 'date':
                validated_value = validate_date(value)
            elif field_type == 'phone':
                validated_value = validate_phone(value)
            elif field_type == 'email':
                validated_value = validate_email(value)
            elif field_type in ['checkbox', 'radio']:
                validated_value = validate_checkbox_value(value)
            else: # Default to text
                validated_value = sanitize_text_value(value)

            if validated_value is not None:
                validated[field] = validated_value
                stats['valid'] += 1
            else:
                stats['invalid'] += 1
                invalid_details.append({'field': field, 'value': value, 'reason': f'Invalid format for type {field_type}'})

        except Exception as e:
            stats['invalid'] += 1
            invalid_details.append({'field': field, 'value': value, 'reason': f'Validation error: {e}'})

    # Log summary
    success_rate = (stats['valid'] / stats['total'] * 100) if stats['total'] > 0 else 0
    logger.info("Field validation complete", {
        'manufacturer': manufacturer_name,
        'stats': stats,
        'success_rate': f"{success_rate:.1f}%"
    })

    if invalid_details:
        logger.warning("Invalid fields detected during validation", {
            'manufacturer': manufacturer_name,
            'invalid_count': len(invalid_details),
            'details': invalid_details[:10] # Log first 10 errors
        })

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

def perform_basic_enhancement(base_data: Dict, manufacturer_name: Optional[str] = None) -> Dict:
    enhanced = base_data.copy()
    
    # Load payers from CSV
    payers = {}
    try:
        with open('docs/data-and-reference/payers.csv', 'r') as f:
            reader = csv.DictReader(f)
            for row in reader:
                if 'id' in row and 'name' in row:
                    payers[row['id']] = row['name']
    except FileNotFoundError:
        logger.warning("payers.csv not found, skipping insurance auto-fill")
    
    # Auto-fill insurance name if missing but payer_id present
    if 'insurance_name' not in enhanced and 'payer_id' in enhanced:
        enhanced['insurance_name'] = payers.get(enhanced['payer_id'], 'Unknown Payer')
    
    # Try to get manufacturer-specific mappings
    if manufacturer_name:
        docuseal_field_names, field_mappings = get_manufacturer_field_mappings(manufacturer_name)
        
        if field_mappings:
            # Use manufacturer-specific mappings
            for docuseal_key, field_name in docuseal_field_names.items():
                if docuseal_key in field_mappings:
                    mapping = field_mappings[docuseal_key]
                    source = mapping.get('source', '')
                    
                    if source == 'computed' and 'computation' in mapping:
                        # Handle computed fields
                        try:
                            # Simple computation handling (e.g., concatenation)
                            if '+' in mapping['computation']:
                                parts = mapping['computation'].split('+')
                                values = []
                                for part in parts:
                                    part = part.strip().strip('"')
                                    if part in base_data:
                                        values.append(str(base_data[part]))
                                    elif part == ' ':
                                        values.append(' ')
                                    elif part == ', ':
                                        values.append(', ')
                                if all(values):
                                    enhanced[field_name] = ''.join(values)
                        except Exception as e:
                            logger.warning(f"Failed to compute field {docuseal_key}: {e}")
                    elif source in base_data and base_data[source]:
                        # Direct mapping
                        value = base_data[source]
                        
                        # Apply transformation if specified
                        transform = mapping.get('transform')
                        if transform:
                            try:
                                if transform == 'date:m/d/Y' and value:
                                    # Convert date format
                                    dt = datetime.strptime(str(value), '%Y-%m-%d')
                                    value = dt.strftime('%m/%d/%Y')
                                elif transform == 'phone:US' and value:
                                    # Format phone number
                                    value = validate_phone(value)
                                elif transform == 'boolean:yes_no' and value is not None:
                                    # Convert boolean to yes/no
                                    value = 'YES' if value else 'NO'
                            except Exception as e:
                                logger.warning(f"Failed to transform field {docuseal_key}: {e}")
                        
                        if value is not None:
                            enhanced[field_name] = value
            
            return enhanced
    
    # Fallback to basic field mappings
    field_mappings = {
        'patient_first_name': ['first_name', 'firstname', 'fname', 'patient_first_name'],
        'patient_last_name': ['last_name', 'lastname', 'lname', 'patient_last_name'],
        'patient_dob': ['dob', 'date_of_birth', 'birthdate', 'patient_dob'],
        'patient_phone': ['phone', 'phone_number', 'telephone', 'patient_phone'],
        'patient_email': ['email', 'email_address', 'patient_email'],
        'patient_address': ['address', 'patient_address', 'patient_address_line1'],
        'patient_city': ['city', 'patient_city'],
        'patient_state': ['state', 'patient_state'],
        'patient_zip': ['zip', 'zip_code', 'patient_zip'],
        'primary_insurance': ['insurance_name', 'primary_insurance_name'],
        'primary_member_id': ['member_id', 'primary_member_id'],
        'provider_name': ['provider_name', 'physician_name', 'doctor_name'],
        'provider_npi': ['npi', 'provider_npi'],
        'facility_name': ['facility_name', 'practice_name', 'office_name'],
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
    manufacturer_context = context.get('manufacturer_context', {})
    manufacturer_name = manufacturer_context.get('name', 'Unknown')
    
    enhanced_fields = perform_basic_enhancement(base_data, manufacturer_name)
    
    # Generate field confidence scores
    field_confidence = {}
    for field in enhanced_fields:
        # Higher confidence for direct mappings from manufacturer config
        if manufacturer_name != 'Unknown':
            field_confidence[field] = 0.7
        else:
            field_confidence[field] = 0.5
    
    return EnhanceMappingResponse(
        enhanced_fields=enhanced_fields,
        confidence=0.6 if manufacturer_name != 'Unknown' else 0.4,
        method="fallback_with_config" if manufacturer_name != 'Unknown' else "fallback",
        recommendations=[
            "AI service unavailable - using manufacturer config field mapping" if manufacturer_name != 'Unknown' 
            else "AI service unavailable - using basic field mapping"
        ],
        field_confidence=field_confidence
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
