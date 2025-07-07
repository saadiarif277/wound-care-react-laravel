import React, { useState, useEffect } from 'react';
import { 
  FiBrain, 
  FiCheck, 
  FiX, 
  FiAlertTriangle, 
  FiInfo, 
  FiThumbsUp, 
  FiThumbsDown,
  FiRefreshCw,
  FiTrendingUp
} from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface FieldMapping {
  source_field: string;
  target_field: string;
  confidence: number;
  method: 'ml_prediction' | 'fallback_mapping' | 'heuristic';
  model_used?: string;
  alternatives?: Array<{
    field: string;
    confidence: number;
  }>;
}

interface MLResults {
  mapped_data: Record<string, any>;
  mapping_results: FieldMapping[];
  unmapped_fields: string[];
}

interface Props {
  manufacturerName: string;
  templateId?: string;
  documentType?: string;
  mlResults?: MLResults;
  onFeedback?: (sourceField: string, targetField: string, success: boolean, userFeedback?: string) => void;
  onRetryMapping?: () => void;
  className?: string;
}

export const FieldMappingConfidence: React.FC<Props> = ({
  manufacturerName,
  templateId,
  documentType = 'IVR',
  mlResults,
  onFeedback,
  onRetryMapping,
  className = ''
}) => {
  // Theme setup
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if context is not available
  }

  const [feedbackGiven, setFeedbackGiven] = useState<Record<string, boolean>>({});
  const [expandedField, setExpandedField] = useState<string | null>(null);

  // Calculate overall confidence statistics
  const confidenceStats = React.useMemo(() => {
    if (!mlResults?.mapping_results) {
      return { avgConfidence: 0, highConfidence: 0, lowConfidence: 0, totalMappings: 0 };
    }

    const mappings = mlResults.mapping_results;
    const totalMappings = mappings.length;
    const avgConfidence = mappings.reduce((sum, m) => sum + m.confidence, 0) / totalMappings;
    const highConfidence = mappings.filter(m => m.confidence >= 0.8).length;
    const lowConfidence = mappings.filter(m => m.confidence < 0.6).length;

    return { avgConfidence, highConfidence, lowConfidence, totalMappings };
  }, [mlResults]);

  const getConfidenceColor = (confidence: number) => {
    if (confidence >= 0.8) return 'text-green-600';
    if (confidence >= 0.6) return 'text-yellow-600';
    return 'text-red-600';
  };

  const getConfidenceIcon = (confidence: number) => {
    if (confidence >= 0.8) return FiCheck;
    if (confidence >= 0.6) return FiAlertTriangle;
    return FiX;
  };

  const getMethodBadge = (method: string) => {
    const badges = {
      ml_prediction: { label: 'ML', color: 'bg-blue-100 text-blue-800' },
      fallback_mapping: { label: 'Fallback', color: 'bg-gray-100 text-gray-800' },
      heuristic: { label: 'Heuristic', color: 'bg-orange-100 text-orange-800' }
    };
    
    return badges[method as keyof typeof badges] || badges.heuristic;
  };

  const handleFeedback = (mapping: FieldMapping, success: boolean, userFeedback?: string) => {
    const key = `${mapping.source_field}-${mapping.target_field}`;
    
    setFeedbackGiven(prev => ({
      ...prev,
      [key]: true
    }));

    if (onFeedback) {
      onFeedback(mapping.source_field, mapping.target_field, success, userFeedback);
    }
  };

  if (!mlResults) {
    return (
      <div className={cn(t.glass.card, "p-4 rounded-lg", className)}>
        <div className="flex items-center text-center">
          <FiBrain className={cn("w-5 h-5 mr-2", t.text.muted)} />
          <span className={cn("text-sm", t.text.secondary)}>
            ML field mapping not available
          </span>
        </div>
      </div>
    );
  }

  return (
    <div className={cn("space-y-4", className)}>
      {/* Overall Confidence Summary */}
      <div className={cn(t.glass.card, "p-4 rounded-lg")}>
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center">
            <FiBrain className={cn("w-5 h-5 mr-2", t.text.primary)} />
            <h3 className={cn("text-lg font-semibold", t.text.primary)}>
              ML Field Mapping Analysis
            </h3>
          </div>
          {onRetryMapping && (
            <button
              onClick={onRetryMapping}
              className={cn(
                "flex items-center px-3 py-1 text-xs rounded-md",
                t.button.ghost.base,
                t.button.ghost.hover
              )}
            >
              <FiRefreshCw className="w-3 h-3 mr-1" />
              Retry
            </button>
          )}
        </div>

        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
          <div className="text-center">
            <div className={cn("text-2xl font-bold", t.text.primary)}>
              {confidenceStats.totalMappings}
            </div>
            <div className={cn("text-xs", t.text.secondary)}>Total Mappings</div>
          </div>
          <div className="text-center">
            <div className={cn("text-2xl font-bold", getConfidenceColor(confidenceStats.avgConfidence))}>
              {Math.round(confidenceStats.avgConfidence * 100)}%
            </div>
            <div className={cn("text-xs", t.text.secondary)}>Avg Confidence</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-bold text-green-600">
              {confidenceStats.highConfidence}
            </div>
            <div className={cn("text-xs", t.text.secondary)}>High Confidence</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-bold text-red-600">
              {confidenceStats.lowConfidence}
            </div>
            <div className={cn("text-xs", t.text.secondary)}>Low Confidence</div>
          </div>
        </div>

        <div className={cn("text-xs", t.text.tertiary)}>
          Manufacturer: {manufacturerName} • Template: {templateId || 'default'} • Type: {documentType}
        </div>
      </div>

      {/* Field Mapping Details */}
      <div className={cn(t.glass.card, "rounded-lg")}>
        <div className={cn("p-4 border-b", theme === 'dark' ? 'border-white/10' : 'border-gray-200')}>
          <h4 className={cn("text-sm font-semibold", t.text.primary)}>
            Field Mapping Details
          </h4>
        </div>

        <div className="max-h-96 overflow-y-auto">
          {mlResults.mapping_results.map((mapping, index) => {
            const ConfidenceIcon = getConfidenceIcon(mapping.confidence);
            const methodBadge = getMethodBadge(mapping.method);
            const feedbackKey = `${mapping.source_field}-${mapping.target_field}`;
            const isExpanded = expandedField === feedbackKey;

            return (
              <div
                key={feedbackKey}
                className={cn(
                  "p-4 border-b transition-colors",
                  theme === 'dark' ? 'border-white/10 hover:bg-white/5' : 'border-gray-200 hover:bg-gray-50'
                )}
              >
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <div className="flex items-center space-x-2 mb-1">
                      <ConfidenceIcon className={cn("w-4 h-4", getConfidenceColor(mapping.confidence))} />
                      <span className={cn("text-sm font-medium", t.text.primary)}>
                        {mapping.source_field}
                      </span>
                      <span className={cn("text-xs", t.text.tertiary)}>→</span>
                      <span className={cn("text-sm", t.text.secondary)}>
                        {mapping.target_field}
                      </span>
                    </div>
                    
                    <div className="flex items-center space-x-2">
                      <span className={cn("text-xs font-medium", getConfidenceColor(mapping.confidence))}>
                        {Math.round(mapping.confidence * 100)}% confidence
                      </span>
                      <span className={cn(
                        "px-2 py-1 text-xs rounded-full",
                        methodBadge.color
                      )}>
                        {methodBadge.label}
                      </span>
                      {mapping.model_used && (
                        <span className={cn("text-xs", t.text.muted)}>
                          via {mapping.model_used}
                        </span>
                      )}
                    </div>
                  </div>

                  <div className="flex items-center space-x-2 ml-4">
                    {!feedbackGiven[feedbackKey] ? (
                      <>
                        <button
                          onClick={() => handleFeedback(mapping, true)}
                          className={cn(
                            "p-1 rounded-md transition-colors",
                            "hover:bg-green-100 text-green-600 hover:text-green-700"
                          )}
                          title="Correct mapping"
                        >
                          <FiThumbsUp className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleFeedback(mapping, false)}
                          className={cn(
                            "p-1 rounded-md transition-colors",
                            "hover:bg-red-100 text-red-600 hover:text-red-700"
                          )}
                          title="Incorrect mapping"
                        >
                          <FiThumbsDown className="w-4 h-4" />
                        </button>
                      </>
                    ) : (
                      <span className={cn("text-xs", t.text.muted)}>
                        Feedback recorded
                      </span>
                    )}

                    {mapping.alternatives && mapping.alternatives.length > 0 && (
                      <button
                        onClick={() => setExpandedField(isExpanded ? null : feedbackKey)}
                        className={cn(
                          "p-1 rounded-md transition-colors",
                          t.button.ghost.base,
                          t.button.ghost.hover
                        )}
                        title="Show alternatives"
                      >
                        <FiInfo className="w-4 h-4" />
                      </button>
                    )}
                  </div>
                </div>

                {/* Alternative Suggestions */}
                {isExpanded && mapping.alternatives && mapping.alternatives.length > 0 && (
                  <div className={cn("mt-3 p-3 rounded-md", t.glass.frost)}>
                    <h5 className={cn("text-xs font-medium mb-2", t.text.primary)}>
                      Alternative Suggestions:
                    </h5>
                    <div className="space-y-1">
                      {mapping.alternatives.map((alt, altIndex) => (
                        <div key={altIndex} className="flex items-center justify-between">
                          <span className={cn("text-xs", t.text.secondary)}>
                            {alt.field}
                          </span>
                          <span className={cn("text-xs", getConfidenceColor(alt.confidence))}>
                            {Math.round(alt.confidence * 100)}%
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </div>

      {/* Unmapped Fields Warning */}
      {mlResults.unmapped_fields && mlResults.unmapped_fields.length > 0 && (
        <div className={cn(t.status.warning, "p-4 rounded-lg")}>
          <div className="flex items-center">
            <FiAlertTriangle className="w-5 h-5 mr-2" />
            <div>
              <h4 className="text-sm font-semibold">Unmapped Fields</h4>
              <p className="text-xs mt-1">
                {mlResults.unmapped_fields.length} fields could not be mapped automatically:
              </p>
              <div className="flex flex-wrap gap-1 mt-2">
                {mlResults.unmapped_fields.map(field => (
                  <span
                    key={field}
                    className="px-2 py-1 text-xs bg-yellow-200 text-yellow-800 rounded-md"
                  >
                    {field}
                  </span>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Help Text */}
      <div className={cn(t.status.info, "p-3 rounded-md")}>
        <div className="flex items-start">
          <FiTrendingUp className="w-4 h-4 mr-2 flex-shrink-0 mt-0.5" />
          <div className="text-xs">
            <p className="font-medium mb-1">How it works:</p>
            <ul className="space-y-1 text-xs opacity-90">
              <li>• ML predictions use training data from previous successful mappings</li>
              <li>• Higher confidence scores indicate more certain predictions</li>
              <li>• Your feedback helps improve future mapping accuracy</li>
              <li>• Fallback methods are used when ML confidence is low</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
};

export default FieldMappingConfidence; 