import React from 'react';
import { CanonicalField, MappingStatistics } from '@/types/field-mapping';
import {
  Target, CheckCircle, AlertTriangle, TrendingUp,
  BarChart3, PieChart, Activity, Layers
} from 'lucide-react';

interface Template {
  id: string;
  template_name: string;
  manufacturer?: {
    name: string;
  };
}

interface MappingStatsDashboardProps {
  templates: Template[];
  mappingStats: Record<string, MappingStatistics>;
  canonicalFields: CanonicalField[];
}

export const MappingStatsDashboard: React.FC<MappingStatsDashboardProps> = ({
  templates,
  mappingStats,
  canonicalFields
}) => {
  // Calculate aggregate statistics
  const aggregateStats = React.useMemo(() => {
    const stats = {
      totalTemplates: templates.length,
      totalFields: 0,
      totalMapped: 0,
      totalUnmapped: 0,
      avgCoverage: 0,
      totalRequired: 0,
      totalRequiredMapped: 0,
      templatesComplete: 0,
      templatesWithErrors: 0,
      categoryBreakdown: {} as Record<string, { total: number; mapped: number }>,
    };

    // Aggregate from all templates
    templates.forEach(template => {
      const templateStats = mappingStats[template.id];
      if (!templateStats) return;

      stats.totalFields += templateStats.totalFields;
      stats.totalMapped += templateStats.mappedFields;
      stats.totalUnmapped += templateStats.unmappedFields;
      stats.totalRequired += templateStats.totalRequiredFields;
      stats.totalRequiredMapped += templateStats.requiredFieldsMapped;

      if (templateStats.coveragePercentage === 100) {
        stats.templatesComplete++;
      }

      if (templateStats.validationStatus.error > 0) {
        stats.templatesWithErrors++;
      }
    });

    // Calculate averages
    if (templates.length > 0) {
      const totalCoverage = templates.reduce((sum, t) => 
        sum + (mappingStats[t.id]?.coveragePercentage || 0), 0
      );
      stats.avgCoverage = Math.round(totalCoverage / templates.length);
    }

    // Category breakdown
    canonicalFields.forEach(field => {
      if (!stats.categoryBreakdown[field.category]) {
        stats.categoryBreakdown[field.category] = { total: 0, mapped: 0 };
      }
      stats.categoryBreakdown[field.category].total++;
    });

    return stats;
  }, [templates, mappingStats, canonicalFields]);

  // Find templates needing attention
  const templatesNeedingAttention = React.useMemo(() => {
    return templates.filter(template => {
      const stats = mappingStats[template.id];
      if (!stats) return false;
      return stats.coveragePercentage < 80 || stats.validationStatus.error > 0;
    });
  }, [templates, mappingStats]);

  return (
    <div className="mt-6 space-y-6">
      {/* Main Stats Grid */}
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div className="bg-white rounded-xl p-4 border border-gray-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Total Fields</p>
              <p className="text-2xl font-bold text-gray-900 mt-1">
                {aggregateStats.totalFields}
              </p>
            </div>
            <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
              <Layers className="w-5 h-5 text-purple-600" />
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl p-4 border border-gray-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Mapped Fields</p>
              <p className="text-2xl font-bold text-green-600 mt-1">
                {aggregateStats.totalMapped}
              </p>
            </div>
            <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
              <CheckCircle className="w-5 h-5 text-green-600" />
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl p-4 border border-gray-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Avg Coverage</p>
              <p className="text-2xl font-bold text-blue-600 mt-1">
                {aggregateStats.avgCoverage}%
              </p>
            </div>
            <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
              <TrendingUp className="w-5 h-5 text-blue-600" />
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl p-4 border border-gray-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Complete</p>
              <p className="text-2xl font-bold text-indigo-600 mt-1">
                {aggregateStats.templatesComplete}
              </p>
            </div>
            <div className="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
              <Target className="w-5 h-5 text-indigo-600" />
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl p-4 border border-gray-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">Need Attention</p>
              <p className="text-2xl font-bold text-orange-600 mt-1">
                {templatesNeedingAttention.length}
              </p>
            </div>
            <div className="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
              <AlertTriangle className="w-5 h-5 text-orange-600" />
            </div>
          </div>
        </div>
      </div>

      {/* Progress Overview */}
      <div className="bg-white rounded-xl p-6 border border-gray-200">
        <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
          <Activity className="w-5 h-5 text-purple-600" />
          Mapping Progress by Template
        </h3>
        
        <div className="space-y-3">
          {templates.slice(0, 5).map(template => {
            const stats = mappingStats[template.id];
            if (!stats) return null;

            const progressColor = stats.coveragePercentage >= 80 ? 'bg-green-600' :
                               stats.coveragePercentage >= 60 ? 'bg-yellow-600' :
                               'bg-red-600';

            return (
              <div key={template.id} className="flex items-center gap-4">
                <div className="flex-1">
                  <div className="flex items-center justify-between mb-1">
                    <span className="text-sm font-medium text-gray-700">
                      {template.template_name}
                    </span>
                    <span className="text-sm text-gray-500">
                      {stats.coveragePercentage}%
                    </span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2">
                    <div
                      className={`${progressColor} h-2 rounded-full transition-all duration-300`}
                      style={{ width: `${stats.coveragePercentage}%` }}
                    />
                  </div>
                </div>
                <div className="flex items-center gap-2 text-xs">
                  {stats.validationStatus.error > 0 && (
                    <span className="px-2 py-1 bg-red-100 text-red-700 rounded-full">
                      {stats.validationStatus.error} errors
                    </span>
                  )}
                  {stats.validationStatus.warning > 0 && (
                    <span className="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full">
                      {stats.validationStatus.warning} warnings
                    </span>
                  )}
                </div>
              </div>
            );
          })}
        </div>

        {templates.length > 5 && (
          <p className="mt-4 text-sm text-gray-500 text-center">
            And {templates.length - 5} more templates...
          </p>
        )}
      </div>

      {/* Category Breakdown */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-xl p-6 border border-gray-200">
          <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <PieChart className="w-5 h-5 text-purple-600" />
            Required Fields Coverage
          </h3>
          
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-600">Required Fields Mapped</span>
              <span className="text-lg font-semibold text-gray-900">
                {aggregateStats.totalRequiredMapped} / {aggregateStats.totalRequired}
              </span>
            </div>
            
            <div className="relative pt-1">
              <div className="flex mb-2 items-center justify-between">
                <div>
                  <span className="text-xs font-semibold inline-block text-purple-600">
                    Required Coverage
                  </span>
                </div>
                <div className="text-right">
                  <span className="text-xs font-semibold inline-block text-purple-600">
                    {aggregateStats.totalRequired > 0 
                      ? Math.round((aggregateStats.totalRequiredMapped / aggregateStats.totalRequired) * 100)
                      : 0}%
                  </span>
                </div>
              </div>
              <div className="overflow-hidden h-2 mb-4 text-xs flex rounded bg-purple-200">
                <div
                  style={{
                    width: `${aggregateStats.totalRequired > 0 
                      ? (aggregateStats.totalRequiredMapped / aggregateStats.totalRequired) * 100
                      : 0}%`
                  }}
                  className="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-purple-600"
                />
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl p-6 border border-gray-200">
          <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <BarChart3 className="w-5 h-5 text-purple-600" />
            Category Coverage
          </h3>
          
          <div className="space-y-3">
            {Object.entries(aggregateStats.categoryBreakdown).slice(0, 5).map(([category, stats]) => (
              <div key={category} className="flex items-center justify-between">
                <span className="text-sm text-gray-700 capitalize">
                  {category.replace(/([A-Z])/g, ' $1').trim()}
                </span>
                <div className="flex items-center gap-2">
                  <span className="text-sm text-gray-500">
                    {stats.mapped}/{stats.total}
                  </span>
                  <div className="w-24 bg-gray-200 rounded-full h-2">
                    <div
                      className="bg-purple-600 h-2 rounded-full"
                      style={{ 
                        width: `${stats.total > 0 ? (stats.mapped / stats.total) * 100 : 0}%` 
                      }}
                    />
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Templates Needing Attention */}
      {templatesNeedingAttention.length > 0 && (
        <div className="bg-orange-50 rounded-xl p-6 border border-orange-200">
          <h3 className="text-lg font-semibold text-orange-900 mb-4 flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 text-orange-600" />
            Templates Requiring Attention
          </h3>
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {templatesNeedingAttention.map(template => {
              const stats = mappingStats[template.id];
              if (!stats) return null;

              return (
                <div key={template.id} className="bg-white rounded-lg p-4 border border-orange-200">
                  <h4 className="font-medium text-gray-900 mb-2 line-clamp-1">
                    {template.template_name}
                  </h4>
                  <div className="space-y-2 text-sm">
                    <div className="flex items-center justify-between">
                      <span className="text-gray-600">Coverage:</span>
                      <span className={`font-medium ${
                        stats.coveragePercentage < 50 ? 'text-red-600' : 'text-orange-600'
                      }`}>
                        {stats.coveragePercentage}%
                      </span>
                    </div>
                    {stats.validationStatus.error > 0 && (
                      <div className="flex items-center justify-between">
                        <span className="text-gray-600">Errors:</span>
                        <span className="font-medium text-red-600">
                          {stats.validationStatus.error}
                        </span>
                      </div>
                    )}
                    <div className="flex items-center justify-between">
                      <span className="text-gray-600">Unmapped:</span>
                      <span className="font-medium text-gray-900">
                        {stats.unmappedFields}
                      </span>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
};