<?php

namespace App\Constants;

/**
 * Normalized DocuSeal Field Schema
 * 
 * From the 15 IVR PDFs, virtually every form carries the same data "atoms."
 * This class defines the canonical keys for storing in form_values and their
 * corresponding DocuSeal widget types.
 */
class DocuSealFields
{
    // Patient Information
    const PATIENT_NAME = 'patient_name';
    const PATIENT_DOB = 'patient_dob';
    const PATIENT_GENDER = 'patient_gender';
    const PATIENT_ADDRESS = 'patient_address';
    const PATIENT_PHONE_HOME = 'patient_phone_home';
    const PATIENT_PHONE_MOBILE = 'patient_phone_mobile';

    // SNF/Nursing Home
    const SNF_RESIDENT = 'snf_resident';
    const SNF_DAYS = 'snf_days';

    // Insurance Information
    const PRIMARY_INS_NAME = 'primary_ins_name';
    const POLICY_NUMBER_PRIMARY = 'policy_number_primary';
    const SECONDARY_INS_NAME = 'secondary_ins_name';
    const POLICY_NUMBER_SECONDARY = 'policy_number_secondary';
    const PAYER_PHONE_PRIMARY = 'payer_phone_primary';
    const PAYER_PHONE_SECONDARY = 'payer_phone_secondary';

    // Provider Information
    const PROVIDER_NAME = 'provider_name';
    const PROVIDER_NPI = 'provider_npi';
    const PROVIDER_TAX_ID = 'provider_tax_id';
    const PROVIDER_PTAN = 'provider_ptan';

    // Facility Information
    const FACILITY_NAME = 'facility_name';
    const FACILITY_NPI = 'facility_npi';
    const FACILITY_TAX_ID = 'facility_tax_id';
    const FACILITY_CONTACT_NAME = 'facility_contact_name';
    const FACILITY_CONTACT_PHONE = 'facility_contact_phone';
    const FACILITY_CONTACT_EMAIL = 'facility_contact_email';

    // Service Information
    const PLACE_OF_SERVICE = 'place_of_service';
    const PRODUCT_CODE = 'product_code';
    const PRODUCT_SIZE = 'product_size';
    const APPLICATION_TYPE = 'application_type';
    const ANTICIPATED_APPLICATION_DATE = 'anticipated_application_date';
    const ANTICIPATED_APPLICATION_COUNT = 'anticipated_application_count';

    // Clinical Information
    const WOUND_TYPE = 'wound_type';
    const ICD10_PRIMARY = 'icd10_primary';
    const ICD10_SECONDARY = 'icd10_secondary';
    const CPT_PRIMARY = 'cpt_primary';
    const CPT_SECONDARY = 'cpt_secondary';
    const WOUND_LOCATION = 'wound_location';
    const WOUND_SIZE_CM2 = 'wound_size_cm2';

    // Authorization
    const PRIOR_AUTHORIZATION_HELP = 'prior_authorization_help';

    // Attachments
    const ATTACHMENTS_INS_CARD = 'attachments_ins_card';
    const ATTACHMENTS_INS_CARD_FRONT = 'attachments_ins_card_front';
    const ATTACHMENTS_INS_CARD_BACK = 'attachments_ins_card_back';

    // Signature
    const AUTHORIZED_SIGNATURE = 'authorized_signature';
    const SIGNATURE_DATE = 'signature_date';

    /**
     * DocuSeal widget type mapping for each field
     */
    const FIELD_TYPES = [
        self::PATIENT_NAME => 'text',
        self::PATIENT_DOB => 'date',
        self::PATIENT_GENDER => 'radio', // Male/Female
        self::PATIENT_ADDRESS => 'textarea',
        self::PATIENT_PHONE_HOME => 'tel',
        self::PATIENT_PHONE_MOBILE => 'tel',

        self::SNF_RESIDENT => 'checkbox', // Yes/No
        self::SNF_DAYS => 'number',

        self::PRIMARY_INS_NAME => 'text',
        self::POLICY_NUMBER_PRIMARY => 'text',
        self::SECONDARY_INS_NAME => 'text',
        self::POLICY_NUMBER_SECONDARY => 'text',
        self::PAYER_PHONE_PRIMARY => 'tel',
        self::PAYER_PHONE_SECONDARY => 'tel',

        self::PROVIDER_NAME => 'text',
        self::PROVIDER_NPI => 'text',
        self::PROVIDER_TAX_ID => 'text',
        self::PROVIDER_PTAN => 'text',

        self::FACILITY_NAME => 'text',
        self::FACILITY_NPI => 'text',
        self::FACILITY_TAX_ID => 'text',
        self::FACILITY_CONTACT_NAME => 'text',
        self::FACILITY_CONTACT_PHONE => 'tel',
        self::FACILITY_CONTACT_EMAIL => 'email',

        self::PLACE_OF_SERVICE => 'checkbox_group', // POS-11, 22, 24, 32...
        self::PRODUCT_CODE => 'select', // HCPCS Q-code dropdown
        self::PRODUCT_SIZE => 'checkbox_group', // 2×2 cm, 4×4 cm, etc.
        self::APPLICATION_TYPE => 'checkbox', // New, Add'l, Re‑verify, New Ins
        self::ANTICIPATED_APPLICATION_DATE => 'date',
        self::ANTICIPATED_APPLICATION_COUNT => 'number',

        self::WOUND_TYPE => 'checkbox_group', // Diabetic, Venous, Pressure, Trauma, etc.
        self::ICD10_PRIMARY => 'text',
        self::ICD10_SECONDARY => 'text',
        self::CPT_PRIMARY => 'text',
        self::CPT_SECONDARY => 'text',
        self::WOUND_LOCATION => 'text',
        self::WOUND_SIZE_CM2 => 'number',

        self::PRIOR_AUTHORIZATION_HELP => 'checkbox', // Yes/No

        self::ATTACHMENTS_INS_CARD => 'file',
        self::ATTACHMENTS_INS_CARD_FRONT => 'file',
        self::ATTACHMENTS_INS_CARD_BACK => 'file',

        self::AUTHORIZED_SIGNATURE => 'signature',
        self::SIGNATURE_DATE => 'date_auto',
    ];

    /**
     * Human-readable labels for fields
     */
    const FIELD_LABELS = [
        self::PATIENT_NAME => 'Patient Name',
        self::PATIENT_DOB => 'Date of Birth',
        self::PATIENT_GENDER => 'Gender',
        self::PATIENT_ADDRESS => 'Patient Address',
        self::PATIENT_PHONE_HOME => 'Home Phone',
        self::PATIENT_PHONE_MOBILE => 'Mobile Phone',

        self::SNF_RESIDENT => 'SNF Resident',
        self::SNF_DAYS => 'SNF Days',

        self::PRIMARY_INS_NAME => 'Primary Insurance',
        self::POLICY_NUMBER_PRIMARY => 'Primary Policy Number',
        self::SECONDARY_INS_NAME => 'Secondary Insurance',
        self::POLICY_NUMBER_SECONDARY => 'Secondary Policy Number',
        self::PAYER_PHONE_PRIMARY => 'Primary Payer Phone',
        self::PAYER_PHONE_SECONDARY => 'Secondary Payer Phone',

        self::PROVIDER_NAME => 'Provider Name',
        self::PROVIDER_NPI => 'Provider NPI',
        self::PROVIDER_TAX_ID => 'Provider Tax ID',
        self::PROVIDER_PTAN => 'Provider PTAN',

        self::FACILITY_NAME => 'Facility Name',
        self::FACILITY_NPI => 'Facility NPI',
        self::FACILITY_TAX_ID => 'Facility Tax ID',
        self::FACILITY_CONTACT_NAME => 'Facility Contact Name',
        self::FACILITY_CONTACT_PHONE => 'Facility Contact Phone',
        self::FACILITY_CONTACT_EMAIL => 'Facility Contact Email',

        self::PLACE_OF_SERVICE => 'Place of Service',
        self::PRODUCT_CODE => 'Product Code',
        self::PRODUCT_SIZE => 'Product Size',
        self::APPLICATION_TYPE => 'Application Type',
        self::ANTICIPATED_APPLICATION_DATE => 'Anticipated Application Date',
        self::ANTICIPATED_APPLICATION_COUNT => 'Anticipated Application Count',

        self::WOUND_TYPE => 'Wound Type',
        self::ICD10_PRIMARY => 'Primary ICD-10',
        self::ICD10_SECONDARY => 'Secondary ICD-10',
        self::CPT_PRIMARY => 'Primary CPT',
        self::CPT_SECONDARY => 'Secondary CPT',
        self::WOUND_LOCATION => 'Wound Location',
        self::WOUND_SIZE_CM2 => 'Wound Size (cm²)',

        self::PRIOR_AUTHORIZATION_HELP => 'Prior Authorization Help',

        self::ATTACHMENTS_INS_CARD => 'Insurance Card',
        self::ATTACHMENTS_INS_CARD_FRONT => 'Insurance Card Front',
        self::ATTACHMENTS_INS_CARD_BACK => 'Insurance Card Back',

        self::AUTHORIZED_SIGNATURE => 'Authorized Signature',
        self::SIGNATURE_DATE => 'Signature Date',
    ];

    /**
     * Get all canonical field keys
     */
    public static function getAllFields(): array
    {
        return [
            // Patient Information
            self::PATIENT_NAME,
            self::PATIENT_DOB,
            self::PATIENT_GENDER,
            self::PATIENT_ADDRESS,
            self::PATIENT_PHONE_HOME,
            self::PATIENT_PHONE_MOBILE,

            // SNF/Nursing Home
            self::SNF_RESIDENT,
            self::SNF_DAYS,

            // Insurance Information
            self::PRIMARY_INS_NAME,
            self::POLICY_NUMBER_PRIMARY,
            self::SECONDARY_INS_NAME,
            self::POLICY_NUMBER_SECONDARY,
            self::PAYER_PHONE_PRIMARY,
            self::PAYER_PHONE_SECONDARY,

            // Provider Information
            self::PROVIDER_NAME,
            self::PROVIDER_NPI,
            self::PROVIDER_TAX_ID,
            self::PROVIDER_PTAN,

            // Facility Information
            self::FACILITY_NAME,
            self::FACILITY_NPI,
            self::FACILITY_TAX_ID,
            self::FACILITY_CONTACT_NAME,
            self::FACILITY_CONTACT_PHONE,
            self::FACILITY_CONTACT_EMAIL,

            // Service Information
            self::PLACE_OF_SERVICE,
            self::PRODUCT_CODE,
            self::PRODUCT_SIZE,
            self::APPLICATION_TYPE,
            self::ANTICIPATED_APPLICATION_DATE,
            self::ANTICIPATED_APPLICATION_COUNT,

            // Clinical Information
            self::WOUND_TYPE,
            self::ICD10_PRIMARY,
            self::ICD10_SECONDARY,
            self::CPT_PRIMARY,
            self::CPT_SECONDARY,
            self::WOUND_LOCATION,
            self::WOUND_SIZE_CM2,

            // Authorization
            self::PRIOR_AUTHORIZATION_HELP,

            // Attachments
            self::ATTACHMENTS_INS_CARD,
            self::ATTACHMENTS_INS_CARD_FRONT,
            self::ATTACHMENTS_INS_CARD_BACK,

            // Signature
            self::AUTHORIZED_SIGNATURE,
            self::SIGNATURE_DATE,
        ];
    }

    /**
     * Get field type for a specific field
     */
    public static function getFieldType(string $field): ?string
    {
        return self::FIELD_TYPES[$field] ?? null;
    }

    /**
     * Get field label for a specific field
     */
    public static function getFieldLabel(string $field): ?string
    {
        return self::FIELD_LABELS[$field] ?? $field;
    }

    /**
     * Get fields grouped by category
     */
    public static function getFieldsByCategory(): array
    {
        return [
            'patient' => [
                self::PATIENT_NAME,
                self::PATIENT_DOB,
                self::PATIENT_GENDER,
                self::PATIENT_ADDRESS,
                self::PATIENT_PHONE_HOME,
                self::PATIENT_PHONE_MOBILE,
                self::SNF_RESIDENT,
                self::SNF_DAYS,
            ],
            'insurance' => [
                self::PRIMARY_INS_NAME,
                self::POLICY_NUMBER_PRIMARY,
                self::SECONDARY_INS_NAME,
                self::POLICY_NUMBER_SECONDARY,
                self::PAYER_PHONE_PRIMARY,
                self::PAYER_PHONE_SECONDARY,
            ],
            'provider' => [
                self::PROVIDER_NAME,
                self::PROVIDER_NPI,
                self::PROVIDER_TAX_ID,
                self::PROVIDER_PTAN,
            ],
            'facility' => [
                self::FACILITY_NAME,
                self::FACILITY_NPI,
                self::FACILITY_TAX_ID,
                self::FACILITY_CONTACT_NAME,
                self::FACILITY_CONTACT_PHONE,
                self::FACILITY_CONTACT_EMAIL,
            ],
            'clinical' => [
                self::WOUND_TYPE,
                self::ICD10_PRIMARY,
                self::ICD10_SECONDARY,
                self::CPT_PRIMARY,
                self::CPT_SECONDARY,
                self::WOUND_LOCATION,
                self::WOUND_SIZE_CM2,
            ],
            'service' => [
                self::PLACE_OF_SERVICE,
                self::PRODUCT_CODE,
                self::PRODUCT_SIZE,
                self::APPLICATION_TYPE,
                self::ANTICIPATED_APPLICATION_DATE,
                self::ANTICIPATED_APPLICATION_COUNT,
            ],
            'authorization' => [
                self::PRIOR_AUTHORIZATION_HELP,
            ],
            'attachments' => [
                self::ATTACHMENTS_INS_CARD,
                self::ATTACHMENTS_INS_CARD_FRONT,
                self::ATTACHMENTS_INS_CARD_BACK,
            ],
            'signature' => [
                self::AUTHORIZED_SIGNATURE,
                self::SIGNATURE_DATE,
            ],
        ];
    }
}
