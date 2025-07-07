#!/usr/bin/env python3
"""
PDF Field Extractor - Extract field metadata from PDF templates
Uses PyPDF2, pdfplumber, and pymupdf for comprehensive field analysis
"""

import json
import sys
import os
import logging
import re
from typing import Dict, List, Optional, Any, Union
from pathlib import Path
from datetime import datetime
import hashlib

# PDF processing libraries
try:
    import PyPDF2
    from PyPDF2 import PdfReader
    PYPDF2_AVAILABLE = True
except ImportError:
    PYPDF2_AVAILABLE = False

try:
    import pdfplumber
    PDFPLUMBER_AVAILABLE = True
except ImportError:
    PDFPLUMBER_AVAILABLE = False

try:
    import fitz  # PyMuPDF
    PYMUPDF_AVAILABLE = True
except ImportError:
    PYMUPDF_AVAILABLE = False

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class PdfFieldExtractor:
    """
    Comprehensive PDF field metadata extractor
    """
    
    def __init__(self):
        self.extraction_methods = {
            'pypdf2': self.extract_with_pypdf2,
            'pdfplumber': self.extract_with_pdfplumber,
            'pymupdf': self.extract_with_pymupdf
        }
        
        # Field type detection patterns
        self.field_type_patterns = {
            'email': [r'email', r'e.?mail', r'mail'],
            'phone': [r'phone', r'tel', r'fax', r'mobile', r'cell'],
            'date': [r'date', r'dob', r'birth', r'born', r'expire', r'valid'],
            'name': [r'name', r'first', r'last', r'full.?name'],
            'address': [r'address', r'street', r'city', r'state', r'zip', r'postal'],
            'number': [r'number', r'num', r'id', r'ssn', r'tax.?id', r'policy', r'member'],
            'currency': [r'amount', r'cost', r'price', r'fee', r'payment', r'dollar'],
            'checkbox': [r'check', r'yes', r'no', r'agree', r'consent'],
            'signature': [r'signature', r'sign', r'patient.?signature', r'doctor.?signature']
        }
        
        # Medical category patterns
        self.medical_categories = {
            'patient': [r'patient', r'pt', r'member', r'beneficiary'],
            'provider': [r'provider', r'physician', r'doctor', r'md', r'practitioner'],
            'facility': [r'facility', r'clinic', r'hospital', r'practice'],
            'insurance': [r'insurance', r'payer', r'plan', r'policy', r'coverage'],
            'clinical': [r'diagnosis', r'icd', r'wound', r'condition', r'treatment'],
            'product': [r'product', r'item', r'supply', r'order', r'quantity']
        }
        
        # Business purpose patterns
        self.business_purposes = {
            'identification': [r'name', r'first', r'last', r'full.?name'],
            'contact_info': [r'address', r'street', r'city', r'state', r'zip'],
            'communication': [r'phone', r'email', r'fax', r'contact'],
            'temporal': [r'date', r'dob', r'birth', r'time', r'expire'],
            'billing': [r'insurance', r'policy', r'member', r'payment', r'cost'],
            'authorization': [r'signature', r'sign', r'consent', r'agreement'],
            'clinical_documentation': [r'diagnosis', r'wound', r'condition', r'treatment']
        }
    
    def extract_field_metadata(self, config: Dict[str, Any]) -> Dict[str, Any]:
        """
        Extract field metadata from PDF using multiple methods
        """
        pdf_path = config.get('pdf_path')
        if not pdf_path or not os.path.exists(pdf_path):
            return self._error_result(f"PDF file not found: {pdf_path}")
        
        logger.info(f"Starting field extraction for: {pdf_path}")
        
        # Get file metadata
        file_metadata = self._get_file_metadata(pdf_path)
        
        # Extract using multiple methods
        extraction_results = {}
        methods = config.get('extraction_methods', ['pypdf2'])
        
        for method in methods:
            if method in self.extraction_methods:
                try:
                    result = self.extraction_methods[method](pdf_path, config)
                    extraction_results[method] = result
                    logger.info(f"Successfully extracted with {method}: {len(result.get('fields', []))} fields")
                except Exception as e:
                    logger.error(f"Error with {method}: {str(e)}")
                    extraction_results[method] = {'error': str(e)}
        
        # Merge results from all methods
        merged_fields = self._merge_extraction_results(extraction_results)
        
        # Analyze and enhance field data
        if config.get('analyze_field_types', True):
            merged_fields = self._analyze_field_types(merged_fields)
        
        if config.get('detect_medical_categories', True):
            merged_fields = self._detect_medical_categories(merged_fields)
        
        # Add confidence scores
        merged_fields = self._add_confidence_scores(merged_fields, extraction_results)
        
        return {
            'success': True,
            'template_id': config.get('template_id'),
            'template_name': config.get('template_name'),
            'manufacturer_name': config.get('manufacturer_name'),
            'fields': merged_fields,
            'extraction_methods': list(extraction_results.keys()),
            'file_metadata': file_metadata,
            'metadata': {
                'extraction_timestamp': datetime.now().isoformat(),
                'extraction_version': '1.0.0',
                'methods_used': list(extraction_results.keys()),
                'total_fields': len(merged_fields),
                'successful_methods': len([r for r in extraction_results.values() if 'error' not in r])
            }
        }
    
    def extract_with_pypdf2(self, pdf_path: str, config: Dict[str, Any]) -> Dict[str, Any]:
        """
        Extract fields using PyPDF2
        """
        if not PYPDF2_AVAILABLE:
            raise ImportError("PyPDF2 is not available")
        
        fields = []
        
        with open(pdf_path, 'rb') as file:
            pdf_reader = PdfReader(file)
            
            for page_num, page in enumerate(pdf_reader.pages):
                if '/Annots' in page:
                    annotations = page['/Annots']
                    
                    for annotation in annotations:
                        annotation_obj = annotation.get_object()
                        if annotation_obj.get('/Subtype') == '/Widget':
                            field_data = self._extract_pypdf2_field(annotation_obj, page_num + 1)
                            if field_data:
                                fields.append(field_data)
        
        return {
            'method': 'pypdf2',
            'fields': fields,
            'pages': len(pdf_reader.pages)
        }
    
    def extract_with_pdfplumber(self, pdf_path: str, config: Dict[str, Any]) -> Dict[str, Any]:
        """
        Extract fields using pdfplumber
        """
        if not PDFPLUMBER_AVAILABLE:
            raise ImportError("pdfplumber is not available")
        
        fields = []
        
        with pdfplumber.open(pdf_path) as pdf:
            for page_num, page in enumerate(pdf.pages):
                # Extract text-based field information
                text = page.extract_text()
                if text:
                    text_fields = self._extract_fields_from_text(text, page_num + 1)
                    fields.extend(text_fields)
                
                # Extract form fields if available
                if hasattr(page, 'annots') and page.annots:
                    for annot in page.annots:
                        field_data = self._extract_pdfplumber_field(annot, page_num + 1)
                        if field_data:
                            fields.append(field_data)
        
        return {
            'method': 'pdfplumber',
            'fields': fields,
            'pages': len(pdf.pages)
        }
    
    def extract_with_pymupdf(self, pdf_path: str, config: Dict[str, Any]) -> Dict[str, Any]:
        """
        Extract fields using PyMuPDF
        """
        if not PYMUPDF_AVAILABLE:
            raise ImportError("PyMuPDF is not available")
        
        fields = []
        
        pdf_doc = fitz.open(pdf_path)
        
        for page_num in range(len(pdf_doc)):
            page = pdf_doc[page_num]
            
            # Extract form fields
            widgets = page.widgets()
            for widget in widgets:
                field_data = self._extract_pymupdf_field(widget, page_num + 1)
                if field_data:
                    fields.append(field_data)
        
        pdf_doc.close()
        
        return {
            'method': 'pymupdf',
            'fields': fields,
            'pages': len(pdf_doc)
        }
    
    def _extract_pypdf2_field(self, annotation_obj: Any, page_num: int) -> Optional[Dict[str, Any]]:
        """
        Extract field data from PyPDF2 annotation
        """
        try:
            field_name = annotation_obj.get('/T', '')
            if isinstance(field_name, bytes):
                field_name = field_name.decode('utf-8', errors='ignore')
            
            if not field_name:
                return None
            
            # Get field rectangle
            rect = annotation_obj.get('/Rect', [0, 0, 0, 0])
            
            # Get field type
            field_type = annotation_obj.get('/FT', '')
            if isinstance(field_type, bytes):
                field_type = field_type.decode('utf-8', errors='ignore')
            
            # Get field flags
            flags = annotation_obj.get('/Ff', 0)
            
            # Get default value
            default_value = annotation_obj.get('/DV', '')
            if isinstance(default_value, bytes):
                default_value = default_value.decode('utf-8', errors='ignore')
            
            return {
                'name': field_name,
                'type': field_type,
                'page': page_num,
                'x': float(rect[0]) if len(rect) > 0 else None,
                'y': float(rect[1]) if len(rect) > 1 else None,
                'width': float(rect[2] - rect[0]) if len(rect) > 2 else None,
                'height': float(rect[3] - rect[1]) if len(rect) > 3 else None,
                'required': bool(flags & 2),  # Required flag
                'readonly': bool(flags & 1),  # ReadOnly flag
                'default_value': default_value,
                'extraction_method': 'pypdf2',
                'raw_data': {
                    'flags': flags,
                    'rect': rect
                }
            }
        except Exception as e:
            logger.error(f"Error extracting PyPDF2 field: {str(e)}")
            return None
    
    def _extract_pdfplumber_field(self, annot: Any, page_num: int) -> Optional[Dict[str, Any]]:
        """
        Extract field data from pdfplumber annotation
        """
        try:
            field_name = annot.get('T', '')
            if not field_name:
                return None
            
            return {
                'name': field_name,
                'type': annot.get('FT', ''),
                'page': page_num,
                'x': annot.get('x0'),
                'y': annot.get('y0'),
                'width': annot.get('width'),
                'height': annot.get('height'),
                'extraction_method': 'pdfplumber',
                'raw_data': annot
            }
        except Exception as e:
            logger.error(f"Error extracting pdfplumber field: {str(e)}")
            return None
    
    def _extract_pymupdf_field(self, widget: Any, page_num: int) -> Optional[Dict[str, Any]]:
        """
        Extract field data from PyMuPDF widget
        """
        try:
            field_name = widget.field_name
            if not field_name:
                return None
            
            rect = widget.rect
            
            return {
                'name': field_name,
                'type': widget.field_type_string,
                'page': page_num,
                'x': rect.x0,
                'y': rect.y0,
                'width': rect.width,
                'height': rect.height,
                'required': widget.field_flags & 2,
                'readonly': widget.field_flags & 1,
                'default_value': widget.field_value,
                'extraction_method': 'pymupdf',
                'raw_data': {
                    'field_type': widget.field_type,
                    'flags': widget.field_flags,
                    'rect': [rect.x0, rect.y0, rect.x1, rect.y1]
                }
            }
        except Exception as e:
            logger.error(f"Error extracting PyMuPDF field: {str(e)}")
            return None
    
    def _extract_fields_from_text(self, text: str, page_num: int) -> List[Dict[str, Any]]:
        """
        Extract potential fields from text using pattern matching
        """
        fields = []
        
        # Look for common field patterns in text
        patterns = [
            (r'([A-Za-z\s]+):\s*_+', 'text'),  # "Field Name: ____"
            (r'([A-Za-z\s]+)\s*\[\s*\]', 'checkbox'),  # "Field Name [ ]"
            (r'([A-Za-z\s]+)\s*\(\s*\)', 'radio'),  # "Field Name ( )"
            (r'([A-Za-z\s]+):\s*\$?\s*_+', 'currency'),  # "Amount: $____"
            (r'([A-Za-z\s]+)\s*Date:\s*_+', 'date'),  # "Date: ____"
        ]
        
        for pattern, field_type in patterns:
            matches = re.finditer(pattern, text, re.IGNORECASE)
            for match in matches:
                field_name = match.group(1).strip()
                if len(field_name) > 2:  # Ignore very short matches
                    fields.append({
                        'name': field_name,
                        'type': field_type,
                        'page': page_num,
                        'extraction_method': 'text_pattern',
                        'confidence': 0.6,  # Lower confidence for text extraction
                        'raw_data': {
                            'pattern': pattern,
                            'match': match.group(0)
                        }
                    })
        
        return fields
    
    def _merge_extraction_results(self, results: Dict[str, Any]) -> List[Dict[str, Any]]:
        """
        Merge field results from multiple extraction methods
        """
        all_fields = []
        field_names = set()
        
        # Priority order for extraction methods
        method_priority = ['pymupdf', 'pypdf2', 'pdfplumber', 'text_pattern']
        
        for method in method_priority:
            if method in results and 'fields' in results[method]:
                for field in results[method]['fields']:
                    field_name = field.get('name', '').strip()
                    if field_name and field_name not in field_names:
                        field_names.add(field_name)
                        all_fields.append(field)
        
        return all_fields
    
    def _analyze_field_types(self, fields: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """
        Analyze and enhance field type information
        """
        for field in fields:
            field_name = field.get('name', '').lower()
            
            # Detect field type based on name patterns
            detected_type = None
            max_confidence = 0
            
            for field_type, patterns in self.field_type_patterns.items():
                for pattern in patterns:
                    if re.search(pattern, field_name, re.IGNORECASE):
                        confidence = len(pattern) / len(field_name)  # Simple confidence score
                        if confidence > max_confidence:
                            detected_type = field_type
                            max_confidence = confidence
            
            if detected_type:
                field['detected_type'] = detected_type
                field['type_confidence'] = max_confidence
                if not field.get('type') or field['type'] == 'text':
                    field['type'] = detected_type
        
        return fields
    
    def _detect_medical_categories(self, fields: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """
        Detect medical categories for fields
        """
        for field in fields:
            field_name = field.get('name', '').lower()
            
            # Detect medical category
            detected_category = None
            max_confidence = 0
            
            for category, patterns in self.medical_categories.items():
                for pattern in patterns:
                    if re.search(pattern, field_name, re.IGNORECASE):
                        confidence = len(pattern) / len(field_name)
                        if confidence > max_confidence:
                            detected_category = category
                            max_confidence = confidence
            
            if detected_category:
                field['medical_category'] = detected_category
                field['category_confidence'] = max_confidence
            
            # Detect business purpose
            detected_purpose = None
            max_confidence = 0
            
            for purpose, patterns in self.business_purposes.items():
                for pattern in patterns:
                    if re.search(pattern, field_name, re.IGNORECASE):
                        confidence = len(pattern) / len(field_name)
                        if confidence > max_confidence:
                            detected_purpose = purpose
                            max_confidence = confidence
            
            if detected_purpose:
                field['business_purpose'] = detected_purpose
                field['purpose_confidence'] = max_confidence
        
        return fields
    
    def _add_confidence_scores(self, fields: List[Dict[str, Any]], results: Dict[str, Any]) -> List[Dict[str, Any]]:
        """
        Add confidence scores based on extraction method and field quality
        """
        for field in fields:
            method = field.get('extraction_method', 'unknown')
            
            # Base confidence by method
            base_confidence = {
                'pymupdf': 0.95,
                'pypdf2': 0.90,
                'pdfplumber': 0.85,
                'text_pattern': 0.60
            }.get(method, 0.50)
            
            # Adjust confidence based on field completeness
            completeness_score = 0
            if field.get('name'): completeness_score += 0.3
            if field.get('type'): completeness_score += 0.2
            if field.get('x') is not None: completeness_score += 0.2
            if field.get('y') is not None: completeness_score += 0.2
            if field.get('width'): completeness_score += 0.1
            
            # Final confidence score
            field['confidence'] = min(base_confidence + completeness_score, 1.0)
        
        return fields
    
    def _get_file_metadata(self, pdf_path: str) -> Dict[str, Any]:
        """
        Get PDF file metadata
        """
        try:
            stat = os.stat(pdf_path)
            
            # Calculate file hash
            with open(pdf_path, 'rb') as f:
                file_hash = hashlib.md5(f.read()).hexdigest()
            
            return {
                'file_size': stat.st_size,
                'file_hash': file_hash,
                'last_modified': datetime.fromtimestamp(stat.st_mtime).isoformat(),
                'file_path': pdf_path
            }
        except Exception as e:
            logger.error(f"Error getting file metadata: {str(e)}")
            return {}
    
    def _error_result(self, error_message: str) -> Dict[str, Any]:
        """
        Create error result
        """
        return {
            'success': False,
            'error': error_message,
            'timestamp': datetime.now().isoformat()
        }

def main():
    """
    Main function to run the PDF field extractor
    """
    if len(sys.argv) != 2:
        print(json.dumps({
            'success': False,
            'error': 'Usage: python pdf_field_extractor.py <config_file>'
        }))
        sys.exit(1)
    
    config_file = sys.argv[1]
    
    try:
        with open(config_file, 'r') as f:
            config = json.load(f)
        
        extractor = PdfFieldExtractor()
        result = extractor.extract_field_metadata(config)
        
        print(json.dumps(result, indent=2))
        
    except Exception as e:
        print(json.dumps({
            'success': False,
            'error': str(e),
            'timestamp': datetime.now().isoformat()
        }))
        sys.exit(1)

if __name__ == '__main__':
    main() 