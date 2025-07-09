#\!/bin/bash
echo "Models with no references:"
for model_file in $(find ./app/Models -name "*.php" -type f); do
    model_name=$(basename "$model_file" .php)
    # Skip if it's a trait or abstract class
    if grep -q "trait\|abstract class" "$model_file" 2>/dev/null; then
        continue
    fi
    # Check for usage in the codebase
    count=$(grep -r "\\\\$model_name\|::class.*$model_name\|new $model_name\|$model_name::" app/ resources/js/ --include="*.php" --include="*.tsx" --include="*.ts" 2>/dev/null | grep -v "$model_file" | wc -l)
    if [ $count -eq 0 ]; then
        echo "$model_file"
    fi
done
