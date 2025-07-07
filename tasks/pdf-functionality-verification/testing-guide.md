# PDF Template Management Testing Guide

## Quick Testing Steps

### 1. Navigate to PDF Templates
- Go to `/admin/pdf-templates`
- You should see the list of uploaded templates

### 2. Upload a New Template (✅ Already Working)
- Click "Upload Template" button
- Select manufacturer, enter name, choose type, set version
- Select PDF file
- Click "Upload PDF Template"
- Should see success message and redirect to detail page

### 3. View Template Details
- Click the eye icon (👁️) on any template in the list
- Should navigate to `/admin/pdf-templates/{id}`
- Should see:
  - Template information card
  - Field mappings section
  - AI Analysis button (purple gradient)
  - Test Fill button (purple)
  - Save Mappings button (blue)

### 4. Test Extract Fields Button
- On the template list page, find a template without fields
- Click "Extract" button
- Should extract form fields from the PDF

### 5. Test AI Analysis
- On template detail page, click "AI Analysis" button
- Should analyze the PDF using Azure Document Intelligence
- Should extract fields and update the template

### 6. Test AI Mapping Suggestions
- Click "Get AI Suggestions" button
- Should show a panel with:
  - Field suggestions with confidence scores
  - Method icons (🔍 pattern, 🧠 semantic, 📋 context, 📚 learned)
  - Ability to select/deselect suggestions
  - "Apply Selected Mappings" button

### 7. Test Field Mapping
- If fields are detected, you should see the field mapping table
- Each field should have:
  - PDF field name
  - Data source dropdown
  - Field type dropdown
  - Transform function dropdown
  - Required checkbox
  - Remove button

### 8. Test Add Mapping
- Click "Add Mapping" button
- Should add a new empty row to mappings

### 9. Test Save Mappings
- Make changes to field mappings
- Click "Save Mappings" button
- Should save and reload page with updated mappings

### 10. Test Fill PDF
- Click "Test Fill" button
- Modal should appear with test data form
- Fill in sample data
- Click "Generate Test PDF"
- Should download a filled PDF

### 11. Test Activate/Deactivate
- On template list, find an active template
- Click yellow X button to deactivate
- Status should change to inactive
- Click green check button to activate
- Status should change to active

### 12. Test Delete
- Click red trash button on a template
- Should show confirmation dialog
- Confirm to delete the template

## Expected AI Features

### 1. Intelligent Field Detection
- Automatically detects form fields in PDFs
- Identifies field types (text, date, checkbox, etc.)
- Extracts field metadata and constraints

### 2. Smart Field Mapping
- Pattern-based suggestions (exact field name matches)
- Semantic similarity (understands "DOB" = "Date of Birth")
- Context awareness (knows medical fields)
- Historical learning (learns from similar templates)

### 3. Confidence Scoring
- Each suggestion has a confidence percentage
- High confidence (80%+) auto-selected
- Color-coded indicators:
  - Green: 90%+ confidence
  - Yellow: 70-89% confidence
  - Orange: 50-69% confidence

### 4. Multiple Analysis Methods
- Folder structure analysis
- Template name pattern matching
- PDF content extraction
- Azure Document Intelligence

## Troubleshooting

### If buttons don't work:
1. Check browser console for errors
2. Verify CSRF token is present
3. Check network tab for failed requests
4. Ensure user has `manage-pdf-templates` permission

### If AI features fail:
1. Check Azure Document Intelligence credentials
2. Verify API endpoints are accessible
3. Check service configuration in `.env`
4. Review logs in `storage/logs/laravel.log`

### Common Issues:
- **"Method not found"**: Controller method might be missing
- **"Route not defined"**: Check route definitions in web.php
- **"Unauthorized"**: User lacks required permissions
- **"File not found"**: PDF might not be accessible

## Success Criteria

✅ All buttons are visible and clickable
✅ Extract fields populates template_fields
✅ AI Analysis updates field count and metadata
✅ AI Suggestions shows relevant mappings
✅ Field mappings can be saved
✅ Test fill generates a PDF download
✅ Templates can be activated/deactivated
✅ Templates can be deleted with confirmation