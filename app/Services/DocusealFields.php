<?php

namespace App\Services;

class DocusealFields
{
    // Patient Information Fields
    const PATIENT_NAME = 'patient_name';
    const PATIENT_FIRST_NAME = 'patient_first_name';
    const PATIENT_LAST_NAME = 'patient_last_name';
    const PATIENT_DOB = 'patient_dob';
    const PATIENT_GENDER = 'patient_gender';
    const PATIENT_ADDRESS = 'patient_address';
    const PATIENT_CITY = 'patient_city';
    const PATIENT_STATE = 'patient_state';
    const PATIENT_ZIP = 'patient_zip';
    const PATIENT_PHONE = 'patient_phone';
    const PATIENT_EMAIL = 'patient_email';

    // Provider Information Fields
    const PROVIDER_NAME = 'provider_name';
    const PROVIDER_NPI = 'provider_npi';
    const PROVIDER_PHONE = 'provider_phone';
    const PROVIDER_EMAIL = 'provider_email';
    const PROVIDER_ADDRESS = 'provider_address';

    // Facility Information Fields
    const FACILITY_NAME = 'facility_name';
    const FACILITY_NPI = 'facility_npi';
    const FACILITY_CONTACT_PHONE = 'facility_contact_phone';
    const FACILITY_CONTACT_EMAIL = 'facility_contact_email';
    const FACILITY_ADDRESS = 'facility_address';

    // Insurance Information Fields
    const PRIMARY_INS_NAME = 'primary_insurance_name';
    const PRIMARY_INS_MEMBER_ID = 'primary_insurance_member_id';
    const SECONDARY_INS_NAME = 'secondary_insurance_name';
    const SECONDARY_INS_MEMBER_ID = 'secondary_insurance_member_id';

    // Service Information Fields
    const PLACE_OF_SERVICE = 'place_of_service';
    const ANTICIPATED_APPLICATION_DATE = 'anticipated_application_date';
    const SERVICE_DATE = 'service_date';

    // Product Information Fields
    const PRODUCT_NAME = 'product_name';
    const PRODUCT_CODE = 'product_code';
    const PRODUCT_SIZE = 'product_size';
    const PRODUCT_QUANTITY = 'product_quantity';
    const PRODUCT_CATEGORY = 'product_category';

    // Clinical Information Fields
    const WOUND_TYPE = 'wound_type';
    const WOUND_LOCATION = 'wound_location';
    const WOUND_SIZE = 'wound_size';
    const ICD10_PRIMARY = 'icd10_primary';
    const ICD10_SECONDARY = 'icd10_secondary';
    const CLINICAL_NOTES = 'clinical_notes';

    /**
     * Get all available field constants
     */
    public static function getAllFields(): array
    {
        return [
            // Patient fields
            self::PATIENT_NAME,
            self::PATIENT_FIRST_NAME,
            self::PATIENT_LAST_NAME,
            self::PATIENT_DOB,
            self::PATIENT_GENDER,
            self::PATIENT_ADDRESS,
            self::PATIENT_CITY,
            self::PATIENT_STATE,
            self::PATIENT_ZIP,
            self::PATIENT_PHONE,
            self::PATIENT_EMAIL,

            // Provider fields
            self::PROVIDER_NAME,
            self::PROVIDER_NPI,
            self::PROVIDER_PHONE,
            self::PROVIDER_EMAIL,
            self::PROVIDER_ADDRESS,

            // Facility fields
            self::FACILITY_NAME,
            self::FACILITY_NPI,
            self::FACILITY_CONTACT_PHONE,
            self::FACILITY_CONTACT_EMAIL,
            self::FACILITY_ADDRESS,

            // Insurance fields
            self::PRIMARY_INS_NAME,
            self::PRIMARY_INS_MEMBER_ID,
            self::SECONDARY_INS_NAME,
            self::SECONDARY_INS_MEMBER_ID,

            // Service fields
            self::PLACE_OF_SERVICE,
            self::ANTICIPATED_APPLICATION_DATE,
            self::SERVICE_DATE,

            // Product fields
            self::PRODUCT_NAME,
            self::PRODUCT_CODE,
            self::PRODUCT_SIZE,
            self::PRODUCT_QUANTITY,
            self::PRODUCT_CATEGORY,

            // Clinical fields
            self::WOUND_TYPE,
            self::WOUND_LOCATION,
            self::WOUND_SIZE,
            self::ICD10_PRIMARY,
            self::ICD10_SECONDARY,
            self::CLINICAL_NOTES,
        ];
    }

    /**
     * Get fields organized by category
     */
    public static function getFieldsByCategory(): array
    {
        return [
            'patient' => [
                self::PATIENT_NAME,
                self::PATIENT_FIRST_NAME,
                self::PATIENT_LAST_NAME,
                self::PATIENT_DOB,
                self::PATIENT_GENDER,
                self::PATIENT_ADDRESS,
                self::PATIENT_CITY,
                self::PATIENT_STATE,
                self::PATIENT_ZIP,
                self::PATIENT_PHONE,
                self::PATIENT_EMAIL,
            ],
            'provider' => [
                self::PROVIDER_NAME,
                self::PROVIDER_NPI,
                self::PROVIDER_PHONE,
                self::PROVIDER_EMAIL,
                self::PROVIDER_ADDRESS,
            ],
            'facility' => [
                self::FACILITY_NAME,
                self::FACILITY_NPI,
                self::FACILITY_CONTACT_PHONE,
                self::FACILITY_CONTACT_EMAIL,
                self::FACILITY_ADDRESS,
            ],
            'insurance' => [
                self::PRIMARY_INS_NAME,
                self::PRIMARY_INS_MEMBER_ID,
                self::SECONDARY_INS_NAME,
                self::SECONDARY_INS_MEMBER_ID,
            ],
            'service' => [
                self::PLACE_OF_SERVICE,
                self::ANTICIPATED_APPLICATION_DATE,
                self::SERVICE_DATE,
            ],
            'product' => [
                self::PRODUCT_NAME,
                self::PRODUCT_CODE,
                self::PRODUCT_SIZE,
                self::PRODUCT_QUANTITY,
                self::PRODUCT_CATEGORY,
            ],
            'clinical' => [
                self::WOUND_TYPE,
                self::WOUND_LOCATION,
                self::WOUND_SIZE,
                self::ICD10_PRIMARY,
                self::ICD10_SECONDARY,
                self::CLINICAL_NOTES,
            ],
        ];
    }

    /**
     * Get human-readable label for a field
     */
    public static function getFieldLabel(string $field): string
    {
        $labels = [
            // Patient labels
            self::PATIENT_NAME => 'Patient Name',
            self::PATIENT_FIRST_NAME => 'First Name',
            self::PATIENT_LAST_NAME => 'Last Name',
            self::PATIENT_DOB => 'Date of Birth',
            self::PATIENT_GENDER => 'Gender',
            self::PATIENT_ADDRESS => 'Address',
            self::PATIENT_CITY => 'City',
            self::PATIENT_STATE => 'State',
            self::PATIENT_ZIP => 'ZIP Code',
            self::PATIENT_PHONE => 'Phone',
            self::PATIENT_EMAIL => 'Email',

            // Provider labels
            self::PROVIDER_NAME => 'Provider Name',
            self::PROVIDER_NPI => 'Provider NPI',
            self::PROVIDER_PHONE => 'Provider Phone',
            self::PROVIDER_EMAIL => 'Provider Email',
            self::PROVIDER_ADDRESS => 'Provider Address',

            // Facility labels
            self::FACILITY_NAME => 'Facility Name',
            self::FACILITY_NPI => 'Facility NPI',
            self::FACILITY_CONTACT_PHONE => 'Facility Phone',
            self::FACILITY_CONTACT_EMAIL => 'Facility Email',
            self::FACILITY_ADDRESS => 'Facility Address',

            // Insurance labels
            self::PRIMARY_INS_NAME => 'Primary Insurance',
            self::PRIMARY_INS_MEMBER_ID => 'Primary Member ID',
            self::SECONDARY_INS_NAME => 'Secondary Insurance',
            self::SECONDARY_INS_MEMBER_ID => 'Secondary Member ID',

            // Service labels
            self::PLACE_OF_SERVICE => 'Place of Service',
            self::ANTICIPATED_APPLICATION_DATE => 'Application Date',
            self::SERVICE_DATE => 'Service Date',

            // Product labels
            self::PRODUCT_NAME => 'Product Name',
            self::PRODUCT_CODE => 'Product Code',
            self::PRODUCT_SIZE => 'Product Size',
            self::PRODUCT_QUANTITY => 'Quantity',
            self::PRODUCT_CATEGORY => 'Category',

            // Clinical labels
            self::WOUND_TYPE => 'Wound Type',
            self::WOUND_LOCATION => 'Wound Location',
            self::WOUND_SIZE => 'Wound Size',
            self::ICD10_PRIMARY => 'Primary Diagnosis',
            self::ICD10_SECONDARY => 'Secondary Diagnosis',
            self::CLINICAL_NOTES => 'Clinical Notes',
        ];

        return $labels[$field] ?? ucwords(str_replace(['_', '-'], ' ', $field));
    }

    /**
     * Get field type for form rendering
     */
    public static function getFieldType(string $field): string
    {
        $types = [
            // Date fields
            self::PATIENT_DOB => 'date',
            self::ANTICIPATED_APPLICATION_DATE => 'date',
            self::SERVICE_DATE => 'date',

            // Phone fields
            self::PATIENT_PHONE => 'phone',
            self::PROVIDER_PHONE => 'phone',
            self::FACILITY_CONTACT_PHONE => 'phone',

            // Email fields
            self::PATIENT_EMAIL => 'email',
            self::PROVIDER_EMAIL => 'email',
            self::FACILITY_CONTACT_EMAIL => 'email',

            // Numeric fields
            self::PATIENT_ZIP => 'zip',
            self::PRODUCT_QUANTITY => 'number',
            self::PROVIDER_NPI => 'npi',
            self::FACILITY_NPI => 'npi',

            // Selection fields
            self::PATIENT_GENDER => 'select',
            self::PLACE_OF_SERVICE => 'select',
            self::WOUND_TYPE => 'select',
            self::PRODUCT_SIZE => 'select',
            self::PRODUCT_CATEGORY => 'select',

            // Textarea fields
            self::PATIENT_ADDRESS => 'textarea',
            self::PROVIDER_ADDRESS => 'textarea',
            self::FACILITY_ADDRESS => 'textarea',
            self::CLINICAL_NOTES => 'textarea',
            self::WOUND_SIZE => 'textarea',

            // Default to text
            'default' => 'text',
        ];

        return $types[$field] ?? $types['default'];
    }

    /**
     * Get validation rules for a field
     */
    public static function getFieldValidation(string $field): array
    {
        $validations = [
            self::PATIENT_EMAIL => ['email'],
            self::PROVIDER_EMAIL => ['email'],
            self::FACILITY_CONTACT_EMAIL => ['email'],
            self::PATIENT_PHONE => ['regex:/^\(\d{3}\)\s?\d{3}-\d{4}$/'],
            self::PROVIDER_PHONE => ['regex:/^\(\d{3}\)\s?\d{3}-\d{4}$/'],
            self::FACILITY_CONTACT_PHONE => ['regex:/^\(\d{3}\)\s?\d{3}-\d{4}$/'],
            self::PATIENT_ZIP => ['regex:/^\d{5}(-\d{4})?$/'],
            self::PROVIDER_NPI => ['regex:/^\d{10}$/'],
            self::FACILITY_NPI => ['regex:/^\d{10}$/'],
            self::PRODUCT_QUANTITY => ['integer', 'min:1'],
        ];

        return $validations[$field] ?? [];
    }
}
