export const getProductSizeLabel = (productName: string, size: number | string): string => {
  const parsedSize = typeof size === 'string' ? parseFloat(size) : size;

  // Map of Biovance sizes (area in cm²) to dimension labels
  const biovanceSizeMap: Record<number, string> = {
    2: '1 x 2 cm',
    4: '2 x 2 cm',
    6: '2 x 3 cm',
    8: '2 x 4 cm',
    10.5: '3 x 3.5 cm',
    16: '4 x 4 cm',
    25: '5 x 5 cm',
    36: '6 x 6 cm',
  };

  // Return custom label for Biovance if available
  if (productName?.toLowerCase() === 'biovance' && biovanceSizeMap[parsedSize]) {
    return biovanceSizeMap[parsedSize];
  }

  // Map of Impax sizes (area in cm²) to dimension labels
  const impaxSizeMap: Record<number, string> = {
    4: '2 x 2 cm',
    6: '2 x 3 cm',
    16: '4 x 4 cm',
    24: '4 x 6 cm',
    32: '4 x 8 cm',
  };

  if (productName?.toLowerCase().includes('impax') && impaxSizeMap[parsedSize]) {
    return impaxSizeMap[parsedSize];
  }

  // Fallback to generic label using cm²
  return `${parsedSize} cm²`;
};
