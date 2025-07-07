import React from 'react';
import { FiTrash2, FiMoveUp, FiMoveDown, FiAlertCircle, FiCheckCircle, FiCpu, FiZap } from 'react-icons/fi';
import { themes, cn } from '@/theme/glass-theme';

interface FieldMapping {
  id?: number;
  pdf_field_name: string;
  data_source: string;
  field_type: 'text' | 'checkbox' | 'radio' | 'select' | 'signature' | 'date' | 'image';
  transform_function?: string;
  default_value?: string;
  is_required: boolean;
  display_order: number;
  validation_rules?: any;
  options?: any;
  ai_suggested?: boolean;
  ai_confidence?: number;
  ai_suggestion_metadata?: {
    method?: string;
    reason?: string;
    suggested_at?: string;
    accepted_by?: number;
  };
}

interface PDFFieldMapperProps {
  mappings: FieldMapping[];
  dataSources: Record<string, Record<string, string>>;
  onMappingChange: (index: number, updates: Partial<FieldMapping>) => void;
  onRemoveMapping: (index: number) => void;
  transformFunctions: Array<{ value: string; label: string }>;
  fieldTypes: Array<{ value: string; label: string }>;
  theme: 'light' | 'dark';
}

export default function PDFFieldMapper({
  mappings,
  dataSources,
  onMappingChange,
  onRemoveMapping,
  transformFunctions,
  fieldTypes,
  theme,
}: PDFFieldMapperProps) {
  const t = themes[theme];

  // Flatten data sources for easy selection
  const flatDataSources = Object.entries(dataSources).flatMap(([category, fields]) =>
    Object.entries(fields).map(([field, label]) => ({
      value: field,
      label: `${label} (${category})`,
      category,
    }))
  );

  const moveMapping = (index: number, direction: 'up' | 'down') => {
    if (
      (direction === 'up' && index === 0) ||
      (direction === 'down' && index === mappings.length - 1)
    ) {
      return;
    }

    const newIndex = direction === 'up' ? index - 1 : index + 1;
    const newMappings = [...mappings];
    const temp = newMappings[index];
    newMappings[index] = newMappings[newIndex];
    newMappings[newIndex] = temp;

    // Update display order
    newMappings.forEach((mapping, i) => {
      onMappingChange(i, { display_order: i });
    });
  };

  const getFieldIcon = (fieldType: string) => {
    switch (fieldType) {
      case 'checkbox':
        return '☑';
      case 'radio':
        return '◉';
      case 'select':
        return '▼';
      case 'signature':
        return '✍';
      case 'date':
        return '📅';
      case 'image':
        return '🖼';
      default:
        return '📝';
    }
  };

  return (
    <div className="space-y-4">
      {mappings.length === 0 ? (
        <div className={cn(
          "text-center py-8 rounded-lg border-2 border-dashed",
          theme === 'dark' ? 'border-gray-700' : 'border-gray-300'
        )}>
          <p className={cn("text-sm", t.text.secondary)}>
            No field mappings configured yet.
          </p>
          <p className={cn("text-xs mt-1", t.text.muted)}>
            Click "Add Mapping" to start mapping PDF fields to data sources.
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {mappings.map((mapping, index) => (
            <div
              key={index}
              className={cn(
                "p-4 rounded-lg border transition-all",
                theme === 'dark'
                  ? 'bg-gray-800/50 border-gray-700 hover:border-gray-600'
                  : 'bg-gray-50 border-gray-200 hover:border-gray-300'
              )}
            >
              {/* Header Row */}
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-3">
                  <span className="text-xl" title={`Field type: ${mapping.field_type}`}>
                    {getFieldIcon(mapping.field_type)}
                  </span>
                  <div>
                    <div className="flex items-center gap-2">
                      <h4 className={cn("font-medium", t.text.primary)}>
                        {mapping.pdf_field_name || 'Unnamed Field'}
                      </h4>
                      {mapping.ai_suggested && (
                        <div className="flex items-center gap-1">
                          <FiCpu className="h-3 w-3 text-purple-500" />
                          <span className="text-xs text-purple-500 font-medium">
                            AI {mapping.ai_confidence ? `${Math.round(mapping.ai_confidence * 100)}%` : ''}
                          </span>
                        </div>
                      )}
                    </div>
                    {mapping.data_source && (
                      <p className={cn("text-sm", t.text.secondary)}>
                        → {flatDataSources.find(ds => ds.value === mapping.data_source)?.label || mapping.data_source}
                      </p>
                    )}
                    {mapping.ai_suggestion_metadata?.reason && (
                      <p className={cn("text-xs mt-1", t.text.muted)}>
                        <FiZap className="inline h-3 w-3 mr-1" />
                        {mapping.ai_suggestion_metadata.reason}
                      </p>
                    )}
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => moveMapping(index, 'up')}
                    disabled={index === 0}
                    className={cn(
                      "p-1.5 rounded transition-colors",
                      index === 0
                        ? 'opacity-50 cursor-not-allowed'
                        : 'hover:bg-gray-700/50',
                      t.text.secondary
                    )}
                    title="Move up"
                  >
                    <FiMoveUp className="h-4 w-4" />
                  </button>
                  <button
                    onClick={() => moveMapping(index, 'down')}
                    disabled={index === mappings.length - 1}
                    className={cn(
                      "p-1.5 rounded transition-colors",
                      index === mappings.length - 1
                        ? 'opacity-50 cursor-not-allowed'
                        : 'hover:bg-gray-700/50',
                      t.text.secondary
                    )}
                    title="Move down"
                  >
                    <FiMoveDown className="h-4 w-4" />
                  </button>
                  <button
                    onClick={() => onRemoveMapping(index)}
                    className="p-1.5 rounded hover:bg-gray-700/50 transition-colors text-red-500"
                    title="Remove mapping"
                  >
                    <FiTrash2 className="h-4 w-4" />
                  </button>
                </div>
              </div>

              {/* Mapping Fields */}
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                {/* PDF Field Name */}
                <div>
                  <label className={cn("block text-xs font-medium mb-1", t.text.muted)}>
                    PDF Field Name
                  </label>
                  <input
                    type="text"
                    value={mapping.pdf_field_name}
                    onChange={(e) => onMappingChange(index, { pdf_field_name: e.target.value })}
                    placeholder="e.g., patient_name"
                    className={cn(
                      "w-full px-3 py-1.5 text-sm rounded border",
                      theme === 'dark'
                        ? 'bg-gray-900 border-gray-700 text-white'
                        : 'bg-white border-gray-300 text-gray-900'
                    )}
                  />
                </div>

                {/* Data Source */}
                <div>
                  <label className={cn("block text-xs font-medium mb-1", t.text.muted)}>
                    Data Source
                  </label>
                  <select
                    value={mapping.data_source}
                    onChange={(e) => onMappingChange(index, { data_source: e.target.value })}
                    className={cn(
                      "w-full px-3 py-1.5 text-sm rounded border",
                      theme === 'dark'
                        ? 'bg-gray-900 border-gray-700 text-white'
                        : 'bg-white border-gray-300 text-gray-900'
                    )}
                  >
                    <option value="">Select source...</option>
                    {Object.entries(dataSources).map(([category, fields]) => (
                      <optgroup key={category} label={category.charAt(0).toUpperCase() + category.slice(1)}>
                        {Object.entries(fields).map(([field, label]) => (
                          <option key={field} value={field}>
                            {label}
                          </option>
                        ))}
                      </optgroup>
                    ))}
                  </select>
                </div>

                {/* Field Type */}
                <div>
                  <label className={cn("block text-xs font-medium mb-1", t.text.muted)}>
                    Field Type
                  </label>
                  <select
                    value={mapping.field_type}
                    onChange={(e) => onMappingChange(index, { field_type: e.target.value as any })}
                    className={cn(
                      "w-full px-3 py-1.5 text-sm rounded border",
                      theme === 'dark'
                        ? 'bg-gray-900 border-gray-700 text-white'
                        : 'bg-white border-gray-300 text-gray-900'
                    )}
                  >
                    {fieldTypes.map((type) => (
                      <option key={type.value} value={type.value}>
                        {type.label}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Transform Function */}
                <div>
                  <label className={cn("block text-xs font-medium mb-1", t.text.muted)}>
                    Transform
                  </label>
                  <select
                    value={mapping.transform_function || ''}
                    onChange={(e) => onMappingChange(index, { transform_function: e.target.value })}
                    className={cn(
                      "w-full px-3 py-1.5 text-sm rounded border",
                      theme === 'dark'
                        ? 'bg-gray-900 border-gray-700 text-white'
                        : 'bg-white border-gray-300 text-gray-900'
                    )}
                  >
                    {transformFunctions.map((func) => (
                      <option key={func.value} value={func.value}>
                        {func.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Additional Options */}
              <div className="mt-3 flex flex-wrap items-center gap-4">
                {/* Default Value */}
                <div className="flex-1 min-w-[200px]">
                  <label className={cn("block text-xs font-medium mb-1", t.text.muted)}>
                    Default Value
                  </label>
                  <input
                    type="text"
                    value={mapping.default_value || ''}
                    onChange={(e) => onMappingChange(index, { default_value: e.target.value })}
                    placeholder="Optional default"
                    className={cn(
                      "w-full px-3 py-1.5 text-sm rounded border",
                      theme === 'dark'
                        ? 'bg-gray-900 border-gray-700 text-white'
                        : 'bg-white border-gray-300 text-gray-900'
                    )}
                  />
                </div>

                {/* Required Checkbox */}
                <div className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    id={`required-${index}`}
                    checked={mapping.is_required}
                    onChange={(e) => onMappingChange(index, { is_required: e.target.checked })}
                    className="rounded border-gray-300"
                  />
                  <label
                    htmlFor={`required-${index}`}
                    className={cn("text-sm", t.text.secondary)}
                  >
                    Required field
                  </label>
                </div>

                {/* Status Indicator */}
                <div className="flex items-center gap-2">
                  {mapping.pdf_field_name && mapping.data_source ? (
                    <span className="flex items-center gap-1 text-xs text-green-500">
                      <FiCheckCircle className="h-3 w-3" />
                      Mapped
                    </span>
                  ) : (
                    <span className="flex items-center gap-1 text-xs text-yellow-500">
                      <FiAlertCircle className="h-3 w-3" />
                      Incomplete
                    </span>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Summary */}
      {mappings.length > 0 && (
        <div className={cn(
          "mt-6 p-4 rounded-lg border",
          theme === 'dark'
            ? 'bg-gray-800/30 border-gray-700'
            : 'bg-gray-50 border-gray-200'
        )}>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <span className={cn("text-sm", t.text.secondary)}>
                Total mappings: <strong>{mappings.length}</strong>
              </span>
              <span className={cn("text-sm", t.text.secondary)}>
                Completed: <strong>{mappings.filter(m => m.pdf_field_name && m.data_source).length}</strong>
              </span>
              <span className={cn("text-sm", t.text.secondary)}>
                Required: <strong>{mappings.filter(m => m.is_required).length}</strong>
              </span>
              {mappings.some(m => m.ai_suggested) && (
                <span className={cn("text-sm flex items-center gap-1", t.text.secondary)}>
                  <FiCpu className="h-3 w-3 text-purple-500" />
                  AI Suggested: <strong className="text-purple-500">{mappings.filter(m => m.ai_suggested).length}</strong>
                </span>
              )}
            </div>
            {mappings.some(m => !m.pdf_field_name || !m.data_source) && (
              <span className="flex items-center gap-1 text-sm text-yellow-500">
                <FiAlertCircle className="h-4 w-4" />
                Some mappings are incomplete
              </span>
            )}
          </div>
        </div>
      )}
    </div>
  );
}