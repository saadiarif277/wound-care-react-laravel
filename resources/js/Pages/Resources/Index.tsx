import React, { useState, useEffect, useRef } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { motion, AnimatePresence } from 'framer-motion';
import {
    FiMapPin, FiPhone, FiGlobe, FiFileText, FiAlertCircle, FiInfo, 
    FiCalendar, FiBook, FiDollarSign, FiClipboard, FiUsers, FiSearch,
    FiExternalLink, FiDownload, FiClock, FiTrendingUp, FiShield,
    FiTarget, FiZap, FiBookOpen, FiLink, FiAward, FiHelpCircle, FiDatabase, 
    FiCheckCircle, FiXCircle, FiActivity, FiHeart,
    FiDroplet, FiThermometer, FiTrendingDown, FiList, FiStar, FiEye, FiX
} from 'react-icons/fi';

interface MACContractor {
    name: string;
    jurisdiction: string;
    phone: string;
    website: string;
    states: string[];
    region: string;
    color: string;
    description: string;
}

interface Resource {
    id: string;
    title: string;
    description: string;
    category: string;
    url: string;
    isExternal: boolean;
    icon: React.ElementType;
    featured: boolean;
    tags: string[];
}

interface ResourceCategory {
    id: string;
    title: string;
    description: string;
    icon: React.ElementType;
    color: string;
    count: number;
}

const macContractors: Record<string, MACContractor> = {
    'Noridian': {
        name: 'Noridian Healthcare Solutions',
        jurisdiction: 'JE & JF',
        phone: '1-855-609-9960',
        website: 'https://med.noridianmedicare.com',
        states: ['AK', 'AZ', 'CA', 'HI', 'ID', 'MT', 'NV', 'OR', 'UT', 'WA', 'WY', 'ND', 'SD'],
        region: 'Western US',
        color: 'from-blue-500 to-cyan-500',
        description: 'Serving the Western United States with comprehensive Medicare administrative services.'
    },
    'Novitas': {
        name: 'Novitas Solutions',
        jurisdiction: 'JH & JL',
        phone: '1-855-252-8782',
        website: 'https://www.novitas-solutions.com',
        states: ['AR', 'CO', 'LA', 'MS', 'NM', 'OK', 'TX', 'DE', 'DC', 'MD', 'NJ', 'PA'],
        region: 'South Central & Mid-Atlantic',
        color: 'from-purple-500 to-pink-500',
        description: 'Comprehensive Medicare coverage across South Central and Mid-Atlantic regions.'
    },
    'WPS': {
        name: 'Wisconsin Physicians Service',
        jurisdiction: 'JK & J8',
        phone: '1-866-590-6727',
        website: 'https://www.wpsmedicare.com',
        states: ['IA', 'KS', 'MO', 'NE', 'IL', 'IN', 'KY', 'MI', 'MN', 'OH', 'WI'],
        region: 'Midwest',
        color: 'from-green-500 to-emerald-500',
        description: 'Midwest Medicare administrative services with focus on provider support.'
    },
    'Palmetto': {
        name: 'Palmetto GBA',
        jurisdiction: 'JJ & JM',
        phone: '1-866-238-9663',
        website: 'https://www.palmettogba.com',
        states: ['AL', 'GA', 'TN', 'NC', 'SC', 'VA', 'WV'],
        region: 'Southeast',
        color: 'from-orange-500 to-red-500',
        description: 'Southeast Medicare administrative contractor with extensive regional expertise.'
    },
    'FirstCoast': {
        name: 'First Coast Service Options',
        jurisdiction: 'JN',
        phone: '1-877-567-7259',
        website: 'https://medicare.fcso.com',
        states: ['FL', 'PR', 'VI'],
        region: 'Florida & Territories',
        color: 'from-teal-500 to-blue-500',
        description: 'Specialized Medicare services for Florida, Puerto Rico, and Virgin Islands.'
    },
    'NGS': {
        name: 'National Government Services',
        jurisdiction: 'JK',
        phone: '1-855-330-4722',
        website: 'https://www.ngsmedicare.com',
        states: ['CT', 'ME', 'MA', 'NH', 'NY', 'RI', 'VT'],
        region: 'Northeast',
        color: 'from-indigo-500 to-purple-500',
        description: 'Northeast Medicare administrative services with comprehensive provider resources.'
    }
};

const resourceCategories: ResourceCategory[] = [
    {
        id: 'billing-coding',
        title: 'Billing & Coding',
        description: 'HCPCS codes, billing guidelines, and coding resources',
        icon: FiClipboard,
        color: 'from-blue-500 to-indigo-600',
        count: 12
    },
    {
        id: 'mac-resources',
        title: 'MAC Resources',
        description: 'Medicare Administrative Contractor information and policies',
        icon: FiShield,
        color: 'from-purple-500 to-pink-600',
        count: 8
    },
    {
        id: 'documentation',
        title: 'Documentation',
        description: 'Clinical documentation and medical necessity guidelines',
        icon: FiFileText,
        color: 'from-green-500 to-emerald-600',
        count: 15
    },
    {
        id: 'reimbursement',
        title: 'Reimbursement',
        description: 'Fee schedules, payment policies, and reimbursement rates',
        icon: FiDollarSign,
        color: 'from-orange-500 to-red-600',
        count: 10
    },
    {
        id: 'training',
        title: 'Training & Education',
        description: 'Webinars, courses, and certification programs',
        icon: FiBookOpen,
        color: 'from-teal-500 to-cyan-600',
        count: 6
    },
    {
        id: 'tools',
        title: 'Tools & Calculators',
        description: 'Billing calculators, decision trees, and interactive tools',
        icon: FiZap,
        color: 'from-pink-500 to-rose-600',
        count: 4
    }
];

const featuredResources: Resource[] = [
    {
        id: '1',
        title: 'CMS Medicare Coverage Database',
        description: 'Official CMS database for LCD and NCD policies',
        category: 'mac-resources',
        url: 'https://www.cms.gov/medicare-coverage-database',
        isExternal: true,
        icon: FiDatabase,
        featured: true,
        tags: ['LCD', 'NCD', 'Coverage', 'CMS']
    },
    {
        id: '2',
        title: 'Wound Care Coding Guidelines',
        description: 'Comprehensive guide to wound care HCPCS and CPT codes',
        category: 'billing-coding',
        url: '/resources/wound-care-coding',
        isExternal: false,
        icon: FiTarget,
        featured: true,
        tags: ['HCPCS', 'CPT', 'Wound Care', 'Coding']
    },
    {
        id: '3',
        title: 'Medicare Fee Schedule Lookup',
        description: 'Interactive tool to lookup current Medicare fee schedules',
        category: 'reimbursement',
        url: 'https://www.cms.gov/medicare/physician-fee-schedule',
        isExternal: true,
        icon: FiSearch,
        featured: true,
        tags: ['Fee Schedule', 'Medicare', 'Reimbursement']
    },
    {
        id: '4',
        title: 'Documentation Best Practices',
        description: 'Clinical documentation templates and best practices',
        category: 'documentation',
        url: '/resources/documentation-templates',
        isExternal: false,
        icon: FiAward,
        featured: true,
        tags: ['Templates', 'Documentation', 'Best Practices']
    },
    {
        id: '5',
        title: 'CMS-1500 Billing Form Guide',
        description: 'Interactive guide with skin substitute billing best practices',
        category: 'billing-coding',
        url: '#cms-1500-modal',
        isExternal: false,
        icon: FiFileText,
        featured: true,
        tags: ['CMS-1500', 'Billing', 'Skin Substitutes', 'Forms']
    }
];

const allStates = [
    { code: 'AL', name: 'Alabama' },
    { code: 'AK', name: 'Alaska' },
    { code: 'AZ', name: 'Arizona' },
    { code: 'AR', name: 'Arkansas' },
    { code: 'CA', name: 'California' },
    { code: 'CO', name: 'Colorado' },
    { code: 'CT', name: 'Connecticut' },
    { code: 'DE', name: 'Delaware' },
    { code: 'FL', name: 'Florida' },
    { code: 'GA', name: 'Georgia' },
    { code: 'HI', name: 'Hawaii' },
    { code: 'ID', name: 'Idaho' },
    { code: 'IL', name: 'Illinois' },
    { code: 'IN', name: 'Indiana' },
    { code: 'IA', name: 'Iowa' },
    { code: 'KS', name: 'Kansas' },
    { code: 'KY', name: 'Kentucky' },
    { code: 'LA', name: 'Louisiana' },
    { code: 'ME', name: 'Maine' },
    { code: 'MD', name: 'Maryland' },
    { code: 'MA', name: 'Massachusetts' },
    { code: 'MI', name: 'Michigan' },
    { code: 'MN', name: 'Minnesota' },
    { code: 'MS', name: 'Mississippi' },
    { code: 'MO', name: 'Missouri' },
    { code: 'MT', name: 'Montana' },
    { code: 'NE', name: 'Nebraska' },
    { code: 'NV', name: 'Nevada' },
    { code: 'NH', name: 'New Hampshire' },
    { code: 'NJ', name: 'New Jersey' },
    { code: 'NM', name: 'New Mexico' },
    { code: 'NY', name: 'New York' },
    { code: 'NC', name: 'North Carolina' },
    { code: 'ND', name: 'North Dakota' },
    { code: 'OH', name: 'Ohio' },
    { code: 'OK', name: 'Oklahoma' },
    { code: 'OR', name: 'Oregon' },
    { code: 'PA', name: 'Pennsylvania' },
    { code: 'RI', name: 'Rhode Island' },
    { code: 'SC', name: 'South Carolina' },
    { code: 'SD', name: 'South Dakota' },
    { code: 'TN', name: 'Tennessee' },
    { code: 'TX', name: 'Texas' },
    { code: 'UT', name: 'Utah' },
    { code: 'VT', name: 'Vermont' },
    { code: 'VA', name: 'Virginia' },
    { code: 'WA', name: 'Washington' },
    { code: 'WV', name: 'West Virginia' },
    { code: 'WI', name: 'Wisconsin' },
    { code: 'WY', name: 'Wyoming' }
];

const ResourcesPage = () => {
    const [selectedState, setSelectedState] = useState<string>('');
    const [selectedContractor, setSelectedContractor] = useState<MACContractor | null>(null);
    const [activeCategory, setActiveCategory] = useState<string>('all');
    const [searchQuery, setSearchQuery] = useState<string>('');
    const [isAnimating, setIsAnimating] = useState(false);
    const [isCms1500ModalOpen, setIsCms1500ModalOpen] = useState(false);
    const cms1500FormRef = useRef<HTMLDivElement>(null);

    // Auto-scroll to form when modal opens
    useEffect(() => {
        if (isCms1500ModalOpen && cms1500FormRef.current) {
            setTimeout(() => {
                cms1500FormRef.current?.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }, 200); // Small delay to ensure modal is fully rendered
        }
    }, [isCms1500ModalOpen]);

    const handleStateChange = (state: string) => {
        setSelectedState(state);
        setIsAnimating(true);
        
        // Find the contractor for this state
        const contractor = Object.values(macContractors).find(mac => 
            mac.states.includes(state)
        );
        
        setTimeout(() => {
            setSelectedContractor(contractor || null);
            setIsAnimating(false);
        }, 300);
    };

    const filteredResources = featuredResources.filter(resource => {
        const matchesCategory = activeCategory === 'all' || resource.category === activeCategory;
        const matchesSearch = resource.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
                            resource.description.toLowerCase().includes(searchQuery.toLowerCase()) ||
                            resource.tags.some(tag => tag.toLowerCase().includes(searchQuery.toLowerCase()));
        return matchesCategory && matchesSearch;
    });

    return (
        <MainLayout>
            <Head title="Resources - Billing & Coding" />
            
            <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
                {/* Hero Section */}
                                    <div className="relative overflow-hidden">
                        <div className="absolute inset-0 bg-gradient-to-r from-blue-600 to-indigo-700 opacity-90"></div>
                        <div className="absolute inset-0 opacity-20">
                            <div className="absolute inset-0 bg-blue-400 bg-opacity-10" 
                                 style={{
                                     backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`,
                                     backgroundRepeat: 'repeat'
                                 }}>
                            </div>
                        </div>
                    
                    <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.8 }}
                            className="text-center"
                        >
                            <h1 className="text-4xl md:text-6xl font-bold text-white mb-6">
                                Billing & Coding
                                <span className="block text-blue-200">Resources Hub</span>
                            </h1>
                            <p className="text-xl text-blue-100 max-w-3xl mx-auto mb-8">
                                Your comprehensive source for Medicare billing guidelines, MAC contractor information, 
                                and coding resources - all in one place.
                            </p>
                            <div className="flex flex-wrap justify-center gap-4">
                                <span className="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-white bg-opacity-20 text-white">
                                    <FiShield className="w-4 h-4 mr-2" />
                                    MAC Contractors
                                </span>
                                <span className="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-white bg-opacity-20 text-white">
                                    <FiClipboard className="w-4 h-4 mr-2" />
                                    Billing Guidelines
                                </span>
                                <span className="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-white bg-opacity-20 text-white">
                                    <FiDollarSign className="w-4 h-4 mr-2" />
                                    Fee Schedules
                                </span>
                                <span className="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-white bg-opacity-20 text-white">
                                    <FiBookOpen className="w-4 h-4 mr-2" />
                                    Training Resources
                                </span>
                            </div>
                        </motion.div>
                    </div>
                </div>

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    {/* MAC Contractor Lookup Section */}
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.8, delay: 0.2 }}
                        className="mb-16"
                    >
                        <div className="text-center mb-12">
                            <h2 className="text-3xl font-bold text-gray-900 mb-4">
                                Find Your MAC Contractor
                            </h2>
                            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
                                Select your state to find your Medicare Administrative Contractor (MAC) information, 
                                contact details, and specific billing requirements.
                            </p>
                        </div>

                        <div className="max-w-4xl mx-auto">
                            <div className="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                    {/* State Selection */}
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-3">
                                            <FiMapPin className="inline w-4 h-4 mr-2" />
                                            Select Your State
                                        </label>
                                        <select
                                            value={selectedState}
                                            onChange={(e) => handleStateChange(e.target.value)}
                                            className="w-full py-3 px-4 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg"
                                        >
                                            <option value="">Choose a state...</option>
                                            {allStates.map(state => (
                                                <option key={state.code} value={state.code}>
                                                    {state.name} ({state.code})
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {/* MAC Contractor Display */}
                                    <div className="flex items-center justify-center">
                                        <AnimatePresence mode="wait">
                                            {!selectedContractor && !isAnimating && (
                                                <motion.div
                                                    key="placeholder"
                                                    initial={{ opacity: 0, scale: 0.8 }}
                                                    animate={{ opacity: 1, scale: 1 }}
                                                    exit={{ opacity: 0, scale: 0.8 }}
                                                    className="text-center p-8 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200"
                                                >
                                                    <FiMapPin className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                                                    <p className="text-gray-500 font-medium">
                                                        Select a state to view MAC information
                                                    </p>
                                                </motion.div>
                                            )}
                                            
                                            {isAnimating && (
                                                <motion.div
                                                    key="loading"
                                                    initial={{ opacity: 0 }}
                                                    animate={{ opacity: 1 }}
                                                    exit={{ opacity: 0 }}
                                                    className="text-center p-8"
                                                >
                                                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                                                    <p className="text-gray-500 mt-4">Loading MAC information...</p>
                                                </motion.div>
                                            )}
                                            
                                            {selectedContractor && !isAnimating && (
                                                <motion.div
                                                    key="contractor"
                                                    initial={{ opacity: 0, y: 20 }}
                                                    animate={{ opacity: 1, y: 0 }}
                                                    exit={{ opacity: 0, y: -20 }}
                                                    className="w-full"
                                                >
                                                    <div className={`bg-gradient-to-r ${selectedContractor.color} rounded-xl p-6 text-white`}>
                                                        <div className="flex items-center justify-between mb-4">
                                                            <h3 className="text-xl font-bold">{selectedContractor.name}</h3>
                                                            <span className="text-sm bg-white bg-opacity-20 px-3 py-1 rounded-full">
                                                                {selectedContractor.jurisdiction}
                                                            </span>
                                                        </div>
                                                        
                                                        <p className="text-blue-100 mb-4 text-sm">
                                                            {selectedContractor.description}
                                                        </p>
                                                        
                                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                            <div className="flex items-center">
                                                                <FiPhone className="w-4 h-4 mr-2" />
                                                                <span className="text-sm">{selectedContractor.phone}</span>
                                                            </div>
                                                            <div className="flex items-center">
                                                                <FiMapPin className="w-4 h-4 mr-2" />
                                                                <span className="text-sm">{selectedContractor.region}</span>
                                                            </div>
                                                        </div>
                                                        
                                                        <div className="mt-4 pt-4 border-t border-white border-opacity-20">
                                                            <div className="flex flex-wrap gap-2">
                                                                {selectedContractor.states.map(state => (
                                                                    <span 
                                                                        key={state}
                                                                        className={`px-2 py-1 text-xs font-medium rounded ${
                                                                            state === selectedState 
                                                                                ? 'bg-white text-blue-600' 
                                                                                : 'bg-white bg-opacity-20 text-white'
                                                                        }`}
                                                                    >
                                                                        {state}
                                                                    </span>
                                                                ))}
                                                            </div>
                                                        </div>
                                                        
                                                        <div className="mt-6">
                                                            <a
                                                                href={selectedContractor.website}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="inline-flex items-center px-4 py-2 bg-white text-blue-600 rounded-lg hover:bg-blue-50 transition-colors font-medium"
                                                            >
                                                                <FiExternalLink className="w-4 h-4 mr-2" />
                                                                Visit MAC Website
                                                            </a>
                                                        </div>
                                                    </div>
                                                </motion.div>
                                            )}
                                        </AnimatePresence>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </motion.div>

                    {/* CMS-1500 Form Guide - Inline with MAC Lookup */}
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.8, delay: 0.25 }}
                        className="mb-16"
                    >
                        <div className="max-w-6xl mx-auto">
                            <div className="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                                <div className="flex items-center gap-4 mb-6">
                                    <div className="w-12 h-12 rounded-lg bg-gradient-to-r from-slate-600 to-slate-700 flex items-center justify-center">
                                        <FiFileText className="w-6 h-6 text-white" />
                                    </div>
                                    <div>
                                        <h2 className="text-2xl font-bold text-gray-900">CMS-1500 Billing Guide</h2>
                                        <p className="text-gray-600">Essential billing practices for skin substitute procedures</p>
                                    </div>
                                    <div className="ml-auto">
                                        <button
                                            onClick={() => setIsCms1500ModalOpen(true)}
                                            className="inline-flex items-center px-6 py-3 bg-slate-700 text-white font-medium rounded-lg hover:bg-slate-800 transition-colors shadow-sm"
                                        >
                                            <FiEye className="w-4 h-4 mr-2" />
                                            View Detailed Guide
                                        </button>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div className="bg-slate-50 border border-slate-200 rounded-lg p-4">
                                        <div className="flex items-center gap-3 mb-3">
                                            <div className="w-8 h-8 bg-slate-600 text-white rounded-lg flex items-center justify-center text-sm font-medium">
                                                1
                                            </div>
                                            <h4 className="font-semibold text-slate-900">Insurance Verification</h4>
                                        </div>
                                        <p className="text-sm text-slate-700">Verify Medicare eligibility and coverage before proceeding with skin substitute application.</p>
                                    </div>

                                    <div className="bg-slate-50 border border-slate-200 rounded-lg p-4">
                                        <div className="flex items-center gap-3 mb-3">
                                            <div className="w-8 h-8 bg-slate-600 text-white rounded-lg flex items-center justify-center text-sm font-medium">
                                                2
                                            </div>
                                            <h4 className="font-semibold text-slate-900">Diagnosis Codes</h4>
                                        </div>
                                        <p className="text-sm text-slate-700">Use appropriate ICD-10 codes (L97.xxx, L89.xxx, I83.xxx) that support medical necessity.</p>
                                    </div>

                                    <div className="bg-slate-50 border border-slate-200 rounded-lg p-4">
                                        <div className="flex items-center gap-3 mb-3">
                                            <div className="w-8 h-8 bg-slate-600 text-white rounded-lg flex items-center justify-center text-sm font-medium">
                                                3
                                            </div>
                                            <h4 className="font-semibold text-slate-900">Product Documentation</h4>
                                        </div>
                                        <p className="text-sm text-slate-700">Document wound measurements, product details, and application technique precisely.</p>
                                    </div>
                                </div>

                                <div className="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                                    <div className="flex items-start gap-3">
                                        <FiAlertCircle className="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" />
                                        <div>
                                            <p className="text-sm text-amber-800">
                                                <span className="font-medium">Professional Guidance:</span> Always consult with your billing team for payer-specific requirements and current guidelines.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </motion.div>

                    {/* IVR Documentation Requirements */}
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.8, delay: 0.3 }}
                        className="mb-16"
                    >
                        <div className="text-center mb-12">
                            <h2 className="text-3xl font-bold text-gray-900 mb-4">
                                IVR Documentation Requirements
                            </h2>
                            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
                                Complete IVR requirements for Commercial and Medicare Advantage plans. 
                                Always verify with your specific payer and billing team.
                            </p>
                        </div>

                        <div className="max-w-6xl mx-auto">
                            <div className="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                    {/* General Requirements */}
                                    <div className="space-y-6">
                                        <div className="flex items-center gap-3 mb-6">
                                            <div className="w-12 h-12 rounded-lg bg-gradient-to-r from-blue-500 to-indigo-600 flex items-center justify-center">
                                                <FiClipboard className="w-6 h-6 text-white" />
                                            </div>
                                            <div>
                                                <h3 className="text-xl font-bold text-gray-900">General Requirements</h3>
                                                <p className="text-gray-600">All Commercial & Medicare Advantage Plans</p>
                                            </div>
                                        </div>

                                        <div className="space-y-4">
                                            <div className="flex items-start gap-3 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                                <FiCalendar className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                                                <div>
                                                    <p className="font-semibold text-blue-900">Progress Notes</p>
                                                    <p className="text-sm text-blue-800">At least 4 weeks of progress notes with measurements</p>
                                                </div>
                                            </div>

                                            <div className="flex items-start gap-3 p-4 bg-red-50 rounded-lg border border-red-200">
                                                <FiActivity className="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" />
                                                <div>
                                                    <p className="font-semibold text-red-900">A1c Lab Result</p>
                                                    <p className="text-sm text-red-800">Within the last 90 days for all diabetic patients</p>
                                                </div>
                                            </div>

                                            <div className="flex items-start gap-3 p-4 bg-green-50 rounded-lg border border-green-200">
                                                <FiHeart className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                                                <div>
                                                    <p className="font-semibold text-green-900">Adequate Circulation</p>
                                                    <p className="text-sm text-green-800">ABI or doppler studies recommended (not all payers accept palpable pulses)</p>
                                                </div>
                                            </div>

                                            <div className="flex items-start gap-3 p-4 bg-purple-50 rounded-lg border border-purple-200">
                                                <FiShield className="w-5 h-5 text-purple-600 mt-0.5 flex-shrink-0" />
                                                <div>
                                                    <p className="font-semibold text-purple-900">Wound-Specific Requirements</p>
                                                    <ul className="text-sm text-purple-800 mt-1 space-y-1">
                                                        <li>• Documentation of off-loading for DFUs</li>
                                                        <li>• Documentation of compression for VLUs</li>
                                                        <li>• Smoking status and counseling (if applicable)</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Payer-Specific Requirements */}
                                    <div className="space-y-6">
                                        <div className="flex items-center gap-3 mb-6">
                                            <div className="w-12 h-12 rounded-lg bg-gradient-to-r from-orange-500 to-red-600 flex items-center justify-center">
                                                <FiStar className="w-6 h-6 text-white" />
                                            </div>
                                            <div>
                                                <h3 className="text-xl font-bold text-gray-900">Payer-Specific Requirements</h3>
                                                <p className="text-gray-600">Additional requirements by payer</p>
                                            </div>
                                        </div>

                                        <div className="space-y-4">
                                            <div className="p-4 bg-gradient-to-r from-orange-50 to-red-50 rounded-lg border border-orange-200">
                                                <div className="flex items-center gap-2 mb-3">
                                                    <div className="w-8 h-8 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center">
                                                        <span className="text-white font-bold text-sm">H</span>
                                                    </div>
                                                    <h4 className="font-bold text-orange-900">Humana Medicare Advantage</h4>
                                                </div>
                                                <div className="space-y-2">
                                                    <div className="flex items-start gap-2">
                                                        <FiCalendar className="w-4 h-4 text-orange-600 mt-0.5" />
                                                        <p className="text-sm text-orange-800">Progress notes with measurements spanning 30 days (5-6 progress notes depending on date)</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="p-4 bg-gradient-to-r from-teal-50 to-cyan-50 rounded-lg border border-teal-200">
                                                <div className="flex items-center gap-2 mb-3">
                                                    <div className="w-8 h-8 bg-gradient-to-r from-teal-500 to-cyan-500 rounded-full flex items-center justify-center">
                                                        <span className="text-white font-bold text-sm">H</span>
                                                    </div>
                                                    <h4 className="font-bold text-teal-900">Humana Commercial & MCA</h4>
                                                </div>
                                                <div className="space-y-2">
                                                    <div className="flex items-start gap-2">
                                                        <FiDroplet className="w-4 h-4 text-teal-600 mt-0.5" />
                                                        <p className="text-sm text-teal-800">Albumin/pre-albumin lab result required</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Professional Disclaimers */}
                                        <div className="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                            <div className="flex items-start gap-3">
                                                <FiAlertCircle className="w-5 h-5 text-yellow-600 mt-0.5 flex-shrink-0" />
                                                <div>
                                                    <h5 className="font-semibold text-yellow-900 mb-2">Important Disclaimer</h5>
                                                    <p className="text-sm text-yellow-800 mb-2">
                                                        Requirements may vary by payer, plan, and region. These are general guidelines based on common payer requirements.
                                                    </p>
                                                    <p className="text-sm text-yellow-800 font-medium">
                                                        Always consult with your billing team and verify current requirements with specific payers before submission.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </motion.div>

                    {/* MAC Contractor News */}
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.8, delay: 0.35 }}
                        className="mb-16"
                    >
                        <div className="text-center mb-12">
                            <h2 className="text-3xl font-bold text-gray-900 mb-4">
                                MAC Contractor Updates
                            </h2>
                            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
                                Stay informed with the latest news and updates from Medicare Administrative Contractors.
                            </p>
                        </div>

                        <div className="max-w-6xl mx-auto">
                            <div className="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {Object.values(macContractors).map((contractor, index) => (
                                        <motion.div
                                            key={contractor.name}
                                            initial={{ opacity: 0, y: 20 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            transition={{ duration: 0.5, delay: index * 0.1 }}
                                            className="group"
                                        >
                                            <div className="bg-gray-50 rounded-xl p-6 hover:bg-gray-100 transition-all duration-300 border border-gray-200">
                                                <div className="flex items-center justify-between mb-4">
                                                    <div className={`w-10 h-10 rounded-lg bg-gradient-to-r ${contractor.color} flex items-center justify-center`}>
                                                        <FiGlobe className="w-5 h-5 text-white" />
                                                    </div>
                                                    <span className="text-xs font-medium text-gray-500">{contractor.jurisdiction}</span>
                                                </div>
                                                
                                                <h3 className="text-lg font-bold text-gray-900 mb-2 group-hover:text-blue-600 transition-colors">
                                                    {contractor.name}
                                                </h3>
                                                
                                                <p className="text-sm text-gray-600 mb-4">
                                                    {contractor.region}
                                                </p>
                                                
                                                <div className="space-y-2">
                                                    <a
                                                        href={`${contractor.website}/news`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center text-sm text-blue-600 hover:text-blue-800 font-medium group-hover:translate-x-1 transition-transform"
                                                    >
                                                        <FiTrendingUp className="w-4 h-4 mr-2" />
                                                        Latest Updates
                                                        <FiExternalLink className="w-3 h-3 ml-1" />
                                                    </a>
                                                    <br />
                                                    <a
                                                        href={contractor.website}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center text-sm text-gray-600 hover:text-gray-800 font-medium group-hover:translate-x-1 transition-transform"
                                                    >
                                                        <FiGlobe className="w-4 h-4 mr-2" />
                                                        Visit Website
                                                        <FiExternalLink className="w-3 h-3 ml-1" />
                                                    </a>
                                                </div>
                                            </div>
                                        </motion.div>
                                    ))}
                                </div>
                                
                                <div className="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div className="flex items-start gap-3">
                                        <FiInfo className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                                        <div>
                                            <h5 className="font-semibold text-blue-900 mb-2">Stay Updated</h5>
                                            <p className="text-sm text-blue-800">
                                                MAC contractors regularly publish updates on coverage policies, billing guidelines, and system changes. 
                                                Subscribe to their newsletters and check their websites regularly for the latest information.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </motion.div>

                    {/* Resource Categories Grid */}
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.8, delay: 0.4 }}
                        className="mb-16"
                    >
                        <div className="text-center mb-12">
                            <h2 className="text-3xl font-bold text-gray-900 mb-4">
                                Resource Categories
                            </h2>
                            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
                                Browse our comprehensive collection of billing, coding, and compliance resources 
                                organized by category for easy access.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {resourceCategories.map((category, index) => (
                                <motion.div
                                    key={category.id}
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.5, delay: index * 0.1 }}
                                    className="group cursor-pointer"
                                    onClick={() => setActiveCategory(category.id)}
                                >
                                    <div className="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 h-full border border-gray-100 hover:border-blue-200">
                                        <div className={`w-12 h-12 rounded-lg bg-gradient-to-r ${category.color} flex items-center justify-center mb-4 group-hover:scale-110 transition-transform`}>
                                            <category.icon className="w-6 h-6 text-white" />
                                        </div>
                                        
                                        <h3 className="text-xl font-bold text-gray-900 mb-2 group-hover:text-blue-600 transition-colors">
                                            {category.title}
                                        </h3>
                                        
                                        <p className="text-gray-600 mb-4 flex-1">
                                            {category.description}
                                        </p>
                                        
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium text-blue-600">
                                                {category.count} Resources
                                            </span>
                                            <FiExternalLink className="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-colors" />
                                        </div>
                                    </div>
                                </motion.div>
                            ))}
                        </div>
                    </motion.div>

                    {/* Featured Resources */}
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.8, delay: 0.6 }}
                        className="mb-16"
                    >
                        <div className="text-center mb-12">
                            <h2 className="text-3xl font-bold text-gray-900 mb-4">
                                Featured Resources
                            </h2>
                            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
                                Quick access to the most popular and essential billing and coding resources.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            {featuredResources.map((resource, index) => (
                                <motion.div
                                    key={resource.id}
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.5, delay: index * 0.1 }}
                                    className="group"
                                >
                                    <div className="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 h-full border border-gray-100 hover:border-blue-200">
                                        <div className="flex items-center justify-between mb-4">
                                            <div className="w-10 h-10 rounded-lg bg-gradient-to-r from-blue-500 to-indigo-600 flex items-center justify-center">
                                                <resource.icon className="w-5 h-5 text-white" />
                                            </div>
                                            {resource.isExternal && (
                                                <FiExternalLink className="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-colors" />
                                            )}
                                        </div>
                                        
                                        <h3 className="text-lg font-bold text-gray-900 mb-2 group-hover:text-blue-600 transition-colors">
                                            {resource.title}
                                        </h3>
                                        
                                        <p className="text-gray-600 mb-4 text-sm">
                                            {resource.description}
                                        </p>
                                        
                                        <div className="flex flex-wrap gap-1 mb-4">
                                            {resource.tags.slice(0, 2).map(tag => (
                                                <span 
                                                    key={tag}
                                                    className="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full"
                                                >
                                                    {tag}
                                                </span>
                                            ))}
                                        </div>
                                        
                                        {resource.id === '5' ? (
                                            <button
                                                onClick={() => setIsCms1500ModalOpen(true)}
                                                className="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium text-sm group-hover:translate-x-1 transition-transform"
                                            >
                                                View Guide
                                                <FiEye className="w-4 h-4 ml-1" />
                                            </button>
                                        ) : (
                                            <a
                                                href={resource.url}
                                                target={resource.isExternal ? "_blank" : "_self"}
                                                rel={resource.isExternal ? "noopener noreferrer" : ""}
                                                className="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium text-sm group-hover:translate-x-1 transition-transform"
                                            >
                                                Access Resource
                                                <FiExternalLink className="w-4 h-4 ml-1" />
                                            </a>
                                        )}
                                    </div>
                                </motion.div>
                            ))}
                        </div>
                    </motion.div>

                    {/* Quick Links Section */}
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.8, delay: 0.8 }}
                        className="mb-16"
                    >
                        <div className="bg-gradient-to-r from-gray-900 to-gray-800 rounded-2xl p-8 text-white">
                            <div className="text-center mb-8">
                                <h2 className="text-3xl font-bold mb-4">
                                    Quick Links & Tools
                                </h2>
                                <p className="text-gray-300 max-w-2xl mx-auto">
                                    Essential links and tools for efficient billing and coding workflows.
                                </p>
                            </div>
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <a
                                    href="https://www.cms.gov/medicare-coverage-database"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="group bg-white bg-opacity-10 rounded-lg p-4 hover:bg-opacity-20 transition-all duration-300"
                                >
                                    <FiDatabase className="w-8 h-8 text-blue-400 mb-3" />
                                    <h3 className="font-semibold mb-2">CMS Coverage Database</h3>
                                    <p className="text-sm text-gray-300">LCD and NCD policies</p>
                                </a>
                                
                                <a
                                    href="https://www.cms.gov/medicare/physician-fee-schedule"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="group bg-white bg-opacity-10 rounded-lg p-4 hover:bg-opacity-20 transition-all duration-300"
                                >
                                    <FiDollarSign className="w-8 h-8 text-green-400 mb-3" />
                                    <h3 className="font-semibold mb-2">Fee Schedule</h3>
                                    <p className="text-sm text-gray-300">Medicare payment rates</p>
                                </a>
                                
                                <a
                                    href="https://www.cms.gov/manuals"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="group bg-white bg-opacity-10 rounded-lg p-4 hover:bg-opacity-20 transition-all duration-300"
                                >
                                    <FiBook className="w-8 h-8 text-purple-400 mb-3" />
                                    <h3 className="font-semibold mb-2">CMS Manuals</h3>
                                    <p className="text-sm text-gray-300">Official guidelines</p>
                                </a>
                                
                                <a
                                    href="https://www.cms.gov/outreach-and-education/medicare-learning-network-mln"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="group bg-white bg-opacity-10 rounded-lg p-4 hover:bg-opacity-20 transition-all duration-300"
                                >
                                    <FiBookOpen className="w-8 h-8 text-yellow-400 mb-3" />
                                    <h3 className="font-semibold mb-2">Medicare Learning Network</h3>
                                    <p className="text-sm text-gray-300">Training resources</p>
                                </a>
                            </div>
                        </div>
                    </motion.div>
                </div>
            </div>

            {/* CMS-1500 Form Modal */}
            <AnimatePresence>
                {isCms1500ModalOpen && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                        onClick={() => setIsCms1500ModalOpen(false)}
                    >
                        <motion.div
                            initial={{ scale: 0.9, opacity: 0 }}
                            animate={{ scale: 1, opacity: 1 }}
                            exit={{ scale: 0.9, opacity: 0 }}
                            className="bg-white rounded-2xl shadow-2xl max-w-6xl max-h-[90vh] overflow-y-auto"
                            onClick={(e) => e.stopPropagation()}
                        >
                            {/* Modal Header */}
                            <div className="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 rounded-t-2xl">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h2 className="text-2xl font-bold text-gray-900">CMS-1500 Form Guide</h2>
                                        <p className="text-gray-600 mt-1">Best practices for skin substitute billing</p>
                                    </div>
                                    <button
                                        onClick={() => setIsCms1500ModalOpen(false)}
                                        className="p-2 hover:bg-gray-100 rounded-full transition-colors"
                                    >
                                        <FiX className="w-6 h-6 text-gray-500" />
                                    </button>
                                </div>
                            </div>

                            {/* Modal Content */}
                            <div className="p-6">
                                {/* CMS-1500 Form Visual Representation */}
                                <div ref={cms1500FormRef} className="relative mb-8">
                                    <div className="bg-white border-2 border-gray-300 rounded-lg p-6 shadow-inner">
                                        {/* Form Header */}
                                        <div className="text-center mb-4 pb-2 border-b border-gray-300">
                                            <h3 className="text-lg font-bold text-gray-900">HEALTH INSURANCE CLAIM FORM</h3>
                                            <p className="text-sm text-gray-600">CMS-1500 (02-12)</p>
                                        </div>

                                        {/* Top Section - Insurance Type */}
                                        <div className="grid grid-cols-12 gap-1 mb-4">
                                            <div className="col-span-12 mb-2">
                                                <div className="relative bg-slate-50 border border-slate-200 rounded p-2 h-12">
                                                    <span className="text-xs font-medium text-slate-900">1. INSURANCE TYPE</span>
                                                    <div className="flex gap-4 mt-1">
                                                        <label className="text-xs text-slate-700">□ Medicare □ Medicaid □ Tricare □ Group Plan</label>
                                                    </div>
                                                    <div className="absolute -top-3 -left-3 w-8 h-8 bg-slate-600 text-white rounded-full flex items-center justify-center text-sm font-bold z-10 shadow-sm">
                                                        1
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Patient Information Section */}
                                        <div className="grid grid-cols-12 gap-1 mb-4">
                                            <div className="col-span-6 space-y-1">
                                                <div className="border border-gray-300 rounded p-2 h-8">
                                                    <span className="text-xs text-gray-600">2. PATIENT'S NAME</span>
                                                </div>
                                                <div className="border border-gray-300 rounded p-2 h-8">
                                                    <span className="text-xs text-gray-600">3. PATIENT'S DATE OF BIRTH</span>
                                                </div>
                                                <div className="border border-gray-300 rounded p-2 h-8">
                                                    <span className="text-xs text-gray-600">4. INSURED'S NAME</span>
                                                </div>
                                            </div>
                                            <div className="col-span-6 space-y-1">
                                                <div className="border border-gray-300 rounded p-2 h-8">
                                                    <span className="text-xs text-gray-600">5. PATIENT'S ADDRESS</span>
                                                </div>
                                                <div className="border border-gray-300 rounded p-2 h-8">
                                                    <span className="text-xs text-gray-600">6. PATIENT'S SEX</span>
                                                </div>
                                                <div className="border border-gray-300 rounded p-2 h-8">
                                                    <span className="text-xs text-gray-600">7. INSURED'S ADDRESS</span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Diagnosis Codes Section */}
                                        <div className="mb-4">
                                            <div className="relative bg-slate-50 border border-slate-200 rounded p-3">
                                                <span className="text-xs font-medium text-slate-900">21. DIAGNOSIS OR NATURE OF ILLNESS OR INJURY</span>
                                                <div className="grid grid-cols-4 gap-2 mt-2">
                                                    <div className="border border-slate-300 rounded p-1 h-6">
                                                        <span className="text-xs text-slate-700">A. L97.xxx</span>
                                                    </div>
                                                    <div className="border border-slate-300 rounded p-1 h-6">
                                                        <span className="text-xs text-slate-700">B. L89.xxx</span>
                                                    </div>
                                                    <div className="border border-slate-300 rounded p-1 h-6">
                                                        <span className="text-xs text-slate-700">C. I83.xxx</span>
                                                    </div>
                                                    <div className="border border-slate-300 rounded p-1 h-6"></div>
                                                </div>
                                                <div className="absolute -top-3 -left-3 w-8 h-8 bg-slate-600 text-white rounded-full flex items-center justify-center text-sm font-bold z-10 shadow-sm">
                                                    2
                                                </div>
                                            </div>
                                        </div>

                                        {/* Service Details Section */}
                                        <div className="border border-gray-300 rounded">
                                            <div className="bg-gray-100 p-2 border-b border-gray-300">
                                                <span className="text-xs font-medium text-gray-900">24. CHARGES</span>
                                            </div>
                                            
                                            {/* Service Line Headers */}
                                            <div className="grid grid-cols-12 text-xs bg-gray-50 p-1 border-b border-gray-300">
                                                <div className="col-span-2 relative">
                                                    <span className="text-gray-700">A. DATE(S) OF SERVICE</span>
                                                    <div className="absolute -top-2 -left-2 w-6 h-6 bg-slate-600 text-white rounded-full flex items-center justify-center text-xs font-bold z-10 shadow-sm">
                                                        3
                                                    </div>
                                                </div>
                                                <div className="col-span-1 text-gray-700">B. PLACE</div>
                                                <div className="col-span-1 text-gray-700">C. EMG</div>
                                                <div className="col-span-3 relative">
                                                    <span className="text-gray-700">D. PROCEDURES/CODES</span>
                                                    <div className="absolute -top-2 -left-2 w-6 h-6 bg-slate-600 text-white rounded-full flex items-center justify-center text-xs font-bold z-10 shadow-sm">
                                                        4
                                                    </div>
                                                </div>
                                                <div className="col-span-1 text-gray-700">E. DIAGNOSIS</div>
                                                <div className="col-span-1 text-gray-700">F. $CHARGES</div>
                                                <div className="col-span-1 relative">
                                                    <span className="text-gray-700">G. UNITS</span>
                                                    <div className="absolute -top-2 -left-2 w-6 h-6 bg-slate-600 text-white rounded-full flex items-center justify-center text-xs font-bold z-10 shadow-sm">
                                                        5
                                                    </div>
                                                </div>
                                                <div className="col-span-2 text-gray-700">H. EPSDT/FAMILY PLAN</div>
                                            </div>

                                            {/* Sample Service Line */}
                                            <div className="grid grid-cols-12 text-xs p-2 border-b border-gray-300 items-center">
                                                <div className="col-span-2 bg-slate-50 border border-slate-200 rounded p-1">
                                                    <span className="text-slate-700">01/15/2025</span>
                                                </div>
                                                <div className="col-span-1 p-1">11</div>
                                                <div className="col-span-1 p-1"></div>
                                                <div className="col-span-3 bg-slate-50 border border-slate-200 rounded p-1">
                                                    <span className="text-slate-700">Q4186, 15271</span>
                                                </div>
                                                <div className="relative col-span-2 bg-slate-50 border border-slate-200 rounded p-1">
                                                    <span className="text-slate-700">JC, KX</span>
                                                    <div className="absolute -top-3 -right-3 w-8 h-8 bg-slate-600 text-white rounded-full flex items-center justify-center text-sm font-bold z-10 shadow-sm">
                                                        5
                                                    </div>
                                                </div>
                                                <div className="relative col-span-1 p-1">
                                                    A
                                                    <div className="absolute -top-3 -right-3 w-8 h-8 bg-slate-600 text-white rounded-full flex items-center justify-center text-sm font-bold z-10 shadow-sm">
                                                        6
                                                    </div>
                                                </div>
                                                <div className="col-span-1 bg-slate-50 border border-slate-200 rounded p-1">
                                                    <span className="text-slate-700">$123.45</span>
                                                </div>
                                                <div className="col-span-1 p-1">1</div>
                                            </div>

                                            {/* Second Sample Service Line for Discarded Portion */}
                                            <div className="grid grid-cols-12 text-xs p-2 border-b border-gray-300 items-center">
                                                <div className="col-span-2 bg-slate-50 border border-slate-200 rounded p-1">
                                                    <span className="text-slate-700">01/15/2025</span>
                                                </div>
                                                <div className="col-span-1 p-1">11</div>
                                                <div className="col-span-1 p-1"></div>
                                                <div className="col-span-3 bg-slate-50 border border-slate-200 rounded p-1">
                                                    <span className="text-slate-700">Q4186</span>
                                                </div>
                                                <div className="relative col-span-2 bg-slate-50 border border-slate-200 rounded p-1">
                                                    <span className="text-slate-700">JD</span>
                                                </div>
                                                <div className="col-span-1 p-1">A</div>
                                                <div className="col-span-1 bg-slate-50 border border-slate-200 rounded p-1">
                                                    <span className="text-slate-700">$20.00</span>
                                                </div>
                                                <div className="col-span-1 p-1">1</div>
                                            </div>
                                        </div>

                                        {/* Bottom Section */}
                                        <div className="grid grid-cols-12 gap-1 mt-4">
                                            <div className="col-span-6 relative">
                                                <div className="border border-gray-300 rounded p-2 h-16">
                                                    <span className="text-xs text-gray-600">31. SIGNATURE OF PHYSICIAN</span>
                                                </div>
                                                <div className="absolute -bottom-3 -right-3 w-8 h-8 bg-slate-600 text-white rounded-full flex items-center justify-center text-sm font-bold z-10 shadow-sm">
                                                    7
                                                </div>
                                            </div>
                                            <div className="col-span-6 relative">
                                                <div className="border border-gray-300 rounded p-2 h-16">
                                                    <span className="text-xs text-gray-600">32. SERVICE FACILITY LOCATION</span>
                                                </div>
                                                <div className="absolute -bottom-3 -right-3 w-8 h-8 bg-slate-600 text-white rounded-full flex items-center justify-center text-sm font-bold z-10 shadow-sm">
                                                    8
                                                </div>
                                            </div>
                                        </div>
                                         <div className="grid grid-cols-12 gap-1 mt-4">
                                            <div className="col-span-12 relative">
                                                <div className="border border-gray-300 rounded p-2 h-16">
                                                    <span className="text-xs text-gray-600">33. BILLING PROVIDER INFO & PH #</span>
                                                </div>
                                                <div className="absolute -bottom-3 -right-3 w-8 h-8 bg-slate-600 text-white rounded-full flex items-center justify-center text-sm font-bold z-10 shadow-sm">
                                                    9
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Best Practices List */}
                                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    {/* Left Column */}
                                    <div className="space-y-6">
                                                                                 <div className="bg-slate-50 border border-slate-200 rounded-lg p-4">
                                            <div className="flex items-start gap-3">
                                                <div className="w-8 h-8 bg-slate-600 text-white rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0">
                                                    1
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold text-slate-900">Box 1: Insurance Type</h4>
                                                    <p className="text-sm text-slate-700 mt-1">
                                                        Check Medicare for CMS billing. Ensure patient eligibility is verified before proceeding.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="bg-slate-50 border border-slate-200 rounded-lg p-4">
                                            <div className="flex items-start gap-3">
                                                <div className="w-8 h-8 bg-slate-600 text-white rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0">
                                                    2
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold text-slate-900">Box 21: Diagnosis Codes</h4>
                                                    <p className="text-sm text-slate-700 mt-1">
                                                        Include primary wound diagnosis (e.g., L97.xxx for diabetic ulcers). Must support medical necessity for skin substitute.
                                                    </p>
                                                    <ul className="text-xs text-slate-600 mt-2 space-y-1">
                                                        <li>• L97.xxx - Diabetic ulcers</li>
                                                        <li>• L89.xxx - Pressure ulcers</li>
                                                        <li>• I83.xxx - Venous ulcers</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="bg-slate-50 border border-slate-200 rounded-lg p-4">
                                            <div className="flex items-start gap-3">
                                                <div className="w-8 h-8 bg-slate-600 text-white rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0">
                                                    3
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold text-slate-900">Box 24A: Service Dates</h4>
                                                    <p className="text-sm text-slate-700 mt-1">
                                                        Enter exact date of skin substitute application. Must match documentation and wound measurement records.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Right Column */}
                                    <div className="space-y-6">
                                        <div className="bg-slate-50 border border-slate-200 rounded-lg p-4">
                                            <div className="flex items-start gap-3">
                                                <div className="w-8 h-8 bg-slate-600 text-white rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0">
                                                    4
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold text-slate-900">Box 24D: CPT/HCPCS Codes</h4>
                                                    <p className="text-sm text-slate-700 mt-1">
                                                        Use correct skin substitute HCPCS codes (e.g., Q4186 for Epifix) and application codes (e.g., 15271).
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="bg-slate-50 border border-slate-200 rounded-lg p-4">
                                            <div className="flex items-start gap-3">
                                                <div className="w-8 h-8 bg-slate-600 text-white rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0">
                                                    5
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold text-slate-900">Box 24D: Modifiers</h4>
                                                    <p className="text-sm text-slate-700 mt-1">
                                                        Append appropriate modifiers. Use <strong>JC</strong> for the portion of the drug administered and <strong>JD</strong> for the discarded portion on a separate line. Use <strong>KX</strong> for medically necessary applications beyond four.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div className="bg-slate-50 border border-slate-200 rounded-lg p-4">
                                            <div className="flex items-start gap-3">
                                                <div className="w-8 h-8 bg-slate-600 text-white rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0">
                                                    6
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold text-slate-900">Box 24E: Diagnosis Pointer</h4>
                                                    <p className="text-sm text-slate-700 mt-1">
                                                        Link the service to the appropriate diagnosis code from Box 21.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="bg-slate-50 border border-slate-200 rounded-lg p-4">
                                            <div className="flex items-start gap-3">
                                                <div className="w-8 h-8 bg-slate-600 text-white rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0">
                                                    7
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold text-slate-900">Box 31: Provider Signature</h4>
                                                    <p className="text-sm text-slate-700 mt-1">
                                                        The signature of the physician or non-physician practitioner is required.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="bg-slate-50 border border-slate-200 rounded-lg p-4">
                                            <div className="flex items-start gap-3">
                                                <div className="w-8 h-8 bg-slate-600 text-white rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0">
                                                    8
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold text-slate-900">Box 32: Service Facility Location</h4>
                                                    <p className="text-sm text-slate-700 mt-1">
                                                        Enter the location where the service was provided.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div className="bg-slate-50 border border-slate-200 rounded-lg p-4">
                                            <div className="flex items-start gap-3">
                                                <div className="w-8 h-8 bg-slate-600 text-white rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0">
                                                    9
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold text-slate-900">Box 33: Billing Provider Info</h4>
                                                    <p className="text-sm text-slate-700 mt-1">
                                                        Enter the billing provider's name, address, and phone number.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Professional Disclaimer */}
                                <div className="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <div className="flex items-start gap-3">
                                        <FiAlertCircle className="w-5 h-5 text-yellow-600 mt-0.5 flex-shrink-0" />
                                        <div>
                                            <h5 className="font-semibold text-yellow-900 mb-2">Professional Guidance Required</h5>
                                            <p className="text-sm text-yellow-800 mb-2">
                                                These are general guidelines based on common billing practices. Requirements may vary by payer, region, and specific product.
                                            </p>
                                            <p className="text-sm text-yellow-800 font-medium">
                                                Always consult with your billing team and verify current requirements with specific payers before submission.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {/* Additional Resources */}
                                <div className="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <a
                                        href="https://www.cms.gov/medicare-coverage-database"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors"
                                    >
                                        <FiDatabase className="w-5 h-5 text-blue-600" />
                                        <div>
                                            <p className="font-medium text-blue-900 text-sm">Coverage Database</p>
                                            <p className="text-xs text-blue-700">LCD/NCD Policies</p>
                                        </div>
                                        <FiExternalLink className="w-4 h-4 text-blue-600" />
                                    </a>

                                    <a
                                        href="https://www.cms.gov/medicare/physician-fee-schedule"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-3 p-3 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors"
                                    >
                                        <FiDollarSign className="w-5 h-5 text-green-600" />
                                        <div>
                                            <p className="font-medium text-green-900 text-sm">Fee Schedule</p>
                                            <p className="text-xs text-green-700">Payment Rates</p>
                                        </div>
                                        <FiExternalLink className="w-4 h-4 text-green-600" />
                                    </a>

                                    <a
                                        href="https://www.cms.gov/manuals"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-3 p-3 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-colors"
                                    >
                                        <FiBook className="w-5 h-5 text-purple-600" />
                                        <div>
                                            <p className="font-medium text-purple-900 text-sm">CMS Manuals</p>
                                            <p className="text-xs text-purple-700">Official Guidelines</p>
                                        </div>
                                        <FiExternalLink className="w-4 h-4 text-purple-600" />
                                    </a>
                                </div>
                            </div>
                        </motion.div>
                    </motion.div>
                )}
            </AnimatePresence>
        </MainLayout>
    );
};

export default ResourcesPage; 