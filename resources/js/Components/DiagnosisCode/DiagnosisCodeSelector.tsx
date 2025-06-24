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

// Wound type configurations
const WOUND_TYPES: WoundType[] = [
  {
    id: 'diabetic_foot_ulcer',
    name: 'Diabetic Foot Ulcer',
    requiresDualCoding: true,
    primaryCategory: 'diabetes',
    secondaryCategory: 'chronic_ulcer',
    primaryLabel: 'Diabetes Diagnosis (E-codes)',
    secondaryLabel: 'Chronic Ulcer Location (L97-codes)'
  },
  {
    id: 'venous_leg_ulcer',
    name: 'Venous Leg Ulcer',
    requiresDualCoding: true,
    primaryCategory: 'varicose',
    secondaryCategory: 'chronic_ulcer',
    primaryLabel: 'Varicose Vein Diagnosis (I83-codes)',
    secondaryLabel: 'Chronic Ulcer Severity (L97-codes)'
  },
  {
    id: 'pressure_ulcer',
    name: 'Pressure Ulcer',
    requiresDualCoding: false,
    primaryCategory: 'pressure',
    primaryLabel: 'Pressure Ulcer with Stage (L89-codes)'
  },
  {
    id: 'surgical_wound',
    name: 'Surgical Wound',
    requiresDualCoding: false,
    primaryCategory: 'surgical',
    primaryLabel: 'Post-procedural Complication'
  },
  {
    id: 'traumatic_wound',
    name: 'Traumatic Wound',
    requiresDualCoding: false,
    primaryCategory: 'trauma',
    primaryLabel: 'Injury Diagnosis'
  },
  {
    id: 'arterial_ulcer',
    name: 'Arterial Ulcer',
    requiresDualCoding: false,
    primaryCategory: 'arterial',
    primaryLabel: 'Atherosclerosis with Ulceration'
  },
  {
    id: 'chronic_ulcer',
    name: 'Chronic Ulcer',
    requiresDualCoding: false,
    primaryCategory: 'chronic_ulcer',
    primaryLabel: 'Non-pressure Chronic Ulcer (L97-codes)'
  },
  {
    id: 'other',
    name: 'Other',
    requiresDualCoding: false,
    primaryCategory: 'other',
    primaryLabel: 'Other Wound Diagnosis'
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

    // Simulate fetching codes based on wound type
    const mockPrimaryCodes: DiagnosisCode[] = [];
    const mockSecondaryCodes: DiagnosisCode[] = [];

    // Use provided diagnosis codes if available for specific categories
    if (currentWoundType.primaryCategory === 'diabetes' && diagnosisCodes?.yellow) {
      mockPrimaryCodes.push(...diagnosisCodes.yellow.map(c => ({
        ...c,
        category: 'diabetes'
      })));
    } else if (currentWoundType.primaryCategory === 'varicose' && diagnosisCodes?.orange) {
      // For venous leg ulcer primary codes
      mockPrimaryCodes.push(...[
        { code: 'I83.001', description: 'Varicose veins of unspecified lower extremity with ulcer of thigh', category: 'varicose' },
        { code: 'I83.002', description: 'Varicose veins of unspecified lower extremity with ulcer of calf', category: 'varicose' },
        { code: 'I83.003', description: 'Varicose veins of unspecified lower extremity with ulcer of ankle', category: 'varicose' },
        { code: 'I83.004', description: 'Varicose veins of unspecified lower extremity with ulcer of heel and midfoot', category: 'varicose' },
        { code: 'I83.005', description: 'Varicose veins of unspecified lower extremity with ulcer other part of foot', category: 'varicose' },
        { code: 'I83.009', description: 'Varicose veins of unspecified lower extremity with ulcer of unspecified site', category: 'varicose' }
      ]);
    }

    // Secondary codes for dual-coding wound types
    if (currentWoundType.secondaryCategory === 'chronic_ulcer' && diagnosisCodes?.orange) {
      mockSecondaryCodes.push(...diagnosisCodes.orange.map(c => ({
        ...c,
        category: 'chronic_ulcer'
      })));
    }

    // Add mock codes for specific wound types
    if (currentWoundType.primaryCategory === 'pressure') {
      mockPrimaryCodes.push(
        { code: 'L89.000', description: 'Pressure ulcer of unspecified elbow, unstageable', category: 'pressure' },
        { code: 'L89.001', description: 'Pressure ulcer of unspecified elbow, stage 1', category: 'pressure' },
        { code: 'L89.002', description: 'Pressure ulcer of unspecified elbow, stage 2', category: 'pressure' },
        { code: 'L89.003', description: 'Pressure ulcer of unspecified elbow, stage 3', category: 'pressure' },
        { code: 'L89.004', description: 'Pressure ulcer of unspecified elbow, stage 4', category: 'pressure' },
        { code: 'L89.100', description: 'Pressure ulcer of unspecified part of back, unstageable', category: 'pressure' },
        { code: 'L89.101', description: 'Pressure ulcer of unspecified part of back, stage 1', category: 'pressure' },
        { code: 'L89.102', description: 'Pressure ulcer of unspecified part of back, stage 2', category: 'pressure' },
        { code: 'L89.103', description: 'Pressure ulcer of unspecified part of back, stage 3', category: 'pressure' },
        { code: 'L89.104', description: 'Pressure ulcer of unspecified part of back, stage 4', category: 'pressure' }
      );
    } else if (currentWoundType.primaryCategory === 'surgical') {
      mockPrimaryCodes.push(
        { code: 'T81.31XA', description: 'Disruption of external operation (surgical) wound, not elsewhere classified, initial encounter', category: 'surgical' },
        { code: 'T81.32XA', description: 'Disruption of internal operation (surgical) wound, not elsewhere classified, initial encounter', category: 'surgical' },
        { code: 'T81.33XA', description: 'Disruption of traumatic injury wound repair, initial encounter', category: 'surgical' },
        { code: 'T81.89XA', description: 'Other complications of procedures, not elsewhere classified, initial encounter', category: 'surgical' },
        { code: 'T81.4XXA', description: 'Infection following a procedure, initial encounter', category: 'surgical' }
      );
    } else if (currentWoundType.primaryCategory === 'trauma') {
      mockPrimaryCodes.push(
        { code: 'S71.001A', description: 'Unspecified open wound, right hip, initial encounter', category: 'trauma' },
        { code: 'S71.101A', description: 'Unspecified open wound, right thigh, initial encounter', category: 'trauma' },
        { code: 'S81.001A', description: 'Unspecified open wound, right knee, initial encounter', category: 'trauma' },
        { code: 'S81.801A', description: 'Unspecified open wound, right lower leg, initial encounter', category: 'trauma' },
        { code: 'S91.001A', description: 'Unspecified open wound, right ankle, initial encounter', category: 'trauma' }
      );
    } else if (currentWoundType.primaryCategory === 'arterial') {
      mockPrimaryCodes.push(
        { code: 'I70.231', description: 'Atherosclerosis of native arteries of right leg with ulceration of thigh', category: 'arterial' },
        { code: 'I70.232', description: 'Atherosclerosis of native arteries of right leg with ulceration of calf', category: 'arterial' },
        { code: 'I70.233', description: 'Atherosclerosis of native arteries of right leg with ulceration of ankle', category: 'arterial' },
        { code: 'I70.234', description: 'Atherosclerosis of native arteries of right leg with ulceration of heel and midfoot', category: 'arterial' },
        { code: 'I70.235', description: 'Atherosclerosis of native arteries of right leg with ulceration of other part of foot', category: 'arterial' }
      );
    } else if (currentWoundType.primaryCategory === 'chronic_ulcer') {
      // For non-specific chronic ulcers
      mockPrimaryCodes.push(...(diagnosisCodes?.orange || []).map(c => ({
        ...c,
        category: 'chronic_ulcer'
      })));
      // Add additional chronic ulcer codes if needed
      if (mockPrimaryCodes.length === 0) {
        mockPrimaryCodes.push(
          { code: 'L97.101', description: 'Non-pressure chronic ulcer of unspecified thigh limited to breakdown of skin', category: 'chronic_ulcer' },
          { code: 'L97.201', description: 'Non-pressure chronic ulcer of unspecified calf limited to breakdown of skin', category: 'chronic_ulcer' },
          { code: 'L97.301', description: 'Non-pressure chronic ulcer of unspecified ankle limited to breakdown of skin', category: 'chronic_ulcer' },
          { code: 'L97.401', description: 'Non-pressure chronic ulcer of unspecified heel and midfoot limited to breakdown of skin', category: 'chronic_ulcer' },
          { code: 'L97.501', description: 'Non-pressure chronic ulcer of other part of unspecified foot limited to breakdown of skin', category: 'chronic_ulcer' }
        );
      }
    } else if (currentWoundType.primaryCategory === 'other' || !mockPrimaryCodes.length) {
      // For 'other' wound types or if no specific codes were added, show all available diagnosis codes
      // Combine all available codes from the provided data
      const allCodes: DiagnosisCode[] = [];
      
      if (diagnosisCodes?.yellow) {
        allCodes.push(...diagnosisCodes.yellow.map(c => ({ ...c, category: 'general' })));
      }
      if (diagnosisCodes?.orange) {
        allCodes.push(...diagnosisCodes.orange.map(c => ({ ...c, category: 'general' })));
      }
      
      // Add some common wound-related codes as fallback
      if (allCodes.length === 0) {
        allCodes.push(
          { code: 'L98.491', description: 'Non-pressure chronic ulcer of skin of other sites limited to breakdown of skin', category: 'other' },
          { code: 'L98.492', description: 'Non-pressure chronic ulcer of skin of other sites with fat layer exposed', category: 'other' },
          { code: 'L98.493', description: 'Non-pressure chronic ulcer of skin of other sites with necrosis of muscle', category: 'other' },
          { code: 'L98.494', description: 'Non-pressure chronic ulcer of skin of other sites with necrosis of bone', category: 'other' },
          { code: 'L98.499', description: 'Non-pressure chronic ulcer of skin of other sites with unspecified severity', category: 'other' }
        );
      }
      
      mockPrimaryCodes.push(...allCodes);
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
              "mt-4 p-2 rounded-lg",
              theme === 'dark' 
                ? 'bg-green-900/20 border border-green-800' 
                : 'bg-green-50 border border-green-200'
            )}>
              <h4 className={cn(
                "font-medium mb-1 flex items-center gap-2 text-sm",
                theme === 'dark' ? 'text-green-300' : 'text-green-800'
              )}>
                <FiCheck className="h-4 w-4" />
                Selection Complete
              </h4>
              <div className="space-y-1 text-xs">
                <div>
                  <span className="font-medium">Wound Type:</span>{' '}
                  {currentWoundType.name}
                </div>
                {currentWoundType.requiresDualCoding ? (
                  <>
                    <div>
                      <span className="font-medium">Primary Code:</span>{' '}
                      <span className="font-mono">{value.primary_diagnosis_code}</span>
                    </div>
                    <div>
                      <span className="font-medium">Secondary Code:</span>{' '}
                      <span className="font-mono">{value.secondary_diagnosis_code}</span>
                    </div>
                  </>
                ) : (
                  <div>
                    <span className="font-medium">Diagnosis Code:</span>{' '}
                    <span className="font-mono">{value.diagnosis_code}</span>
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