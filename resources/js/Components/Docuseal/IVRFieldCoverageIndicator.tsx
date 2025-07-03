import { useState } from 'react';
import { CheckCircle, AlertCircle, Info, TrendingUp, Eye } from 'lucide-react';
import IVRPreviewModal from './IVRPreviewModal';

interface FieldCoverage {
  total_fields: number;
  filled_fields: number;
  missing_fields: string[];
  extracted_fields: string[];
  percentage: number;
  coverage_level: 'excellent' | 'good' | 'fair' | 'poor';
}

interface IVRFieldCoverageIndicatorProps {
  coverage: FieldCoverage;
  className?: string;
  showDetails?: boolean;
  formData?: any;
  showPreviewButton?: boolean;
}

const IVRFieldCoverageIndicator: React.FC<IVRFieldCoverageIndicatorProps> = ({
  coverage,
  className = '',
  showDetails = false,
  formData = {},
  showPreviewButton = false
}) => {
  const [showPreview, setShowPreview] = useState(false);
  const getCoverageColor = (level: string) => {
    switch (level) {
      case 'excellent': return 'text-green-600 bg-green-50 border-green-200';
      case 'good': return 'text-blue-600 bg-blue-50 border-blue-200';
      case 'fair': return 'text-yellow-600 bg-yellow-50 border-yellow-200';
      case 'poor': return 'text-red-600 bg-red-50 border-red-200';
      default: return 'text-gray-600 bg-gray-50 border-gray-200';
    }
  };

  const getCoverageIcon = (level: string) => {
    switch (level) {
      case 'excellent': return <CheckCircle className="w-5 h-5" />;
      case 'good': return <TrendingUp className="w-5 h-5" />;
      case 'fair': return <AlertCircle className="w-5 h-5" />;
      case 'poor': return <AlertCircle className="w-5 h-5" />;
      default: return <Info className="w-5 h-5" />;
    }
  };

  const getCoverageMessage = (level: string, percentage: number) => {
    switch (level) {
      case 'excellent': return `Excellent! ${percentage}% of IVR fields will be pre-filled`;
      case 'good': return `Good coverage - ${percentage}% of IVR fields will be pre-filled`;
      case 'fair': return `Fair coverage - ${percentage}% of IVR fields will be pre-filled`;
      case 'poor': return `Limited coverage - only ${percentage}% of IVR fields will be pre-filled`;
      default: return `${percentage}% field coverage`;
    }
  };

  return (
    <div className={`p-4 rounded-lg border ${getCoverageColor(coverage.coverage_level)} ${className}`}>
      {/* Header */}
      <div className="flex items-center space-x-2 mb-2">
        {getCoverageIcon(coverage.coverage_level)}
        <div className="flex-1">
          <h3 className="font-semibold text-sm">
            IVR Field Coverage: {coverage.percentage}%
          </h3>
          <p className="text-xs opacity-80">
            {getCoverageMessage(coverage.coverage_level, coverage.percentage)}
          </p>
        </div>
      </div>

      {/* Progress Bar */}
      <div className="w-full bg-gray-200 rounded-full h-2 mb-3">
        <div
          className={`h-2 rounded-full transition-all duration-300 ${
            coverage.coverage_level === 'excellent' ? 'bg-green-500' :
            coverage.coverage_level === 'good' ? 'bg-blue-500' :
            coverage.coverage_level === 'fair' ? 'bg-yellow-500' : 'bg-red-500'
          }`}
          style={{ width: `${coverage.percentage}%` }}
        ></div>
      </div>

      {/* Statistics and Preview Button */}
      <div className="flex justify-between items-center text-xs">
        <div className="flex space-x-4">
          <span>{coverage.filled_fields} of {coverage.total_fields} fields filled</span>
          <span>{coverage.extracted_fields.length} auto-extracted</span>
        </div>
        {showPreviewButton && formData.manufacturer_id && (
          <button
            onClick={() => setShowPreview(true)}
            className="flex items-center space-x-1 px-2 py-1 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors"
          >
            <Eye className="w-3 h-3" />
            <span>Preview IVR</span>
          </button>
        )}
        {showPreviewButton && !formData.manufacturer_id && (
          <span className="text-xs text-gray-500 dark:text-gray-400">
            Select product to preview IVR
          </span>
        )}
      </div>

      {/* Detailed Breakdown (Optional) */}
      {showDetails && (
        <div className="mt-4 pt-4 border-t border-current border-opacity-20">
          <div className="grid grid-cols-2 gap-4 text-xs">
            <div>
              <h4 className="font-medium mb-1">Auto-Extracted Fields ({coverage.extracted_fields.length})</h4>
              <div className="space-y-1 max-h-24 overflow-y-auto">
                {coverage.extracted_fields.slice(0, 5).map((field, index) => (
                  <div key={index} className="flex items-center space-x-1">
                    <CheckCircle className="w-3 h-3 text-green-500" />
                    <span className="text-green-700">{field.replace(/_/g, ' ')}</span>
                  </div>
                ))}
                {coverage.extracted_fields.length > 5 && (
                  <div className="text-gray-500">+ {coverage.extracted_fields.length - 5} more</div>
                )}
              </div>
            </div>

            <div>
              <h4 className="font-medium mb-1">Missing Fields ({coverage.missing_fields.length})</h4>
              <div className="space-y-1 max-h-24 overflow-y-auto">
                {coverage.missing_fields.slice(0, 5).map((field, index) => (
                  <div key={index} className="flex items-center space-x-1">
                    <AlertCircle className="w-3 h-3 text-red-500" />
                    <span className="text-red-700">{field.replace(/_/g, ' ')}</span>
                  </div>
                ))}
                {coverage.missing_fields.length > 5 && (
                  <div className="text-gray-500">+ {coverage.missing_fields.length - 5} more</div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* IVR Preview Modal */}
      {showPreview && (
        <IVRPreviewModal
          isOpen={showPreview}
          onClose={() => setShowPreview(false)}
          coverage={coverage}
          formData={formData}
          manufacturerName={formData.manufacturer_name || 'Selected Manufacturer'}
        />
      )}
    </div>
  );
};

export default IVRFieldCoverageIndicator;
