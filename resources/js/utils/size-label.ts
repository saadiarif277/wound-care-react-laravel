export const getProductSizeLabel = (productName: string, size: string | number): string => {
  const parsedSize = typeof size === 'string' ? parseFloat(size) : size;
  
  // Handle invalid sizes
  if (isNaN(parsedSize) || parsedSize <= 0) {
    return 'Invalid size';
  }
  
  // For wound care products, sizes are typically in cm²
  // Calculate dimensions for square sizes (common in wound care)
  const sqrtSize = Math.sqrt(parsedSize);
  const isSquare = sqrtSize === Math.floor(sqrtSize);
  
  if (isSquare) {
    return `${sqrtSize} x ${sqrtSize} cm`;
  }
  
  // For non-square sizes, just show the area
  return `${parsedSize} cm²`;
};
