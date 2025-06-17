# Google Maps API Setup Guide

## Overview
The Google Maps Places API is used for address autocomplete functionality in the application. This is an optional feature - the application works perfectly fine without it.

## Setup Steps

### 1. Get a Google Maps API Key
1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the following APIs:
   - Maps JavaScript API
   - Places API
4. Create credentials (API Key)
5. Restrict the API key:
   - Application restrictions: HTTP referrers
   - Add your domains: `*.wound-care-react-laravel.test/*`, `localhost:*`
   - API restrictions: Select the APIs you enabled

### 2. Add to Environment Configuration
Add the following to your `.env` file:
```
GOOGLE_MAPS_API_KEY=your-actual-api-key-here
```

### 3. Clear Configuration Cache
```bash
php artisan config:clear
php artisan config:cache
```

### 4. Verify Setup
1. Refresh the page
2. The address input should now show autocomplete suggestions as you type
3. The "Manual entry only" message should disappear

## Important Notes
- This is NOT required for FHIR functionality
- Patient addresses are stored in FHIR regardless of how they're entered
- The Google Maps API has usage limits and may incur costs at high volumes
- Consider implementing request quotas if enabling this in production

## Troubleshooting
If autocomplete doesn't work after setup:
1. Check browser console for API errors
2. Verify API key restrictions allow your domain
3. Ensure Places API is enabled in Google Cloud Console
4. Check that the API key has proper billing set up (Google requires billing even for free tier)