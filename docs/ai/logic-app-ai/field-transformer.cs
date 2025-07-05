using System;
using System.Globalization;
using System.Text.RegularExpressions;

namespace IVRFormMapper
{
    public class FieldTransformer
    {
        public object Transform(object value, string transformationType)
        {
            if (value == null) return null;

            var parts = transformationType.Split(':');
            var transformType = parts[0];
            var transformParam = parts.Length > 1 ? parts[1] : null;

            switch (transformType.ToLower())
            {
                case "date":
                    return TransformDate(value, transformParam);
                
                case "datetime":
                    return TransformDateTime(value, transformParam);
                
                case "phone":
                    return TransformPhone(value, transformParam);
                
                case "boolean":
                    return TransformBoolean(value, transformParam);
                
                case "number":
                    return TransformNumber(value, transformParam);
                
                case "currency":
                    return TransformCurrency(value);
                
                case "tax_id":
                    return TransformTaxId(value);
                
                case "equals":
                    return value.ToString().Equals(transformParam, StringComparison.OrdinalIgnoreCase);
                
                case "not_in":
                    var notInValues = transformParam?.Split(',') ?? new string[0];
                    return !Array.Exists(notInValues, v => v.Trim().Equals(value.ToString(), StringComparison.OrdinalIgnoreCase));
                
                default:
                    return value;
            }
        }

        private object TransformDate(object value, string format)
        {
            format = format ?? "MM/dd/yyyy";
            
            if (value is DateTime dateTime)
            {
                return dateTime.ToString(format);
            }
            else if (DateTime.TryParse(value.ToString(), out DateTime parsedDate))
            {
                return parsedDate.ToString(format);
            }
            
            return value;
        }

        private object TransformDateTime(object value, string format)
        {
            format = format ?? "MM/dd/yyyy hh:mm tt";
            
            if (value is DateTime dateTime)
            {
                return dateTime.ToString(format);
            }
            else if (DateTime.TryParse(value.ToString(), out DateTime parsedDate))
            {
                return parsedDate.ToString(format);
            }
            
            return value;
        }

        private object TransformPhone(object value, string country)
        {
            var phoneNumber = value.ToString();
            
            // Remove all non-digit characters
            var digits = Regex.Replace(phoneNumber, @"\D", "");
            
            if (country == "US")
            {
                // Remove leading 1 if present for US numbers
                if (digits.Length == 11 && digits.StartsWith("1"))
                {
                    digits = digits.Substring(1);
                }
                
                // Format as (XXX) XXX-XXXX
                if (digits.Length == 10)
                {
                    return $"({digits.Substring(0, 3)}) {digits.Substring(3, 3)}-{digits.Substring(6, 4)}";
                }
            }
            
            return phoneNumber;
        }

        private object TransformBoolean(object value, string format)
        {
            bool boolValue;
            
            if (value is bool b)
            {
                boolValue = b;
            }
            else if (bool.TryParse(value.ToString(), out bool parsed))
            {
                boolValue = parsed;
            }
            else
            {
                // Handle various string representations
                var strValue = value.ToString().ToLower();
                boolValue = strValue == "yes" || strValue == "true" || strValue == "1" || strValue == "on";
            }

            switch (format?.ToLower())
            {
                case "yes_no":
                    return boolValue ? "Yes" : "No";
                
                case "checkbox":
                    return boolValue;
                
                case "on_off":
                    return boolValue ? "On" : "Off";
                
                default:
                    return boolValue;
            }
        }

        private object TransformNumber(object value, string precision)
        {
            if (decimal.TryParse(value.ToString(), out decimal number))
            {
                if (int.TryParse(precision, out int decimalPlaces))
                {
                    return Math.Round(number, decimalPlaces);
                }
                return number;
            }
            
            return value;
        }

        private object TransformCurrency(object value)
        {
            if (decimal.TryParse(value.ToString(), out decimal amount))
            {
                return amount.ToString("C", CultureInfo.GetCultureInfo("en-US"));
            }
            
            return value;
        }

        private object TransformTaxId(object value)
        {
            var taxId = value.ToString();
            
            // Remove all non-alphanumeric characters
            var cleaned = Regex.Replace(taxId, @"[^\w]", "");
            
            // Format as XX-XXXXXXX for US EIN
            if (cleaned.Length == 9 && Regex.IsMatch(cleaned, @"^\d{9}$"))
            {
                return $"{cleaned.Substring(0, 2)}-{cleaned.Substring(2)}";
            }
            
            return cleaned;
        }
    }
}