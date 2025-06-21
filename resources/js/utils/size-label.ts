export const getProductSizeLabel = (productName: string, size: number | string, sizeUnit?: string): string => {
  const parsedSize = typeof size === 'string' ? parseFloat(size) : size;

  // If size unit is 'cm' (indicating cm²), calculate dimensions
  if (sizeUnit === 'cm' || !sizeUnit) {
    // For square sizes, calculate the square root to get dimensions
    const sqrtSize = Math.sqrt(parsedSize);
    
    // Check if it's a perfect square
    if (Number.isInteger(sqrtSize)) {
      return `${sqrtSize} x ${sqrtSize} cm`;
    }
    
    // For non-square sizes, try to find reasonable dimensions
    // Common rectangular wound dressing dimensions
    const rectangularDimensions: Record<number, string> = {
      2: '1 x 2 cm',
      6: '2 x 3 cm',
      8: '2 x 4 cm',
      10: '2 x 5 cm',
      10.5: '3 x 3.5 cm',
      12: '3 x 4 cm',
      15: '3 x 5 cm',
      18: '3 x 6 cm',
      20: '4 x 5 cm',
      24: '4 x 6 cm',
      28: '4 x 7 cm',
      30: '5 x 6 cm',
      32: '4 x 8 cm',
      35: '5 x 7 cm',
      40: '5 x 8 cm',
      42: '6 x 7 cm',
      48: '6 x 8 cm',
      54: '6 x 9 cm',
      56: '7 x 8 cm',
      63: '7 x 9 cm',
      72: '8 x 9 cm',
      80: '8 x 10 cm',
      90: '9 x 10 cm',
    };
    
    if (rectangularDimensions[parsedSize]) {
      return rectangularDimensions[parsedSize];
    }
    
    // Fallback to showing cm² for non-standard sizes
    return `${parsedSize} cm²`;
  }
  
  // For other size units (like inches), just append the unit
  if (sizeUnit) {
    return `${parsedSize} ${sizeUnit}`;
  }
  
  // Default fallback
  return `${parsedSize} cm²`;
};
