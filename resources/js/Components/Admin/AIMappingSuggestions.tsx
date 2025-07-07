import React, { useState } from 'react';
import { FiCpu, FiCheck, FiX, FiAlertCircle, FiRefreshCw, FiZap, FiTrendingUp } from 'react-icons/fi';
import { themes, cn } from '@/theme/glass-theme';
import { fetchWithCSRF } from '@/utils/csrf';

interface FieldSuggestion {
  data_source: string;
  confidence: number;
  method: string;
  reason: string;
  field_type?: string;
  methods?: string[];
  reasons?: string[];
  historical_boost?: number;
  historical_matches?: number;
}

interface AIMappingSuggestionsProps {
  templateId: number;
  onSuggestionsApplied: () => void;
  theme: 'light' | 'dark';
}

export default function AIMappingSuggestions({ templateId, onSuggestionsApplied, theme }: AIMappingSuggestionsProps) {
  const t = themes[theme];
  const [isLoading, setIsLoading] = useState(false);
  const [suggestions, setSuggestions] = useState<Record<string, FieldSuggestion[]>>({});
  const [selectedSuggestions, setSelectedSuggestions] = useState<Record<string, FieldSuggestion>>({});
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [stats, setStats] = useState<{
    total_fields: number;
    mapped_fields: number;
    suggested_fields: number;
    confidence_threshold: number;
  } | null>(null);

  const getSuggestions = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await fetchWithCSRF(
        route('admin.pdf-templates.suggest-mappings', templateId),
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            min_confidence: 0.5,
            max_suggestions: 5,
            include_historical: true,
          }),
        }
      );

      const data = await response.json();

      if (data.success) {
        setSuggestions(data.suggestions || {});
        setStats({
          total_fields: data.total_fields,
          mapped_fields: data.mapped_fields,
          suggested_fields: data.suggested_fields,
          confidence_threshold: data.confidence_threshold,
        });
        setShowSuggestions(true);

        // Auto-select high confidence suggestions
        const autoSelected: Record<string, FieldSuggestion> = {};
        Object.entries(data.suggestions || {}).forEach(([field, fieldSuggestions]) => {
          const suggestions = fieldSuggestions as FieldSuggestion[];
          if (suggestions.length > 0 && suggestions[0].confidence >= 0.8) {
            autoSelected[field] = suggestions[0];
          }
        });
        setSelectedSuggestions(autoSelected);
      } else {
        setError(data.error || 'Failed to get suggestions');
      }
    } catch (err) {
      console.error('Error getting AI suggestions:', err);
      setError('Failed to get AI suggestions');
    } finally {
      setIsLoading(false);
    }
  };

  const applySuggestions = async () => {
    if (Object.keys(selectedSuggestions).length === 0) {
      setError('No suggestions selected');
      return;
    }

    setIsLoading(true);
    setError(null);

    try {
      const response = await fetchWithCSRF(
        route('admin.pdf-templates.apply-ai-mappings', templateId),
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            accepted_suggestions: selectedSuggestions,
          }),
        }
      );

      const data = await response.json();

      if (data.success) {
        onSuggestionsApplied();
        setShowSuggestions(false);
        setSuggestions({});
        setSelectedSuggestions({});
      } else {
        setError(data.error || 'Failed to apply suggestions');
      }
    } catch (err) {
      console.error('Error applying suggestions:', err);
      setError('Failed to apply suggestions');
    } finally {
      setIsLoading(false);
    }
  };

  const selectSuggestion = (field: string, suggestion: FieldSuggestion | null) => {
    if (suggestion) {
      setSelectedSuggestions({
        ...selectedSuggestions,
        [field]: suggestion,
      });
    } else {
      const updated = { ...selectedSuggestions };
      delete updated[field];
      setSelectedSuggestions(updated);
    }
  };

  const getConfidenceColor = (confidence: number) => {
    if (confidence >= 0.9) return 'text-green-500';
    if (confidence >= 0.7) return 'text-yellow-500';
    return 'text-orange-500';
  };

  const getMethodIcon = (method: string) => {
    switch (method) {
      case 'pattern':
        return '🔍';
      case 'semantic':
        return '🧠';
      case 'context':
        return '📋';
      case 'learned':
        return '📚';
      default:
        return '✨';
    }
  };

  return (
    <div className={cn("relative", theme === 'dark' ? 'text-white' : 'text-gray-900')}>
      {/* AI Suggestions Button */}
      <button
        onClick={getSuggestions}
        disabled={isLoading}
        className={cn(
          "flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-all",
          "bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700",
          "text-white shadow-lg hover:shadow-xl transform hover:scale-105",
          "disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
        )}
      >
        {isLoading ? (
          <FiRefreshCw className="h-4 w-4 animate-spin" />
        ) : (
          <FiCpu className="h-4 w-4" />
        )}
        Get AI Suggestions
      </button>

      {/* Error Display */}
      {error && (
        <div className={cn(
          "mt-4 p-4 rounded-lg flex items-center gap-2",
          theme === 'dark' ? 'bg-red-900/20 text-red-400' : 'bg-red-100 text-red-700'
        )}>
          <FiAlertCircle className="h-5 w-5 flex-shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {/* Suggestions Panel */}
      {showSuggestions && Object.keys(suggestions).length > 0 && (
        <div className={cn(
          "absolute top-12 left-0 right-0 z-50 mt-2 p-6 rounded-xl shadow-2xl",
          "max-h-[80vh] overflow-y-auto",
          t.glass.card,
          "border",
          theme === 'dark' ? 'border-gray-700' : 'border-gray-200'
        )}>
          {/* Header */}
          <div className="flex items-center justify-between mb-6">
            <div>
              <h3 className={cn("text-lg font-semibold flex items-center gap-2", t.text.primary)}>
                <FiZap className="h-5 w-5 text-yellow-500" />
                AI Mapping Suggestions
              </h3>
              {stats && (
                <p className={cn("text-sm mt-1", t.text.secondary)}>
                  Found suggestions for {stats.suggested_fields} of {stats.total_fields - stats.mapped_fields} unmapped fields
                </p>
              )}
            </div>
            <button
              onClick={() => setShowSuggestions(false)}
              className={cn(
                "p-2 rounded-lg transition-colors",
                theme === 'dark' ? 'hover:bg-gray-700' : 'hover:bg-gray-200'
              )}
            >
              <FiX className="h-5 w-5" />
            </button>
          </div>

          {/* Suggestions List */}
          <div className="space-y-4 mb-6">
            {Object.entries(suggestions).map(([field, fieldSuggestions]) => {
              const selected = selectedSuggestions[field];
              const typedSuggestions = fieldSuggestions as FieldSuggestion[];

              return (
                <div
                  key={field}
                  className={cn(
                    "p-4 rounded-lg border transition-all",
                    selected
                      ? theme === 'dark'
                        ? 'bg-green-900/20 border-green-700'
                        : 'bg-green-50 border-green-300'
                      : theme === 'dark'
                      ? 'bg-gray-800/50 border-gray-700'
                      : 'bg-gray-50 border-gray-200'
                  )}
                >
                  {/* Field Name */}
                  <div className="flex items-center justify-between mb-3">
                    <h4 className={cn("font-medium", t.text.primary)}>
                      {field}
                    </h4>
                    {selected && (
                      <span className="flex items-center gap-1 text-xs text-green-500">
                        <FiCheck className="h-3 w-3" />
                        Selected
                      </span>
                    )}
                  </div>

                  {/* Suggestions */}
                  <div className="space-y-2">
                    {typedSuggestions.map((suggestion, index) => (
                      <div
                        key={index}
                        className={cn(
                          "flex items-center justify-between p-3 rounded-lg cursor-pointer transition-all",
                          selected === suggestion
                            ? theme === 'dark'
                              ? 'bg-blue-900/30 ring-2 ring-blue-500'
                              : 'bg-blue-100 ring-2 ring-blue-400'
                            : theme === 'dark'
                            ? 'bg-gray-700/50 hover:bg-gray-700'
                            : 'bg-white hover:bg-gray-100',
                          "border",
                          theme === 'dark' ? 'border-gray-600' : 'border-gray-200'
                        )}
                        onClick={() => selectSuggestion(field, selected === suggestion ? null : suggestion)}
                      >
                        <div className="flex-1">
                          <div className="flex items-center gap-3">
                            <span className="text-lg" title={`Method: ${suggestion.method}`}>
                              {getMethodIcon(suggestion.method)}
                            </span>
                            <div>
                              <p className={cn("font-medium", t.text.primary)}>
                                {suggestion.data_source}
                              </p>
                              <p className={cn("text-xs", t.text.muted)}>
                                {suggestion.reason}
                              </p>
                              {suggestion.historical_matches && (
                                <p className={cn("text-xs mt-1 flex items-center gap-1", t.text.secondary)}>
                                  <FiTrendingUp className="h-3 w-3" />
                                  Used in {suggestion.historical_matches} similar templates
                                </p>
                              )}
                            </div>
                          </div>
                        </div>
                        <div className="flex items-center gap-3">
                          <span className={cn("text-sm font-medium", getConfidenceColor(suggestion.confidence))}>
                            {Math.round(suggestion.confidence * 100)}%
                          </span>
                          {selected === suggestion && (
                            <FiCheck className="h-5 w-5 text-green-500" />
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              );
            })}
          </div>

          {/* Actions */}
          <div className="flex items-center justify-between pt-4 border-t border-gray-700">
            <p className={cn("text-sm", t.text.secondary)}>
              {Object.keys(selectedSuggestions).length} of {Object.keys(suggestions).length} fields selected
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowSuggestions(false)}
                className={cn(
                  "px-4 py-2 rounded-lg font-medium transition-colors",
                  theme === 'dark'
                    ? 'bg-gray-700 hover:bg-gray-600 text-white'
                    : 'bg-gray-200 hover:bg-gray-300 text-gray-800'
                )}
              >
                Cancel
              </button>
              <button
                onClick={applySuggestions}
                disabled={Object.keys(selectedSuggestions).length === 0 || isLoading}
                className={cn(
                  "px-4 py-2 rounded-lg font-medium transition-colors",
                  "bg-gradient-to-r from-green-600 to-blue-600 hover:from-green-700 hover:to-blue-700",
                  "text-white shadow-lg hover:shadow-xl",
                  "disabled:opacity-50 disabled:cursor-not-allowed"
                )}
              >
                Apply Selected Mappings
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}