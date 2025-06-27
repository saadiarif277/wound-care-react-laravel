import React, { useState } from 'react';
import { FiAlertCircle, FiCheck, FiClock, FiFileText, FiPlus, FiX } from 'react-icons/fi';
import axios from 'axios';

interface Diagnosis {
    code: string;
    description: string;
}

interface PriorAuthFormProps {
    eligibilityTransactionId: string;
    onSuccess: (response: any) => void;
    onCancel: () => void;
}

const PriorAuthForm: React.FC<PriorAuthFormProps> = ({
    eligibilityTransactionId,
    onSuccess,
    onCancel,
}) => {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [diagnoses, setDiagnoses] = useState<Diagnosis[]>([{ code: '', description: '' }]);
    const [woundDetails, setWoundDetails] = useState({
        type: '',
        location: '',
        severity: '',
    });

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        try {
            const response = await axios.post('/eligibility/prior-auth/submit', {
                eligibility_transaction_id: eligibilityTransactionId,
                clinical_data: {
                    diagnoses,
                    wound_details: woundDetails,
                },
            });

            onSuccess(response.data);
        } catch (error) {
            console.error('Prior auth submission failed:', error);
        } finally {
            setIsSubmitting(false);
        }
    };

    const addDiagnosis = () => {
        setDiagnoses([...diagnoses, { code: '', description: '' }]);
    };

    const removeDiagnosis = (index: number) => {
        setDiagnoses(diagnoses.filter((_, i) => i !== index));
    };

    const updateDiagnosis = (index: number, field: keyof Diagnosis, value: string) => {
        const newDiagnoses = [...diagnoses];
        newDiagnoses[index] = { ...newDiagnoses[index], [field]: value };
        setDiagnoses(newDiagnoses);
    };

    return (
        <div className="bg-white shadow rounded-lg p-6">
            <h2 className="text-lg font-medium text-gray-900 mb-4 flex items-center gap-2">
                <FiAlertCircle className="text-indigo-600" />
                Prior Authorization Request
            </h2>

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Diagnoses */}
                <div>
                    <div className="flex items-center justify-between mb-2">
                        <label className="block text-sm font-medium text-gray-700">Diagnoses</label>
                        <button
                            type="button"
                            onClick={addDiagnosis}
                            className="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200"
                        >
                            <FiPlus className="w-4 h-4 mr-1" />
                            Add Diagnosis
                        </button>
                    </div>
                    {diagnoses.map((diagnosis, index) => (
                        <div key={index} className="flex gap-4 mb-2">
                            <div className="flex-grow">
                                <input
                                    type="text"
                                    value={diagnosis.code}
                                    onChange={e => updateDiagnosis(index, 'code', e.target.value)}
                                    placeholder="Diagnosis Code (ICD-10)"
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                />
                            </div>
                            <div className="flex-grow">
                                <input
                                    type="text"
                                    value={diagnosis.description}
                                    onChange={e => updateDiagnosis(index, 'description', e.target.value)}
                                    placeholder="Diagnosis Description"
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                />
                            </div>
                            {index > 0 && (
                                <button
                                    type="button"
                                    onClick={() => removeDiagnosis(index)}
                                    className="inline-flex items-center p-2 border border-transparent rounded-md text-red-700 bg-red-100 hover:bg-red-200"
                                >
                                    <FiX className="w-4 h-4" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>

                {/* Wound Details */}
                <div className="space-y-4">
                    <h3 className="text-sm font-medium text-gray-700">Wound Details</h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Wound Type</label>
                            <select
                                value={woundDetails.type}
                                onChange={e => setWoundDetails(prev => ({ ...prev, type: e.target.value }))}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="">Select type</option>
                                <option value="pressure_ulcer">Pressure Ulcer</option>
                                <option value="diabetic_ulcer">Diabetic Ulcer</option>
                                <option value="venous_ulcer">Venous Ulcer</option>
                                <option value="arterial_ulcer">Arterial Ulcer</option>
                                <option value="surgical_wound">Surgical Wound</option>
                                <option value="traumatic_wound">Traumatic Wound</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Location</label>
                            <select
                                value={woundDetails.location}
                                onChange={e => setWoundDetails(prev => ({ ...prev, location: e.target.value }))}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="">Select location</option>
                                <option value="sacrum">Sacrum</option>
                                <option value="heel">Heel</option>
                                <option value="ankle">Ankle</option>
                                <option value="foot">Foot</option>
                                <option value="leg">Leg</option>
                                <option value="abdomen">Abdomen</option>
                                <option value="back">Back</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Severity</label>
                            <select
                                value={woundDetails.severity}
                                onChange={e => setWoundDetails(prev => ({ ...prev, severity: e.target.value }))}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="">Select severity</option>
                                <option value="stage_1">Stage 1</option>
                                <option value="stage_2">Stage 2</option>
                                <option value="stage_3">Stage 3</option>
                                <option value="stage_4">Stage 4</option>
                                <option value="unstageable">Unstageable</option>
                                <option value="deep_tissue">Deep Tissue Injury</option>
                            </select>
                        </div>
                    </div>
                </div>

                {/* Additional Notes */}
                <div>
                    <label className="block text-sm font-medium text-gray-700">Additional Notes</label>
                    <textarea
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        rows={3}
                        placeholder="Enter any additional clinical information that may be relevant to the prior authorization request..."
                    />
                </div>

                <div className="flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onCancel}
                        className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={isSubmitting}
                        className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                    >
                        {isSubmitting ? (
                            <>
                                <FiClock className="animate-spin -ml-1 mr-2 h-4 w-4" />
                                Submitting...
                            </>
                        ) : (
                            <>
                                <FiCheck className="-ml-1 mr-2 h-4 w-4" />
                                Submit Prior Authorization
                            </>
                        )}
                    </button>
                </div>
            </form>
        </div>
    );
};

export default PriorAuthForm;
