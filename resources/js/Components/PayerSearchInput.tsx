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

  // Mock payer data - in a real app, this would come from an API
  const mockPayers: Payer[] = [
    { id: '1', name: 'Blue Cross Blue Shield' },
    { id: '2', name: 'Aetna' },
    { id: '3', name: 'Cigna' },
    { id: '4', name: 'UnitedHealth Group' },
    { id: '5', name: 'Humana' },
    { id: '6', name: 'Kaiser Permanente' },
    { id: '7', name: 'Anthem' },
    { id: '8', name: 'Molina Healthcare' },
    { id: '9', name: 'Centene Corporation' },
    { id: '10', name: 'WellCare Health Plans' }
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
