import React, { useState, useEffect } from 'react';
import { FiSearch, FiX } from 'react-icons/fi';

interface Payer {
  id: string;
  name: string;
}

interface PayerSearchInputProps {
  value: Payer;
  onChange: (payer: Payer) => void;
  placeholder?: string;
  error?: string;
  required?: boolean;
}

const PayerSearchInput: React.FC<PayerSearchInputProps> = ({
  value,
  onChange,
  placeholder = "Search for insurance...",
  error,
  required = false
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState(value.name || '');
  const [suggestions, setSuggestions] = useState<Payer[]>([]);

  // Comprehensive list of major medical insurance providers in the US
  const mockPayers: Payer[] = [
    // Major National Carriers
    { id: '1', name: 'Blue Cross Blue Shield' },
    { id: '2', name: 'Aetna' },
    { id: '3', name: 'Cigna' },
    { id: '4', name: 'UnitedHealthcare' },
    { id: '5', name: 'Humana' },
    { id: '6', name: 'Kaiser Permanente' },
    { id: '7', name: 'Anthem' },
    { id: '8', name: 'Molina Healthcare' },
    { id: '9', name: 'Centene Corporation' },
    { id: '10', name: 'WellCare Health Plans' },

    // Medicare & Medicaid
    { id: '11', name: 'Medicare Part A' },
    { id: '12', name: 'Medicare Part B' },
    { id: '13', name: 'Medicare Part C (Medicare Advantage)' },
    { id: '14', name: 'Medicare Part D' },
    { id: '15', name: 'Medicaid' },
    { id: '16', name: 'CHIP (Children\'s Health Insurance Program)' },

    // Regional Blue Cross Blue Shield Plans
    { id: '17', name: 'Anthem Blue Cross (California)' },
    { id: '18', name: 'Blue Cross Blue Shield of Illinois' },
    { id: '19', name: 'Blue Cross Blue Shield of Texas' },
    { id: '20', name: 'Blue Cross Blue Shield of Michigan' },
    { id: '21', name: 'Blue Cross Blue Shield of North Carolina' },
    { id: '22', name: 'Blue Cross Blue Shield of Massachusetts' },
    { id: '23', name: 'Blue Cross Blue Shield of New York' },
    { id: '24', name: 'Blue Cross Blue Shield of Florida' },
    { id: '25', name: 'Blue Cross Blue Shield of Georgia' },
    { id: '26', name: 'Blue Cross Blue Shield of Pennsylvania' },
    { id: '27', name: 'Blue Cross Blue Shield of Ohio' },
    { id: '28', name: 'Blue Cross Blue Shield of Tennessee' },
    { id: '29', name: 'Blue Cross Blue Shield of Louisiana' },
    { id: '30', name: 'Blue Cross Blue Shield of Alabama' },

    // Major Health Insurance Companies
    { id: '31', name: 'Aetna Better Health' },
    { id: '32', name: 'Aetna Medicare' },
    { id: '33', name: 'Cigna Healthcare' },
    { id: '34', name: 'Cigna Medicare' },
    { id: '35', name: 'UnitedHealthcare Community Plan' },
    { id: '36', name: 'UnitedHealthcare Medicare' },
    { id: '37', name: 'Humana Medicare' },
    { id: '38', name: 'Humana Military (Tricare)' },
    { id: '39', name: 'Kaiser Permanente Medicare' },
    { id: '40', name: 'Anthem Medicare' },

    // Government & Military
    { id: '41', name: 'TRICARE' },
    { id: '42', name: 'VA Health Care (Veterans Affairs)' },
    { id: '43', name: 'Indian Health Service' },
    { id: '44', name: 'Federal Employee Health Benefits (FEHB)' },

    // Regional & State-Specific Plans
    { id: '45', name: 'Kaiser Permanente Northwest' },
    { id: '46', name: 'Kaiser Permanente Southern California' },
    { id: '47', name: 'Kaiser Permanente Northern California' },
    { id: '48', name: 'Health Net' },
    { id: '49', name: 'Molina Healthcare of California' },
    { id: '50', name: 'Molina Healthcare of Texas' },
    { id: '51', name: 'Molina Healthcare of Florida' },
    { id: '52', name: 'Molina Healthcare of New York' },
    { id: '53', name: 'Molina Healthcare of Illinois' },
    { id: '54', name: 'Molina Healthcare of Ohio' },
    { id: '55', name: 'Molina Healthcare of Michigan' },
    { id: '56', name: 'Molina Healthcare of Pennsylvania' },
    { id: '57', name: 'Molina Healthcare of Georgia' },
    { id: '58', name: 'Molina Healthcare of Virginia' },
    { id: '59', name: 'Molina Healthcare of Washington' },
    { id: '60', name: 'Molina Healthcare of Oregon' },

    // Specialized & Niche Plans
    { id: '61', name: 'Ambetter' },
    { id: '62', name: 'Bright Health' },
    { id: '63', name: 'Oscar Health' },
    { id: '64', name: 'Clover Health' },
    { id: '65', name: 'Devoted Health' },
    { id: '66', name: 'Alignment Healthcare' },
    { id: '67', name: 'GoHealth' },
    { id: '68', name: 'eHealth' },
    { id: '69', name: 'HealthMarkets' },
    { id: '70', name: 'SelectHealth' },

    // Employer & Group Plans
    { id: '71', name: 'UnitedHealthcare Choice' },
    { id: '72', name: 'UnitedHealthcare Navigate' },
    { id: '73', name: 'Aetna Open Choice PPO' },
    { id: '74', name: 'Aetna Select' },
    { id: '75', name: 'Cigna Open Access Plus' },
    { id: '76', name: 'Cigna LocalPlus' },
    { id: '77', name: 'Humana Choice POS' },
    { id: '78', name: 'Humana HMO Premier' },
    { id: '79', name: 'Kaiser Permanente HMO' },
    { id: '80', name: 'Anthem PPO' },

    // Additional Regional Carriers
    { id: '81', name: 'Premera Blue Cross' },
    { id: '82', name: 'Regence Blue Cross Blue Shield' },
    { id: '83', name: 'Highmark Blue Cross Blue Shield' },
    { id: '84', name: 'Independence Blue Cross' },
    { id: '85', name: 'Empire Blue Cross Blue Shield' },
    { id: '86', name: 'CareFirst Blue Cross Blue Shield' },
    { id: '87', name: 'Blue Cross Blue Shield of Minnesota' },
    { id: '88', name: 'Blue Cross Blue Shield of Wisconsin' },
    { id: '89', name: 'Blue Cross Blue Shield of Kansas' },
    { id: '90', name: 'Blue Cross Blue Shield of Missouri' },
    { id: '91', name: 'Blue Cross Blue Shield of Oklahoma' },
    { id: '92', name: 'Blue Cross Blue Shield of Arkansas' },
    { id: '93', name: 'Blue Cross Blue Shield of Mississippi' },
    { id: '94', name: 'Blue Cross Blue Shield of South Carolina' },
    { id: '95', name: 'Blue Cross Blue Shield of Virginia' },
    { id: '96', name: 'Blue Cross Blue Shield of West Virginia' },
    { id: '97', name: 'Blue Cross Blue Shield of Kentucky' },
    { id: '98', name: 'Blue Cross Blue Shield of Indiana' },
    { id: '99', name: 'Blue Cross Blue Shield of Iowa' },
    { id: '100', name: 'Blue Cross Blue Shield of Nebraska' },

    // Additional Major Carriers
    { id: '101', name: 'EmblemHealth' },
    { id: '102', name: 'Healthfirst' },
    { id: '103', name: 'Fidelis Care' },
    { id: '104', name: 'Affinity Health Plan' },
    { id: '105', name: 'MetroPlus Health Plan' },
    { id: '106', name: 'VNS Choice' },
    { id: '107', name: 'WellCare of New York' },
    { id: '108', name: 'WellCare of Florida' },
    { id: '109', name: 'WellCare of Texas' },
    { id: '110', name: 'WellCare of Georgia' },

    // Medicare Advantage Specialists
    { id: '111', name: 'AARP Medicare Advantage' },
    { id: '112', name: 'Aetna Medicare Advantage' },
    { id: '113', name: 'Cigna Medicare Advantage' },
    { id: '114', name: 'Humana Medicare Advantage' },
    { id: '115', name: 'Kaiser Permanente Medicare Advantage' },
    { id: '116', name: 'UnitedHealthcare Medicare Advantage' },
    { id: '117', name: 'Anthem Medicare Advantage' },
    { id: '118', name: 'Molina Medicare Advantage' },
    { id: '119', name: 'WellCare Medicare Advantage' },
    { id: '120', name: 'Bright Health Medicare Advantage' }
  ];

  useEffect(() => {
    if (searchTerm.length > 0) {
      const filtered = mockPayers.filter(payer =>
        payer.name.toLowerCase().includes(searchTerm.toLowerCase())
      );
      setSuggestions(filtered);
    } else {
      setSuggestions([]);
    }
  }, [searchTerm]);

  const handleSelect = (payer: Payer) => {
    onChange(payer);
    setSearchTerm(payer.name);
    setIsOpen(false);
  };

  const handleClear = () => {
    onChange({ id: '', name: '' });
    setSearchTerm('');
    setIsOpen(false);
  };

  return (
    <div className="relative">
      <div className="relative">
        <input
          type="text"
          value={searchTerm}
          onChange={(e) => {
            setSearchTerm(e.target.value);
            setIsOpen(true);
          }}
          onFocus={() => setIsOpen(true)}
          placeholder={placeholder}
          required={required}
          className={`w-full p-2 pr-8 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500 ${
            error ? 'border-red-500' : 'border-gray-300 dark:border-gray-700'
          } bg-white dark:bg-gray-800 text-gray-900 dark:text-white`}
        />
        <div className="absolute inset-y-0 right-0 flex items-center pr-2">
          {value.name ? (
            <button
              type="button"
              onClick={handleClear}
              className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            >
              <FiX className="w-4 h-4" />
            </button>
          ) : (
            <FiSearch className="w-4 h-4 text-gray-400" />
          )}
        </div>
      </div>

      {isOpen && suggestions.length > 0 && (
        <div className="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto">
          {suggestions.map((payer) => (
            <button
              key={payer.id}
              type="button"
              onClick={() => handleSelect(payer)}
              className="w-full px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700 focus:bg-gray-100 dark:focus:bg-gray-700 focus:outline-none"
            >
              {payer.name}
            </button>
          ))}
        </div>
      )}

      {error && (
        <p className="mt-1 text-sm text-red-500">{error}</p>
      )}
    </div>
  );
};

export default PayerSearchInput;
