// src/contexts/EligibilityContext.tsx
import React, { createContext, useContext, ReactNode } from 'react';

interface Verification {
  id: string;
  customerId: string;
  requestDate?: string;
  patientInfo: {
    firstName: string;
    lastName: string;
  };
  insuranceInfo: {
    payerId: string;
  };
}

interface VerificationResult {
  id: string;
  status: 'active' | 'inactive' | 'pending' | 'error';
}

interface EligibilityContextType {
  verifications: Verification[];
  results: Record<string, VerificationResult>;
}

const EligibilityContext = createContext<EligibilityContextType | undefined>(undefined);

export const useEligibility = () => {
  const context = useContext(EligibilityContext);
  if (!context) {
    throw new Error('useEligibility must be used within an EligibilityProvider');
  }
  return context;
};

export const EligibilityProvider: React.FC<{children: ReactNode}> = ({ children }) => {
  // Dummy data
  const verifications: Verification[] = [
    {
      id: 'ver-001',
      customerId: 'customer-001',
      requestDate: '2023-06-15',
      patientInfo: {
        firstName: 'John',
        lastName: 'Doe'
      },
      insuranceInfo: {
        payerId: 'AETNA-123'
      }
    },
    {
      id: 'ver-002',
      customerId: 'customer-002',
      requestDate: '2023-06-14',
      patientInfo: {
        firstName: 'Jane',
        lastName: 'Smith'
      },
      insuranceInfo: {
        payerId: 'BCBS-456'
      }
    },
    {
      id: 'ver-003',
      customerId: 'customer-003',
      requestDate: '2023-06-13',
      patientInfo: {
        firstName: 'Robert',
        lastName: 'Johnson'
      },
      insuranceInfo: {
        payerId: 'MEDICARE-789'
      }
    },
    {
      id: 'ver-004',
      customerId: 'customer-001',
      requestDate: '2023-06-12',
      patientInfo: {
        firstName: 'Sarah',
        lastName: 'Williams'
      },
      insuranceInfo: {
        payerId: 'UNITED-101'
      }
    },
    {
      id: 'ver-005',
      customerId: 'customer-002',
      requestDate: '2023-06-11',
      patientInfo: {
        firstName: 'Michael',
        lastName: 'Brown'
      },
      insuranceInfo: {
        payerId: 'CIGNA-202'
      }
    }
  ];

  const results: Record<string, VerificationResult> = {
    'ver-001': { id: 'ver-001', status: 'active' },
    'ver-002': { id: 'ver-002', status: 'inactive' },
    'ver-003': { id: 'ver-003', status: 'pending' },
    'ver-004': { id: 'ver-004', status: 'error' },
    'ver-005': { id: 'ver-005', status: 'active' }
  };

  return (
    <EligibilityContext.Provider value={{ verifications, results }}>
      {children}
    </EligibilityContext.Provider>
  );
};
