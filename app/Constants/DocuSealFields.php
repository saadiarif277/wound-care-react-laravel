<?php

namespace App\Constants;

/**
 * Constants for Docuseal field names used throughout the application.
 * These constants ensure consistency when mapping data to Docuseal templates.
 */
class DocusealFields
{
    // Patient Information
    public const PATIENT_NAME = 'patient_name';
    public const PATIENT_DOB = 'patient_dob';
    public const PATIENT_GENDER = 'patient_gender';
    public const PATIENT_ADDRESS = 'patient_address';
    public const PATIENT_PHONE = 'patient_phone';
    public const PATIENT_EMAIL = 'patient_email';

    // Provider Information
    public const PROVIDER_NAME = 'provider_name';
    public const PROVIDER_NPI = 'provider_npi';
    public const PROVIDER_PTAN = 'provider_ptan';
    public const PROVIDER_CREDENTIALS = 'provider_credentials';

    // Facility Information
    public const FACILITY_NAME = 'facility_name';
    public const FACILITY_NPI = 'facility_npi';
    public const FACILITY_PTAN = 'facility_ptan';
    public const FACILITY_CONTACT_PHONE = 'facility_contact_phone';
    public const FACILITY_CONTACT_EMAIL = 'facility_contact_email';
    public const FACILITY_ADDRESS = 'facility_address';

    // Insurance Information
    public const PRIMARY_INS_NAME = 'primary_ins_name';
    public const PRIMARY_MEMBER_ID = 'primary_member_id';
    public const PRIMARY_GROUP_NUMBER = 'primary_group_number';
    public const PRIMARY_PLAN_TYPE = 'primary_plan_type';
    public const SECONDARY_INS_NAME = 'secondary_ins_name';
    public const SECONDARY_MEMBER_ID = 'secondary_member_id';
    public const SECONDARY_GROUP_NUMBER = 'secondary_group_number';

    // Service Information
    public const PLACE_OF_SERVICE = 'place_of_service';
    public const ANTICIPATED_APPLICATION_DATE = 'anticipated_application_date';
    public const SHIPPING_SPEED = 'shipping_speed';

    // Product Information
    public const PRODUCT_CODE = 'product_code';
    public const PRODUCT_SIZE = 'product_size';
    public const PRODUCT_QUANTITY = 'product_quantity';
    public const PRODUCT_NAME = 'product_name';

    // Clinical Information
    public const WOUND_TYPE = 'wound_type';
    public const WOUND_LOCATION = 'wound_location';
    public const WOUND_SIZE_LENGTH = 'wound_size_length';
    public const WOUND_SIZE_WIDTH = 'wound_size_width';
    public const WOUND_SIZE_DEPTH = 'wound_size_depth';
    public const ICD10_PRIMARY = 'icd10_primary';
    public const ICD10_SECONDARY = 'icd10_secondary';
    public const CPT_CODES = 'cpt_codes';

    // Clinical Attestations
    public const FAILED_CONSERVATIVE_TREATMENT = 'failed_conservative_treatment';
    public const INFORMATION_ACCURATE = 'information_accurate';
    public const MEDICAL_NECESSITY_ESTABLISHED = 'medical_necessity_established';
    public const MAINTAIN_DOCUMENTATION = 'maintain_documentation';

    // Medicare Specific
    public const MEDICARE_PART_B_AUTHORIZED = 'medicare_part_b_authorized';
    public const SNF_DAYS = 'snf_days';
    public const HOSPICE_STATUS = 'hospice_status';
    public const PART_A_STATUS = 'part_a_status';
    public const GLOBAL_PERIOD_STATUS = 'global_period_status';

    /**
     * Get all field constants as an array.
     */
    public static function getAllFields(): array
    {
        return [
            // Patient Information
            self::PATIENT_NAME,
            self::PATIENT_DOB,
            self::PATIENT_GENDER,
            self::PATIENT_ADDRESS,
            self::PATIENT_PHONE,
            self::PATIENT_EMAIL,

            // Provider Information
            self::PROVIDER_NAME,
            self::PROVIDER_NPI,
            self::PROVIDER_PTAN,
            self::PROVIDER_CREDENTIALS,

            // Facility Information
            self::FACILITY_NAME,
            self::FACILITY_NPI,
            self::FACILITY_PTAN,
            self::FACILITY_CONTACT_PHONE,
            self::FACILITY_CONTACT_EMAIL,
            self::FACILITY_ADDRESS,

            // Insurance Information
            self::PRIMARY_INS_NAME,
            self::PRIMARY_MEMBER_ID,
            self::PRIMARY_GROUP_NUMBER,
            self::PRIMARY_PLAN_TYPE,
            self::SECONDARY_INS_NAME,
            self::SECONDARY_MEMBER_ID,
            self::SECONDARY_GROUP_NUMBER,

            // Service Information
            self::PLACE_OF_SERVICE,
            self::ANTICIPATED_APPLICATION_DATE,
            self::SHIPPING_SPEED,

            // Product Information
            self::PRODUCT_CODE,
            self::PRODUCT_SIZE,
            self::PRODUCT_QUANTITY,
            self::PRODUCT_NAME,

            // Clinical Information
            self::WOUND_TYPE,
            self::WOUND_LOCATION,
            self::WOUND_SIZE_LENGTH,
            self::WOUND_SIZE_WIDTH,
            self::WOUND_SIZE_DEPTH,
            self::ICD10_PRIMARY,
            self::ICD10_SECONDARY,
            self::CPT_CODES,

            // Clinical Attestations
            self::FAILED_CONSERVATIVE_TREATMENT,
            self::INFORMATION_ACCURATE,
            self::MEDICAL_NECESSITY_ESTABLISHED,
            self::MAINTAIN_DOCUMENTATION,

            // Medicare Specific
            self::MEDICARE_PART_B_AUTHORIZED,
            self::SNF_DAYS,
            self::HOSPICE_STATUS,
            self::PART_A_STATUS,
            self::GLOBAL_PERIOD_STATUS,
        ];
    }

    /**
     * Get fields grouped by category.
     */
    public static function getFieldsByCategory(): array
    {
        return [
            'Patient Information' => [
                self::PATIENT_NAME,
                self::PATIENT_DOB,
                self::PATIENT_GENDER,
                self::PATIENT_ADDRESS,
                self::PATIENT_PHONE,
                self::PATIENT_EMAIL,
            ],
            'Provider Information' => [
                self::PROVIDER_NAME,
                self::PROVIDER_NPI,
                self::PROVIDER_PTAN,
                self::PROVIDER_CREDENTIALS,
            ],
            'Facility Information' => [
                self::FACILITY_NAME,
                self::FACILITY_NPI,
                self::FACILITY_PTAN,
                self::FACILITY_CONTACT_PHONE,
                self::FACILITY_CONTACT_EMAIL,
                self::FACILITY_ADDRESS,
            ],
            'Insurance Information' => [
                self::PRIMARY_INS_NAME,
                self::PRIMARY_MEMBER_ID,
                self::PRIMARY_GROUP_NUMBER,
                self::PRIMARY_PLAN_TYPE,
                self::SECONDARY_INS_NAME,
                self::SECONDARY_MEMBER_ID,
                self::SECONDARY_GROUP_NUMBER,
            ],
            'Service Information' => [
                self::PLACE_OF_SERVICE,
                self::ANTICIPATED_APPLICATION_DATE,
                self::SHIPPING_SPEED,
            ],
            'Product Information' => [
                self::PRODUCT_CODE,
                self::PRODUCT_SIZE,
                self::PRODUCT_QUANTITY,
                self::PRODUCT_NAME,
            ],
            'Clinical Information' => [
                self::WOUND_TYPE,
                self::WOUND_LOCATION,
                self::WOUND_SIZE_LENGTH,
                self::WOUND_SIZE_WIDTH,
                self::WOUND_SIZE_DEPTH,
                self::ICD10_PRIMARY,
                self::ICD10_SECONDARY,
                self::CPT_CODES,
            ],
            'Clinical Attestations' => [
                self::FAILED_CONSERVATIVE_TREATMENT,
                self::INFORMATION_ACCURATE,
                self::MEDICAL_NECESSITY_ESTABLISHED,
                self::MAINTAIN_DOCUMENTATION,
            ],
            'Medicare Specific' => [
                self::MEDICARE_PART_B_AUTHORIZED,
                self::SNF_DAYS,
                self::HOSPICE_STATUS,
                self::PART_A_STATUS,
                self::GLOBAL_PERIOD_STATUS,
            ],
        ];
    }

    /**
     * Get human-readable label for a field.
     */
    public static function getFieldLabel(string $field): string
    {
        $labels = [
            // Patient Information
            self::PATIENT_NAME => 'Patient Name',
            self::PATIENT_DOB => 'Date of Birth',
            self::PATIENT_GENDER => 'Gender',
            self::PATIENT_ADDRESS => 'Address',
            self::PATIENT_PHONE => 'Phone',
            self::PATIENT_EMAIL => 'Email',

            // Provider Information
            self::PROVIDER_NAME => 'Provider Name',
            self::PROVIDER_NPI => 'Provider NPI',
            self::PROVIDER_PTAN => 'Provider PTAN',
            self::PROVIDER_CREDENTIALS => 'Provider Credentials',

            // Facility Information
            self::FACILITY_NAME => 'Facility Name',
            self::FACILITY_NPI => 'Facility NPI',
            self::FACILITY_PTAN => 'Facility PTAN',
            self::FACILITY_CONTACT_PHONE => 'Facility Phone',
            self::FACILITY_CONTACT_EMAIL => 'Facility Email',
            self::FACILITY_ADDRESS => 'Facility Address',

            // Insurance Information
            self::PRIMARY_INS_NAME => 'Primary Insurance Name',
            self::PRIMARY_MEMBER_ID => 'Primary Member ID',
            self::PRIMARY_GROUP_NUMBER => 'Primary Group Number',
            self::PRIMARY_PLAN_TYPE => 'Primary Plan Type',
            self::SECONDARY_INS_NAME => 'Secondary Insurance Name',
            self::SECONDARY_MEMBER_ID => 'Secondary Member ID',
            self::SECONDARY_GROUP_NUMBER => 'Secondary Group Number',

            // Service Information
            self::PLACE_OF_SERVICE => 'Place of Service',
            self::ANTICIPATED_APPLICATION_DATE => 'Anticipated Application Date',
            self::SHIPPING_SPEED => 'Shipping Speed',

            // Product Information
            self::PRODUCT_CODE => 'Product Code',
            self::PRODUCT_SIZE => 'Product Size',
            self::PRODUCT_QUANTITY => 'Product Quantity',
            self::PRODUCT_NAME => 'Product Name',

            // Clinical Information
            self::WOUND_TYPE => 'Wound Type',
            self::WOUND_LOCATION => 'Wound Location',
            self::WOUND_SIZE_LENGTH => 'Wound Length',
            self::WOUND_SIZE_WIDTH => 'Wound Width',
            self::WOUND_SIZE_DEPTH => 'Wound Depth',
            self::ICD10_PRIMARY => 'Primary ICD-10 Code',
            self::ICD10_SECONDARY => 'Secondary ICD-10 Code',
            self::CPT_CODES => 'CPT Codes',

            // Clinical Attestations
            self::FAILED_CONSERVATIVE_TREATMENT => 'Failed Conservative Treatment',
            self::INFORMATION_ACCURATE => 'Information Accurate',
            self::MEDICAL_NECESSITY_ESTABLISHED => 'Medical Necessity Established',
            self::MAINTAIN_DOCUMENTATION => 'Maintain Documentation',

            // Medicare Specific
            self::MEDICARE_PART_B_AUTHORIZED => 'Medicare Part B Authorized',
            self::SNF_DAYS => 'SNF Days',
            self::HOSPICE_STATUS => 'Hospice Status',
            self::PART_A_STATUS => 'Part A Status',
            self::GLOBAL_PERIOD_STATUS => 'Global Period Status',
        ];

        return $labels[$field] ?? ucwords(str_replace('_', ' ', $field));
    }

    /**
     * Get field type for form rendering.
     */
    public static function getFieldType(string $field): string
    {
        $types = [
            // Text fields
            self::PATIENT_NAME => 'text',
            self::PROVIDER_NAME => 'text',
            self::FACILITY_NAME => 'text',
            self::PRIMARY_INS_NAME => 'text',
            self::SECONDARY_INS_NAME => 'text',
            self::PRODUCT_CODE => 'text',
            self::PRODUCT_NAME => 'text',
            self::WOUND_TYPE => 'text',
            self::WOUND_LOCATION => 'text',

            // Date fields
            self::PATIENT_DOB => 'date',
            self::ANTICIPATED_APPLICATION_DATE => 'date',

            // Number fields
            self::WOUND_SIZE_LENGTH => 'number',
            self::WOUND_SIZE_WIDTH => 'number',
            self::WOUND_SIZE_DEPTH => 'number',
            self::PRODUCT_QUANTITY => 'number',
            self::SNF_DAYS => 'number',

            // Select fields
            self::PATIENT_GENDER => 'select',
            self::PLACE_OF_SERVICE => 'select',
            self::SHIPPING_SPEED => 'select',
            self::PRODUCT_SIZE => 'select',

            // Boolean fields
            self::FAILED_CONSERVATIVE_TREATMENT => 'boolean',
            self::INFORMATION_ACCURATE => 'boolean',
            self::MEDICAL_NECESSITY_ESTABLISHED => 'boolean',
            self::MAINTAIN_DOCUMENTATION => 'boolean',
            self::MEDICARE_PART_B_AUTHORIZED => 'boolean',

            // Default to text for unknown fields
        ];

        return $types[$field] ?? 'text';
    }
}
