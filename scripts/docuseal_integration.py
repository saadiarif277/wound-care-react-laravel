#!/usr/bin/env python3
"""
DocuSeal Integration Enhancements for Medical AI Service
Adds live template field discovery and submission capabilities
"""

import os
import json
import logging
import asyncio
from typing import Dict, List, Optional, Any
from datetime import datetime
import httpx
from fastapi import HTTPException

logger = logging.getLogger(__name__)

class DocuSealIntegration:
    """DocuSeal API integration for the Medical AI Service"""
    
    def __init__(self):
        self.api_key = os.getenv('DOCUSEAL_API_KEY')
        self.base_url = os.getenv('DOCUSEAL_BASE_URL', 'https://api.docuseal.com')
        self.timeout = int(os.getenv('DOCUSEAL_TIMEOUT', 30))
        
        if not self.api_key:
            raise ValueError("DOCUSEAL_API_KEY environment variable is required")
    
    async def get_template_fields(self, template_id: str) -> Dict[str, Any]:
        """Fetch template structure and field names from DocuSeal API"""
        headers = {
            'X-Auth-Token': self.api_key,
            'Content-Type': 'application/json',
            'User-Agent': 'MSC-Medical-AI-Service/1.0'
        }
        
        try:
            async with httpx.AsyncClient(timeout=self.timeout) as client:
                response = await client.get(
                    f"{self.base_url}/templates/{template_id}",
                    headers=headers
                )
                
                if response.status_code == 404:
                    raise HTTPException(status_code=404, detail=f"DocuSeal template {template_id} not found")
                elif response.status_code == 401:
                    raise HTTPException(status_code=401, detail="DocuSeal authentication failed")
                elif not response.is_success:
                    raise HTTPException(status_code=response.status_code, detail=f"DocuSeal API error: {response.text}")
                
                template = response.json()
                
                field_names = []
                field_details = {}
                
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
                
                return {
                    'template_id': template_id,
                    'template_name': template.get('name', 'Unknown'),
                    'field_names': field_names,
                    'field_details': field_details,
                    'total_fields': len(field_names)
                }
                
        except httpx.TimeoutException:
            raise HTTPException(status_code=408, detail="DocuSeal API timeout")
        except httpx.RequestError as e:
            raise HTTPException(status_code=503, detail=f"DocuSeal API connection failed: {str(e)}")
        except Exception as e:
            logger.error(f"DocuSeal template retrieval failed: {e}")
            raise HTTPException(status_code=500, detail=str(e))
    
    async def submit_form(self, template_id: str, mapped_fields: Dict[str, Any], submitter_email: str, 
                         metadata: Optional[Dict] = None) -> Dict[str, Any]:
        """Submit mapped fields to DocuSeal for form creation"""
        headers = {
            'X-Auth-Token': self.api_key,
            'Content-Type': 'application/json',
            'User-Agent': 'MSC-Medical-AI-Service/1.0'
        }
        
        # Clean mapped fields - remove None/empty values and format properly
        cleaned_fields = {}
        for field_name, value in mapped_fields.items():
            if value is not None and value != '':
                # Convert boolean values for DocuSeal checkboxes
                if isinstance(value, bool):
                    cleaned_fields[field_name] = 'true' if value else 'false'
                else:
                    cleaned_fields[field_name] = str(value)
        
        payload = {
            'template_id': int(template_id),
            'submitters': [{
                'email': submitter_email,
                'role': 'First Party',
                'values': cleaned_fields
            }]
        }
        
        # Add metadata if provided
        if metadata:
            payload['metadata'] = metadata
        
        try:
            async with httpx.AsyncClient(timeout=self.timeout) as client:
                response = await client.post(
                    f"{self.base_url}/submissions",
                    headers=headers,
                    json=payload
                )
                
                if response.status_code == 422:
                    error_detail = response.json()
                    raise HTTPException(
                        status_code=422, 
                        detail=f"DocuSeal validation error: {error_detail}"
                    )
                elif not response.is_success:
                    raise HTTPException(
                        status_code=response.status_code, 
                        detail=f"DocuSeal submission failed: {response.text}"
                    )
                
                result = response.json()
                
                logger.info(f"DocuSeal submission created successfully: {result.get('id')}")
                
                return {
                    'submission_id': result.get('id'),
                    'status': result.get('status'),
                    'form_url': result.get('submissions', [{}])[0].get('url') if result.get('submissions') else None,
                    'created_at': result.get('created_at'),
                    'fields_submitted': len(cleaned_fields),
                    'template_id': template_id
                }
                
        except httpx.TimeoutException:
            raise HTTPException(status_code=408, detail="DocuSeal submission timeout")
        except httpx.RequestError as e:
            raise HTTPException(status_code=503, detail=f"DocuSeal API connection failed: {str(e)}")
        except Exception as e:
            logger.error(f"DocuSeal submission failed: {e}")
            raise HTTPException(status_code=500, detail=str(e))
    
    async def test_connection(self) -> Dict[str, Any]:
        """Test DocuSeal API connectivity"""
        try:
            headers = {
                'X-Auth-Token': self.api_key,
                'Content-Type': 'application/json'
            }
            
            async with httpx.AsyncClient(timeout=10) as client:
                response = await client.get(
                    f"{self.base_url}/templates?limit=1",
                    headers=headers
                )
                
                if response.is_success:
                    data = response.json()
                    return {
                        'success': True,
                        'message': 'DocuSeal API connection successful',
                        'templates_accessible': len(data.get('data', [])) > 0
                    }
                else:
                    return {
                        'success': False,
                        'message': f'DocuSeal API error: {response.status_code}',
                        'error': response.text
                    }
                    
        except Exception as e:
            return {
                'success': False,
                'message': f'DocuSeal API connection failed: {str(e)}',
                'error': str(e)
            }

# Enhanced endpoints for the existing FastAPI app
def add_docuseal_endpoints(app, ai_agent):
    """Add DocuSeal integration endpoints to the existing FastAPI app"""
    
    docuseal = DocuSealIntegration()
    
    @app.post("/map-for-docuseal")
    async def map_for_docuseal(
        template_id: str,
        manufacturer_data: Dict[str, Any],
        manufacturer_name: Optional[str] = None,
        submitter_email: Optional[str] = None
    ):
        """Map manufacturer data to DocuSeal template fields with live field discovery"""
        try:
            # Get live field names from DocuSeal template
            template_info = await docuseal.get_template_fields(template_id)
            field_names = template_info['field_names']
            
            # Use existing AI mapping with DocuSeal fields as target schema
            target_schema = {field_name: "string" for field_name in field_names}
            
            if ai_agent:
                mapping_result = await ai_agent.map_fields(
                    manufacturer_data,
                    DocumentType.GENERAL,  # You'll need to import this from medical_ai_service
                    target_schema,
                    manufacturer_name
                )
            else:
                # Use local fallback
                mapping_result = {
                    "mapped_fields": manufacturer_data,
                    "confidence_scores": {k: 0.5 for k in manufacturer_data.keys()},
                    "quality_grade": "C",
                    "suggestions": ["Azure AI not available, using fallback mapping"],
                    "processing_notes": ["Local fallback mapping used"]
                }
            
            # Optionally submit to DocuSeal if submitter_email provided
            submission_result = None
            if submitter_email and mapping_result.get('mapped_fields'):
                submission_result = await docuseal.submit_form(
                    template_id,
                    mapping_result['mapped_fields'],
                    submitter_email,
                    metadata={
                        'manufacturer': manufacturer_name,
                        'mapped_at': datetime.now().isoformat(),
                        'confidence_score': mapping_result.get('confidence_scores', {})
                    }
                )
            
            return {
                'template_info': template_info,
                'mapping_result': mapping_result,
                'submission_result': submission_result,
                'success': True
            }
            
        except Exception as e:
            logger.error(f"DocuSeal mapping failed: {e}")
            raise HTTPException(status_code=500, detail=str(e))
    
    @app.get("/docuseal/template/{template_id}/fields")
    async def get_template_fields_endpoint(template_id: str):
        """Get field names from a DocuSeal template"""
        return await docuseal.get_template_fields(template_id)
    
    @app.post("/docuseal/submit")
    async def submit_to_docuseal_endpoint(
        template_id: str,
        mapped_fields: Dict[str, Any],
        submitter_email: str,
        metadata: Optional[Dict] = None
    ):
        """Submit mapped fields to DocuSeal"""
        return await docuseal.submit_form(template_id, mapped_fields, submitter_email, metadata)
    
    @app.get("/docuseal/test")
    async def test_docuseal_connection():
        """Test DocuSeal API connectivity"""
        return await docuseal.test_connection()

# Example usage in your existing medical_ai_service.py:
# add_docuseal_endpoints(app, ai_agent) 