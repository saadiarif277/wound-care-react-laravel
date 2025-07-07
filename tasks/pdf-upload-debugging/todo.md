# PDF Upload Debugging Enhancement

## Todo List

- [x] Add comprehensive error handling and debugging to PDF upload
- [x] Enhance frontend error display with detailed messages
- [x] Add server-side logging for upload failures
- [x] Create debug mode for verbose error reporting
- [x] Fix file upload recognition issue with Inertia.js
- [x] Create manual test page for file upload diagnosis

## Review

### Summary of Changes

This task addressed the user's issue where PDF uploads were failing with uninformative error messages. The following enhancements were implemented:

#### 1. Enhanced Backend Error Handling (PDFTemplateController.php)
- Added comprehensive debug mode that can be activated via config or query parameter (`?debug=1`)
- Enhanced error logging with detailed file information:
  - File presence check
  - File metadata (size, mime type, validity)
  - Upload error codes and messages
  - Storage operation results
- Improved error categorization with user-friendly messages:
  - Storage errors → "Storage service error. Please check your Azure/storage configuration."
  - Invalid file errors → "The uploaded file appears to be corrupted or invalid."
  - Permission errors → "Permission denied. Please check storage permissions."
- Made field extraction non-fatal so uploads can continue even if PDF analysis fails
- Added debug information to response when in debug mode

#### 2. Enhanced Frontend Error Display (PDFTemplateManager.tsx)
- Added debug mode toggle for administrators (visible in development environment)
- Enhanced error display with icons and better formatting
- Added collapsible debug information panel for detailed error inspection
- Implemented flash message support for session-based error/success messages
- Added general error display area in upload modal
- Improved individual field error displays with alert icons
- Added upload progress tracking with visual progress bar

#### 3. Created Test Script (test-pdf-upload-debug.js)
- Comprehensive test script to verify upload functionality
- Tests multiple scenarios:
  - Valid PDF upload
  - Missing PDF file
  - Invalid file type
  - Oversized PDF (>10MB)
  - Missing required fields
- Automatically creates test files and cleans up after testing
- Can be run with `npm run test:pdf-upload`

### Key Improvements

1. **Better Error Visibility**: Admins now see exactly what went wrong during upload, including file validation issues, storage errors, and field extraction problems.

2. **Debug Mode**: When enabled, provides detailed technical information about the upload process, helping diagnose issues quickly.

3. **Graceful Degradation**: Field extraction failures no longer block uploads, allowing the template to be saved even if automatic field detection fails.

4. **User-Friendly Messages**: Generic error messages replaced with specific, actionable feedback.

5. **Visual Feedback**: Progress bar shows upload status, and all errors are displayed with appropriate styling and icons.

### Additional Fix: File Upload Recognition

The issue where the system wasn't recognizing uploaded PDF files was due to how Inertia.js handles file uploads. The debug output showed that while the test endpoint could detect the file (`has_file: true`), Inertia's form submission was not properly sending it.

The fix involved:

1. **Changed from form.post() to router.post()**: Using `router.post()` with manually created FormData ensures proper file handling
2. **Manual FormData construction**: Creating FormData and explicitly appending all fields including the file
3. **Removed transform**: Removed the transform function that was interfering with file handling
4. **Added file validation**: Added check to ensure pdf_file is a File instance before appending
5. **Enhanced logging**: Added console logs to track FormData creation

Key insight from debugging:
- The test endpoint showed `has_file: true` and `files_array: {pdf_file: {...}}`
- But Inertia's form.post() was not properly handling the File object
- Solution: Always use `router.post()` with manually created FormData for file uploads in Inertia.js

### Usage

To debug upload issues:
1. Enable debug mode by checking the "Enable debug mode" checkbox in the upload modal (development only)
2. Or append `?debug=1` to the URL when uploading
3. Check the browser console for detailed logging during upload
4. Check the debug information panel that appears when errors occur
5. Review server logs for detailed technical information

### Testing

Run the test script to verify upload functionality:
```bash
npm run test:pdf-upload
```

This will test various upload scenarios and verify proper error handling.

### Troubleshooting

If uploads still fail:
1. Check browser console for JavaScript errors
2. Verify the file is actually a PDF (not renamed)
3. Check file size is under 10MB
4. Enable debug mode to see server-side file detection
5. Check PHP settings: `upload_max_filesize` and `post_max_size`
6. Test with manual upload page: Open `/tests/scripts/test-manual-pdf-upload.html` in browser
7. Check Laravel logs for detailed error information
8. Verify user has `manage-pdf-templates` permission

### Debugging Tools Added

1. **Test Upload Controller**: `/admin/test-upload` endpoint for raw file detection testing
2. **Manual Test Page**: `tests/scripts/test-manual-pdf-upload.html` - Tests file upload without Inertia
3. **Enhanced Debug Mode**: Shows all request data, file arrays, and PHP configuration
4. **Test File Detection Button**: Available in debug mode to test file detection directly

### Current Investigation

The file upload issue appears to be related to how Inertia.js handles multipart form data. Several approaches have been tried:
1. Using `post()` with `forceFormData: true`
2. Using `router.post()` with manual FormData
3. Adding transform to ensure proper data handling

The manual test page can help determine if the issue is:
- Frontend (Inertia) related
- Backend permission/middleware related
- PHP configuration related
- CSRF token related