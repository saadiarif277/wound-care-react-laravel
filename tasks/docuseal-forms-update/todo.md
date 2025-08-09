# Docuseal Forms Update Task

## Objective

Update Docuseal form implementations in Step 4 of Quick Request and IVR to improve user experience by:

- Removing "open in new tab" options
- Making forms scrollable within the current view
- Updating resolution/height to show more of the form
- Using data-minimize="true" attribute to minimize input text boxes

## Todo Items

### 1. Update DocusealEmbed Component

- [x] Add `data-minimize="true"` attribute to the docuseal-form element
- [x] Remove/hide the "Open in new tab" button in the embedded view
- [x] Increase the default height for better visibility (set to 1200px)
- [x] Ensure proper scrolling within the container (overflow-auto)
- [x] Remove the option to toggle between embedded and new window modes

### 2. Update Step7DocusealIVR Component

- [x] Remove the "Open in new tab" button from the integration info section
- [x] Ensure the DocusealEmbed is configured to display inline only
- [x] Verify that the form displays with proper height and scrolling
- [x] Clean up unused imports

### 3. Check Step4 Implementation

- [x] Identify where Docuseal forms are used in Step 4 (RESULT: Step 4 doesn't use Docuseal - it's just a clinical form)
- [x] Apply the same inline display improvements (N/A - no Docuseal in Step 4)
- [x] Remove any popup or new tab behaviors (N/A - no Docuseal in Step 4)

### 4. Testing

- [ ] Test the Quick Request flow Step 4 to ensure forms display inline
- [ ] Test the IVR form in Step 7 to ensure proper inline display
- [ ] Verify that data-minimize attribute properly minimizes input boxes
- [ ] Check scrolling behavior on different screen sizes

## Technical Details

### Changes to DocusealEmbed Component

1. In the embedded form container, add `data-minimize="true"` to the docuseal-form element
2. Remove the "Open in new tab" button from the status bar
3. Adjust the container height from 1000px/100vh to a more appropriate fixed height
4. Remove the toggle functionality between embedded and direct URL modes

### Changes to Step7DocusealIVR Component

1. Remove the "open in new tab" option from the integration info section
2. Force the DocusealEmbed to always use embedded mode
3. Ensure proper container sizing for scrollability

## Expected Outcomes

- Users will complete forms without leaving the current interface
- Forms will be more accessible with minimized input boxes
- Better user experience with inline scrollable forms
- No popups or new tabs disrupting the workflow

## Review

### Summary of Changes Made

1. **DocusealEmbed Component Updates** (`/resources/js/Components/QuickRequest/DocusealEmbed.tsx`):
   - Added `data-minimize="true"` attribute to the docuseal-form element to minimize input text boxes
   - Removed the "Open in new tab" button from the embedded view status bar
   - Changed container height from dynamic (100vh with min/max) to fixed 1200px for better visibility
   - Added `overflow-auto` to enable scrolling within the container
   - Removed the `useDirectUrl` state and forced embedded mode only
   - Removed all code related to direct URL mode and toggle functionality
   - Cleaned up unused imports (ExternalLink)

2. **Step7DocusealIVR Component Updates** (`/resources/js/Pages/QuickRequest/Components/Step7DocusealIVR.tsx`):
   - Updated DocusealEmbed usage to remove height constraints
   - Cleaned up unused imports and state variables
   - The component now always displays forms inline without any "open in new tab" options

3. **Step4 Investigation**:
   - Confirmed that Step4ClinicalBilling does NOT use Docuseal forms
   - It's a standard React form for clinical and billing information
   - No changes were needed for Step 4

### Key Technical Changes

- The `data-minimize="true"` attribute will minimize Docuseal's input fields, making them less obtrusive
- Fixed height of 1200px provides a good viewing area while ensuring scrollability
- Removed all user-facing options to open forms in new tabs/windows
- Forms are now always embedded inline for a consistent user experience

### Next Steps

- Testing is required to verify that the forms display properly
- Check that the minimize attribute works as expected
- Ensure scrolling behavior is smooth on different devices and screen sizes
