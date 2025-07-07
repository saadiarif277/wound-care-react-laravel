#!/usr/bin/env node

/**
 * Test Script for PDF Upload Debugging
 * 
 * This script tests the PDF upload functionality with various scenarios
 * to ensure proper error handling and debugging information is displayed.
 * 
 * Usage: node tests/scripts/test-pdf-upload-debug.js
 */

const fs = require('fs');
const path = require('path');
const FormData = require('form-data');
const axios = require('axios');

// Configuration
const API_BASE = process.env.API_BASE || 'http://localhost:8000';
const API_TOKEN = process.env.API_TOKEN || 'your-test-token-here';

// Test scenarios
const testScenarios = [
  {
    name: 'Valid PDF Upload',
    data: {
      manufacturer_id: '1',
      template_name: 'Test Template',
      document_type: 'ivr',
      version: '1.0',
      is_active: false,
    },
    file: 'test-valid.pdf',
    expectedResult: 'success'
  },
  {
    name: 'Missing PDF File',
    data: {
      manufacturer_id: '1',
      template_name: 'Test Template',
      document_type: 'ivr',
      version: '1.0',
    },
    file: null,
    expectedResult: 'error',
    expectedError: 'pdf_file'
  },
  {
    name: 'Invalid File Type',
    data: {
      manufacturer_id: '1',
      template_name: 'Test Template',
      document_type: 'ivr',
      version: '1.0',
    },
    file: 'test-invalid.txt',
    expectedResult: 'error',
    expectedError: 'pdf_file'
  },
  {
    name: 'Oversized PDF',
    data: {
      manufacturer_id: '1',
      template_name: 'Test Template',
      document_type: 'ivr',
      version: '1.0',
    },
    file: 'test-oversized.pdf',
    expectedResult: 'error',
    expectedError: 'pdf_file'
  },
  {
    name: 'Missing Required Fields',
    data: {
      template_name: 'Test Template',
      version: '1.0',
    },
    file: 'test-valid.pdf',
    expectedResult: 'error',
    expectedError: 'manufacturer_id'
  }
];

/**
 * Create test PDF files
 */
function createTestFiles() {
  const testDir = path.join(__dirname, 'test-files');
  
  if (!fs.existsSync(testDir)) {
    fs.mkdirSync(testDir, { recursive: true });
  }

  // Create valid PDF (minimal PDF structure)
  const validPdf = Buffer.from([
    0x25, 0x50, 0x44, 0x46, 0x2D, 0x31, 0x2E, 0x34, // %PDF-1.4
    0x0A, 0x25, 0xE2, 0xE3, 0xCF, 0xD3, 0x0A, // %âãÏÓ
    // ... minimal PDF content
    0x25, 0x25, 0x45, 0x4F, 0x46 // %%EOF
  ]);
  fs.writeFileSync(path.join(testDir, 'test-valid.pdf'), validPdf);

  // Create invalid text file
  fs.writeFileSync(path.join(testDir, 'test-invalid.txt'), 'This is not a PDF');

  // Create oversized PDF (11MB - over the 10MB limit)
  const oversizedContent = Buffer.alloc(11 * 1024 * 1024);
  oversizedContent.write('%PDF-1.4\n');
  oversizedContent.write('%%EOF', oversizedContent.length - 5);
  fs.writeFileSync(path.join(testDir, 'test-oversized.pdf'), oversizedContent);

  console.log('✓ Test files created');
}

/**
 * Run a single test scenario
 */
async function runTest(scenario) {
  console.log(`\n🧪 Testing: ${scenario.name}`);
  
  try {
    const form = new FormData();
    
    // Add form data
    Object.entries(scenario.data).forEach(([key, value]) => {
      form.append(key, value);
    });
    
    // Add file if specified
    if (scenario.file) {
      const filePath = path.join(__dirname, 'test-files', scenario.file);
      if (fs.existsSync(filePath)) {
        form.append('pdf_file', fs.createReadStream(filePath));
      }
    }
    
    // Add debug flag
    const url = `${API_BASE}/api/admin/pdf-templates?debug=1`;
    
    const response = await axios.post(url, form, {
      headers: {
        ...form.getHeaders(),
        'Authorization': `Bearer ${API_TOKEN}`,
        'Accept': 'application/json',
      },
      validateStatus: () => true // Don't throw on error status
    });
    
    if (scenario.expectedResult === 'success' && response.status === 200) {
      console.log('✅ Test passed: Upload successful');
    } else if (scenario.expectedResult === 'error' && response.status === 422) {
      const errors = response.data.errors || {};
      if (errors[scenario.expectedError]) {
        console.log(`✅ Test passed: Expected error on '${scenario.expectedError}'`);
        console.log(`   Error message: ${errors[scenario.expectedError]}`);
        
        // Check for debug info
        if (response.data.upload_debug) {
          console.log('   Debug info available:', Object.keys(response.data.upload_debug));
        }
      } else {
        console.log(`❌ Test failed: Expected error on '${scenario.expectedError}' but got:`, Object.keys(errors));
      }
    } else {
      console.log(`❌ Test failed: Unexpected response`);
      console.log(`   Status: ${response.status}`);
      console.log(`   Data:`, response.data);
    }
    
  } catch (error) {
    console.log(`❌ Test failed with exception:`, error.message);
  }
}

/**
 * Clean up test files
 */
function cleanup() {
  const testDir = path.join(__dirname, 'test-files');
  if (fs.existsSync(testDir)) {
    fs.rmSync(testDir, { recursive: true, force: true });
  }
  console.log('\n✓ Cleanup complete');
}

/**
 * Main test runner
 */
async function main() {
  console.log('PDF Upload Debug Test Script');
  console.log('============================\n');
  
  // Check if we can connect to the API
  try {
    const healthCheck = await axios.get(`${API_BASE}/api/health`, {
      validateStatus: () => true
    });
    if (healthCheck.status !== 200) {
      console.error('❌ Cannot connect to API at', API_BASE);
      console.error('   Make sure the Laravel server is running');
      process.exit(1);
    }
  } catch (error) {
    console.error('❌ Cannot connect to API:', error.message);
    console.error('   Make sure the Laravel server is running at', API_BASE);
    process.exit(1);
  }
  
  // Create test files
  createTestFiles();
  
  // Run tests
  for (const scenario of testScenarios) {
    await runTest(scenario);
  }
  
  // Cleanup
  cleanup();
  
  console.log('\n✅ All tests complete');
}

// Run the tests
main().catch(error => {
  console.error('Fatal error:', error);
  cleanup();
  process.exit(1);
});