// JavaScript/TypeScript Example
async function mapFormToIVR(formData, manufacturerId, isOrderForm = false) {
  const apiUrl = 'https://your-function-app.azurewebsites.net/api/MapFormToIVR';
  
  const requestBody = {
    manufacturerId: manufacturerId,
    isOrderForm: isOrderForm,
    formData: formData
  };

  try {
    const response = await fetch(apiUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'x-functions-key': 'your-function-key' // If using function-level auth
      },
      body: JSON.stringify(requestBody)
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Form mapping failed');
    }

    return await response.json();
  } catch (error) {
    console.error('Error mapping form:', error);
    throw error;
  }
}

// Example usage
const formData = {
  // Patient Information
  patient_first_name: 'John',
  patient_last_name: 'Doe',
  patient_dob: '1980-01-15',
  patient_gender: 'male',
  patient_phone: '5551234567',
  patient_email: 'john.doe@email.com',
  patient_address_line1: '123 Main St',
  patient_address_line2: 'Apt 4B',
  patient_city: 'Houston',
  patient_state: 'TX',
  patient_zip: '77001',
  
  // Provider Information
  provider_name: 'Dr. Jane Smith',
  provider_npi: '1234567890',
  provider_specialty: 'Wound Care',
  provider_phone: '5559876543',
  provider_fax: '5559876544',
  
  // Facility Information
  facility_name: 'Houston Medical Center',
  facility_npi: '9876543210',
  facility_tax_id: '12-3456789',
  facility_address: '456 Medical Plaza',
  facility_city: 'Houston',
  facility_state: 'TX',
  facility_zip: '77002',
  facility_phone: '5559876543',
  facility_contact_name: 'Mary Johnson',
  facility_contact_email: 'mary@houstonmed.com',
  
  // Insurance Information
  primary_insurance_name: 'BlueCross BlueShield',
  primary_member_id: 'ABC123456789',
  primary_insurance_phone: '8001234567',
  secondary_insurance_name: 'Medicare',
  secondary_member_id: 'XYZ987654321',
  
  // Clinical Information
  wound_size_length: 5,
  wound_size_width: 3,
  wound_location: 'Left foot, plantar surface',
  wound_duration_value: 6,
  wound_duration_unit: 'weeks',
  wound_types: ['diabetic_foot_ulcer'],
  
  // Diagnosis and Procedure Codes
  icd10_codes: ['L97.511', 'E11.621', 'Z86.31'],
  application_cpt_codes: ['15271', '15272'],
  hcpcs_codes: ['Q4161'],
  
  // Service Information
  expected_service_date: '2025-07-10',
  place_of_service: '11', // Office
  
  // SNF Status
  patient_in_snf: false,
  snf_days: 0,
  
  // Global Period Status
  global_period_status: false,
  
  // Product Selection
  selected_products: [{
    code: 'Q4161',
    name: 'Bio-ConneKt',
    size: '2x2cm',
    quantity: 1
  }]
};

// Call the function
mapFormToIVR(formData, 'medlife-solutions')
  .then(result => {
    console.log('Mapped successfully:', result);
    // Use result.mappedFields to populate Docuseal form
    // Template ID is in result.docusealTemplateId
  })
  .catch(error => {
    console.error('Mapping failed:', error);
  });


// Python Example
import requests
import json

def map_form_to_ivr(form_data, manufacturer_id, is_order_form=False):
    """
    Map form data to manufacturer-specific IVR fields
    """
    api_url = 'https://your-function-app.azurewebsites.net/api/MapFormToIVR'
    
    request_body = {
        'manufacturerId': manufacturer_id,
        'isOrderForm': is_order_form,
        'formData': form_data
    }
    
    headers = {
        'Content-Type': 'application/json',
        'x-functions-key': 'your-function-key'  # If using function-level auth
    }
    
    try:
        response = requests.post(api_url, json=request_body, headers=headers)
        response.raise_for_status()
        return response.json()
    except requests.exceptions.RequestException as e:
        print(f"Error mapping form: {e}")
        raise

# Example usage
form_data = {
    # Patient Information
    'patient_first_name': 'John',
    'patient_last_name': 'Doe',
    'patient_dob': '1980-01-15',
    'patient_gender': 'male',
    'patient_phone': '5551234567',
    
    # Provider Information
    'provider_name': 'Dr. Jane Smith',
    'provider_npi': '1234567890',
    
    # Add more fields as needed...
}

result = map_form_to_ivr(form_data, 'biowound-solutions')
print(f"Template ID: {result['docusealTemplateId']}")
print(f"Mapped Fields: {json.dumps(result['mappedFields'], indent=2)}")


// C# Example
using System;
using System.Net.Http;
using System.Text;
using System.Threading.Tasks;
using Newtonsoft.Json;

public class IVRFormMapperClient
{
    private readonly HttpClient _httpClient;
    private readonly string _functionUrl;
    private readonly string _functionKey;

    public IVRFormMapperClient(string functionUrl, string functionKey = null)
    {
        _httpClient = new HttpClient();
        _functionUrl = functionUrl;
        _functionKey = functionKey;
    }

    public async Task<FormMappingResponse> MapFormToIVRAsync(
        string manufacturerId, 
        Dictionary<string, object> formData, 
        bool isOrderForm = false)
    {
        var request = new FormMappingRequest
        {
            ManufacturerId = manufacturerId,
            IsOrderForm = isOrderForm,
            FormData = formData
        };

        var json = JsonConvert.SerializeObject(request);
        var content = new StringContent(json, Encoding.UTF8, "application/json");

        if (!string.IsNullOrEmpty(_functionKey))
        {
            _httpClient.DefaultRequestHeaders.Add("x-functions-key", _functionKey);
        }

        var response = await _httpClient.PostAsync($"{_functionUrl}/api/MapFormToIVR", content);
        
        if (!response.IsSuccessStatusCode)
        {
            var error = await response.Content.ReadAsStringAsync();
            throw new Exception($"Form mapping failed: {error}");
        }

        var result = await response.Content.ReadAsStringAsync();
        return JsonConvert.DeserializeObject<FormMappingResponse>(result);
    }
}

// Usage example
var client = new IVRFormMapperClient("https://your-function-app.azurewebsites.net");

var formData = new Dictionary<string, object>
{
    ["patient_first_name"] = "John",
    ["patient_last_name"] = "Doe",
    ["patient_dob"] = "1980-01-15",
    ["provider_name"] = "Dr. Jane Smith",
    ["provider_npi"] = "1234567890",
    // Add more fields...
};

var result = await client.MapFormToIVRAsync("advanced-solution", formData);
Console.WriteLine($"Docuseal Template ID: {result.DocusealTemplateId}");


// PHP Example
<?php

function mapFormToIVR($formData, $manufacturerId, $isOrderForm = false) {
    $apiUrl = 'https://your-function-app.azurewebsites.net/api/MapFormToIVR';
    
    $requestBody = [
        'manufacturerId' => $manufacturerId,
        'isOrderForm' => $isOrderForm,
        'formData' => $formData
    ];
    
    $headers = [
        'Content-Type: application/json',
        'x-functions-key: your-function-key' // If using function-level auth
    ];
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Form mapping failed: ' . $response);
    }
    
    return json_decode($response, true);
}

// Example usage
$formData = [
    // Patient Information
    'patient_first_name' => 'John',
    'patient_last_name' => 'Doe',
    'patient_dob' => '1980-01-15',
    
    // Provider Information
    'provider_name' => 'Dr. Jane Smith',
    'provider_npi' => '1234567890',
    
    // Add more fields...
];

try {
    $result = mapFormToIVR($formData, 'centurion-therapeutics');
    echo "Template ID: " . $result['docusealTemplateId'] . "\n";
    print_r($result['mappedFields']);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}


// Ruby Example
require 'net/http'
require 'json'
require 'uri'

def map_form_to_ivr(form_data, manufacturer_id, is_order_form = false)
  uri = URI('https://your-function-app.azurewebsites.net/api/MapFormToIVR')
  
  request_body = {
    manufacturerId: manufacturer_id,
    isOrderForm: is_order_form,
    formData: form_data
  }
  
  http = Net::HTTP.new(uri.host, uri.port)
  http.use_ssl = true
  
  request = Net::HTTP::Post.new(uri)
  request['Content-Type'] = 'application/json'
  request['x-functions-key'] = 'your-function-key' # If using function-level auth
  request.body = request_body.to_json
  
  response = http.request(request)
  
  if response.code != '200'
    raise "Form mapping failed: #{response.body}"
  end
  
  JSON.parse(response.body)
end

# Example usage
form_data = {
  patient_first_name: 'John',
  patient_last_name: 'Doe',
  patient_dob: '1980-01-15',
  provider_name: 'Dr. Jane Smith',
  provider_npi: '1234567890'
}

result = map_form_to_ivr(form_data, 'medlife-solutions')
puts "Template ID: #{result['docusealTemplateId']}"
puts "Mapped Fields: #{result['mappedFields']}"