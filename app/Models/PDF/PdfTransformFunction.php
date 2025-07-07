<?php

namespace App\Models\PDF;

use Illuminate\Database\Eloquent\Model;

class PdfTransformFunction extends Model
{
    protected $fillable = [
        'function_name',
        'display_name',
        'description',
        'category',
        'function_code',
        'parameters',
        'examples',
        'is_active'
    ];

    protected $casts = [
        'parameters' => 'array',
        'examples' => 'array',
        'is_active' => 'boolean'
    ];

    /**
     * Execute the transform function
     */
    public function execute($value, array $params = [])
    {
        // Create a safe execution context
        $execute = function($value, $params) {
            return eval($this->function_code);
        };

        try {
            return $execute($value, $params);
        } catch (\Exception $e) {
            \Log::error('Transform function execution failed', [
                'function' => $this->function_name,
                'error' => $e->getMessage()
            ]);
            return $value; // Return original value on error
        }
    }

    /**
     * Validate function code syntax
     */
    public function validateSyntax(): array
    {
        $errors = [];
        
        // Basic PHP syntax check
        $testCode = "<?php\n" . $this->function_code;
        $result = shell_exec("echo " . escapeshellarg($testCode) . " | php -l 2>&1");
        
        if (strpos($result, 'No syntax errors detected') === false) {
            $errors[] = 'PHP syntax error: ' . $result;
        }

        // Check for dangerous functions
        $dangerousFunctions = [
            'exec', 'system', 'shell_exec', 'passthru', 'eval',
            'file_get_contents', 'file_put_contents', 'fopen',
            'include', 'require', 'include_once', 'require_once'
        ];

        foreach ($dangerousFunctions as $func) {
            if (stripos($this->function_code, $func) !== false) {
                $errors[] = "Dangerous function '{$func}' is not allowed";
            }
        }

        return $errors;
    }

    /**
     * Scope to get active functions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get functions by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get all available categories
     */
    public static function getCategories(): array
    {
        return [
            'date' => 'Date Formatting',
            'text' => 'Text Manipulation',
            'number' => 'Number Formatting',
            'boolean' => 'Boolean Logic',
            'array' => 'Array Processing',
            'custom' => 'Custom Logic'
        ];
    }
}