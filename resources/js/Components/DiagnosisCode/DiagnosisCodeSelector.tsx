import React, { useState, useEffect, useMemo } from 'react';
import { FiSearch, FiAlertCircle, FiCheck, FiChevronDown } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

// Types
interface DiagnosisCode {
  code: string;
  description: string;
  category?: string;
}

interface WoundType {
  id: string;
  name: string;
  requiresDualCoding: boolean;
  primaryCategory?: string;
  secondaryCategory?: string;
  primaryLabel?: string;
  secondaryLabel?: string;
}

interface DiagnosisCodeSelectorProps {
  value: {
    wound_type?: string;
    primary_diagnosis_code?: string;
    secondary_diagnosis_code?: string;
    diagnosis_code?: string;
  };
  onChange: (selection: {
    wound_type?: string;
    primary_diagnosis_code?: string;
    secondary_diagnosis_code?: string;
    diagnosis_code?: string;
  }) => void;
  errors?: {
    wound_type?: string;
    diagnosis?: string;
  };
  diagnosisCodes?: {
    yellow: Array<{ code: string; description: string }>;
    orange: Array<{ code: string; description: string }>;
  };
}

// Wound type configurations based on CSV structure
const WOUND_TYPES: WoundType[] = [
  {
    id: 'venous_leg_ulcer',
    name: 'Venous Leg Ulcer',
    requiresDualCoding: true,
    primaryCategory: 'venous_ulcer_combined',
    secondaryCategory: 'chronic_ulcer_l97',
    primaryLabel: 'Venous Diagnosis (I83 or I87 codes)',
    secondaryLabel: 'Chronic Ulcer (L97-codes)'
  },
  {
    id: 'diabetic_foot_ulcer',
    name: 'Diabetic Foot Ulcer',
    requiresDualCoding: true,
    primaryCategory: 'diabetes_mellitus',
    secondaryCategory: 'chronic_ulcer_l97',
    primaryLabel: 'Diabetes Mellitus (E-codes)',
    secondaryLabel: 'Chronic Ulcer (L97-codes)'
  },
  {
    id: 'pressure_ulcer',
    name: 'Pressure Ulcer',
    requiresDualCoding: false,
    primaryCategory: 'pressure_ulcer_l89',
    primaryLabel: 'Pressure Ulcer (L89-codes)'
  },
  {
    id: 'chronic_ulcer',
    name: 'Chronic Ulcer',
    requiresDualCoding: false,
    primaryCategory: 'chronic_ulcer_l97_single',
    primaryLabel: 'Chronic Ulcer (L97-codes)'
  }
];

// Searchable Select Component
const SearchableSelect: React.FC<{
  options: DiagnosisCode[];
  value: string;
  onChange: (code: string) => void;
  placeholder: string;
  searchPlaceholder: string;
  className?: string;
  error?: boolean;
}> = ({ options, value, onChange, placeholder, searchPlaceholder, className = '', error }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');

  // Theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  const filteredOptions = useMemo(() => {
    if (!searchTerm) return options;
    const search = searchTerm.toLowerCase();
    return options.filter(
      option =>
        option.code.toLowerCase().includes(search) ||
        option.description.toLowerCase().includes(search)
    );
  }, [options, searchTerm]);

  const selectedOption = options.find(opt => opt.code === value);

  return (
    <div className={`relative ${className}`}>
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className={cn(
          "w-full px-3 py-2 text-left rounded-md border transition-all duration-200 flex items-center justify-between",
          theme === 'dark'
            ? 'bg-gray-800 border-gray-700 text-white hover:border-gray-600'
            : 'bg-white border-gray-300 text-gray-900 hover:border-gray-400',
          error && 'border-red-500',
          "focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2",
          theme === 'dark' && "focus:ring-offset-gray-900"
        )}
      >
        {selectedOption ? (
          <span className="truncate">
            <span className="font-mono text-sm">{selectedOption.code}</span> - {selectedOption.description}
          </span>
        ) : (
          <span className={cn("text-sm", t.text.tertiary)}>{placeholder}</span>
        )}
        <FiChevronDown className={cn("ml-2 h-4 w-4 shrink-0", t.text.tertiary)} />
      </button>

      {isOpen && (
        <>
          <div
            className="fixed inset-0 z-40"
            onClick={() => {
              setIsOpen(false);
              setSearchTerm('');
            }}
          />
          <div className={cn(
            "absolute z-50 w-full mt-1 rounded-md shadow-lg max-h-96 overflow-hidden",
            theme === 'dark' ? 'bg-gray-800 border border-gray-700' : 'bg-white border border-gray-200'
          )}>
            <div className="p-2 border-b border-gray-200 dark:border-gray-700">
              <div className="relative">
                <FiSearch className={cn("absolute left-2 top-2.5 h-4 w-4", t.text.tertiary)} />
                <input
                  type="text"
                  placeholder={searchPlaceholder}
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className={cn(
                    "w-full pl-8 pr-3 py-2 rounded-md text-sm transition-all",
                    theme === 'dark'
                      ? 'bg-gray-900 border-gray-700 text-white placeholder-gray-400 focus:border-blue-500'
                      : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500 focus:border-blue-500',
                    "border focus:outline-none focus:ring-2 focus:ring-blue-500"
                  )}
                  onClick={(e) => e.stopPropagation()}
                />
              </div>
            </div>
            <div className="max-h-80 overflow-y-auto">
              {filteredOptions.length === 0 ? (
                <div className={cn("p-3 text-sm text-center", t.text.tertiary)}>
                  No diagnosis codes found
                </div>
              ) : (
                filteredOptions.slice(0, 50).map((option) => (
                  <div
                    key={option.code}
                    onClick={() => {
                      onChange(option.code);
                      setIsOpen(false);
                      setSearchTerm('');
                    }}
                    className={cn(
                      "p-3 cursor-pointer transition-colors",
                      theme === 'dark'
                        ? 'hover:bg-gray-700 border-b border-gray-700'
                        : 'hover:bg-gray-50 border-b border-gray-100',
                      "last:border-b-0"
                    )}
                  >
                    <div className="flex items-start gap-3">
                      <FiCheck
                        className={cn(
                          "mt-0.5 h-4 w-4",
                          value === option.code
                            ? 'text-blue-500'
                            : 'text-transparent'
                        )}
                      />
                      <div className="flex-1 min-w-0">
                        <div className={cn("font-mono text-sm font-medium", t.text.primary)}>
                          {option.code}
                        </div>
                        <div className={cn("text-sm mt-1", t.text.secondary)}>
                          {option.description}
                        </div>
                      </div>
                    </div>
                  </div>
                ))
              )}
              {filteredOptions.length > 50 && (
                <div className={cn(
                  "p-3 text-sm text-center border-t",
                  theme === 'dark' ? 'border-gray-700' : 'border-gray-200',
                  t.text.tertiary
                )}>
                  Showing first 50 results. Type to narrow search.
                </div>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
};

export const DiagnosisCodeSelector: React.FC<DiagnosisCodeSelectorProps> = ({
  value,
  onChange,
  errors,
  diagnosisCodes
}) => {
  const [dynamicCodes, setDynamicCodes] = useState<{
    primary: DiagnosisCode[];
    secondary: DiagnosisCode[];
  }>({
    primary: [],
    secondary: []
  });

  // Theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  const currentWoundType = useMemo(
    () => WOUND_TYPES.find(wt => wt.id === value.wound_type),
    [value.wound_type]
  );

  // Mock diagnosis codes - In production, these would come from an API
  useEffect(() => {
    if (!currentWoundType) {
      setDynamicCodes({ primary: [], secondary: [] });
      return;
    }

    // Load diagnosis codes based on wound type category from CSV structure
    const mockPrimaryCodes: DiagnosisCode[] = [];
    const mockSecondaryCodes: DiagnosisCode[] = [];

    // Group 1 & 2 Combined - Venous Leg Ulcers (I83 + I87 codes combined)
    if (currentWoundType.primaryCategory === 'venous_ulcer_combined') {
      // I83 codes - Varicose veins with ulcer
      mockPrimaryCodes.push(
        { code: 'I83.001', description: 'Varicose veins of unspecified lower extremity with ulcer of thigh', category: 'venous_ulcer' },
        { code: 'I83.002', description: 'Varicose veins of unspecified lower extremity with ulcer of calf', category: 'venous_ulcer' },
        { code: 'I83.003', description: 'Varicose veins of unspecified lower extremity with ulcer of ankle', category: 'venous_ulcer' },
        { code: 'I83.004', description: 'Varicose veins of unspecified lower extremity with ulcer of heel and midfoot', category: 'venous_ulcer' },
        { code: 'I83.005', description: 'Varicose veins of unspecified lower extremity with ulcer other part of foot', category: 'venous_ulcer' },
        { code: 'I83.008', description: 'Varicose veins of unspecified lower extremity with ulcer other part of lower leg', category: 'venous_ulcer' },
        { code: 'I83.009', description: 'Varicose veins of unspecified lower extremity with ulcer of unspecified site', category: 'venous_ulcer' },
        { code: 'I83.011', description: 'Varicose veins of right lower extremity with ulcer of thigh', category: 'venous_ulcer' },
        { code: 'I83.012', description: 'Varicose veins of right lower extremity with ulcer of calf', category: 'venous_ulcer' },
        { code: 'I83.013', description: 'Varicose veins of right lower extremity with ulcer of ankle', category: 'venous_ulcer' },
        { code: 'I83.014', description: 'Varicose veins of right lower extremity with ulcer of heel and midfoot', category: 'venous_ulcer' },
        { code: 'I83.015', description: 'Varicose veins of right lower extremity with ulcer other part of foot', category: 'venous_ulcer' },
        { code: 'I83.018', description: 'Varicose veins of right lower extremity with ulcer other part of lower leg', category: 'venous_ulcer' },
        { code: 'I83.019', description: 'Varicose veins of right lower extremity with ulcer of unspecified site', category: 'venous_ulcer' },
        { code: 'I83.021', description: 'Varicose veins of left lower extremity with ulcer of thigh', category: 'venous_ulcer' },
        { code: 'I83.022', description: 'Varicose veins of left lower extremity with ulcer of calf', category: 'venous_ulcer' },
        { code: 'I83.023', description: 'Varicose veins of left lower extremity with ulcer of ankle', category: 'venous_ulcer' },
        { code: 'I83.024', description: 'Varicose veins of left lower extremity with ulcer of heel and midfoot', category: 'venous_ulcer' },
        { code: 'I83.025', description: 'Varicose veins of left lower extremity with ulcer other part of foot', category: 'venous_ulcer' },
        { code: 'I83.028', description: 'Varicose veins of left lower extremity with ulcer other part of lower leg', category: 'venous_ulcer' },
        { code: 'I83.029', description: 'Varicose veins of left lower extremity with ulcer of unspecified site', category: 'venous_ulcer' },
        
        // I87 codes - Venous insufficiency with ulcer
        { code: 'I87.011', description: 'Postthrombotic syndrome with ulcer of right lower extremity', category: 'venous_ulcer' },
        { code: 'I87.012', description: 'Postthrombotic syndrome with ulcer of left lower extremity', category: 'venous_ulcer' },
        { code: 'I87.013', description: 'Postthrombotic syndrome with ulcer of bilateral lower extremity', category: 'venous_ulcer' },
        { code: 'I87.019', description: 'Postthrombotic syndrome with ulcer of unspecified lower extremity', category: 'venous_ulcer' },
        { code: 'I87.031', description: 'Postthrombotic syndrome with ulcer and inflammation of right lower extremity', category: 'venous_ulcer' },
        { code: 'I87.032', description: 'Postthrombotic syndrome with ulcer and inflammation of left lower extremity', category: 'venous_ulcer' },
        { code: 'I87.033', description: 'Postthrombotic syndrome with ulcer and inflammation of bilateral lower extremity', category: 'venous_ulcer' },
        { code: 'I87.039', description: 'Postthrombotic syndrome with ulcer and inflammation of unspecified lower extremity', category: 'venous_ulcer' },
        { code: 'I87.2', description: 'Venous insufficiency (chronic) (peripheral)', category: 'venous_ulcer' },
        { code: 'I87.311', description: 'Chronic venous hypertension (idiopathic) with ulcer of right lower extremity', category: 'venous_ulcer' },
        { code: 'I87.312', description: 'Chronic venous hypertension (idiopathic) with ulcer of left lower extremity', category: 'venous_ulcer' },
        { code: 'I87.313', description: 'Chronic venous hypertension (idiopathic) with ulcer of bilateral lower extremity', category: 'venous_ulcer' },
        { code: 'I87.319', description: 'Chronic venous hypertension (idiopathic) with ulcer of unspecified lower extremity', category: 'venous_ulcer' },
        { code: 'I87.331', description: 'Chronic venous hypertension (idiopathic) with ulcer and inflammation of right lower extremity', category: 'venous_ulcer' },
        { code: 'I87.332', description: 'Chronic venous hypertension (idiopathic) with ulcer and inflammation of left lower extremity', category: 'venous_ulcer' },
        { code: 'I87.333', description: 'Chronic venous hypertension (idiopathic) with ulcer and inflammation of bilateral lower extremity', category: 'venous_ulcer' },
        { code: 'I87.339', description: 'Chronic venous hypertension (idiopathic) with ulcer and inflammation of unspecified lower extremity', category: 'venous_ulcer' }
      );
    }
    
    // Group 3 - Diabetic Foot Ulcers (E-codes + L97)
    else if (currentWoundType.primaryCategory === 'diabetes_mellitus') {
      mockPrimaryCodes.push(
        { code: 'E08.621', description: 'Diabetes mellitus due to underlying condition with foot ulcer', category: 'diabetes' },
        { code: 'E08.622', description: 'Diabetes mellitus due to underlying condition with other skin ulcer', category: 'diabetes' },
        { code: 'E09.621', description: 'Drug or chemical induced diabetes mellitus with foot ulcer', category: 'diabetes' },
        { code: 'E09.622', description: 'Drug or chemical induced diabetes mellitus with other skin ulcer', category: 'diabetes' },
        { code: 'E10.621', description: 'Type 1 diabetes mellitus with foot ulcer', category: 'diabetes' },
        { code: 'E10.622', description: 'Type 1 diabetes mellitus with other skin ulcer', category: 'diabetes' },
        { code: 'E11.621', description: 'Type 2 diabetes mellitus with foot ulcer', category: 'diabetes' },
        { code: 'E11.622', description: 'Type 2 diabetes mellitus with other skin ulcer', category: 'diabetes' },
        { code: 'E13.621', description: 'Other specified diabetes mellitus with foot ulcer', category: 'diabetes' },
        { code: 'E13.622', description: 'Other specified diabetes mellitus with other skin ulcer', category: 'diabetes' },
        { code: 'E11.42', description: 'Type 2 diabetes mellitus with diabetic polyneuropathy', category: 'diabetes' },
        { code: 'E11.65', description: 'Type 2 diabetes mellitus with hyperglycemia', category: 'diabetes' },
        { code: 'E11.4', description: 'Type 2 diabetes mellitus with neurological complications', category: 'diabetes' },
        { code: 'E11.9', description: 'Type 2 diabetes mellitus without complications', category: 'diabetes' },
        { code: 'E11.49', description: 'Type 2 diabetes mellitus with other diabetic neurological complication', category: 'diabetes' }
      );
    }
    
    // Group 4 - Pressure Ulcers (L89 - single coding)
    else if (currentWoundType.primaryCategory === 'pressure_ulcer_l89') {
      mockPrimaryCodes.push(
        { code: 'L89.000', description: 'Pressure ulcer of unspecified elbow, unstageable', category: 'pressure_ulcer' },
        { code: 'L89.002', description: 'Pressure ulcer of unspecified elbow, stage 2', category: 'pressure_ulcer' },
        { code: 'L89.003', description: 'Pressure ulcer of unspecified elbow, stage 3', category: 'pressure_ulcer' },
        { code: 'L89.004', description: 'Pressure ulcer of unspecified elbow, stage 4', category: 'pressure_ulcer' },
        { code: 'L89.009', description: 'Pressure ulcer of unspecified elbow, unspecified stage', category: 'pressure_ulcer' },
        { code: 'L89.010', description: 'Pressure ulcer of right elbow, unstageable', category: 'pressure_ulcer' },
        { code: 'L89.011', description: 'Pressure ulcer of right elbow, stage 1', category: 'pressure_ulcer' },
        { code: 'L89.012', description: 'Pressure ulcer of right elbow, stage 2', category: 'pressure_ulcer' },
        { code: 'L89.013', description: 'Pressure ulcer of right elbow, stage 3', category: 'pressure_ulcer' },
        { code: 'L89.014', description: 'Pressure ulcer of right elbow, stage 4', category: 'pressure_ulcer' },
        { code: 'L89.150', description: 'Pressure ulcer of sacral region, unstageable', category: 'pressure_ulcer' },
        { code: 'L89.151', description: 'Pressure ulcer of sacral region, stage 1', category: 'pressure_ulcer' },
        { code: 'L89.152', description: 'Pressure ulcer of sacral region, stage 2', category: 'pressure_ulcer' },
        { code: 'L89.153', description: 'Pressure ulcer of sacral region, stage 3', category: 'pressure_ulcer' },
        { code: 'L89.154', description: 'Pressure ulcer of sacral region, stage 4', category: 'pressure_ulcer' },
        { code: 'L89.600', description: 'Pressure ulcer of unspecified heel, unstageable', category: 'pressure_ulcer' },
        { code: 'L89.601', description: 'Pressure ulcer of unspecified heel, stage 1', category: 'pressure_ulcer' },
        { code: 'L89.602', description: 'Pressure ulcer of unspecified heel, stage 2', category: 'pressure_ulcer' },
        { code: 'L89.603', description: 'Pressure ulcer of unspecified heel, stage 3', category: 'pressure_ulcer' },
        { code: 'L89.604', description: 'Pressure ulcer of unspecified heel, stage 4', category: 'pressure_ulcer' }
      );
    }
    
    // Group 5 - Chronic Ulcers (L97 - single coding)
    else if (currentWoundType.primaryCategory === 'chronic_ulcer_l97_single') {
      mockPrimaryCodes.push(
        { code: 'L97.101', description: 'Non-pressure chronic ulcer of unspecified thigh limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.102', description: 'Non-pressure chronic ulcer of unspecified thigh with fat layer exposed', category: 'chronic_ulcer' },
        { code: 'L97.103', description: 'Non-pressure chronic ulcer of unspecified thigh with necrosis of muscle', category: 'chronic_ulcer' },
        { code: 'L97.104', description: 'Non-pressure chronic ulcer of unspecified thigh with necrosis of bone', category: 'chronic_ulcer' },
        { code: 'L97.109', description: 'Non-pressure chronic ulcer of unspecified thigh with unspecified severity', category: 'chronic_ulcer' },
        { code: 'L97.201', description: 'Non-pressure chronic ulcer of unspecified calf limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.202', description: 'Non-pressure chronic ulcer of unspecified calf with fat layer exposed', category: 'chronic_ulcer' },
        { code: 'L97.203', description: 'Non-pressure chronic ulcer of unspecified calf with necrosis of muscle', category: 'chronic_ulcer' },
        { code: 'L97.204', description: 'Non-pressure chronic ulcer of unspecified calf with necrosis of bone', category: 'chronic_ulcer' },
        { code: 'L97.301', description: 'Non-pressure chronic ulcer of unspecified ankle limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.302', description: 'Non-pressure chronic ulcer of unspecified ankle with fat layer exposed', category: 'chronic_ulcer' },
        { code: 'L97.401', description: 'Non-pressure chronic ulcer of unspecified heel and midfoot limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.402', description: 'Non-pressure chronic ulcer of unspecified heel and midfoot with fat layer exposed', category: 'chronic_ulcer' },
        { code: 'L97.501', description: 'Non-pressure chronic ulcer of other part of unspecified foot limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.801', description: 'Non-pressure chronic ulcer of other part of unspecified lower leg limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L98.411', description: 'Non-pressure chronic ulcer of buttock limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L98.421', description: 'Non-pressure chronic ulcer of back limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L98.491', description: 'Non-pressure chronic ulcer of skin of other sites limited to breakdown of skin', category: 'chronic_ulcer' }
      );
    }

    // Secondary codes for dual-coding wound types (L97 codes)
    if (currentWoundType.secondaryCategory === 'chronic_ulcer_l97') {
      mockSecondaryCodes.push(
        { code: 'L97.101', description: 'Non-pressure chronic ulcer of unspecified thigh limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.102', description: 'Non-pressure chronic ulcer of unspecified thigh with fat layer exposed', category: 'chronic_ulcer' },
        { code: 'L97.103', description: 'Non-pressure chronic ulcer of unspecified thigh with necrosis of muscle', category: 'chronic_ulcer' },
        { code: 'L97.104', description: 'Non-pressure chronic ulcer of unspecified thigh with necrosis of bone', category: 'chronic_ulcer' },
        { code: 'L97.109', description: 'Non-pressure chronic ulcer of unspecified thigh with unspecified severity', category: 'chronic_ulcer' },
        { code: 'L97.111', description: 'Non-pressure chronic ulcer of right thigh limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.112', description: 'Non-pressure chronic ulcer of right thigh with fat layer exposed', category: 'chronic_ulcer' },
        { code: 'L97.121', description: 'Non-pressure chronic ulcer of left thigh limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.201', description: 'Non-pressure chronic ulcer of unspecified calf limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.202', description: 'Non-pressure chronic ulcer of unspecified calf with fat layer exposed', category: 'chronic_ulcer' },
        { code: 'L97.211', description: 'Non-pressure chronic ulcer of right calf limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.221', description: 'Non-pressure chronic ulcer of left calf limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.301', description: 'Non-pressure chronic ulcer of unspecified ankle limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.311', description: 'Non-pressure chronic ulcer of right ankle limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.321', description: 'Non-pressure chronic ulcer of left ankle limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.401', description: 'Non-pressure chronic ulcer of unspecified heel and midfoot limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.411', description: 'Non-pressure chronic ulcer of right heel and midfoot limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.421', description: 'Non-pressure chronic ulcer of left heel and midfoot limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.501', description: 'Non-pressure chronic ulcer of other part of unspecified foot limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.511', description: 'Non-pressure chronic ulcer of other part of right foot limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.521', description: 'Non-pressure chronic ulcer of other part of left foot limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.801', description: 'Non-pressure chronic ulcer of other part of unspecified lower leg limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.811', description: 'Non-pressure chronic ulcer of other part of right lower leg limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.821', description: 'Non-pressure chronic ulcer of other part of left lower leg limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L97.901', description: 'Non-pressure chronic ulcer of unspecified part of unspecified lower leg limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L98.411', description: 'Non-pressure chronic ulcer of buttock limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L98.421', description: 'Non-pressure chronic ulcer of back limited to breakdown of skin', category: 'chronic_ulcer' },
        { code: 'L98.491', description: 'Non-pressure chronic ulcer of skin of other sites limited to breakdown of skin', category: 'chronic_ulcer' }
      );
    }

    setDynamicCodes({ primary: mockPrimaryCodes, secondary: mockSecondaryCodes });
  }, [currentWoundType, diagnosisCodes]);

  const handleWoundTypeChange = (woundTypeId: string) => {
    onChange({
      wound_type: woundTypeId,
      primary_diagnosis_code: '',
      secondary_diagnosis_code: '',
      diagnosis_code: ''
    });
  };

  const handlePrimaryCodeChange = (code: string) => {
    if (currentWoundType?.requiresDualCoding) {
      onChange({
        ...value,
        primary_diagnosis_code: code,
        diagnosis_code: '' // Clear single code field when using dual coding
      });
    } else {
      onChange({
        ...value,
        diagnosis_code: code,
        primary_diagnosis_code: '', // Clear dual code fields when using single code
        secondary_diagnosis_code: '',
        wound_type: value.wound_type ?? ''
      });
    }
  };

  const handleSecondaryCodeChange = (code: string) => {
    onChange({
      ...value,
      secondary_diagnosis_code: code
    });
  };

  const isSelectionComplete = () => {
    if (!value.wound_type) return false;
    if (currentWoundType?.requiresDualCoding) {
      return !!(value.primary_diagnosis_code && value.secondary_diagnosis_code);
    }
    return !!value.diagnosis_code;
  };

  return (
    <div className="space-y-4">
      {/* Wound Type Selection */}
      <div className="space-y-3">
        <label className={cn("text-sm font-medium", t.text.primary)}>
          Select Wound Type <span className="text-red-500">*</span>
        </label>
        <div className="grid grid-cols-2 gap-3">
          {WOUND_TYPES.map((woundType) => (
            <div
              key={woundType.id}
              className={cn(
                "flex items-center space-x-3 p-3 rounded-lg cursor-pointer transition-all",
                theme === 'dark'
                  ? 'border border-gray-700 hover:bg-gray-800'
                  : 'border border-gray-200 hover:bg-gray-50',
                value.wound_type === woundType.id && (
                  theme === 'dark'
                    ? 'bg-gray-800 border-blue-500'
                    : 'bg-blue-50 border-blue-500'
                )
              )}
              onClick={() => handleWoundTypeChange(woundType.id)}
            >
              <input
                type="radio"
                name="woundType"
                value={woundType.id}
                checked={value.wound_type === woundType.id}
                onChange={() => handleWoundTypeChange(woundType.id)}
                className="h-4 w-4 text-blue-600 focus:ring-blue-500"
              />
              <label className="font-normal cursor-pointer flex items-center gap-2 flex-1">
                <span className={cn("text-sm", t.text.primary)}>{woundType.name}</span>
                {woundType.requiresDualCoding && (
                  <span className={cn(
                    "inline-flex items-center px-2 py-0.5 rounded text-xs font-medium",
                    theme === 'dark'
                      ? 'bg-blue-900 text-blue-300 border border-blue-700'
                      : 'bg-blue-100 text-blue-800 border border-blue-200'
                  )}>
                    Dual Code
                  </span>
                )}
              </label>
            </div>
          ))}
        </div>
        {errors?.wound_type && (
          <p className="mt-1 text-sm text-red-500">{errors.wound_type}</p>
        )}
      </div>

      {/* Diagnosis Code Selection */}
      {value.wound_type && currentWoundType && (
        <div className="space-y-4">
          {/* Primary Code Selection */}
          <div className="space-y-2">
            <label className={cn("text-sm font-medium", t.text.primary)}>
              {currentWoundType.primaryLabel}
              <span className="text-red-500 ml-1">*</span>
            </label>
            <SearchableSelect
              options={dynamicCodes.primary}
              value={currentWoundType.requiresDualCoding ? value.primary_diagnosis_code || '' : value.diagnosis_code || ''}
              onChange={handlePrimaryCodeChange}
              placeholder="Search for a diagnosis code..."
              searchPlaceholder="Search by code or description..."
              error={!!errors?.diagnosis}
            />
          </div>

          {/* Secondary Code Selection (for dual coding) */}
          {currentWoundType.requiresDualCoding && (
            <div className="space-y-2">
              <label className={cn("text-sm font-medium", t.text.primary)}>
                {currentWoundType.secondaryLabel}
                <span className="text-red-500 ml-1">*</span>
              </label>
              <SearchableSelect
                options={dynamicCodes.secondary}
                value={value.secondary_diagnosis_code || ''}
                onChange={handleSecondaryCodeChange}
                placeholder="Search for a diagnosis code..."
                searchPlaceholder="Search by code or description..."
                error={!!errors?.diagnosis}
              />
            </div>
          )}

          {/* Validation Alert */}
          {currentWoundType.requiresDualCoding && (
            <div className={cn(
              "p-4 rounded-lg flex items-start gap-2",
              theme === 'dark'
                ? 'bg-blue-900/20 border border-blue-800'
                : 'bg-blue-50 border border-blue-200'
            )}>
              <FiAlertCircle className={cn(
                "h-4 w-4 mt-0.5 flex-shrink-0",
                theme === 'dark' ? 'text-blue-400' : 'text-blue-600'
              )} />
              <div className={cn(
                "text-sm",
                theme === 'dark' ? 'text-blue-300' : 'text-blue-800'
              )}>
                {currentWoundType.name} requires both a primary diagnosis code and a
                secondary chronic ulcer code for proper billing compliance.
              </div>
            </div>
          )}

          {/* Error message */}
          {errors?.diagnosis && (
            <p className="text-sm text-red-500">{errors.diagnosis}</p>
          )}

                    {/* Selection Summary */}
          {isSelectionComplete() && (
            <div className={cn(
              "mt-4 px-4 py-2 rounded-lg w-full",
              theme === 'dark'
                ? 'bg-green-900/20 border border-green-800'
                : 'bg-green-50 border border-green-200'
            )}>
              <h4 className={cn(
                "font-medium mb-2 flex items-center gap-2 text-sm",
                theme === 'dark' ? 'text-green-300' : 'text-green-800'
              )}>
                <FiCheck className="h-4 w-4" />
                Selection Complete
              </h4>
              <div className="space-y-1 text-sm">
                <div className="flex justify-between items-center">
                  <span className="font-medium">Wound Type:</span>
                  <span className={cn(
                    "font-normal",
                    theme === 'dark' ? 'text-gray-300' : 'text-gray-700'
                  )}>{currentWoundType.name}</span>
                </div>
                {currentWoundType.requiresDualCoding ? (
                  <>
                    <div className="flex justify-between items-center">
                      <span className="font-medium">Primary Code:</span>
                      <span className={cn(
                        "font-mono",
                        theme === 'dark' ? 'text-gray-300' : 'text-gray-700'
                      )}>{value.primary_diagnosis_code}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <span className="font-medium">Secondary Code:</span>
                      <span className={cn(
                        "font-mono",
                        theme === 'dark' ? 'text-gray-300' : 'text-gray-700'
                      )}>{value.secondary_diagnosis_code}</span>
                    </div>
                  </>
                ) : (
                  <div className="flex justify-between items-center">
                    <span className="font-medium">Diagnosis Code:</span>
                    <span className={cn(
                      "font-mono",
                      theme === 'dark' ? 'text-gray-300' : 'text-gray-700'
                    )}>{value.diagnosis_code}</span>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default DiagnosisCodeSelector;
