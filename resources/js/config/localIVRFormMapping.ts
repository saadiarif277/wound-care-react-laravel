// Local IVR Form Mapping Configuration
// Maps manufacturer names to their local IVR form files

export interface IVRFormMapping {
  manufacturerName: string;
  formPath: string;
  formType: 'ivr' | 'order';
  displayName: string;
  isActive: boolean;
}

export const localIVRFormMappings: IVRFormMapping[] = [
  {
    manufacturerName: 'ACZ & Associates',
    formPath: '/docs/ivr-forms/ACZ/Updated Q2 IVR ACZ.pdf',
    formType: 'ivr',
    displayName: 'ACZ & Associates IVR Form',
    isActive: true
  },
  {
    manufacturerName: 'MEDLIFE SOLUTIONS',
    formPath: '/docs/ivr-forms/Medlife/AMNIO AMP MedLife IVR-fillable .pdf',
    formType: 'ivr',
    displayName: 'MedLife IVR Form',
    isActive: true
  },
  {
    manufacturerName: 'BIOWOUND SOLUTIONS',
    formPath: '/docs/ivr-forms/BioWound/California-Non-HOPD-IVR-Form.pdf',
    formType: 'ivr',
    displayName: 'BioWound IVR Form',
    isActive: true
  },
  {
    manufacturerName: 'BioWerX',
    formPath: '/docs/ivr-forms/BioWerX/BioWerX Fillable IVR Apr 2024.pdf',
    formType: 'ivr',
    displayName: 'BioWerX IVR Form',
    isActive: true
  },
  {
    manufacturerName: 'Extremity Care LLC',
    formPath: '/docs/ivr-forms/Extremity Care/Q2 CompleteFT IVR.pdf',
    formType: 'ivr',
    displayName: 'Extremity Care CompleteFT IVR',
    isActive: true
  },
  {
    manufacturerName: 'Extremity Care LLC',
    formPath: '/docs/ivr-forms/Extremity Care/Q2 Restorigin  IVR.pdf',
    formType: 'ivr',
    displayName: 'Extremity Care Restorigin IVR',
    isActive: true
  },
  {
    manufacturerName: 'Extremity Care LLC',
    formPath: '/docs/ivr-forms/Extremity Care/Q4 PM Coll-e-Derm IVR.pdf',
    formType: 'ivr',
    displayName: 'Extremity Care Coll-e-Derm IVR',
    isActive: true
  },
  {
    manufacturerName: 'CENTURION THERAPEUTICS',
    formPath: '/docs/ivr-forms/Centurion Therapeutics/AmnioBand MTF Generic Prior Auth Form (7).xls',
    formType: 'ivr',
    displayName: 'Centurion Therapeutics Prior Auth Form',
    isActive: true
  }
];

// Helper function to get IVR form by manufacturer name
export function getIVRFormByManufacturer(manufacturerName: string): IVRFormMapping | null {
  return localIVRFormMappings.find(
    mapping => mapping.manufacturerName === manufacturerName && mapping.isActive
  ) || null;
}

// Helper function to get all forms for a manufacturer
export function getFormsByManufacturer(manufacturerName: string): IVRFormMapping[] {
  return localIVRFormMappings.filter(
    mapping => mapping.manufacturerName === manufacturerName && mapping.isActive
  );
}

// Helper function to get all available manufacturers
export function getAvailableManufacturers(): string[] {
  return Array.from(new Set(
    localIVRFormMappings
      .filter(mapping => mapping.isActive)
      .map(mapping => mapping.manufacturerName)
  ));
} 