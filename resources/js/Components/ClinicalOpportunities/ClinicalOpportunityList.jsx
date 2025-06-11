import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { 
    AlertCircle, 
    TrendingUp, 
    Calendar, 
    Package, 
    Users,
    ChevronRight,
    RefreshCw 
} from 'lucide-react';
import axios from 'axios';

const ClinicalOpportunityList = ({ patientId }) => {
    const queryClient = useQueryClient();
    const [selectedOpportunity, setSelectedOpportunity] = useState(null);
    const [showActionDialog, setShowActionDialog] = useState(false);

    // Fetch opportunities
    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['clinical-opportunities', patientId],
        queryFn: async () => {
            const response = await axios.get(
                `/api/v1/clinical-opportunities/patients/${patientId}/opportunities`,
                {
                    params: {
                        use_ai: true,
                        limit: 20
                    }
                }
            );
            return response.data;
        },
        staleTime: 5 * 60 * 1000, // 5 minutes
    });

    // Take action mutation
    const actionMutation = useMutation({
        mutationFn: async ({ opportunityId, action }) => {
            const response = await axios.post(
                `/api/v1/clinical-opportunities/opportunities/${opportunityId}/actions`,
                action
            );
            return response.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries(['clinical-opportunities', patientId]);
            setShowActionDialog(false);
            setSelectedOpportunity(null);
        }
    });

    const getPriorityColor = (priority) => {
        if (priority >= 9) return 'destructive';
        if (priority >= 7) return 'warning';
        if (priority >= 5) return 'default';
        return 'secondary';
    };

    const getCategoryIcon = (category) => {
        switch (category) {
            case 'wound_care':
                return <AlertCircle className="w-4 h-4" />;
            case 'diabetes_management':
                return <TrendingUp className="w-4 h-4" />;
            case 'quality_improvement':
                return <Users className="w-4 h-4" />;
            case 'preventive_care':
                return <Calendar className="w-4 h-4" />;
            default:
                return <Package className="w-4 h-4" />;
        }
    };

    const handleAction = (opportunity, actionType) => {
        const actionData = {
            type: actionType,
            data: {}
        };

        // Add specific data based on action type
        switch (actionType) {
            case 'order_product':
                actionData.data.product_ids = opportunity.product_recommendations?.map(p => p.id) || [];
                break;
            case 'schedule_assessment':
                actionData.data.assessment_type = opportunity.actions?.[0]?.details?.assessment_type || 'wound_assessment';
                break;
        }

        actionMutation.mutate({
            opportunityId: opportunity.rule_id,
            action: actionData
        });
    };

    if (isLoading) {
        return (
            <div className="flex items-center justify-center p-8">
                <RefreshCw className="w-6 h-6 animate-spin text-gray-400" />
                <span className="ml-2 text-gray-600">Analyzing clinical data...</span>
            </div>
        );
    }

    if (error) {
        return (
            <Alert variant="destructive">
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>
                    Failed to load clinical opportunities. Please try again.
                </AlertDescription>
            </Alert>
        );
    }

    const opportunities = data?.opportunities || [];
    const summary = data?.summary || {};

    return (
        <div className="space-y-6">
            {/* Summary Section */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card>
                    <CardContent className="p-4">
                        <div className="text-2xl font-bold">{summary.total_opportunities || 0}</div>
                        <p className="text-sm text-gray-600">Total Opportunities</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="text-2xl font-bold text-red-600">{summary.urgent_actions || 0}</div>
                        <p className="text-sm text-gray-600">Urgent Actions</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="text-2xl font-bold text-green-600">
                            ${(summary.potential_cost_savings || 0).toLocaleString()}
                        </div>
                        <p className="text-sm text-gray-600">Potential Savings</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <Button 
                            onClick={() => refetch()} 
                            variant="outline" 
                            className="w-full"
                            disabled={isLoading}
                        >
                            <RefreshCw className="w-4 h-4 mr-2" />
                            Refresh
                        </Button>
                    </CardContent>
                </Card>
            </div>

            {/* Opportunities List */}
            <div className="space-y-4">
                {opportunities.length === 0 ? (
                    <Card>
                        <CardContent className="p-8 text-center">
                            <p className="text-gray-600">No clinical opportunities identified at this time.</p>
                            <p className="text-sm text-gray-500 mt-2">
                                Continue monitoring patient progress and check back later.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    opportunities.map((opportunity) => (
                        <Card key={opportunity.rule_id} className="hover:shadow-lg transition-shadow">
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="flex items-start space-x-3">
                                        <div className="mt-1">
                                            {getCategoryIcon(opportunity.category)}
                                        </div>
                                        <div className="flex-1">
                                            <CardTitle className="text-lg">
                                                {opportunity.title}
                                            </CardTitle>
                                            <div className="flex items-center gap-2 mt-2">
                                                <Badge variant={getPriorityColor(opportunity.priority)}>
                                                    {opportunity.priority >= 9 ? 'Critical' : 
                                                     opportunity.priority >= 7 ? 'High' : 
                                                     opportunity.priority >= 5 ? 'Medium' : 'Low'} Priority
                                                </Badge>
                                                <Badge variant="outline">
                                                    {Math.round(opportunity.confidence_score * 100)}% Confidence
                                                </Badge>
                                                <Badge variant="secondary">
                                                    {opportunity.category.replace('_', ' ')}
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="text-gray-700 mb-4">{opportunity.description}</p>
                                
                                {/* Evidence */}
                                {opportunity.evidence && opportunity.evidence.length > 0 && (
                                    <div className="mb-4">
                                        <h4 className="text-sm font-semibold text-gray-600 mb-2">Evidence:</h4>
                                        <ul className="list-disc list-inside text-sm text-gray-600 space-y-1">
                                            {opportunity.evidence.map((item, idx) => (
                                                <li key={idx}>{item}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                {/* Potential Impact */}
                                {opportunity.potential_impact && (
                                    <div className="mb-4 p-3 bg-blue-50 rounded-lg">
                                        <h4 className="text-sm font-semibold text-blue-900 mb-2">Potential Impact:</h4>
                                        <div className="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                                            {Object.entries(opportunity.potential_impact).map(([key, value]) => (
                                                <div key={key}>
                                                    <span className="text-blue-700">{key.replace('_', ' ')}:</span>
                                                    <span className="ml-1 font-semibold text-blue-900">{value}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Actions */}
                                <div className="flex flex-wrap gap-2">
                                    {opportunity.actions.map((action, idx) => (
                                        <Button
                                            key={idx}
                                            onClick={() => handleAction(opportunity, action.type)}
                                            variant={action.priority === 'urgent' ? 'destructive' : 'default'}
                                            size="sm"
                                            disabled={actionMutation.isLoading}
                                        >
                                            {action.description}
                                            <ChevronRight className="w-4 h-4 ml-1" />
                                        </Button>
                                    ))}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setSelectedOpportunity(opportunity)}
                                    >
                                        View Details
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </div>
    );
};

export default ClinicalOpportunityList;