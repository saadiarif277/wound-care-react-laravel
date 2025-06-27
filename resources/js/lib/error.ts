/**
 * Centralized API error handler
 * All API helper functions call this; it always throws so the caller's
 * declared return type remains accurate and TypeScript control-flow
 * analysis knows execution stops here.
 */

// If you use a notification system (e.g. toast), inject it here.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function handleApiError(error: unknown): never {
  // Basic console logging – replace/extend with Sentry, toast, etc. as needed
  // You may want to inspect AxiosError to extract a user-friendly message
  // but always re-throw so callers keep their typings intact.
  if (import.meta && (import.meta.env?.DEV ?? false)) {
    console.error('[API ERROR]', error);
  }

  // Optionally: display a toast/alert here
  // toast.error(getErrorMessage(error));

  // Re-throw so the promise chain rejects
  throw error;
}
