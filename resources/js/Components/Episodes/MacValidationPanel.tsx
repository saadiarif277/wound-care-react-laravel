import React, { useEffect, useState } from 'react';
import { 
  CheckCircle, 
  AlertTriangle, 
  XCircle, 
  FileText, 
  DollarSign, 
  Clock,
  Info,
  Shield,
  TrendingUp
} from 'lucide-react';
import { cn } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import axios from 'axios';

interface MacValidationData {
  risk_score: number;
  risk_level: 'low' | 'medium' | 'high' | 'critical';
  coverage_status: 'covered' | 'conditional' | 'not_covered' | 'requires_prior_auth';
  contractor: {
    name: string;
    jurisdiction: string;
  };
  lcd_compliance: {
    status: 'compliant' | 'partial' | 'non_compliant';
    missing_criteria?: string[];
    documentation_required?: string[];
  };
  denial_prediction: {
    probability: number;
    top_risk_factors: Array<{
      factor: string;
      impact: 'high' | 'medium' | 'low';
      mitigation?: string;
    }>;
  };
  financial_impact: {
    potential_denial_amount: number;
    approval_confidence: number;
    estimated_reimbursement: number;
  };
  recommendations: Array<{
    priority: 'critical' | 'high' | 'medium' | 'low';
    action: string;
    impact: string;
  }>;
}

interface MacValidationPanelProps {
  episodeId: string;
  orders: Array<{
    id: string;
    order_number: string;
    products?: Array<{
      name: string;
      hcpcs_code?: string;
    }>;
  }>;
  facilityState?: string;
  className?: string;
}

export default function MacValidationPanel({ 
  episodeId, 
  orders, 
  facilityState,
  className 
}: MacValidationPanelProps) {
  const [validationData, setValidationData] = useState<MacValidationData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  useEffect(() => {
    fetchMacValidation();
  }, [episodeId]);

  const fetchMacValidation = async () => {
    try {
      setLoading(true);
      const response = await axios.get(`/api/episodes/${episodeId}/mac-validation`);
      setValidationData(response.data.data);
    } catch (err) {
      setError('Unable to fetch MAC validation data');
      console.error('MAC validation error:', err);
    } finally {
      setLoading(false);
    }
  };

  const getRiskColor = (level: string) => {
    const colors = {
      low: theme === 'dark' ? 'text-green-400' : 'text-green-600',
      medium: theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600',
      high: theme === 'dark' ? 'text-orange-400' : 'text-orange-600',
      critical: theme === 'dark' ? 'text-red-400' : 'text-red-600'
    };
    return colors[level] || colors.medium;
  };

  const getRiskBg = (level: string) => {
    const backgrounds = {
      low: theme === 'dark' ? 'bg-green-500/20' : 'bg-green-100',
      medium: theme === 'dark' ? 'bg-yellow-500/20' : 'bg-yellow-100',
      high: theme === 'dark' ? 'bg-orange-500/20' : 'bg-orange-100',
      critical: theme === 'dark' ? 'bg-red-500/20' : 'bg-red-100'
    };
    return backgrounds[level] || backgrounds.medium;
  };

  const getCoverageIcon = (status: string) => {
    switch (status) {
      case 'covered':
        return CheckCircle;
      case 'conditional':
        return AlertTriangle;
      case 'not_covered':
        return XCircle;
      case 'requires_prior_auth':
        return FileText;
      default:
        return Info;
    }
  };

  if (loading) {
    return (
      <div className={cn(
        "p-4 rounded-lg animate-pulse",
        theme === 'dark' ? 'bg-white/5' : 'bg-gray-100',
        className
      )}>
        <div className="space-y-3">
          <div className="h-4 bg-gray-300 rounded w-3/4"></div>
          <div className="h-4 bg-gray-300 rounded w-1/2"></div>
        </div>
      </div>
    );
  }

  if (error || !validationData) {
    return null;
  }

  const CoverageIcon = getCoverageIcon(validationData.coverage_status);

  return (
    <div className={cn(
      "p-4 rounded-lg border",
      theme === 'dark' 
        ? 'bg-gradient-to-br from-blue-900/20 to-purple-900/20 border-blue-500/30' 
        : 'bg-gradient-to-br from-blue-50 to-purple-50 border-blue-200',
      className
    )}>
      {/* Header with Risk Score */}
      <div className="flex items-start justify-between mb-4">
        <div className="flex items-center space-x-2">
          <Shield className={cn("w-5 h-5", getRiskColor(validationData.risk_level))} />
          <h4 className={cn("font-semibold", t.text.primary)}>
            MAC Validation Analysis
          </h4>
        </div>
        <div className={cn(
          "px-3 py-1 rounded-full text-sm font-semibold",
          getRiskBg(validationData.risk_level),
          getRiskColor(validationData.risk_level)
        )}>
          {validationData.risk_score}% Risk
        </div>
      </div>

      {/* Coverage Status */}
      <div className="grid grid-cols-2 gap-3 mb-4">
        <div className={cn(
          "p-3 rounded-lg",
          theme === 'dark' ? 'bg-white/10' : 'bg-white/80'
        )}>
          <div className="flex items-center space-x-2 mb-1">
            <CoverageIcon className={cn(
              "w-4 h-4",
              validationData.coverage_status === 'covered' 
                ? 'text-green-500' 
                : validationData.coverage_status === 'conditional'
                ? 'text-yellow-500'
                : 'text-red-500'
            )} />
            <span className={cn("text-xs font-medium", t.text.secondary)}>
              Coverage Status
            </span>
          </div>
          <p className={cn("text-sm font-semibold capitalize", t.text.primary)}>
            {validationData.coverage_status.replace('_', ' ')}
          </p>
        </div>

        <div className={cn(
          "p-3 rounded-lg",
          theme === 'dark' ? 'bg-white/10' : 'bg-white/80'
        )}>
          <div className="flex items-center space-x-2 mb-1">
            <TrendingUp className="w-4 h-4 text-green-500" />
            <span className={cn("text-xs font-medium", t.text.secondary)}>
              Approval Confidence
            </span>
          </div>
          <p className={cn("text-sm font-semibold", t.text.primary)}>
            {validationData.financial_impact.approval_confidence}%
          </p>
        </div>
      </div>

      {/* MAC Contractor Info */}
      <div className={cn(
        "p-3 rounded-lg mb-3",
        theme === 'dark' ? 'bg-white/5' : 'bg-gray-50'
      )}>
        <p className={cn("text-xs", t.text.secondary)}>MAC Contractor</p>
        <p className={cn("text-sm font-medium", t.text.primary)}>
          {validationData.contractor.name} ({validationData.contractor.jurisdiction})
        </p>
      </div>

      {/* LCD Compliance Status */}
      {validationData.lcd_compliance.status !== 'compliant' && (
        <div className={cn(
          "p-3 rounded-lg mb-3",
          theme === 'dark' ? 'bg-yellow-500/10' : 'bg-yellow-50'
        )}>
          <p className={cn("text-xs font-medium mb-2", 'text-yellow-600')}>
            LCD Compliance Issues
          </p>
          {validationData.lcd_compliance.missing_criteria?.map((criteria, idx) => (
            <p key={idx} className={cn("text-xs", t.text.secondary)}>
              • {criteria}
            </p>
          ))}
        </div>
      )}

      {/* Top Risk Factors */}
      {validationData.denial_prediction.top_risk_factors.length > 0 && (
        <div className="mb-3">
          <p className={cn("text-xs font-medium mb-2", t.text.secondary)}>
            Top Risk Factors
          </p>
          <div className="space-y-1">
            {validationData.denial_prediction.top_risk_factors.slice(0, 2).map((factor, idx) => (
              <div key={idx} className={cn(
                "p-2 rounded text-xs",
                theme === 'dark' ? 'bg-white/5' : 'bg-gray-50'
              )}>
                <div className="flex items-center justify-between">
                  <span className={t.text.primary}>{factor.factor}</span>
                  <span className={cn(
                    "text-xs px-2 py-0.5 rounded",
                    factor.impact === 'high' ? 'bg-red-500/20 text-red-500' :
                    factor.impact === 'medium' ? 'bg-yellow-500/20 text-yellow-500' :
                    'bg-green-500/20 text-green-500'
                  )}>
                    {factor.impact}
                  </span>
                </div>
                {factor.mitigation && (
                  <p className={cn("mt-1 text-xs", t.text.secondary)}>
                    💡 {factor.mitigation}
                  </p>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Financial Impact */}
      <div className={cn(
        "p-3 rounded-lg",
        theme === 'dark' ? 'bg-white/5' : 'bg-gray-50'
      )}>
        <div className="flex items-center justify-between">
          <div>
            <p className={cn("text-xs", t.text.secondary)}>Potential Denial Amount</p>
            <p className={cn("text-lg font-semibold", 'text-red-500')}>
              ${validationData.financial_impact.potential_denial_amount.toLocaleString()}
            </p>
          </div>
          <div className="text-right">
            <p className={cn("text-xs", t.text.secondary)}>Est. Reimbursement</p>
            <p className={cn("text-lg font-semibold", 'text-green-500')}>
              ${validationData.financial_impact.estimated_reimbursement.toLocaleString()}
            </p>
          </div>
        </div>
      </div>

      {/* Top Recommendation */}
      {validationData.recommendations.length > 0 && (
        <div className={cn(
          "mt-3 p-3 rounded-lg",
          validationData.recommendations[0].priority === 'critical' 
            ? theme === 'dark' ? 'bg-red-500/20' : 'bg-red-50'
            : theme === 'dark' ? 'bg-blue-500/20' : 'bg-blue-50'
        )}>
          <p className={cn("text-xs font-medium mb-1", t.text.primary)}>
            Recommended Action
          </p>
          <p className={cn("text-xs", t.text.primary)}>
            {validationData.recommendations[0].action}
          </p>
        </div>
      )}
    </div>
  );
}