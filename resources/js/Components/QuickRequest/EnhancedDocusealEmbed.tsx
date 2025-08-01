import React, { useEffect, useState, useCallback } from 'react';
import { DocusealEmbed } from '@/Components/QuickRequest/DocusealEmbed';
import { useACZFieldMapping } from '@/hooks/useDocusealFieldMapping';
import { Button } from '@/Components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Progress } from '@/Components/ui/progress';
import { Badge } from '@/Components/ui/badge';
import {
  CheckCircle,
  AlertCircle,
  Loader2,
  Info,
  Zap,
  Settings,
  Eye,
  EyeOff
} from 'lucide-react';

interface EnhancedDocusealEmbedProps {
  manufacturerId: string;
  templateId?: string;
  productCode: string;
  documentType?: 'IVR' | 'OrderForm';
  formData?: Record<string, any>;
  episodeId?: number;
  onComplete?: (data: any) => void;
  onError?: (error: string) => void;
  className?: string;
  debug?: boolean;
  useFrontendMapping?: boolean;
  useBackendEnhancedMapping?: boolean;
}

export const EnhancedDocusealEmbed: React.FC<EnhancedDocusealEmbedProps> = ({
  manufacturerId,
  templateId,
  productCode,
  documentType = 'IVR',
  formData = {},
  episodeId,
  onComplete,
  onError,
  className = '',
  debug = false,
  useFrontendMapping = false,
  useBackendEnhancedMapping = true
}) => {
  const [showMappingDetails, setShowMappingDetails] = useState(debug);
  const [docuSealTemplate, setDocuSealTemplate] = useState(null);
  const [mappingMode, setMappingMode] = useState<'backend' | 'frontend' | 'hybrid'>(
    useFrontendMapping ? 'frontend' : 'backend'
  );

  // Initialize field mapping hook
  const {
    mapFields,
    mappedFields,
    isMapping,
    mappingProgress,
    isMappingComplete,
    validationStatus,
    stats,
    error: mappingError,
    resetMapping
  } = useACZFieldMapping({
    debug,
    useEnhancedMapping: true
  });

  // Fetch DocuSeal template structure
  const fetchTemplate = useCallback(async () => {
    if (!templateId || !useFrontendMapping) return;

    try {
      // This would be an API call to get the template structure
      // For now, we'll use a mock template structure
      const mockTemplate = {
        id: templateId,
        fields: [
          { uuid: 'field1', name: 'Product Q Code', type: 'radio', options: [
            { uuid: 'opt1', value: 'Q4205' },
            { uuid: 'opt2', value: 'Q4290' },
            { uuid: 'opt3', value: 'Q4344' }
          ]},
          { uuid: 'field2', name: 'Sales Rep', type: 'text' },
          { uuid: 'field3', name: 'Physician Name', type: 'text' },
          { uuid: 'field4', name: 'Patient Name', type: 'text' },
          { uuid: 'field5', name: 'Physician Status With Primary', type: 'radio', options: [
            { uuid: 'opt4', value: 'In-Network' },
            { uuid: 'opt5', value: 'Out-of-Network' }
          ]},
          // Add more fields as needed
        ]
      };

      setDocuSealTemplate(mockTemplate);
    } catch (error) {
      console.error('Failed to fetch template:', error);
    }
  }, [templateId, useFrontendMapping]);

  // Perform field mapping
  const performFieldMapping = useCallback(async () => {
    if (!formData || Object.keys(formData).length === 0) return;

    if (mappingMode === 'frontend' && docuSealTemplate) {
      await mapFields(formData, docuSealTemplate);
    }
  }, [formData, mappingMode, docuSealTemplate, mapFields]);

  // Initialize component
  useEffect(() => {
    fetchTemplate();
  }, [fetchTemplate]);

  useEffect(() => {
    performFieldMapping();
  }, [performFieldMapping]);

  // Handle mapping mode change
  const handleMappingModeChange = (mode: 'backend' | 'frontend' | 'hybrid') => {
    setMappingMode(mode);
    resetMapping();
  };

  // Get status badge
  const getStatusBadge = () => {
    switch (validationStatus) {
      case 'valid':
        return <Badge variant="success" className="bg-green-100 text-green-800"><CheckCircle className="w-3 h-3 mr-1" />Valid</Badge>;
      case 'valid_with_warnings':
        return <Badge variant="warning" className="bg-yellow-100 text-yellow-800"><AlertCircle className="w-3 h-3 mr-1" />Valid with Warnings</Badge>;
      case 'invalid':
        return <Badge variant="destructive" className="bg-red-100 text-red-800"><AlertCircle className="w-3 h-3 mr-1" />Invalid</Badge>;
      default:
        return <Badge variant="secondary" className="bg-gray-100 text-gray-800"><Info className="w-3 h-3 mr-1" />Not Validated</Badge>;
    }
  };

  // Render mapping details panel
  const renderMappingDetails = () => {
    if (!showMappingDetails) return null;

    return (
      <Card className="mb-4">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Zap className="w-5 h-5" />
            Field Mapping Details
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Mapping Mode Selector */}
          <div className="flex items-center gap-4">
            <span className="text-sm font-medium">Mapping Mode:</span>
            <div className="flex gap-2">
              <Button
                size="sm"
                variant={mappingMode === 'backend' ? 'default' : 'outline'}
                onClick={() => handleMappingModeChange('backend')}
              >
                Backend Enhanced
              </Button>
              <Button
                size="sm"
                variant={mappingMode === 'frontend' ? 'default' : 'outline'}
                onClick={() => handleMappingModeChange('frontend')}
              >
                Frontend Mapper
              </Button>
              <Button
                size="sm"
                variant={mappingMode === 'hybrid' ? 'default' : 'outline'}
                onClick={() => handleMappingModeChange('hybrid')}
              >
                Hybrid
              </Button>
            </div>
          </div>

          {/* Mapping Progress */}
          {isMapping && (
            <div className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <span>Mapping Progress</span>
                <span>{mappingProgress}%</span>
              </div>
              <Progress value={mappingProgress} className="h-2" />
            </div>
          )}

          {/* Validation Status */}
          <div className="flex items-center gap-2">
            <span className="text-sm font-medium">Validation:</span>
            {getStatusBadge()}
          </div>

          {/* Mapping Statistics */}
          {stats && (
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span className="font-medium">Mapped Fields:</span> {stats.totalMapped}
              </div>
              <div>
                <span className="font-medium">Coverage:</span> {stats.mappingCoverage}%
              </div>
              <div>
                <span className="font-medium">Field Types:</span>
                <div className="mt-1 space-y-1">
                  {Object.entries(stats.fieldTypes).map(([type, count]) => (
                    <Badge key={type} variant="outline" className="mr-1">
                      {type}: {count}
                    </Badge>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* Mapped Fields List */}
          {mappedFields.length > 0 && (
            <div>
              <span className="text-sm font-medium">Mapped Fields ({mappedFields.length}):</span>
              <div className="mt-2 max-h-32 overflow-y-auto space-y-1">
                {mappedFields.map((field, index) => (
                  <div key={index} className="text-xs bg-gray-50 p-2 rounded">
                    <span className="font-medium">{field.name || field.uuid}:</span> {field.value}
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Error Display */}
          {mappingError && (
            <Alert variant="destructive">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>Mapping Error: {mappingError}</AlertDescription>
            </Alert>
          )}
        </CardContent>
      </Card>
    );
  };

  // Prepare submission data based on mapping mode
  const getSubmissionData = () => {
    const baseData = {
      manufacturerId,
      templateId,
      productCode,
      documentType,
      formData,
      episodeId,
      debug
    };

    if (mappingMode === 'frontend' && mappedFields.length > 0) {
      // Use frontend mapped fields
      return {
        ...baseData,
        prefillFields: mappedFields,
        useFrontendMapping: true
      };
    } else if (mappingMode === 'hybrid') {
      // Use both backend and frontend mapping
      return {
        ...baseData,
        useEnhancedMapping: useBackendEnhancedMapping,
        prefillFields: mappedFields,
        useFrontendMapping: true
      };
    } else {
      // Use backend mapping only
      return {
        ...baseData,
        useEnhancedMapping: useBackendEnhancedMapping
      };
    }
  };

  return (
    <div className={className}>
      {/* Mapping Details Toggle */}
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <Settings className="w-4 h-4" />
          <span className="text-sm font-medium">Enhanced Field Mapping</span>
        </div>
        <Button
          size="sm"
          variant="ghost"
          onClick={() => setShowMappingDetails(!showMappingDetails)}
        >
          {showMappingDetails ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
          {showMappingDetails ? 'Hide' : 'Show'} Details
        </Button>
      </div>

      {/* Mapping Details Panel */}
      {renderMappingDetails()}

      {/* DocuSeal Embed Component */}
      <DocusealEmbed
        {...getSubmissionData()}
        onComplete={onComplete}
        onError={onError}
      />

      {/* Mapping Status Footer */}
      {isMappingComplete && (
        <div className="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
          <div className="flex items-center gap-2 text-green-800">
            <CheckCircle className="w-4 h-4" />
            <span className="text-sm font-medium">
              Field mapping completed successfully! {mappedFields.length} fields mapped.
            </span>
          </div>
        </div>
      )}
    </div>
  );
};

export default EnhancedDocusealEmbed;
