{
  "templateName": "MSC_Wound_Care_Universal_IVR_Form",
  "version": "1.0",
  "lastUpdated": "2025-03-28",
  "description": "Universal mapping template for insurance verification requests",
  
  "sections": {
    "requestInfo": {
      "title": "Request Information",
      "fields": [
        {
          "id": "requestType",
          "label": "Request Type",
          "type": "select",
          "required": true,
          "options": [
            {"value": "new_request", "label": "New Request"},
            {"value": "reverification", "label": "Re-verification"},
            {"value": "additional_applications", "label": "Additional Applications"},
            {"value": "new_insurance", "label": "New Insurance"}
          ],
          "description": "Type of insurance verification request"
        },
        {
          "id": "requestDate",
          "label": "Request Date",
          "type": "date",
          "required": true,
          "description": "Date of the request",
          "defaultValue": "[CURRENT_DATE]"
        },
        {
          "id": "salesRepName",
          "label": "Sales Representative",
          "type": "text",
          "required": true,
          "description": "Name of the sales representative"
        },
        {
          "id": "salesRepEmail",
          "label": "Sales Rep Email",
          "type": "email",
          "required": false,
          "description": "Email of the sales representative"
        },
        {
          "id": "additionalNotificationEmails",
          "label": "Additional Notification Emails",
          "type": "text",
          "required": false,
          "description": "Additional emails for notification (BAA required)"
        }
      ]
    },
    
    "patientInfo": {
      "title": "Patient Information",
      "fields": [
        {
          "id": "patientName",
          "label": "Patient Name",
          "type": "text",
          "required": true,
          "description": "Full name of the patient"
        },
        {
          "id": "patientDOB",
          "label": "Patient Date of Birth",
          "type": "date",
          "required": true,
          "description": "Patient's date of birth"
        },
        {
          "id": "patientGender",
          "label": "Gender",
          "type": "select",
          "required": false,
          "options": [
            {"value": "male", "label": "Male"},
            {"value": "female", "label": "Female"}
          ],
          "description": "Patient's gender"
        },
        {
          "id": "patientAddressLine1",
          "label": "Address Line 1",
          "type": "text",
          "required": false,
          "description": "Patient's street address"
        },
        {
          "id": "patientAddressLine2",
          "label": "Address Line 2",
          "type": "text",
          "required": false,
          "description": "Additional address information"
        },
        {
          "id": "patientCity",
          "label": "City",
          "type": "text",
          "required": false
        },
        {
          "id": "patientState",
          "label": "State",
          "type": "text",
          "required": false
        },
        {
          "id": "patientZipCode",
          "label": "ZIP Code",
          "type": "text",
          "required": false
        },
        {
          "id": "patientPhone",
          "label": "Phone Number",
          "type": "tel",
          "required": false,
          "description": "Patient's contact phone number"
        },
        {
          "id": "patientFaxEmail",
          "label": "Fax/Email",
          "type": "text",
          "required": false,
          "description": "Patient's fax or email for contact"
        },
        {
          "id": "patientCaregiverInfo",
          "label": "Caregiver Information",
          "type": "text",
          "required": false,
          "description": "Contact information for patient's caregiver"
        },
        {
          "id": "patientContactPermission",
          "label": "Permission to Contact Patient",
          "type": "checkbox",
          "required": false,
          "description": "Whether the patient can be contacted directly"
        }
      ]
    },
    
    "insuranceInfo": {
      "title": "Insurance Information",
      "subsections": [
        {
          "id": "primaryInsurance",
          "title": "Primary Insurance",
          "fields": [
            {
              "id": "primaryInsuranceName",
              "label": "Insurance Name",
              "type": "text",
              "required": true,
              "description": "Name of primary insurance carrier"
            },
            {
              "id": "primaryPolicyNumber",
              "label": "Policy Number",
              "type": "text",
              "required": true,
              "description": "Primary insurance policy/ID number"
            },
            {
              "id": "primarySubscriberName",
              "label": "Subscriber Name",
              "type": "text",
              "required": false,
              "description": "Name of the primary policyholder"
            },
            {
              "id": "primarySubscriberDOB",
              "label": "Subscriber DOB",
              "type": "date",
              "required": false,
              "description": "Date of birth of the primary policyholder"
            },
            {
              "id": "primaryPayerPhone",
              "label": "Payer Phone",
              "type": "tel",
              "required": true,
              "description": "Phone number for the insurance company"
            },
            {
              "id": "primaryPlanType",
              "label": "Plan Type",
              "type": "select",
              "required": false,
              "options": [
                {"value": "hmo", "label": "HMO"},
                {"value": "ppo", "label": "PPO"},
                {"value": "other", "label": "Other"}
              ],
              "description": "Type of insurance plan"
            },
            {
              "id": "primaryNetworkStatus",
              "label": "Provider Network Status",
              "type": "select",
              "required": false,
              "options": [
                {"value": "in_network", "label": "In-Network"},
                {"value": "out_of_network", "label": "Out-of-Network"},
                {"value": "unknown", "label": "Not Sure (Please verify)"}
              ],
              "description": "Network participation status with this payer"
            }
          ]
        },
        {
          "id": "secondaryInsurance",
          "title": "Secondary Insurance",
          "fields": [
            {
              "id": "secondaryInsuranceName",
              "label": "Insurance Name",
              "type": "text",
              "required": false,
              "description": "Name of secondary insurance carrier"
            },
            {
              "id": "secondaryPolicyNumber",
              "label": "Policy Number",
              "type": "text",
              "required": false,
              "description": "Secondary insurance policy/ID number"
            },
            {
              "id": "secondarySubscriberName",
              "label": "Subscriber Name",
              "type": "text",
              "required": false,
              "description": "Name of the secondary policyholder"
            },
            {
              "id": "secondarySubscriberDOB",
              "label": "Subscriber DOB",
              "type": "date",
              "required": false,
              "description": "Date of birth of the secondary policyholder"
            },
            {
              "id": "secondaryPayerPhone",
              "label": "Payer Phone",
              "type": "tel",
              "required": false,
              "description": "Phone number for the secondary insurance company"
            },
            {
              "id": "secondaryPlanType",
              "label": "Plan Type",
              "type": "select",
              "required": false,
              "options": [
                {"value": "hmo", "label": "HMO"},
                {"value": "ppo", "label": "PPO"},
                {"value": "other", "label": "Other"}
              ],
              "description": "Type of secondary insurance plan"
            },
            {
              "id": "secondaryNetworkStatus",
              "label": "Provider Network Status",
              "type": "select",
              "required": false,
              "options": [
                {"value": "in_network", "label": "In-Network"},
                {"value": "out_of_network", "label": "Out-of-Network"},
                {"value": "unknown", "label": "Not Sure (Please verify)"}
              ],
              "description": "Network participation status with secondary payer"
            }
          ]
        }
      ],
      "fields": [
        {
          "id": "authorizationPermission",
          "label": "Permission to Initiate/Follow Up on Prior Authorization",
          "type": "checkbox",
          "required": true,
          "description": "Whether the company is authorized to handle prior authorization"
        },
        {
          "id": "requestPriorAuthAssistance",
          "label": "Request Prior Authorization Assistance",
          "type": "checkbox",
          "required": false,
          "description": "Whether assistance is requested for prior authorization"
        },
        {
          "id": "cardsAttached",
          "label": "Insurance Cards Attached",
          "type": "checkbox",
          "required": false,
          "description": "Whether front and back of insurance cards are attached"
        }
      ]
    },
    
    "physicianInfo": {
      "title": "Physician Information",
      "fields": [
        {
          "id": "physicianName",
          "label": "Physician Name",
          "type": "text",
          "required": true,
          "description": "Name of the treating physician"
        },
        {
          "id": "physicianSpecialty",
          "label": "Physician Specialty",
          "type": "text",
          "required": false,
          "description": "Medical specialty of the physician"
        },
        {
          "id": "physicianNPI",
          "label": "NPI",
          "type": "text",
          "required": true,
          "description": "National Provider Identifier"
        },
        {
          "id": "physicianTaxID",
          "label": "Tax ID",
          "type": "text",
          "required": true,
          "description": "Physician's tax identification number"
        },
        {
          "id": "physicianPTAN",
          "label": "PTAN",
          "type": "text",
          "required": false,
          "description": "Provider Transaction Access Number"
        },
        {
          "id": "physicianMedicaidNumber",
          "label": "Medicaid #",
          "type": "text",
          "required": false,
          "description": "Physician's Medicaid provider number"
        },
        {
          "id": "physicianPhone",
          "label": "Phone #",
          "type": "tel",
          "required": false,
          "description": "Physician's office phone number"
        },
        {
          "id": "physicianFax",
          "label": "Fax #",
          "type": "tel",
          "required": false,
          "description": "Physician's office fax number"
        }
      ]
    },
    
    "facilityInfo": {
      "title": "Facility Information",
      "fields": [
        {
          "id": "facilityName",
          "label": "Facility Name",
          "type": "text",
          "required": true,
          "description": "Name of the healthcare facility"
        },
        {
          "id": "facilityAddressLine1",
          "label": "Address Line 1",
          "type": "text",
          "required": true,
          "description": "Facility's street address"
        },
        {
          "id": "facilityAddressLine2",
          "label": "Address Line 2",
          "type": "text",
          "required": false,
          "description": "Additional facility address information"
        },
        {
          "id": "facilityCity",
          "label": "City",
          "type": "text",
          "required": true
        },
        {
          "id": "facilityState",
          "label": "State",
          "type": "text",
          "required": true
        },
        {
          "id": "facilityZipCode",
          "label": "ZIP Code",
          "type": "text",
          "required": true
        },
        {
          "id": "facilityNPI",
          "label": "NPI",
          "type": "text",
          "required": true,
          "description": "Facility's National Provider Identifier"
        },
        {
          "id": "facilityTaxID",
          "label": "Tax ID",
          "type": "text",
          "required": true,
          "description": "Facility's tax identification number"
        },
        {
          "id": "facilityPTAN",
          "label": "PTAN",
          "type": "text",
          "required": false,
          "description": "Facility's Provider Transaction Access Number"
        },
        {
          "id": "facilityMedicareAdminContractor",
          "label": "Medicare Administrative Contractor",
          "type": "text",
          "required": false
        },
        {
          "id": "facilityContactName",
          "label": "Contact Name",
          "type": "text",
          "required": false,
          "description": "Name of primary contact at facility"
        },
        {
          "id": "facilityContactPhone",
          "label": "Contact Phone",
          "type": "tel",
          "required": false,
          "description": "Phone number for facility contact"
        },
        {
          "id": "facilityContactFax",
          "label": "Contact Fax",
          "type": "tel",
          "required": false,
          "description": "Fax number for facility contact"
        },
        {
          "id": "facilityContactEmail",
          "label": "Contact Email",
          "type": "email",
          "required": false,
          "description": "Email for facility contact"
        },
        {
          "id": "managementCompany",
          "label": "Management Company",
          "type": "text",
          "required": false,
          "description": "Facility management company name"
        }
      ]
    },
    
    "placeOfService": {
      "title": "Place of Service",
      "fields": [
        {
          "id": "placeOfService",
          "label": "Place of Service",
          "type": "select",
          "required": true,
          "options": [
            {"value": "11", "label": "Physician Office (POS 11)"},
            {"value": "22", "label": "Hospital Outpatient (POS 22)"},
            {"value": "24", "label": "Ambulatory Surgical Center (POS 24)"},
            {"value": "12", "label": "Home (POS 12)"},
            {"value": "13", "label": "Assisted Living Facility (POS 13)"},
            {"value": "31", "label": "Skilled Nursing Facility (POS 31)"},
            {"value": "32", "label": "Nursing Facility (POS 32)"},
            {"value": "other", "label": "Other"}
          ],
          "description": "Setting where the patient is being treated"
        },
        {
          "id": "otherPlaceOfService",
          "label": "Other Place of Service",
          "type": "text",
          "required": false,
          "conditionalDisplay": "placeOfService === 'other'",
          "description": "Specify other place of service"
        },
        {
          "id": "snfStatus",
          "label": "Is the patient currently in a Skilled Nursing Facility?",
          "type": "checkbox",
          "required": true,
          "description": "Whether the patient is in a skilled nursing facility"
        },
        {
          "id": "snfDays",
          "label": "Days in SNF",
          "type": "number",
          "required": false,
          "conditionalDisplay": "snfStatus === true",
          "description": "Number of days patient has been in the skilled nursing facility"
        },
        {
          "id": "snfOver100Days",
          "label": "Has the patient been in SNF for over 100 days?",
          "type": "checkbox",
          "required": false,
          "conditionalDisplay": "snfStatus === true",
          "description": "Whether the patient has been in SNF for more than 100 days"
        },
        {
          "id": "hospiceStatus",
          "label": "Is the patient currently in Hospice?",
          "type": "checkbox",
          "required": false,
          "description": "Whether the patient is currently in hospice care"
        },
        {
          "id": "partAStatus",
          "label": "Is the patient in a facility under Part A stay?",
          "type": "checkbox",
          "required": false,
          "description": "Whether the patient is covered under Medicare Part A"
        }
      ]
    },
    
    "woundInfo": {
      "title": "Wound Information",
      "fields": [
        {
          "id": "woundType",
          "label": "Wound Type",
          "type": "multiselect",
          "required": true,
          "options": [
            {"value": "diabetic_foot_ulcer", "label": "Diabetic Foot Ulcer"},
            {"value": "venous_leg_ulcer", "label": "Venous Leg Ulcer"},
            {"value": "pressure_ulcer", "label": "Pressure Ulcer"},
            {"value": "traumatic", "label": "Traumatic Burns"},
            {"value": "radiation", "label": "Radiation Burns"},
            {"value": "necrotizing_fasciitis", "label": "Necrotizing Fasciitis"},
            {"value": "dehisced_surgical", "label": "Dehisced Surgical Wound"},
            {"value": "mohs_surgical", "label": "Mohs Surgical Wound"},
            {"value": "chronic_ulcer", "label": "Chronic Ulcer"},
            {"value": "other", "label": "Other"}
          ],
          "description": "Type of wound being treated"
        },
        {
          "id": "woundOtherSpecify",
          "label": "Other Wound Type",
          "type": "text",
          "required": false,
          "conditionalDisplay": "woundType.includes('other')",
          "description": "Specify other wound type"
        },
        {
          "id": "woundLocation",
          "label": "Wound Location",
          "type": "select",
          "required": true,
          "options": [
            {"value": "trunk_arms_legs_small", "label": "Legs/Arms/Trunk ≤ 100 sq cm"},
            {"value": "trunk_arms_legs_large", "label": "Legs/Arms/Trunk > 100 sq cm"},
            {"value": "hands_feet_head_small", "label": "Feet/Hands/Head ≤ 100 sq cm"},
            {"value": "hands_feet_head_large", "label": "Feet/Hands/Head > 100 sq cm"}
          ],
          "description": "Location of the wound on the body"
        },
        {
          "id": "woundLocationDetails",
          "label": "Specific Wound Location",
          "type": "text",
          "required": false,
          "description": "More specific details about wound location"
        },
        {
          "id": "woundSizeLength",
          "label": "Wound Size - Length (cm)",
          "type": "number",
          "required": false,
          "description": "Length measurement of the wound"
        },
        {
          "id": "woundSizeWidth",
          "label": "Wound Size - Width (cm)",
          "type": "number",
          "required": false,
          "description": "Width measurement of the wound"
        },
        {
          "id": "woundSizeDepth",
          "label": "Wound Size - Depth (cm)",
          "type": "number",
          "required": false,
          "description": "Depth measurement of the wound"
        },
        {
          "id": "woundSizeTotal",
          "label": "Total Wound Size (sq cm)",
          "type": "number",
          "required": true,
          "description": "Total area of the wound",
          "calculation": "woundSizeLength * woundSizeWidth"
        },
        {
          "id": "woundDuration",
          "label": "Wound Duration",
          "type": "text",
          "required": false,
          "description": "How long the wound has been present"
        },
        {
          "id": "previousTreatments",
          "label": "Previously Used Therapies",
          "type": "textarea",
          "required": false,
          "description": "Treatments that have been used previously"
        }
      ]
    },
    
    "procedureInfo": {
      "title": "Procedure Information",
      "fields": [
        {
          "id": "globalPeriodStatus",
          "label": "Is the patient currently under a post-op global surgical period?",
          "type": "checkbox",
          "required": true,
          "description": "Whether the patient is in a post-operative global period"
        },
        {
          "id": "globalPeriodCptCodes",
          "label": "CPT Code(s) of Previous Surgery",
          "type": "text",
          "required": false,
          "conditionalDisplay": "globalPeriodStatus === true",
          "description": "CPT codes of surgeries within global period"
        },
        {
          "id": "globalPeriodSurgeryDate",
          "label": "Surgery Date",
          "type": "date",
          "required": false,
          "conditionalDisplay": "globalPeriodStatus === true",
          "description": "Date of previous surgery"
        },
        {
          "id": "anticipatedTreatmentDate",
          "label": "Anticipated Treatment Date",
          "type": "date",
          "required": false,
          "description": "When the treatment is expected to start"
        },
        {
          "id": "anticipatedApplications",
          "label": "Number of Anticipated Applications",
          "type": "number",
          "required": false,
          "description": "Expected number of product applications"
        },
        {
          "id": "applicationCptCodes",
          "label": "Application CPT Codes",
          "type": "multiselect",
          "required": true,
          "options": [
            {"value": "15271", "label": "15271 - First 25 sq cm trunk/arms/legs"},
            {"value": "15272", "label": "15272 - Each additional 25 sq cm trunk/arms/legs"},
            {"value": "15273", "label": "15273 - First 100 sq cm trunk/arms/legs"},
            {"value": "15274", "label": "15274 - Each additional 100 sq cm trunk/arms/legs"},
            {"value": "15275", "label": "15275 - First 25 sq cm feet/hands/head"},
            {"value": "15276", "label": "15276 - Each additional 25 sq cm feet/hands/head"},
            {"value": "15277", "label": "15277 - First 100 sq cm feet/hands/head"},
            {"value": "15278", "label": "15278 - Each additional 100 sq cm feet/hands/head"}
          ],
          "description": "CPT codes for the product application"
        },
        {
          "id": "diagnosisCodes",
          "label": "ICD-10 Diagnosis Codes",
          "type": "textarea",
          "required": true,
          "description": "ICD-10 codes for the diagnosed conditions"
        },
        {
          "id": "primaryDiagnosisCode",
          "label": "Primary Diagnosis Code",
          "type": "text",
          "required": false,
          "description": "Primary ICD-10 code"
        },
        {
          "id": "secondaryDiagnosisCodes",
          "label": "Secondary Diagnosis Codes",
          "type": "text",
          "required": false,
          "description": "Additional ICD-10 codes"
        },
        {
          "id": "comorbidities",
          "label": "Co-Morbidities",
          "type": "textarea",
          "required": false,
          "description": "Patient's comorbid conditions"
        }
      ]
    },
    
    "productInfo": {
      "title": "Product Information",
      "fields": [
        {
          "id": "selectedProducts",
          "label": "Selected Products",
          "type": "multiselect",
          "required": true,
          "options": [
            {"value": "Q4205", "label": "Membrane Wrap (Q4205)"},
            {"value": "Q4238", "label": "Derm-maxx (Q4238)"},
            {"value": "Q4239", "label": "Amnio-maxx (Q4239)"},
            {"value": "Q4289", "label": "Revoshield (Q4289)"},
            {"value": "Q4290", "label": "Membrane Wrap Hydro (Q4290)"},
            {"value": "Q4161", "label": "Bio-Connekt (Q4161)"},
            {"value": "Q4265", "label": "NeoStim TL (Q4265)"},
            {"value": "Q4266", "label": "NeoStim SL (Q4266)"},
            {"value": "Q4267", "label": "NeoStim DL (Q4267)"},
            {"value": "Q4257", "label": "Relese (Q4257)"},
            {"value": "Q4232", "label": "Corplex (Q4232)"},
            {"value": "Q4180", "label": "Revita (Q4180)"},
            {"value": "Q4151", "label": "AmnioBand (Q4151)"},
            {"value": "Q4128", "label": "Allopatch (Q4128)"},
            {"value": "Q4271", "label": "completeFT (Q4271)"},
            {"value": "A2005", "label": "Microlyte Matrix (A2005)"}
          ],
          "description": "Product(s) to be used"
        },
        {
          "id": "productSizes",
          "label": "Product Sizes",
          "type": "multiselect",
          "required": false,
          "options": [
            {"value": "1.6mm_disc", "label": "1.6 Millimeter Disc"},
            {"value": "2x2", "label": "2x2 Square Centimeter Sheet"},
            {"value": "2x3", "label": "2x3 Square Centimeter Sheet"},
            {"value": "2x4", "label": "2x4 Square Centimeter Sheet"},
            {"value": "3x3", "label": "3x3 Square Centimeter Sheet"},
            {"value": "4x4", "label": "4x4 Square Centimeter Sheet"},
            {"value": "4x6", "label": "4x6 Square Centimeter Sheet"},
            {"value": "4x8", "label": "4x8 Square Centimeter Sheet"},
            {"value": "5x5", "label": "5x5 Square Centimeter Sheet"},
            {"value": "10x10", "label": "10x10 Square Centimeter Sheet"}
          ],
          "description": "Sizes of the product(s) needed"
        },
        {
          "id": "graftSizeRequested",
          "label": "Size of Graft Requested",
          "type": "text",
          "required": false,
          "description": "Size of the graft needed"
        }
      ]
    },
    
    "additionalInfo": {
      "title": "Additional Information",
      "fields": [
        {
          "id": "clinicalStudyParticipation",
          "label": "Is the patient part of a Clinical Study?",
          "type": "checkbox",
          "required": false
        },
        {
          "id": "clinicalStudyName",
          "label": "Study/Trial/Case Series Name",
          "type": "text",
          "required": false,
          "conditionalDisplay": "clinicalStudyParticipation === true",
          "description": "Name of the clinical study"
        },
        {
          "id": "generalNotes",
          "label": "Additional Notes",
          "type": "textarea",
          "required": false,
          "description": "Any additional relevant information"
        }
      ]
    },
    
    "requiredDocuments": {
      "title": "Required Documents",
      "fields": [
        {
          "id": "demographicSheet",
          "label": "Patient Demographic Sheet",
          "type": "checkbox",
          "required": false,
          "description": "Whether patient demographic sheet is attached"
        },
        {
          "id": "insuranceCards",
          "label": "Front and Back of Insurance Cards",
          "type": "checkbox",
          "required": false,
          "description": "Whether copies of insurance cards are attached"
        },
        {
          "id": "clinicalNotes",
          "label": "Clinical Notes",
          "type": "checkbox",
          "required": false,
          "description": "Whether clinical notes are attached"
        }
      ]
    },
    
    "authorization": {
      "title": "Authorization",
      "fields": [
        {
          "id": "signature",
          "label": "Authorized Signature",
          "type": "signature",
          "required": true,
          "description": "Electronic signature of authorized healthcare professional"
        },
        {
          "id": "signatureDate",
          "label": "Date",
          "type": "date",
          "required": true,
          "description": "Date of signature",
          "defaultValue": "[CURRENT_DATE]"
        }
      ]
    }
  },
  
  "manufacturerMappings": {
    "ACZ_Distribution": {
      "templateFileName": "ACZ_Distribution_Insurance_Verification_Request.pdf",
      "fieldMappings": {
        "requestInfo.salesRepName": "REPRESENTATIVE NAME",
        "requestInfo.additionalNotificationEmails": "ADDITIONAL EMAILS FOR NOTIFICATION (REQUIRES BAA)",
        "physicianInfo.physicianName": "PHYSICIAN NAME",
        "physicianInfo.physicianSpecialty": "PHYSICIAN SPECIALTY",
        "physicianInfo.physicianNPI": "NPI",
        "physicianInfo.physicianTaxID": "TAX ID",
        "physicianInfo.physicianPTAN": "PTAN",
        "physicianInfo.physicianMedicaidNumber": "MEDICAID #",
        "physicianInfo.physicianPhone": "PHONE #",
        "physicianInfo.physicianFax": "FAX #",
        "physicianInfo.managementCompany": "MANAGEMENT CO",
        "facilityInfo.facilityName": "FACILITY NAME",
        "facilityInfo.facilityAddressLine1": "FACILITY ADDRESS",
        "facilityInfo.facilityCity": "CITY",
        "facilityInfo.facilityState": "STATE",
        "facilityInfo.facilityZipCode": "ZIP",
        "facilityInfo.facilityContactName": "CONTACT NAME",
        "facilityInfo.facilityContactPhone": "CONTACT PH/EMAIL",
        "patientInfo.patientName": "PATIENT NAME",
        "patientInfo.patientDOB": "PATIENT DOB",
        "patientInfo.patientAddressLine1": "PATIENT ADDRESS",
        "patientInfo.patientCity": "CITY",
        "patientInfo.patientState": "STATE",
        "patientInfo.patientZipCode": "ZIP",
        "patientInfo.patientPhone": "PATIENT PHONE",
        "patientInfo.patientFaxEmail": "PATIENT FAX/EMAIL",
        "patientInfo.patientCaregiverInfo": "PATIENT CAREGIVER INFO",
        "placeOfService.placeOfService": "PLACE OF SERVICE WHERE PATIENT IS BEING SEEN",
        "insuranceInfo.primaryInsurance.primaryInsuranceName": "INSURANCE NAME (PRIMARY)",
        "insuranceInfo.primaryInsurance.primaryPolicyNumber": "POLICY NUMBER (PRIMARY)",
        "insuranceInfo.primaryInsurance.primaryPayerPhone": "PAYER PHONE (PRIMARY)",
        "insuranceInfo.primaryInsurance.primaryNetworkStatus": "PROVIDER STATUS (PRIMARY)",
        "insuranceInfo.secondaryInsurance.secondaryInsuranceName": "INSURANCE NAME (SECONDARY)",
        "insuranceInfo.secondaryInsurance.secondaryPolicyNumber": "POLICY NUMBER (SECONDARY)",
        "insuranceInfo.secondaryInsurance.secondaryPayerPhone": "PAYER PHONE (SECONDARY)",
        "insuranceInfo.secondaryInsurance.secondaryNetworkStatus": "PROVIDER STATUS (SECONDARY)",
        "insuranceInfo.authorizationPermission": "DO WE HAVE YOUR PERMISSION TO INITIATE AND FOLLOW UP ON PRIOR AUTHORIZATION",
        "placeOfService.hospiceStatus": "IS THE PATIENT CURRENTLY IN HOSPICE",
        "placeOfService.partAStatus": "IS THE PATIENT IN A FACILITY UNDER PART A STAY",
        "procedureInfo.globalPeriodStatus": "IS THE PATIENT CURRENTLY UNDER A POST-OP GLOBAL SURGICAL PERIOD",
        "procedureInfo.globalPeriodCptCodes": "CPT CODE(S) OF PREVIOUS SURGERY",
        "procedureInfo.globalPeriodSurgeryDate": "SURGERY DATE",
        "woundInfo.woundLocation": "LOCATION OF WOUND",
        "procedureInfo.diagnosisCodes": "ICD-10 CODES",
        "woundInfo.woundSizeTotal": "TOTAL WOUND SIZE",
        "productInfo.selectedProducts": "PRODUCTS"
      }
    },
    
    "Advanced_Solution": {
      "templateFileName": "Advanced_Solution_Patient_Insurance_Support_Form.pdf",
      "fieldMappings": {
        "requestInfo.salesRepName": "Sales Rep",
        "placeOfService.placeOfService": "Place of Service",
        "facilityInfo.facilityName": "Facility Name",
        "facilityInfo.facilityAddressLine1": "Address",
        "facilityInfo.facilityContactName": "Contact Name",
        "facilityInfo.facilityContactPhone": "Phone",
        "facilityInfo.facilityContactFax": "Fax",
        "facilityInfo.facilityMedicareAdminContractor": "Medicare Admin Contractor",
        "facilityInfo.facilityNPI": "NPI",
        "facilityInfo.facilityTaxID": "TIN",
        "facilityInfo.facilityPTAN": "PTAN",
        "physicianInfo.physicianName": "Physician Name",
        "physicianInfo.physicianAddressLine1": "Address",
        "physicianInfo.physicianPhone": "Phone",
        "physicianInfo.physicianFax": "Fax",
        "physicianInfo.physicianNPI": "NPI",
        "physicianInfo.physicianTaxID": "TIN",
        "patientInfo.patientName": "Patient Name",
        "patientInfo.patientAddressLine1": "Address",
        "patientInfo.patientDOB": "Date of Birth",
        "patientInfo.patientPhone": "Phone",
        "patientInfo.patientContactPermission": "OK to Contact Patient?",
        "insuranceInfo.primaryInsurance.primaryInsuranceName": "Primary Insurance",
        "insuranceInfo.primaryInsurance.primarySubscriberName": "Subscriber Name (Primary)",
        "insuranceInfo.primaryInsurance.primaryPolicyNumber": "Policy Number (Primary)",
        "insuranceInfo.primaryInsurance.primarySubscriberDOB": "Subscriber DOB (Primary)",
        "insuranceInfo.primaryInsurance.primaryPlanType": "Type of Plan (Primary)",
        "insuranceInfo.primaryInsurance.primaryPayerPhone": "Insurance Phone Number (Primary)",
        "insuranceInfo.primaryInsurance.primaryNetworkStatus": "Does Provider Participate with Network? (Primary)",
        "insuranceInfo.secondaryInsurance.secondaryInsuranceName": "Secondary Insurance",
        "insuranceInfo.secondaryInsurance.secondarySubscriberName": "Subscriber Name (Secondary)",
        "insuranceInfo.secondaryInsurance.secondaryPolicyNumber": "Policy Number (Secondary)",
        "insuranceInfo.secondaryInsurance.secondarySubscriberDOB": "Subscriber DOB (Secondary)",
        "insuranceInfo.secondaryInsurance.secondaryPlanType": "Type of Plan (Secondary)",
        "insuranceInfo.secondaryInsurance.secondaryPayerPhone": "Insurance Phone Number (Secondary)",
        "insuranceInfo.secondaryInsurance.secondaryNetworkStatus": "Does Provider Participate with Network? (Secondary)",
        "woundInfo.woundType": "Wound Type",
        "woundInfo.woundSizeTotal": "Wound Size(s)",
        "procedureInfo.applicationCptCodes": "Application CPT(s)",
        "procedureInfo.anticipatedTreatmentDate": "Date of Procedure",
        "procedureInfo.diagnosisCodes": "ICD-10 Diagnosis Code(s)",
        "productInfo.selectedProducts": "Product Information",
        "placeOfService.snfStatus": "Is the patient currently residing in SNF?",
        "procedureInfo.globalPeriodStatus": "Is the patient under a surgical Global Period?",
        "procedureInfo.globalPeriodCptCodes": "CPT Code",
        "insuranceInfo.requestPriorAuthAssistance": "If Prior Authorization is Required, check here",
        "authorization.signature": "Physician or Authorized Signature",
        "authorization.signatureDate": "Date"
      }
    },
    
    "MedLife_Solutions": {
      "templateFileName": "MedLife_Solutions_IVR.pdf",
      "fieldMappings": {
        "requestInfo.salesRepName": "Distributor / Company",
        "physicianInfo.physicianName": "Physician Name",
        "physicianInfo.physicianPTAN": "Physician PTAN",
        "physicianInfo.physicianNPI": "Physician NPI",
        "facilityInfo.facilityName": "Practice Name",
        "facilityInfo.facilityPTAN": "Practice PTAN",
        "facilityInfo.facilityNPI": "Practice NPI",
        "facilityInfo.facilityTaxID": "TAX ID#",
        "facilityInfo.facilityContactName": "Office Contact Name",
        "facilityInfo.facilityContactEmail": "Office Contact Email",
        "patientInfo.patientName": "Patient Name",
        "patientInfo.patientDOB": "Patient DOB",
        "insuranceInfo.primaryInsurance.primaryInsuranceName": "Primary Insurance",
        "insuranceInfo.primaryInsurance.primaryPolicyNumber": "Member ID (Primary)",
        "insuranceInfo.secondaryInsurance.secondaryInsuranceName": "Secondary Insurance",
        "insuranceInfo.secondaryInsurance.secondaryPolicyNumber": "Member ID (Secondary)",
        "insuranceInfo.cardsAttached": "Copy of Front and Back of Insurance card attached",
        "placeOfService.placeOfService": "Place of Service",
        "placeOfService.snfStatus": "Is the patient currently residing in a Nursing Home OR Skilled Nursing Facility",
        "placeOfService.snfOver100Days": "has it been over 100 days?",
        "procedureInfo.globalPeriodStatus": "Is this patient currently under a post-op period?",
        "procedureInfo.globalPeriodCptCodes": "please list CPT code(s) of previous surgery",
        "procedureInfo.globalPeriodSurgeryDate": "Surgery Date",
        "procedureInfo.anticipatedTreatmentDate": "Procedure Date",
        "woundInfo.woundSizeLength": "Wound Size: L",
        "woundInfo.woundSizeWidth": "Wound Size: W",
        "woundInfo.woundSizeTotal": "Wound Size: Total",
        "woundInfo.woundLocationDetails": "Wound location",
        "productInfo.graftSizeRequested": "Size of Graft Requested",
        "procedureInfo.primaryDiagnosisCode": "ICD-10 (1)",
        "procedureInfo.secondaryDiagnosisCodes": "ICD-10 (2, 3, 4)",
        "procedureInfo.applicationCptCodes": "CPT codes"
      }
    },
    
    "BioWound_Solutions": {
      "templateFileName": "BioWound_Solutions_Patient_Insurance_Verification_Request.pdf",
      "fieldMappings": {
        "requestInfo.requestType": "New Request/Re-Verification/Additional Applications/New Insurance",
        "requestInfo.salesRepName": "Sales Rep",
        "requestInfo.salesRepEmail": "Rep Email",
        "physicianInfo.physicianName": "Physician Name",
        "physicianInfo.physicianSpecialty": "Physician Specialty",
        "facilityInfo.facilityName": "Facility Name",
        "facilityInfo.facilityAddressLine1": "Facility Address",
        "facilityInfo.facilityCity": "City",
        "facilityInfo.facilityState": "State",
        "facilityInfo.facilityZipCode": "Zip",
        "facilityInfo.facilityContactName": "Contact Name",
        "facilityInfo.facilityContactPhone": "Phone #",
        "facilityInfo.facilityContactFax": "Fax #",
        "facilityInfo.facilityContactEmail": "Contact Email",
        "physicianInfo.physicianNPI": "NPI (Physician)",
        "physicianInfo.physicianTaxID": "Tax ID (Physician)",
        "facilityInfo.facilityNPI": "NPI (Facility)",
        "facilityInfo.facilityTaxID": "Tax ID (Facility)",
        "facilityInfo.facilityPTAN": "PTAN (Facility)",
        "physicianInfo.physicianMedicaidNumber": "Medicaid # (Physician)",
        "facilityInfo.facilityMedicaidNumber": "Medicaid # (Facility)",
        "placeOfService.placeOfService": "Place of Service",
        "patientInfo.patientName": "Patient Name",
        "patientInfo.patientDOB": "Patient Date of Birth",
        "patientInfo.patientAddressLine1": "Patient Address",
        "placeOfService.snfStatus": "Is the patient currently in a Skilled Nursing Facility?",
        "placeOfService.snfDays": "Number of Days in SNF?",
        "insuranceInfo.primaryInsurance.primaryInsuranceName": "Payer Name (Primary)",
        "insuranceInfo.primaryInsurance.primaryPolicyNumber": "Policy # (Primary)",
        "insuranceInfo.primaryInsurance.primaryPayerPhone": "Payer Phone # (Primary)",
        "insuranceInfo.secondaryInsurance.secondaryInsuranceName": "Payer Name (Secondary)",
        "insuranceInfo.secondaryInsurance.secondaryPolicyNumber": "Policy # (Secondary)",
        "insuranceInfo.secondaryInsurance.secondaryPayerPhone": "Payer Phone # (Secondary)",
        "insuranceInfo.requestPriorAuthAssistance": "If the payer requires prior authorization for pre-determination for BioWound Solutions product applications",
        "productInfo.selectedProducts": "Product",
        "woundInfo.woundType": "Wound Type",
        "procedureInfo.primaryDiagnosisCode": "Primary ICD-10 Code",
        "procedureInfo.secondaryDiagnosisCodes": "Secondary ICD-10 Code",
        "woundInfo.woundLocationDetails": "Wound Location",
        "woundInfo.woundDuration": "Wound Duration",
        "woundInfo.comorbidities": "Co-Morbities",
        "woundInfo.woundSizeTotal": "Post Debridement Total Size of Ulcers",
        "woundInfo.previousTreatments": "Previously Used Therapies",
        "authorization.signature": "Authorized Signature",
        "authorization.signatureDate": "Date"
      }
    },
    
    "Imbed_Biosciences": {
      "templateFileName": "Imbed_Biosciences_Benefit_Verification.pdf",
      "fieldMappings": {
        "requestInfo.requestType": "Request Type",
        "requestInfo.salesRepName": "Distributor Name/Account Manager",
        "clinicalStudyParticipation": "Is the patient part of a Clinical Study?",
        "clinicalStudyName": "name of the study/trial/case series",
        "patientInfo.patientName": "Name",
        "patientInfo.patientDOB": "Date of Birth",
        "procedureInfo.anticipatedTreatmentDate": "Procedure Date",
        "patientInfo.patientAddressLine1": "Address",
        "patientInfo.patientPhone": "Phone",
        "placeOfService.snfStatus": "Does patient reside in nursing home?",
        "placeOfService.snfOver100Days": "If yes, has the patient been there for over 100 days?",
        "physicianInfo.physicianName": "Name (Physician)",
        "physicianInfo.physicianNPI": "NPI (Physician)",
        "physicianInfo.physicianTaxID": "Tax ID (Physician)",
        "facilityInfo.facilityName": "Facility Name",
        "facilityInfo.facilityAddressLine1": "Facility Address",
        "facilityInfo.facilityNPI": "NPI (Facility)",
        "facilityInfo.facilityTaxID": "Tax ID (Facility)",
        "facilityInfo.facilityContactPhone": "Phone (Facility)",
        "facilityInfo.facilityContactEmail": "Email (Facility)",
        "facilityInfo.facilityType": "Facility Type",
        "facilityInfo.facilityContactName": "Point of Contact (Facility)",
        "procedureInfo.primaryDiagnosisCode": "Primary Diagnosis Code",
        "procedureInfo.secondaryDiagnosisCodes": "Secondary/Other Diagnosis Code",
        "procedureInfo.applicationCptCodes": "Procedure (CPT)",
        "woundInfo.woundSizeTotal": "Total Area of Wound",
        "woundInfo.woundSizeLength": "Dimensions - Length",
        "woundInfo.woundSizeWidth": "Dimensions - Width",
        "woundInfo.woundSizeDepth": "Dimensions - Depth",
        "productInfo.selectedProducts": "Product (A2005)",
        "productInfo.productSizes": "Product Sizes",
        "insuranceInfo.demographicSheet": "Patient demographic sheet",
        "insuranceInfo.insuranceCards": "Front and back of insurance cards",
        "insuranceInfo.clinicalNotes": "Most recent clinical notes"
      }
    },
    
    "Extremity_Care": {
      "templateFileName": "Extremity_Care_Insurance_Verification_Request.pdf",
      "fieldMappings": {
        "requestInfo.requestType": "New Application/Additional Application/Re-verification/New Insurance",
        "requestInfo.salesRepName": "Sales Rep",
        "productInfo.productSizes": "Product Requested",
        "placeOfService.placeOfService": "Place of Service",
        "patientInfo.patientName": "Patient Name",
        "patientInfo.patientDOB": "DOB",
        "patientInfo.patientGender": "Male/Female",
        "patientInfo.patientAddressLine1": "Address",
        "patientInfo.patientCity": "City",
        "patientInfo.patientState": "State",
        "patientInfo.patientZipCode": "Zip",
        "placeOfService.snfStatus": "Is this patient currently in a skilled nursing facility or nursing home?",
        "placeOfService.snfDays": "how many days has the patient been admitted",
        "insuranceInfo.primaryInsurance.primaryInsuranceName": "Primary Insurance",
        "insuranceInfo.primaryInsurance.primaryPayerPhone": "Payer Phone # (Primary)",
        "insuranceInfo.primaryInsurance.primaryPolicyNumber": "Policy Number (Primary)",
        "insuranceInfo.secondaryInsurance.secondaryInsuranceName": "Secondary Insurance",
        "insuranceInfo.secondaryInsurance.secondaryPayerPhone": "Payer Phone # (Secondary)",
        "insuranceInfo.secondaryInsurance.secondaryPolicyNumber": "Policy Number (Secondary)",
        "physicianInfo.physicianName": "Provider Name",
        "physicianInfo.physicianNPI": "Provider NPI",
        "physicianInfo.physicianTaxID": "Provider Tax ID#",
        "physicianInfo.physicianMedicaidNumber": "Medicare Provider #",
        "facilityInfo.facilityName": "Facility Name",
        "facilityInfo.facilityAddressLine1": "Facility Address",
        "facilityInfo.facilityCity": "Facility City",
        "facilityInfo.facilityState": "Facility State",
        "facilityInfo.facilityZipCode": "Facility Zip",
        "facilityInfo.facilityNPI": "Facility NPI",
        "facilityInfo.facilityTaxID": "Facility Tax ID#",
        "facilityInfo.facilityContactName": "Facility Contact",
        "facilityInfo.facilityContactPhone": "Facility Phone#",
        "facilityInfo.facilityContactFax": "Facility Fax#",
        "facilityInfo.facilityContactEmail": "Facility Contact Email",
        "productInfo.selectedProducts": "completeFT",
        "procedureInfo.applicationCptCodes": "CPT Codes",
        "procedureInfo.anticipatedTreatmentDate": "Anticipated Application Date",
        "procedureInfo.anticipatedApplications": "Number of Anticipated Applications",
        "woundInfo.woundType": "Wound Type",
        "procedureInfo.primaryDiagnosisCode": "ICD-10 Primary Code",
        "procedureInfo.secondaryDiagnosisCodes": "ICD-10 Secondary Codes"
      }
    },
    
    "StimLabs": {
      "templateFileName": "StimLabs_Authorization_Request_Form.pdf",
      "fieldMappings": {
        "requestInfo.salesRepName": "Sales Representative Name",
        "productInfo.selectedProducts": "StimLabs product",
        "patientInfo.patientName": "Patient Name",
        "patientInfo.patientDOB": "Patient Date of Birth",
        "patientInfo.patientGender": "Gender",
        "patientInfo.patientAddressLine1": "Street Address",
        "patientInfo.patientCity": "City",
        "patientInfo.patientState": "State",
        "patientInfo.patientZipCode": "Zip",
        "patientInfo.patientPhone": "Phone Number",
        "physicianInfo.physicianName": "Physician Name",
        "facilityInfo.facilityName": "Name of Practice",
        "physicianInfo.physicianNPI": "NPI",
        "physicianInfo.physicianTaxID": "Tax ID",
        "facilityInfo.facilityContactName": "Contact Name",
        "facilityInfo.facilityContactEmail": "Contact Email Address",
        "facilityInfo.facilityContactPhone": "Contact Phone Number",
        "facilityInfo.facilityContactFax": "Contact Fax Number",
        "facilityInfo.facilityAddressLine1": "Office Street Address",
        "facilityInfo.facilityCity": "City",
        "facilityInfo.facilityState": "State",
        "facilityInfo.facilityZipCode": "Zip",
        "placeOfService.placeOfService": "Place of Service",
        "placeOfService.otherPlaceOfService": "Other Place of Service",
        "insuranceInfo.primaryInsurance.primaryInsuranceName": "Primary Insurance",
        "insuranceInfo.primaryInsurance.primaryPayerPhone": "Payer Phone Number (Primary)",
        "insuranceInfo.primaryInsurance.primaryPolicyNumber": "Policy Number (Primary)",
        "insuranceInfo.primaryInsurance.primarySubscriberName": "Subscriber Name (Primary)",
        "insuranceInfo.secondaryInsurance.secondaryInsuranceName": "Secondary Insurance",
        "insuranceInfo.secondaryInsurance.secondaryPayerPhone": "Payer Phone Number (Secondary)",
        "insuranceInfo.secondaryInsurance.secondaryPolicyNumber": "Policy Number (Secondary)",
        "insuranceInfo.secondaryInsurance.secondarySubscriberName": "Subscriber Name (Secondary)",
        "procedureInfo.anticipatedTreatmentDate": "Scheduled date of service",
        "procedureInfo.applicationCptCodes": "Procedure code(s)",
        "procedureInfo.diagnosisCodes": "Diagnosis code(s)",
        "woundInfo.woundLocationDetails": "Where on/in the patient's body will the product be used?",
        "woundInfo.woundSizeTotal": "What is the size of the wound in square centimeters?",
        "woundInfo.previousTreatments": "What past treatments have failed for this patient?",
        "placeOfService.snfStatus": "Is the patient in a skilled nursing facility?",
        "authorization.signature": "Authorized Signature",
        "authorization.signatureDate": "Date"
      }
    },
    
    "Centurion_Therapeutics": {
      "templateFileName": "Centurion_Therapeutics_Insurance_Verification.pdf",
      "fieldMappings": {
        "requestInfo.requestType": "New Wound/Additional Application/Re-verification/New Insurance",
        "patientInfo.patientName": "Patient Name",
        "patientInfo.patientDOB": "DOB",
        "patientInfo.patientGender": "Male/Female",
        "patientInfo.patientAddressLine1": "Address",
        "patientInfo.patientCity": "City",
        "patientInfo.patientState": "State",
        "patientInfo.patientZipCode": "Zip",
        "patientInfo.patientPhone": "Home Phone #",
        "patientInfo.patientMobile": "Mobile #",
        "placeOfService.snfStatus": "Is this patient currently in a skilled facility or nursing home?",
        "placeOfService.snfDays": "how many days has the patient been admitted",
        "insuranceInfo.primaryInsurance.primaryInsuranceName": "Primary Insurance",
        "insuranceInfo.primaryInsurance.primaryPayerPhone": "Payer Phone # (Primary)",
        "insuranceInfo.primaryInsurance.primaryPolicyNumber": "Policy Number (Primary)",
        "insuranceInfo.primaryInsurance.primarySubscriberName": "Subscriber Name (Primary)",
        "insuranceInfo.secondaryInsurance.secondaryInsuranceName": "Secondary Insurance",
        "insuranceInfo.secondaryInsurance.secondaryPayerPhone": "Payer Phone (Secondary)",
        "insuranceInfo.secondaryInsurance.secondaryPolicyNumber": "Policy Number (Secondary)",
        "insuranceInfo.secondaryInsurance.secondarySubscriberName": "Subscriber Name (Secondary)",
        "physicianInfo.physicianName": "Provider Name",
        "physicianInfo.physicianSpecialty": "Specialty",
        "physicianInfo.physicianNPI": "Provider NPI",
        "physicianInfo.physicianTaxID": "Tax ID",
        "physicianInfo.physicianMedicaidNumber": "Medicaid Provider #",
        "facilityInfo.facilityName": "Facility Name",
        "facilityInfo.facilityAddressLine1": "Address",
        "facilityInfo.facilityCity": "City",
        "facilityInfo.facilityState": "State",
        "facilityInfo.facilityZipCode": "Zip",
        "facilityInfo.facilityNPI": "Facility NPI",
        "facilityInfo.facilityTaxID": "Tax ID",
        "facilityInfo.facilityPTAN": "PTAN #",
        "facilityInfo.facilityContactName": "Facility Contact",
        "facilityInfo.facilityContactPhone": "Phone #",
        "facilityInfo.facilityContactFax": "Fax #",
        "facilityInfo.facilityContactEmail": "Email Address",
        "placeOfService.placeOfService": "Treatment Setting",
        "productInfo.selectedProducts": "AmnioBand/Allopatch",
        "procedureInfo.primaryDiagnosisCode": "Primary",
        "procedureInfo.secondaryDiagnosisCodes": "Secondary/Tertiary",
        "procedureInfo.anticipatedTreatmentDate": "Anticipated Treatment Start Date",
        "procedureInfo.anticipatedApplications": "Number of Applications",
        "insuranceInfo.requestPriorAuthAssistance": "If the payer requires prior authorization for pre-determination for product applications",
        "authorization.signature": "Provider Signature",
        "authorization.signatureDate": "Date"
      }
    }
  },
  
  "calculatedFields": [
    {
      "id": "woundSizeTotal",
      "calculation": "woundSizeLength * woundSizeWidth",
      "description": "Calculate total wound area from length and width measurements"
    }
  ],
  
  "defaultValues": {
    "requestInfo.requestDate": "[CURRENT_DATE]",
    "authorization.signatureDate": "[CURRENT_DATE]"
  },
  
  "docuSealSettings": {
    "apiEndpoint": "https://api.docuseal.com",
    "submissionEndpoint": "/submissions",
    "templatesEndpoint": "/templates",
    "submissionOptions": {
      "generatePdf": true,
      "sendEmails": true,
      "allowSigning": true,
      "sendAfterAllSign": true,
      "redirectUrl": ""
    }
  }
}

