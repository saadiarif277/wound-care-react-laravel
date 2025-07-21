# Manufacturer Config Migration Guide

## Overview
This guide explains how to migrate manufacturer configurations from the old format to the new declarative V2 format.

## Key Changes

### Old Format (❌)
```php
return [
    'name' => 'ACZ & Associates',
    'fields' => [
        'patient_name' => [
            'source' => 'patient_first_name + patient_last_name',
            'required' => true,
        ],
        // Simple mappings only
    ],
    'docuseal_field_names' => [
        'patient_name' => 'Patient Name',
    ]
];
```

### New V2 Format (✅)
```php
return [
    'version' => '2.0',
    'name' => 'ACZ & Associates',
    
    // Field mappings with full capabilities
    'field_mappings' => [
        'patient_name' => [
            'sources' => ['patient_first_name', 'patient_last_name'],
            'type' => 'string',
            'required' => true,
            'docuseal_field' => 'Patient Name',
            'transformations' => [
                ['type' => 'concat', 'separator' => ' '],
                ['type' => 'trim']
            ],
            'validations' => [
                ['type' => 'required'],
                ['type' => 'min_length', 'value' => 2]
            ]
        ],
    ],
    
    // Business rules
    'business_rules' => [
        'wound_duration_check' => [
            'condition' => 'wound_duration_weeks > 4',
            'actions' => [
                ['type' => 'set_field', 'field' => 'meets_duration_requirement', 'value' => true]
            ],
            'else_actions' => [
                ['type' => 'add_warning', 'message' => 'Wound duration does not meet requirement']
            ]
        ]
    ],
    
    // Field groups for organization
    'field_groups' => [
        'patient_info' => [
            'label' => 'Patient Information',
            'fields' => ['patient_name', 'patient_dob', 'patient_gender']
        ]
    ],
    
    // Metadata
    'metadata' => [
        'template_version' => '1.0',
        'last_updated' => '2025-01-01',
        'author' => 'System'
    ]
];
```

## Migration Steps

### 1. Update Version
Add version at the top:
```php
'version' => '2.0',
```

### 2. Convert Field Mappings

#### Simple Field
**Old:**
```php
'fields' => [
    'patient_phone' => [
        'source' => 'patient_phone',
        'required' => true,
        'type' => 'phone'
    ]
]
```

**New:**
```php
'field_mappings' => [
    'patient_phone' => [
        'sources' => ['patient_phone'],
        'type' => 'phone',
        'required' => true,
        'docuseal_field' => 'Patient Phone',
        'transformations' => [
            ['type' => 'phone', 'format' => 'US']
        ],
        'validations' => [
            ['type' => 'required'],
            ['type' => 'phone', 'format' => 'US']
        ]
    ]
]
```

#### Computed Field
**Old:**
```php
'patient_name' => [
    'source' => 'patient_first_name + patient_last_name',
    'required' => true
]
```

**New:**
```php
'patient_name' => [
    'sources' => ['patient_first_name', 'patient_last_name'],
    'type' => 'string',
    'required' => true,
    'docuseal_field' => 'Patient Name',
    'transformations' => [
        ['type' => 'concat', 'separator' => ' '],
        ['type' => 'trim']
    ]
]
```

#### Conditional Field
**Old:**
```php
'insurance_type' => [
    'source' => 'computed',
    'computation' => 'primary_plan_type == "Medicare" ? "Medicare" : "Other"'
]
```

**New:**
```php
'insurance_type' => [
    'sources' => ['primary_plan_type'],
    'type' => 'string',
    'docuseal_field' => 'Insurance Type',
    'transformations' => [
        [
            'type' => 'conditional',
            'conditions' => [
                ['if' => 'equals:Medicare', 'then' => 'Medicare'],
                ['else' => 'Other']
            ]
        ]
    ]
]
```

### 3. Add Business Rules

Convert any business logic from code to declarative rules:

```php
'business_rules' => [
    'medicare_validation' => [
        'condition' => 'primary_insurance_name == "Medicare"',
        'actions' => [
            ['type' => 'validate_field', 'field' => 'patient_medicare_number', 'validation' => 'required']
        ]
    ]
]
```

### 4. Add Field Groups

Organize fields for better UI presentation:

```php
'field_groups' => [
    'patient_info' => [
        'label' => 'Patient Information',
        'fields' => ['patient_name', 'patient_dob', 'patient_gender', 'patient_phone']
    ],
    'insurance_info' => [
        'label' => 'Insurance Information',
        'fields' => ['primary_insurance_name', 'primary_member_id', 'insurance_type']
    ]
]
```

### 5. Common Transformations

#### Date Formatting
```php
'transformations' => [
    ['type' => 'date', 'from' => 'Y-m-d', 'to' => 'm/d/Y']
]
```

#### Phone Formatting
```php
'transformations' => [
    ['type' => 'phone', 'format' => 'US'] // (XXX) XXX-XXXX
]
```

#### Boolean to Checkbox
```php
'transformations' => [
    ['type' => 'boolean', 'format' => 'checkbox'] // "true"/"false"
]
```

#### Address Formatting
```php
'transformations' => [
    ['type' => 'template', 'template' => '{address_line1}, {city}, {state} {zip}']
]
```

### 6. Validation Rules

#### Required Field
```php
'validations' => [
    ['type' => 'required']
]
```

#### Pattern Validation
```php
'validations' => [
    ['type' => 'pattern', 'pattern' => '/^\d{10}$/', 'message' => 'Must be 10 digits']
]
```

#### Conditional Required
```php
'validations' => [
    ['type' => 'required_if', 'field' => 'has_insurance', 'value' => true]
]
```

## Testing Your Migration

1. **Validate Config Structure**
   ```bash
   php artisan config:validate manufacturers/your-manufacturer-v2.php
   ```

2. **Test Field Mapping**
   ```bash
   php artisan field-mapping:test your-manufacturer
   ```

3. **Compare Output**
   - Map sample data with old config
   - Map same data with new config
   - Ensure outputs match

## Benefits of V2 Format

1. **Declarative**: All logic in config, not code
2. **Flexible**: Supports complex transformations
3. **Validated**: Built-in validation rules
4. **Documented**: Self-documenting structure
5. **Extensible**: Easy to add new features
6. **Testable**: Can unit test configs 