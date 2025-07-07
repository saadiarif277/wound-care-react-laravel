#!/usr/bin/env node

/**
 * Test script to verify BioWound Solutions field mapping
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Load manufacturer config
const configPath = path.join(__dirname, '../../config/manufacturers/biowound-solutions.php');
const configContent = fs.readFileSync(configPath, 'utf8');

// Extract field names from config using regex
const fieldNamesMatch = configContent.match(/docuseal_field_names[^=]*=\s*\[([\s\S]*?)\],/);
const fieldsMatch = configContent.match(/fields[^=]*=\s*\[([\s\S]*?)\]\s*\];/);

if (!fieldNamesMatch || !fieldsMatch) {
    console.error('Could not parse BioWound Solutions config');
    process.exit(1);
}

// Extract field names
const fieldNameLines = fieldNamesMatch[1].split('\n');
const fieldNames = [];
fieldNameLines.forEach(line => {
    const match = line.match(/'([^']+)'\s*=>\s*'([^']+)'/);
    if (match) {
        fieldNames.push({
            key: match[1],
            label: match[2]
        });
    }
});

console.log('BioWound Solutions Field Mapping Test');
console.log('=====================================\n');
console.log(`Total DocuSeal fields: ${fieldNames.length}\n`);

// Group fields by category
const categories = {
    contact: [],
    requestType: [],
    physician: [],
    facility: [],
    placeOfService: [],
    patient: [],
    insurance: [],
    wound: [],
    product: [],
    other: []
};

// Categorize fields
fieldNames.forEach(field => {
    if (field.key.includes('name') || field.key.includes('email') || 
        field.key.includes('phone') || field.key.includes('territory') || 
        field.key.includes('sales') || field.key.includes('rep')) {
        categories.contact.push(field);
    } else if (field.key.includes('new_request') || field.key.includes('verification') || 
               field.key.includes('additional_applications') || field.key.includes('new_insurance')) {
        categories.requestType.push(field);
    } else if (field.key.includes('physician') || field.key.includes('provider')) {
        categories.physician.push(field);
    } else if (field.key.includes('facility')) {
        categories.facility.push(field);
    } else if (field.key.includes('pos_')) {
        categories.placeOfService.push(field);
    } else if (field.key.includes('patient')) {
        categories.patient.push(field);
    } else if (field.key.includes('primary') || field.key.includes('secondary') || 
               field.key.includes('prior_auth')) {
        categories.insurance.push(field);
    } else if (field.key.includes('wound') || field.key.includes('icd10') || 
               field.key.includes('therapies') || field.key.includes('co_morbidities')) {
        categories.wound.push(field);
    } else if (field.key.includes('q4')) {
        categories.product.push(field);
    } else {
        categories.other.push(field);
    }
});

// Display categorized fields
console.log('Fields by Category:');
console.log('==================\n');

Object.entries(categories).forEach(([category, fields]) => {
    if (fields.length > 0) {
        console.log(`${category.toUpperCase()} (${fields.length} fields):`);
        fields.forEach(field => {
            console.log(`  - ${field.key}: "${field.label}"`);
        });
        console.log('');
    }
});

// Check for potential missing fields
console.log('Potential Missing Fields Check:');
console.log('==============================\n');

const requiredFields = [
    'name', 'email', 'phone', 'sales_rep',
    'physician_name', 'provider_npi',
    'facility_name', 'patient_name', 'patient_dob',
    'primary_name', 'primary_policy'
];

const missingRequired = requiredFields.filter(field => 
    !fieldNames.some(f => f.key === field)
);

if (missingRequired.length > 0) {
    console.log('❌ Missing required fields:');
    missingRequired.forEach(field => console.log(`  - ${field}`));
} else {
    console.log('✅ All required fields are present');
}

// Generate sample test data
console.log('\n\nSample Test Data for BioWound:');
console.log('==============================\n');

const sampleData = {
    // Contact Information
    name: 'John Doe',
    email: 'john.doe@example.com',
    phone: '555-123-4567',
    territory: 'Northeast',
    sales_rep: 'John Doe',
    rep_email: 'john.doe@example.com',
    
    // Request Type
    new_request: true,
    re_verification: false,
    additional_applications: false,
    new_insurance: false,
    
    // Physician Information
    physician_name: 'Dr. Jane Smith',
    physician_specialty: 'Wound Care',
    provider_npi: '1234567890',
    provider_tax_id: '12-3456789',
    provider_ptan: 'ABC123',
    provider_medicaid: 'MED12345',
    provider_phone: '555-234-5678',
    provider_fax: '555-234-5679',
    
    // Facility Information
    facility_name: 'Advanced Wound Care Center',
    facility_npi: '0987654321',
    facility_tax_id: '98-7654321',
    facility_address: '123 Medical Plaza',
    facility_ptan: 'XYZ789',
    facility_medicaid: 'FMED67890',
    facility_phone: '555-345-6789',
    facility_fax: '555-345-6790',
    city_state_zip: 'Boston, MA 02115',
    
    // Place of Service
    pos_11: true,
    pos_21: false,
    pos_24: false,
    pos_22: false,
    pos_32: false,
    pos_13: false,
    critical_access_hospital: false,
    pos_12: false,
    other_pos: '',
    
    // Patient Information
    patient_name: 'James Wilson',
    patient_dob: '01/15/1950',
    patient_address: '456 Oak Street, Boston, MA 02116',
    patient_snf_yes: false,
    patient_snf_no: true,
    snf_days: '',
    patient_global_yes: false,
    patient_global_no: true,
    
    // Insurance
    primary_name: 'Medicare',
    primary_policy: 'ABC123456789',
    primary_phone: '800-MEDICARE',
    secondary_name: '',
    secondary_policy: '',
    secondary_phone: '',
    prior_auth_yes: true,
    prior_auth_no: false,
    
    // Product Q-codes
    q4161: true,
    q4205: false,
    q4290: false,
    q4238: false,
    q4239: false,
    q4266: false,
    q4267: false,
    q4265: false,
    
    // Wound Information
    wound_dfu: true,
    wound_vlu: false,
    wound_chronic_ulcer: false,
    wound_dehisced_surgical: false,
    wound_mohs_surgical: false,
    wound_other: false,
    primary_icd10: 'E11.621',
    secondary_icd10: 'L97.511',
    previously_used_therapies: 'Compression therapy, Silver dressings',
    location_of_wound: 'Right foot, plantar surface',
    wound_duration: '12 weeks',
    co_morbidities: 'Diabetes Type 2, Peripheral Neuropathy',
    post_debridement_size: '4.5',
    
    // Date
    date: '2025-01-07'
};

console.log(JSON.stringify(sampleData, null, 2));

console.log('\n\nTest Summary:');
console.log('============\n');
console.log(`✅ Total fields configured: ${fieldNames.length}`);
console.log(`✅ Contact fields: ${categories.contact.length}`);
console.log(`✅ Request type fields: ${categories.requestType.length}`);
console.log(`✅ Physician fields: ${categories.physician.length}`);
console.log(`✅ Facility fields: ${categories.facility.length}`);
console.log(`✅ Place of Service fields: ${categories.placeOfService.length}`);
console.log(`✅ Patient fields: ${categories.patient.length}`);
console.log(`✅ Insurance fields: ${categories.insurance.length}`);
console.log(`✅ Wound fields: ${categories.wound.length}`);
console.log(`✅ Product Q-code fields: ${categories.product.length}`);
console.log(`✅ Other fields: ${categories.other.length}`);

console.log('\nTest complete!');