/**
 * Converts an ISO date string to a human-readable format
 * @param dateString - ISO date string (e.g., "2025-07-10T00:00:00.000000Z")
 * @returns Human-readable date string (e.g., "July 10, 2025")
 */
export function formatHumanReadableDate(dateString?: string): string {
  if (!dateString) return 'N/A';

  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return 'Invalid Date';

    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  } catch (error) {
    console.error('Error formatting date:', error);
    return 'Invalid Date';
  }
}

/**
 * Converts an ISO date string to a short format
 * @param dateString - ISO date string
 * @returns Short date string (e.g., "Jul 10, 2025")
 */
export function formatShortDate(dateString?: string): string {
  if (!dateString) return 'N/A';

  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return 'Invalid Date';

    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  } catch (error) {
    console.error('Error formatting date:', error);
    return 'Invalid Date';
  }
}

/**
 * Checks if a date is today
 * @param dateString - ISO date string
 * @returns boolean indicating if the date is today
 */
export function isToday(dateString?: string): boolean {
  if (!dateString) return false;

  try {
    const date = new Date(dateString);
    const today = new Date();

    return date.toDateString() === today.toDateString();
  } catch (error) {
    return false;
  }
}

/**
 * Checks if a date is in the future
 * @param dateString - ISO date string
 * @returns boolean indicating if the date is in the future
 */
export function isFutureDate(dateString?: string): boolean {
  if (!dateString) return false;

  try {
    const date = new Date(dateString);
    const today = new Date();

    return date > today;
  } catch (error) {
    return false;
  }
}
