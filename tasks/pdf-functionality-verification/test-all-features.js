// Test script to verify all PDF template features are working
// Run this in the browser console on the PDF Template Detail page

console.log('=== PDF Template Feature Test Suite ===\n');

// Test 1: Check if all buttons exist
console.log('1. Checking for all required buttons...');
const buttons = {
    'AI Analysis': document.querySelector('button:has(.h-4.w-4) span:contains("AI Analysis")'),
    'Test Fill': document.querySelector('button:has(.h-4.w-4) span:contains("Test Fill")'),
    'Save Mappings': document.querySelector('button:has(.h-4.w-4) span:contains("Save Mappings")'),
    'Get AI Suggestions': document.querySelector('button:has(.h-4.w-4) span:contains("Get AI Suggestions")'),
    'Add Mapping': document.querySelector('button:has(.h-4.w-4) span:contains("Add Mapping")')
};

Object.entries(buttons).forEach(([name, button]) => {
    if (button) {
        console.log(`✅ ${name} button found`);
    } else {
        console.log(`❌ ${name} button NOT found`);
    }
});

// Test 2: Check API endpoints
console.log('\n2. Testing API endpoints...');

async function testEndpoint(name, url, method = 'POST', body = {}) {
    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: method !== 'GET' ? JSON.stringify(body) : undefined
        });
        
        if (response.ok) {
            console.log(`✅ ${name}: ${response.status} OK`);
            const data = await response.json();
            console.log(`   Response:`, data);
        } else {
            console.log(`❌ ${name}: ${response.status} ${response.statusText}`);
            const error = await response.text();
            console.log(`   Error:`, error);
        }
    } catch (error) {
        console.log(`❌ ${name}: Network error - ${error.message}`);
    }
}

// Get template ID from URL
const templateId = window.location.pathname.match(/pdf-templates\/(\d+)/)?.[1];

if (templateId) {
    console.log(`\nTesting endpoints for template ID: ${templateId}\n`);
    
    // Test each endpoint
    (async () => {
        await testEndpoint('Extract Fields', `/admin/pdf-templates/${templateId}/extract-fields`);
        await testEndpoint('AI Analysis', `/admin/pdf-templates/${templateId}/analyze-with-ai`);
        await testEndpoint('Suggest Mappings', `/admin/pdf-templates/${templateId}/suggest-mappings`, 'POST', {
            min_confidence: 0.5,
            max_suggestions: 5,
            include_historical: true
        });
        await testEndpoint('Test Fill', `/admin/pdf-templates/${templateId}/test-fill`, 'POST', {
            test_data: {
                patient_name: 'Test Patient',
                patient_dob: '01/01/1990',
                provider_name: 'Dr. Test Provider'
            }
        });
    })();
} else {
    console.log('❌ Could not determine template ID from URL');
}

// Test 3: Check React props
console.log('\n3. Checking React component props...');
const reactRoot = document.getElementById('app')?._reactRootContainer;
if (reactRoot) {
    console.log('✅ React root found');
    // Try to access Inertia page props
    if (window.$page) {
        console.log('Page props:', window.$page.props);
    }
} else {
    console.log('❌ React root not found');
}

// Test 4: Check for AI components
console.log('\n4. Checking for AI components...');
const aiComponents = {
    'Field Mapper': document.querySelector('[class*="PDFFieldMapper"]'),
    'AI Suggestions': document.querySelector('[class*="AIMappingSuggestions"]'),
    'Field Mappings Section': document.querySelector('h2:contains("Field Mappings")')?.parentElement
};

Object.entries(aiComponents).forEach(([name, component]) => {
    if (component) {
        console.log(`✅ ${name} component found`);
    } else {
        console.log(`❌ ${name} component NOT found`);
    }
});

console.log('\n=== Test Complete ===');
console.log('Note: Some tests may fail if you\'re not on the PDF Template Detail page');
console.log('To fully test, navigate to: /admin/pdf-templates/{id} where {id} is a valid template ID');