import React, { useState } from 'react';
import { FiMapPin, FiPhone, FiGlobe, FiFileText, FiAlertCircle, FiInfo, FiCalendar } from 'react-icons/fi';

interface MACContractor {
    name: string;
    jurisdiction: string;
    phone: string;
    website: string;
    states: string[];
    region: string;
}

interface WoundCareRequirement {
    category: string;
    requirements: string[];
    priority: 'high' | 'medium' | 'low';
}

interface CoveragePolicy {
    title: string;
    effectiveDate: string;
    description: string;
    type: 'LCD' | 'NCD';
}

const macContractors: Record<string, MACContractor> = {
    'Noridian': {
        name: 'Noridian Healthcare Solutions',
        jurisdiction: 'JE & JF',
        phone: '1-855-609-9960',
        website: 'https://med.noridianmedicare.com',
        states: ['AK', 'AZ', 'CA', 'HI', 'ID', 'MT', 'NV', 'OR', 'UT', 'WA', 'WY', 'ND', 'SD'],
        region: 'Western US'
    },
    'Novitas': {
        name: 'Novitas Solutions',
        jurisdiction: 'JH & JL',
        phone: '1-855-252-8782',
        website: 'https://www.novitas-solutions.com',
        states: ['AR', 'CO', 'LA', 'MS', 'NM', 'OK', 'TX', 'DE', 'DC', 'MD', 'NJ', 'PA'],
        region: 'South Central & Mid-Atlantic'
    },
    'WPS': {
        name: 'Wisconsin Physicians Service',
        jurisdiction: 'JK & J8',
        phone: '1-866-590-6727',
        website: 'https://www.wpsmedicare.com',
        states: ['IA', 'KS', 'MO', 'NE', 'IL', 'IN', 'KY', 'MI', 'MN', 'OH', 'WI'],
        region: 'Midwest'
    },
    'Palmetto': {
        name: 'Palmetto GBA',
        jurisdiction: 'JJ & JM',
        phone: '1-866-238-9663',
        website: 'https://www.palmettogba.com',
        states: ['AL', 'GA', 'TN', 'NC', 'SC', 'VA', 'WV'],
        region: 'Southeast'
    },
    'FirstCoast': {
        name: 'First Coast Service Options',
        jurisdiction: 'JN',
        phone: '1-877-567-7259',
        website: 'https://medicare.fcso.com',
        states: ['FL', 'PR', 'VI'],
        region: 'Florida & Territories'
    },
    'NGS': {
        name: 'National Government Services',
        jurisdiction: 'JK',
        phone: '1-855-330-4722',
        website: 'https://www.ngsmedicare.com',
        states: ['CT', 'ME', 'MA', 'NH', 'NY', 'RI', 'VT'],
        region: 'Northeast'
    }
};

const woundCareRequirements: WoundCareRequirement[] = [
    {
        category: 'Documentation Requirements',
        priority: 'high',
        requirements: [
            'Failed 4+ weeks of conservative wound care',
            'Wound measurements and photographic documentation',
            'Provider specialty certification (wound care preferred)',
            'Medical necessity documentation',
            'Patient consent forms'
        ]
    },
    {
        category: 'Clinical Requirements',
        priority: 'high',
        requirements: [
            'Adequate blood supply (ABI > 0.65 or TcPO2 > 30mmHg)',
            'Absence of active infection at application site',
            'Patient compliance with offloading requirements',
            'Wound bed preparation completed',
            'Appropriate wound size (typically 1-100 cm²)'
        ]
    },
    {
        category: 'Coverage Limitations',
        priority: 'medium',
        requirements: [
            'Frequency limits vary by product and contractor',
            'Prior authorization may be required for certain products',
            'Product-specific size limitations',
            'Diagnosis code requirements (ICD-10 wound codes)',
            'Provider type restrictions'
        ]
    },
    {
        category: 'Billing Considerations',
        priority: 'medium',
        requirements: [
            'Appropriate HCPCS codes (Q4xxx series)',
            'Proper modifier usage (GA, KX, etc.)',
            'Waste reporting with JW modifier when applicable',
            'Accurate square centimeter reporting',
            'Coordination with other wound care services'
        ]
    }
];

const commonPolicies: CoveragePolicy[] = [
    {
        title: 'Skin Substitutes and Biologicals',
        effectiveDate: '2024-01-01',
        description: 'Coverage guidelines for cellular and tissue-based products (CTPs) used in wound care.',
        type: 'LCD'
    },
    {
        title: 'Wound Care Dressings',
        effectiveDate: '2024-01-01',
        description: 'Coverage criteria for advanced wound care dressings and negative pressure wound therapy.',
        type: 'LCD'
    },
    {
        title: 'Hyperbaric Oxygen Therapy',
        effectiveDate: '2023-06-01',
        description: 'National coverage determination for hyperbaric oxygen therapy in wound treatment.',
        type: 'NCD'
    }
];

const WoundCareMACTool: React.FC = () => {
    const [selectedState, setSelectedState] = useState<string>('');
    const [selectedContractor, setSelectedContractor] = useState<MACContractor | null>(null);
    const [activeTab, setActiveTab] = useState<'overview' | 'requirements' | 'policies'>('overview');

    const allStates = ['AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 
                     'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 
                     'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 
                     'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 
                     'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'];

    const handleStateChange = (state: string) => {
        setSelectedState(state);
        
        // Find the contractor for this state
        const contractor = Object.values(macContractors).find(mac => 
            mac.states.includes(state)
        );
        
        setSelectedContractor(contractor || null);
    };

    const getPriorityColor = (priority: 'high' | 'medium' | 'low') => {
        switch (priority) {
            case 'high': return 'text-red-600 bg-red-50 border-red-200';
            case 'medium': return 'text-yellow-600 bg-yellow-50 border-yellow-200';
            case 'low': return 'text-green-600 bg-green-50 border-green-200';
        }
    };

    return (
        <div className="space-y-6">
            {/* State Selection */}
            <div className="bg-gray-50 rounded-lg p-6">
                <div className="flex items-center gap-2 mb-4">
                    <FiMapPin className="text-indigo-600" />
                    <h3 className="text-lg font-medium text-gray-900">Select State for MAC Information</h3>
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            State
                        </label>
                        <select
                            value={selectedState}
                            onChange={(e) => handleStateChange(e.target.value)}
                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">Select a state...</option>
                            {allStates.map(state => (
                                <option key={state} value={state}>{state}</option>
                            ))}
                        </select>
                    </div>
                    
                    {selectedContractor && (
                        <div className="bg-white rounded-lg p-4 border border-gray-200">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-sm font-medium text-gray-900">MAC Contractor</span>
                                <span className="text-xs text-gray-500">{selectedContractor.jurisdiction}</span>
                            </div>
                            <p className="text-lg font-semibold text-indigo-600">{selectedContractor.name}</p>
                            <p className="text-sm text-gray-600">{selectedContractor.region}</p>
                        </div>
                    )}
                </div>
            </div>

            {/* MAC Information Tabs */}
            {selectedContractor && (
                <div className="bg-white rounded-lg border border-gray-200">
                    {/* Tab Navigation */}
                    <div className="border-b border-gray-200">
                        <nav className="flex space-x-8 px-6 pt-4">
                            <button
                                onClick={() => setActiveTab('overview')}
                                className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                    activeTab === 'overview'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                            >
                                <FiInfo className="inline mr-2" />
                                Overview
                            </button>
                            <button
                                onClick={() => setActiveTab('requirements')}
                                className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                    activeTab === 'requirements'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                            >
                                <FiFileText className="inline mr-2" />
                                Requirements
                            </button>
                            <button
                                onClick={() => setActiveTab('policies')}
                                className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                    activeTab === 'policies'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                            >
                                <FiCalendar className="inline mr-2" />
                                Policies
                            </button>
                        </nav>
                    </div>

                    {/* Tab Content */}
                    <div className="p-6">
                        {activeTab === 'overview' && (
                            <div className="space-y-6">
                                <div>
                                    <h4 className="text-lg font-medium text-gray-900 mb-4">Contractor Information</h4>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-4">
                                            <div className="flex items-center gap-3">
                                                <FiPhone className="text-gray-400" />
                                                <div>
                                                    <p className="text-sm text-gray-600">Phone</p>
                                                    <p className="font-medium">{selectedContractor.phone}</p>
                                                </div>
                                            </div>
                                            
                                            <div className="flex items-center gap-3">
                                                <FiGlobe className="text-gray-400" />
                                                <div>
                                                    <p className="text-sm text-gray-600">Website</p>
                                                    <a 
                                                        href={selectedContractor.website}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="font-medium text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        Visit MAC Website →
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <p className="text-sm text-gray-600 mb-2">Covered States</p>
                                            <div className="flex flex-wrap gap-2">
                                                {selectedContractor.states.map(state => (
                                                    <span 
                                                        key={state}
                                                        className={`px-2 py-1 text-xs font-medium rounded ${
                                                            state === selectedState 
                                                                ? 'bg-indigo-100 text-indigo-800' 
                                                                : 'bg-gray-100 text-gray-600'
                                                        }`}
                                                    >
                                                        {state}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div className="flex items-start gap-3">
                                        <FiAlertCircle className="text-blue-600 mt-0.5" />
                                        <div>
                                            <h5 className="font-medium text-blue-900 mb-1">Important Note</h5>
                                            <p className="text-sm text-blue-800">
                                                This information provides general guidance for wound care and skin substitute coverage. 
                                                Always verify current LCD requirements and consult the MAC's website for the most 
                                                up-to-date coverage policies before submitting claims.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'requirements' && (
                            <div className="space-y-6">
                                <h4 className="text-lg font-medium text-gray-900">General Wound Care Requirements</h4>
                                
                                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    {woundCareRequirements.map((category, index) => (
                                        <div 
                                            key={index}
                                            className={`border rounded-lg p-4 ${getPriorityColor(category.priority)}`}
                                        >
                                            <div className="flex items-center justify-between mb-3">
                                                <h5 className="font-medium">{category.category}</h5>
                                                <span className="text-xs font-medium px-2 py-1 rounded-full bg-white bg-opacity-50">
                                                    {category.priority.toUpperCase()}
                                                </span>
                                            </div>
                                            <ul className="space-y-2">
                                                {category.requirements.map((req, reqIndex) => (
                                                    <li key={reqIndex} className="text-sm flex items-start gap-2">
                                                        <span className="text-current opacity-60 mt-1">•</span>
                                                        <span>{req}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    ))}
                                </div>

                                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <div className="flex items-start gap-3">
                                        <FiAlertCircle className="text-yellow-600 mt-0.5" />
                                        <div>
                                            <h5 className="font-medium text-yellow-900 mb-1">MAC-Specific Variations</h5>
                                            <p className="text-sm text-yellow-800">
                                                Requirements may vary by MAC contractor. Some MACs have stricter documentation 
                                                requirements or additional clinical criteria. Always check the specific LCD for 
                                                your MAC jurisdiction.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'policies' && (
                            <div className="space-y-6">
                                <h4 className="text-lg font-medium text-gray-900">Common Coverage Policies</h4>
                                
                                <div className="space-y-4">
                                    {commonPolicies.map((policy, index) => (
                                        <div key={index} className="border border-gray-200 rounded-lg p-4">
                                            <div className="flex items-start justify-between mb-2">
                                                <div className="flex-1">
                                                    <h5 className="font-medium text-gray-900">{policy.title}</h5>
                                                    <p className="text-sm text-gray-600 mt-1">{policy.description}</p>
                                                </div>
                                                <div className="text-right ml-4">
                                                    <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                                                        policy.type === 'LCD' 
                                                            ? 'bg-blue-100 text-blue-800' 
                                                            : 'bg-green-100 text-green-800'
                                                    }`}>
                                                        {policy.type}
                                                    </span>
                                                    <p className="text-xs text-gray-500 mt-1">
                                                        Effective: {new Date(policy.effectiveDate).toLocaleDateString()}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                <div className="bg-gray-50 rounded-lg p-4">
                                    <h5 className="font-medium text-gray-900 mb-2">Policy Resources</h5>
                                    <div className="space-y-2 text-sm">
                                        <p>
                                            <strong>LCD Database:</strong>{' '}
                                            <a href="https://www.cms.gov/medicare-coverage-database" target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-800">
                                                CMS Medicare Coverage Database
                                            </a>
                                        </p>
                                        <p>
                                            <strong>NCD Database:</strong>{' '}
                                            <a href="https://www.cms.gov/medicare-coverage-database/overview-and-quick-search.aspx" target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-800">
                                                National Coverage Determinations
                                            </a>
                                        </p>
                                        <p>
                                            <strong>Contractor Specific:</strong>{' '}
                                            <a href={selectedContractor.website} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-800">
                                                {selectedContractor.name} Website
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {!selectedState && (
                <div className="text-center py-12 bg-gray-50 rounded-lg">
                    <FiMapPin className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                    <h3 className="text-lg font-medium text-gray-900 mb-2">Select a State</h3>
                    <p className="text-gray-600">
                        Choose a state above to view MAC contractor information and wound care coverage requirements.
                    </p>
                </div>
            )}
        </div>
    );
};

export default WoundCareMACTool;
