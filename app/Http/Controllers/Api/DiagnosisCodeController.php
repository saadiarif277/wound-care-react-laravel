<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiagnosisCodeController extends Controller
{
    /**
     * Get diagnosis codes by wound type(s)
     */
    public function getByWoundType(Request $request)
    {
        $request->validate([
            'wound_types' => 'required|array',
            'wound_types.*' => 'string|exists:wound_types,code',
        ]);

        $woundTypes = $request->input('wound_types');
        
        // Get diagnosis codes for the selected wound types
        $diagnosisCodes = DB::table('wound_type_diagnosis_codes as wtdc')
            ->join('diagnosis_codes as dc', 'wtdc.diagnosis_code', '=', 'dc.code')
            ->whereIn('wtdc.wound_type_code', $woundTypes)
            ->where('dc.is_active', true)
            ->select([
                'dc.code',
                'dc.description',
                'wtdc.category',
                'wtdc.wound_type_code',
                'wtdc.is_required',
                'dc.specialty'
            ])
            ->orderBy('wtdc.category')
            ->orderBy('dc.code')
            ->get();

        // Group by category and wound type
        $groupedCodes = [
            'yellow' => [],
            'orange' => [],
            'none' => [], // For pressure ulcers which don't have color categories
            'requirements' => []
        ];

        foreach ($diagnosisCodes as $code) {
            $category = $code->category ?: 'none';
            
            if (!isset($groupedCodes[$category])) {
                $groupedCodes[$category] = [];
            }

            // Add to the appropriate category
            $codeData = [
                'code' => $code->code,
                'description' => $code->description,
                'wound_type' => $code->wound_type_code,
                'specialty' => $code->specialty
            ];

            // Avoid duplicates
            $exists = false;
            foreach ($groupedCodes[$category] as $existingCode) {
                if ($existingCode['code'] === $code->code) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $groupedCodes[$category][] = $codeData;
            }

            // Track requirements
            if ($code->is_required) {
                if (!isset($groupedCodes['requirements'][$code->wound_type_code])) {
                    $groupedCodes['requirements'][$code->wound_type_code] = [];
                }
                
                if ($code->category) {
                    $groupedCodes['requirements'][$code->wound_type_code][$code->category] = true;
                } else {
                    $groupedCodes['requirements'][$code->wound_type_code]['any'] = true;
                }
            }
        }

        // Build requirement messages
        $requirementMessages = [];
        foreach ($groupedCodes['requirements'] as $woundType => $requirements) {
            $woundTypeName = DB::table('wound_types')
                ->where('code', $woundType)
                ->value('display_name');

            if (isset($requirements['yellow']) && isset($requirements['orange'])) {
                $requirementMessages[] = "{$woundTypeName} requires 1 Yellow AND 1 Orange diagnosis code";
            } elseif (isset($requirements['any'])) {
                $requirementMessages[] = "{$woundTypeName} requires at least 1 diagnosis code";
            }
        }

        return response()->json([
            'codes' => $groupedCodes,
            'requirements' => $requirementMessages,
            'wound_types' => $woundTypes
        ]);
    }

    /**
     * Get all diagnosis codes (for backward compatibility)
     */
    public function getAll()
    {
        $diagnosisCodes = DB::table('diagnosis_codes')
            ->where('is_active', true)
            ->select(['code', 'description', 'category', 'specialty'])
            ->orderBy('category')
            ->orderBy('code')
            ->get();

        $grouped = [
            'yellow' => [],
            'orange' => [],
            'none' => []
        ];

        foreach ($diagnosisCodes as $code) {
            $category = $code->category ?: 'none';
            $grouped[$category][] = [
                'code' => $code->code,
                'description' => $code->description,
                'specialty' => $code->specialty
            ];
        }

        return response()->json($grouped);
    }
}