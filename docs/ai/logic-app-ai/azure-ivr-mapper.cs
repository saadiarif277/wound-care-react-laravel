using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.RegularExpressions;
using System.Threading.Tasks;
using Microsoft.AspNetCore.Mvc;
using Microsoft.Azure.WebJobs;
using Microsoft.Azure.WebJobs.Extensions.Http;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.Logging;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;

namespace IVRFormMapper
{
    public static class IVRFormMapperFunction
    {
        [FunctionName("MapFormToIVR")]
        public static async Task<IActionResult> Run(
            [HttpTrigger(AuthorizationLevel.Function, "post", Route = null)] HttpRequest req,
            ILogger log)
        {
            log.LogInformation("IVR Form Mapper function processing request.");

            try
            {
                string requestBody = await new StreamReader(req.Body).ReadToEndAsync();
                var request = JsonConvert.DeserializeObject<FormMappingRequest>(requestBody);

                if (request == null || string.IsNullOrEmpty(request.ManufacturerId))
                {
                    return new BadRequestObjectResult(new { error = "Invalid request. ManufacturerId is required." });
                }

                // Get manufacturer configuration
                var manufacturerConfig = ManufacturerConfigService.GetConfiguration(request.ManufacturerId);
                if (manufacturerConfig == null)
                {
                    return new BadRequestObjectResult(new { error = $"Unknown manufacturer: {request.ManufacturerId}" });
                }

                // Map the form data
                var mapper = new FormMapper(manufacturerConfig, log);
                var mappedData = mapper.MapFormData(request.FormData);

                // Validate required fields
                var validationResult = mapper.ValidateRequiredFields(mappedData);
                if (!validationResult.IsValid)
                {
                    return new BadRequestObjectResult(new 
                    { 
                        error = "Validation failed",
                        missingFields = validationResult.MissingFields,
                        invalidFields = validationResult.InvalidFields
                    });
                }

                return new OkObjectResult(new FormMappingResponse
                {
                    ManufacturerId = request.ManufacturerId,
                    ManufacturerName = manufacturerConfig.Name,
                    DocusealTemplateId = manufacturerConfig.DocusealTemplateId,
                    MappedFields = mappedData,
                    IsOrderForm = request.IsOrderForm,
                    OrderFormTemplateId = request.IsOrderForm ? manufacturerConfig.OrderFormTemplateId : null
                });
            }
            catch (Exception ex)
            {
                log.LogError(ex, "Error processing form mapping request");
                return new StatusCodeResult(StatusCodes.Status500InternalServerError);
            }
        }
    }

    public class FormMappingRequest
    {
        public string ManufacturerId { get; set; }
        public bool IsOrderForm { get; set; }
        public Dictionary<string, object> FormData { get; set; }
    }

    public class FormMappingResponse
    {
        public string ManufacturerId { get; set; }
        public string ManufacturerName { get; set; }
        public string DocusealTemplateId { get; set; }
        public string OrderFormTemplateId { get; set; }
        public bool IsOrderForm { get; set; }
        public Dictionary<string, object> MappedFields { get; set; }
    }

    public class ValidationResult
    {
        public bool IsValid { get; set; }
        public List<string> MissingFields { get; set; } = new List<string>();
        public List<string> InvalidFields { get; set; } = new List<string>();
    }

    public class FormMapper
    {
        private readonly ManufacturerConfig _config;
        private readonly ILogger _logger;
        private readonly FieldTransformer _transformer;

        public FormMapper(ManufacturerConfig config, ILogger logger)
        {
            _config = config;
            _logger = logger;
            _transformer = new FieldTransformer();
        }

        public Dictionary<string, object> MapFormData(Dictionary<string, object> sourceData)
        {
            var mappedData = new Dictionary<string, object>();

            foreach (var fieldConfig in _config.Fields)
            {
                try
                {
                    var fieldName = fieldConfig.Key;
                    var fieldSpec = fieldConfig.Value;
                    
                    object value = null;

                    if (fieldSpec.Source == "computed")
                    {
                        value = ComputeFieldValue(fieldSpec.Computation, sourceData);
                    }
                    else
                    {
                        value = ExtractFieldValue(fieldSpec.Source, sourceData);
                    }

                    // Apply transformation if specified
                    if (!string.IsNullOrEmpty(fieldSpec.Transform) && value != null)
                    {
                        value = _transformer.Transform(value, fieldSpec.Transform);
                    }

                    // Map to Docuseal field name if available
                    var docusealFieldName = _config.DocusealFieldNames.ContainsKey(fieldName) 
                        ? _config.DocusealFieldNames[fieldName] 
                        : fieldName;

                    if (value != null || fieldSpec.Required)
                    {
                        mappedData[docusealFieldName] = value;
                    }
                }
                catch (Exception ex)
                {
                    _logger.LogError(ex, $"Error mapping field: {fieldConfig.Key}");
                }
            }

            return mappedData;
        }

        public ValidationResult ValidateRequiredFields(Dictionary<string, object> mappedData)
        {
            var result = new ValidationResult { IsValid = true };

            foreach (var field in _config.Fields.Where(f => f.Value.Required))
            {
                var docusealFieldName = _config.DocusealFieldNames.ContainsKey(field.Key) 
                    ? _config.DocusealFieldNames[field.Key] 
                    : field.Key;

                if (!mappedData.ContainsKey(docusealFieldName) || 
                    mappedData[docusealFieldName] == null || 
                    string.IsNullOrWhiteSpace(mappedData[docusealFieldName]?.ToString()))
                {
                    result.IsValid = false;
                    result.MissingFields.Add(field.Key);
                }
                else if (!string.IsNullOrEmpty(field.Value.Validation))
                {
                    if (!ValidateFieldValue(mappedData[docusealFieldName], field.Value.Validation))
                    {
                        result.IsValid = false;
                        result.InvalidFields.Add($"{field.Key} (invalid {field.Value.Validation})");
                    }
                }
            }

            return result;
        }

        private object ExtractFieldValue(string source, Dictionary<string, object> sourceData)
        {
            // Handle OR operator for fallback sources
            var sources = source.Split(new[] { " || " }, StringSplitOptions.RemoveEmptyEntries);
            
            foreach (var src in sources)
            {
                var trimmedSrc = src.Trim();
                
                // Handle nested properties (e.g., patient.address)
                if (trimmedSrc.Contains("."))
                {
                    var value = GetNestedValue(sourceData, trimmedSrc);
                    if (value != null) return value;
                }
                // Handle array access (e.g., icd10_codes[0])
                else if (trimmedSrc.Contains("[") && trimmedSrc.Contains("]"))
                {
                    var value = GetArrayValue(sourceData, trimmedSrc);
                    if (value != null) return value;
                }
                // Simple property access
                else if (sourceData.ContainsKey(trimmedSrc))
                {
                    var value = sourceData[trimmedSrc];
                    if (value != null && !string.IsNullOrWhiteSpace(value.ToString()))
                        return value;
                }
            }

            return null;
        }

        private object ComputeFieldValue(string computation, Dictionary<string, object> sourceData)
        {
            // Handle special computation functions
            if (computation.StartsWith("format_duration"))
            {
                return FormatDuration(sourceData);
            }
            else if (computation.StartsWith("now()"))
            {
                return DateTime.UtcNow;
            }
            else if (computation.StartsWith("addDays"))
            {
                var match = Regex.Match(computation, @"addDays\(now\(\),\s*(\d+)\)");
                if (match.Success && int.TryParse(match.Groups[1].Value, out int days))
                {
                    return DateTime.UtcNow.AddDays(days);
                }
            }
            else if (computation.Contains("+") || computation.Contains("*") || computation.Contains("=="))
            {
                return EvaluateExpression(computation, sourceData);
            }

            return null;
        }

        private object GetNestedValue(Dictionary<string, object> data, string path)
        {
            var parts = path.Split('.');
            object current = data;

            foreach (var part in parts)
            {
                if (current is Dictionary<string, object> dict && dict.ContainsKey(part))
                {
                    current = dict[part];
                }
                else if (current is JObject jObj && jObj.ContainsKey(part))
                {
                    current = jObj[part];
                }
                else
                {
                    return null;
                }
            }

            return current;
        }

        private object GetArrayValue(Dictionary<string, object> data, string path)
        {
            var match = Regex.Match(path, @"(\w+)\[(\d+)\]");
            if (!match.Success) return null;

            var arrayName = match.Groups[1].Value;
            var index = int.Parse(match.Groups[2].Value);

            if (data.ContainsKey(arrayName))
            {
                var value = data[arrayName];
                if (value is JArray jArray && index < jArray.Count)
                {
                    return jArray[index];
                }
                else if (value is List<object> list && index < list.Count)
                {
                    return list[index];
                }
                else if (value is object[] array && index < array.Length)
                {
                    return array[index];
                }
            }

            return null;
        }

        private object EvaluateExpression(string expression, Dictionary<string, object> data)
        {
            // Simple expression evaluator for common patterns
            // This is a simplified version - you might want to use a proper expression evaluator library

            // Handle string concatenation (e.g., "patient_first_name + patient_last_name")
            if (expression.Contains("+") && !expression.Contains("=="))
            {
                var parts = expression.Split('+').Select(p => p.Trim()).ToArray();
                var values = new List<string>();

                foreach (var part in parts)
                {
                    if (part.StartsWith("\"") && part.EndsWith("\""))
                    {
                        // Literal string
                        values.Add(part.Trim('"'));
                    }
                    else
                    {
                        // Variable reference
                        var value = ExtractFieldValue(part, data);
                        if (value != null)
                        {
                            values.Add(value.ToString());
                        }
                    }
                }

                return string.Join(" ", values.Where(v => !string.IsNullOrWhiteSpace(v)));
            }

            // Handle multiplication (e.g., "wound_size_length * wound_size_width")
            if (expression.Contains("*"))
            {
                var parts = expression.Split('*').Select(p => p.Trim()).ToArray();
                if (parts.Length == 2)
                {
                    var val1 = ExtractFieldValue(parts[0], data);
                    var val2 = ExtractFieldValue(parts[1], data);

                    if (val1 != null && val2 != null &&
                        decimal.TryParse(val1.ToString(), out decimal num1) &&
                        decimal.TryParse(val2.ToString(), out decimal num2))
                    {
                        return num1 * num2;
                    }
                }
            }

            // Handle equality comparison (e.g., "patient_gender == 'male'")
            if (expression.Contains("=="))
            {
                var parts = expression.Split(new[] { "==" }, StringSplitOptions.None)
                    .Select(p => p.Trim()).ToArray();
                
                if (parts.Length == 2)
                {
                    var leftValue = ExtractFieldValue(parts[0], data);
                    var rightValue = parts[1].Trim('\'', '"');
                    
                    return leftValue?.ToString().Equals(rightValue, StringComparison.OrdinalIgnoreCase) ?? false;
                }
            }

            return null;
        }

        private string FormatDuration(Dictionary<string, object> data)
        {
            // Example implementation - customize based on your needs
            if (data.ContainsKey("wound_duration_value") && data.ContainsKey("wound_duration_unit"))
            {
                return $"{data["wound_duration_value"]} {data["wound_duration_unit"]}";
            }
            return null;
        }

        private bool ValidateFieldValue(object value, string validationType)
        {
            if (value == null) return false;

            switch (validationType.ToLower())
            {
                case "npi":
                    // NPI should be 10 digits
                    return Regex.IsMatch(value.ToString(), @"^\d{10}$");
                
                case "icd10":
                    // Basic ICD-10 format validation
                    return Regex.IsMatch(value.ToString(), @"^[A-Z]\d{2}(\.\d{1,4})?$");
                
                case "email":
                    return Regex.IsMatch(value.ToString(), @"^[^@\s]+@[^@\s]+\.[^@\s]+$");
                
                case "phone":
                    var phoneDigits = Regex.Replace(value.ToString(), @"\D", "");
                    return phoneDigits.Length == 10 || phoneDigits.Length == 11;
                
                default:
                    return true;
            }
        }
    }
}