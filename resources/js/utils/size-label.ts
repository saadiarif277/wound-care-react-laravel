export const getProductSizeLabel = (size: number | string, sizeUnit?: string): string => {
  const parsedSize = typeof size === 'string' ? parseFloat(size) : size;

  // If size unit is provided, use it
  if (sizeUnit) {
    if (sizeUnit === 'cm') {
      // For cm unit, we're dealing with area (cm²)
      return `${parsedSize} cm²`;
    } else if (sizeUnit === 'inch' || sizeUnit === 'inches') {
      // For inches, just show the value with quotes
      return `${parsedSize}"`;
    } else {
      // For any other unit, append it directly
      return `${parsedSize} ${sizeUnit}`;
    }
  }
  
  // Default fallback - assume cm²
  return `${parsedSize} cm²`;
};
