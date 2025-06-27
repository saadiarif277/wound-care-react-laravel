<?php

namespace App\Services;

/**
 * Sample data for CMS Coverage API when the real API is unavailable
 * This provides realistic data structure based on actual CMS API responses
 */
class CmsCoverageApiSampleData
{
    /**
     * Get sample LCDs for California
     */
    public static function getSampleLCDs(): array
    {
        return [
            'data' => [
                [
                    'documentId' => 'L36690',
                    'documentTitle' => 'Skin Substitute Grafts/Cellular and Tissue-Based Products for the Treatment of Diabetic Foot Ulcers and Venous Leg Ulcers',
                    'contractor' => 'Noridian Healthcare Solutions, LLC',
                    'contractorNumber' => 'JF',
                    'originalEffectiveDate' => '2017-01-01',
                    'revisionEffectiveDate' => '2023-10-26',
                    'revisionEndingDate' => null,
                    'retiredDate' => null,
                    'jurisdictionStates' => ['AK', 'AZ', 'CA', 'HI', 'ID', 'MT', 'NV', 'OR', 'UT', 'WA', 'WY']
                ],
                [
                    'documentId' => 'L37166',
                    'documentTitle' => 'Wound Care',
                    'contractor' => 'Noridian Healthcare Solutions, LLC',
                    'contractorNumber' => 'JF',
                    'originalEffectiveDate' => '2017-10-01',
                    'revisionEffectiveDate' => '2023-01-01',
                    'revisionEndingDate' => null,
                    'retiredDate' => null,
                    'jurisdictionStates' => ['AK', 'AZ', 'CA', 'HI', 'ID', 'MT', 'NV', 'OR', 'UT', 'WA', 'WY']
                ],
                [
                    'documentId' => 'L38295',
                    'documentTitle' => 'Surgical Dressings',
                    'contractor' => 'Noridian Healthcare Solutions, LLC',
                    'contractorNumber' => 'JF',
                    'originalEffectiveDate' => '2019-01-01',
                    'revisionEffectiveDate' => '2022-07-01',
                    'revisionEndingDate' => null,
                    'retiredDate' => null,
                    'jurisdictionStates' => ['AK', 'AZ', 'CA', 'HI', 'ID', 'MT', 'NV', 'OR', 'UT', 'WA', 'WY']
                ]
            ],
            'meta' => [
                'totalItems' => 3,
                'itemsPerPage' => 100,
                'currentPage' => 1
            ]
        ];
    }

    /**
     * Get sample NCDs
     */
    public static function getSampleNCDs(): array
    {
        return [
            'data' => [
                [
                    'ncdId' => '270.5',
                    'documentId' => 'NCD270.5',
                    'documentTitle' => 'Hyperbaric Oxygen Therapy for Hypoxic Wounds and Diabetic Wounds of the Lower Extremities',
                    'implementationDate' => '2017-05-05',
                    'endDate' => null,
                    'coverageDescription' => 'Coverage of HBO therapy for diabetic wounds of the lower extremities'
                ],
                [
                    'ncdId' => '270.1',
                    'documentId' => 'NCD270.1',
                    'documentTitle' => 'Electrical Stimulation and Electromagnetic Therapy for the Treatment of Wounds',
                    'implementationDate' => '2004-07-01',
                    'endDate' => null,
                    'coverageDescription' => 'Coverage determination for electrical stimulation in wound treatment'
                ]
            ],
            'meta' => [
                'totalItems' => 2,
                'itemsPerPage' => 50,
                'currentPage' => 1
            ]
        ];
    }

    /**
     * Check if we should use sample data based on API status
     */
    public static function shouldUseSampleData(int $httpStatus): bool
    {
        // Use sample data for server errors (5xx) or when explicitly in demo mode
        return $httpStatus >= 500 || config('cms.api.use_sample_data', false);
    }
}