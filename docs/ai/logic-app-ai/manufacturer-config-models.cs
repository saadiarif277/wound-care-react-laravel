using System.Collections.Generic;

namespace IVRFormMapper
{
    public class ManufacturerConfig
    {
        public string Id { get; set; }
        public string Name { get; set; }
        public bool SignatureRequired { get; set; }
        public bool HasOrderForm { get; set; }
        public string DocusealTemplateId { get; set; }
        public string OrderFormTemplateId { get; set; }
        public bool SupportsInsuranceUploadInIvr { get; set; }
        public string DurationRequirement { get; set; }
        
        // Field name mappings for Docuseal
        public Dictionary<string, string> DocusealFieldNames { get; set; } = new Dictionary<string, string>();
        
        // Order form field names (if applicable)
        public Dictionary<string, string> OrderFormFieldNames { get; set; } = new Dictionary<string, string>();
        
        // Field configurations
        public Dictionary<string, FieldConfig> Fields { get; set; } = new Dictionary<string, FieldConfig>();
    }

    public class FieldConfig
    {
        public string Source { get; set; }
        public string Computation { get; set; }
        public bool Required { get; set; }
        public string Type { get; set; }
        public string Transform { get; set; }
        public string Validation { get; set; }
    }

    public static class ManufacturerConfigService
    {
        private static readonly Dictionary<string, ManufacturerConfig> _configurations = InitializeConfigurations();

        public static ManufacturerConfig GetConfiguration(string manufacturerId)
        {
            _configurations.TryGetValue(manufacturerId.ToLower(), out var config);
            return config;
        }

        private static Dictionary<string, ManufacturerConfig> InitializeConfigurations()
        {
            var configs = new Dictionary<string, ManufacturerConfig>();

            // ACZ & Associates
            configs["acz-associates"] = new ManufacturerConfig
            {
                Id = "1",
                Name = "ACZ & Associates",
                SignatureRequired = true,
                HasOrderForm = false,
                DurationRequirement = "greater_than_4_weeks",
                DocusealFieldNames = new Dictionary<string, string>
                {
                    ["patient_name"] = "PATIENT NAME",
                    ["patient_dob"] = "PATIENT DOB",
                    ["physician_name"] = "PHYSICIAN NAME",
                    ["physician_npi"] = "NPI",
                    ["facility_name"] = "FACILITY NAME",
                    ["facility_ptan"] = "PTAN",
                    ["insurance_name"] = "INSURANCE NAME",
                    ["policy_number"] = "POLICY NUMBER",
                    ["place_of_service"] = "PLACE OF SERVICE WHERE PATIENT IS BEING SEEN"
                },
                Fields = GetACZFields()
            };

            // Advanced Solution
            configs["advanced-solution"] = new ManufacturerConfig
            {
                Id = "11",
                Name = "ADVANCED SOLUTION",
                SignatureRequired = true,
                HasOrderForm = true,
                DocusealTemplateId = "1199885",
                DocusealFieldNames = GetAdvancedSolutionFieldNames(),
                Fields = GetAdvancedSolutionFields()
            };

            // Advanced Solution Order Form
            configs["advanced-solution-order"] = new ManufacturerConfig
            {
                Id = "12",
                Name = "ADVANCED SOLUTION ORDER FORM",
                SignatureRequired = false,
                HasOrderForm = false,
                DocusealTemplateId = "1299488",
                DocusealFieldNames = GetAdvancedSolutionOrderFieldNames(),
                Fields = GetAdvancedSolutionOrderFields()
            };

            // BioWound Solutions
            configs["biowound-solutions"] = new ManufacturerConfig
            {
                Id = "3",
                Name = "BIOWOUND SOLUTIONS",
                SignatureRequired = true,
                HasOrderForm = true,
                DocusealTemplateId = "1254774",
                OrderFormTemplateId = "1299495",
                DocusealFieldNames = GetBioWoundFieldNames(),
                OrderFormFieldNames = GetBioWoundOrderFieldNames(),
                Fields = GetBioWoundFields()
            };

            // Centurion Therapeutics
            configs["centurion-therapeutics"] = new ManufacturerConfig
            {
                Id = "10",
                Name = "CENTURION THERAPEUTICS",
                SignatureRequired = true,
                HasOrderForm = false,
                DocusealTemplateId = "1233918",
                DocusealFieldNames = GetCenturionFieldNames(),
                Fields = GetCenturionFields()
            };

            // MedLife Solutions
            configs["medlife-solutions"] = new ManufacturerConfig
            {
                Id = "5",
                Name = "MEDLIFE SOLUTIONS",
                SignatureRequired = true,
                HasOrderForm = true,
                SupportsInsuranceUploadInIvr = true,
                DocusealTemplateId = "1233913",
                OrderFormTemplateId = "1234279",
                DocusealFieldNames = GetMedLifeFieldNames(),
                OrderFormFieldNames = GetMedLifeOrderFieldNames(),
                Fields = GetMedLifeFields()
            };

            return configs;
        }

        // Helper methods to populate field configurations
        private static Dictionary<string, FieldConfig> GetACZFields()
        {
            return new Dictionary<string, FieldConfig>
            {
                ["patient_name"] = new FieldConfig
                {
                    Source = "computed",
                    Computation = "patient_first_name + patient_last_name",
                    Required = true,
                    Type = "string"
                },
                ["patient_first_name"] = new FieldConfig
                {
                    Source = "patient_first_name",
                    Required = true,
                    Type = "string"
                },
                ["patient_last_name"] = new FieldConfig
                {
                    Source = "patient_last_name",
                    Required = true,
                    Type = "string"
                },
                ["patient_dob"] = new FieldConfig
                {
                    Source = "patient_dob",
                    Transform = "date:m/d/Y",
                    Required = true,
                    Type = "date"
                },
                ["patient_gender"] = new FieldConfig
                {
                    Source = "patient_gender",
                    Required = false,
                    Type = "string"
                },
                ["patient_phone"] = new FieldConfig
                {
                    Source = "patient_phone",
                    Transform = "phone:US",
                    Required = true,
                    Type = "phone"
                },
                ["patient_email"] = new FieldConfig
                {
                    Source = "patient_email",
                    Required = false,
                    Type = "email"
                },
                ["patient_address"] = new FieldConfig
                {
                    Source = "computed",
                    Computation = "patient_address_line1 + patient_address_line2",
                    Required = true,
                    Type = "string"
                },
                ["patient_city"] = new FieldConfig
                {
                    Source = "patient_city",
                    Required = true,
                    Type = "string"
                },
                ["patient_state"] = new FieldConfig
                {
                    Source = "patient_state",
                    Required = true,
                    Type = "string"
                },
                ["patient_zip"] = new FieldConfig
                {
                    Source = "patient_zip",
                    Required = true,
                    Type = "zip"
                }
                // Add more fields as needed...
            };
        }

        private static Dictionary<string, string> GetAdvancedSolutionFieldNames()
        {
            return new Dictionary<string, string>
            {
                ["sales_rep"] = "Sales Rep",
                ["date_signed"] = "Date Signed",
                ["facility_name"] = "Facility Name",
                ["facility_address"] = "Facility Address",
                ["facility_contact_name"] = "Factility Contact Name",
                ["facility_phone"] = "Facility Phone Number",
                ["facility_fax"] = "Facility Fax Number",
                ["facility_npi"] = "Facility NPI",
                ["facility_tin"] = "Facility TIN",
                ["facility_ptan"] = "Facility PTAN",
                ["mac"] = "MAC",
                ["pos_office"] = "Office",
                ["pos_outpatient_hospital"] = "Outpatient Hospital",
                ["pos_ambulatory_surgical_center"] = "Ambulatory Surgical Center",
                ["pos_other"] = "Other",
                ["pos_other_text"] = "POS Other",
                ["physician_name"] = "Physician Name",
                ["physician_address"] = "Physician Address",
                ["physician_phone"] = "Physician Phone",
                ["physician_fax"] = "Physician Fax",
                ["physician_npi"] = "Physician NPI",
                ["physician_tin"] = "Physician TIN",
                ["patient_name"] = "Patient Name",
                ["patient_address"] = "Patient Address",
                ["patient_dob"] = "Patient DOB",
                ["patient_phone"] = "Patient Phone",
                ["contact_patient_yes"] = "Ok to Contact Patient Yes",
                ["contact_patient_no"] = "OK to Contact Patient No"
                // Add more field mappings...
            };
        }

        private static Dictionary<string, FieldConfig> GetAdvancedSolutionFields()
        {
            return new Dictionary<string, FieldConfig>
            {
                ["name"] = new FieldConfig
                {
                    Source = "contact_name || office_contact_name || sales_rep_name",
                    Required = false,
                    Type = "string"
                },
                ["email"] = new FieldConfig
                {
                    Source = "contact_email || office_contact_email || sales_rep_email",
                    Required = false,
                    Type = "email"
                },
                ["phone"] = new FieldConfig
                {
                    Source = "contact_phone || office_contact_phone || sales_rep_phone",
                    Transform = "phone:US",
                    Required = false,
                    Type = "phone"
                },
                ["sales_rep"] = new FieldConfig
                {
                    Source = "sales_rep_name || sales_rep || distributor_name",
                    Required = false,
                    Type = "string"
                }
                // Add more fields...
            };
        }

        private static Dictionary<string, string> GetAdvancedSolutionOrderFieldNames()
        {
            return new Dictionary<string, string>
            {
                ["name"] = "Name",
                ["email"] = "Email",
                ["phone"] = "Phone",
                ["facility_name"] = "Facility Name",
                ["shipping_contact_name"] = "Shipping Contact Name",
                ["shipping_address"] = "Shipping Address",
                ["phone_number"] = "Phone Number",
                ["fax_number"] = "Fax Number",
                ["email_address"] = "Email Address",
                ["date_of_case"] = "Date of Case",
                ["product_arrival_date_time"] = "Product Arrival Date  Time"
                // Add more mappings...
            };
        }

        private static Dictionary<string, FieldConfig> GetAdvancedSolutionOrderFields()
        {
            return new Dictionary<string, FieldConfig>
            {
                ["name"] = new FieldConfig
                {
                    Source = "contact_name || billing_contact_name || order_contact_name",
                    Required = true,
                    Type = "string"
                }
                // Add more fields...
            };
        }

        private static Dictionary<string, string> GetBioWoundFieldNames()
        {
            return new Dictionary<string, string>
            {
                ["name"] = "Name",
                ["email"] = "Email",
                ["phone"] = "Phone",
                ["territory"] = "Territory",
                ["sales_rep"] = "Sales Rep",
                ["rep_email"] = "Rep Email",
                ["new_request"] = "New Request",
                ["re_verification"] = "Re-Verification",
                ["additional_applications"] = "Additional Applications",
                ["new_insurance"] = "New Insurance"
                // Add more mappings...
            };
        }

        private static Dictionary<string, string> GetBioWoundOrderFieldNames()
        {
            return new Dictionary<string, string>
            {
                ["order_date"] = "DATE",
                ["delivery_date"] = "REQUESTED DELIVERY DATE",
                ["po_number"] = "PO#",
                ["ship_to_name"] = "SHIP TO",
                ["bill_to_name"] = "BILL TO"
                // Add more mappings...
            };
        }

        private static Dictionary<string, FieldConfig> GetBioWoundFields()
        {
            return new Dictionary<string, FieldConfig>
            {
                ["name"] = new FieldConfig
                {
                    Source = "contact_name || sales_contact_name || representative_name",
                    Required = true,
                    Type = "string"
                }
                // Add more fields...
            };
        }

        private static Dictionary<string, string> GetCenturionFieldNames()
        {
            return new Dictionary<string, string>
            {
                ["name"] = "Name",
                ["email"] = "Email",
                ["phone"] = "Phone",
                ["check_new_wound"] = "Check: New Wound",
                ["check_additional_application"] = "chkAdditionalApplication",
                ["check_reverification"] = "Check: Reverification",
                ["check_new_insurance"] = "Check: New Insurance"
                // Add more mappings...
            };
        }

        private static Dictionary<string, FieldConfig> GetCenturionFields()
        {
            return new Dictionary<string, FieldConfig>
            {
                ["name"] = new FieldConfig
                {
                    Source = "contact_name || office_contact_name || sales_rep_name",
                    Required = false,
                    Type = "string"
                }
                // Add more fields...
            };
        }

        private static Dictionary<string, string> GetMedLifeFieldNames()
        {
            return new Dictionary<string, string>
            {
                ["name"] = "Name",
                ["email"] = "Email",
                ["phone"] = "Phone",
                ["distributor_company"] = "Distributor/Company",
                ["physician_name"] = "Physician Name",
                ["physician_ptan"] = "Physician PTAN",
                ["physician_npi"] = "Physician NPI"
                // Add more mappings...
            };
        }

        private static Dictionary<string, string> GetMedLifeOrderFieldNames()
        {
            return new Dictionary<string, string>
            {
                ["name"] = "Name",
                ["email"] = "Email",
                ["phone"] = "Phone",
                ["shipping_2_day"] = "Shipping: 2-Day",
                ["shipping_overnight"] = "Shipping: Overnight",
                ["shipping_pick_up"] = "Shipping: Pick up"
                // Add more mappings...
            };
        }

        private static Dictionary<string, FieldConfig> GetMedLifeFields()
        {
            return new Dictionary<string, FieldConfig>
            {
                ["name"] = new FieldConfig
                {
                    Source = "contact_name || office_contact_name || sales_rep_name",
                    Required = false,
                    Type = "string"
                }
                // Add more fields...
            };
        }
    }
}