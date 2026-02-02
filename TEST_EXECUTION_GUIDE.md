# Email Template Save & Preview - Test Execution Guide

## Overview

This guide provides step-by-step instructions for executing comprehensive tests to debug the email template save and preview functionality issues.

**Reported Issues:**
- Template save doesn't work
- Preview doesn't work
- Recent fix: preview HTML extraction bug in EmailTemplatesPage.tsx line 124

## Test Files Created

### Frontend Tests (Cypress E2E)
1. **C:\Users\daszi\Documents\dev\functionalfit_calendar_project\frontend\cypress\e2e\admin\email-templates-debug.cy.ts**
   - Comprehensive E2E tests with detailed debugging output
   - Tests save functionality with network monitoring
   - Tests preview functionality with iframe inspection
   - Monitors authentication, validation, and data persistence

2. **C:\Users\daszi\Documents\dev\functionalfit_calendar_project\frontend\cypress\e2e\admin\EMAIL_TEMPLATE_DEBUG_INSTRUCTIONS.md**
   - Manual testing instructions
   - Troubleshooting guide
   - Expected vs actual behavior checklist

### Backend Tests (Pest)
1. **C:\Users\daszi\Documents\dev\functionalfit_calendar_project\backend\tests\Feature\Admin\EmailTemplateDebugTest.php**
   - Backend API tests with detailed console output
   - Tests save with payload debugging
   - Tests preview with response structure analysis
   - Validates authorization and error handling

## Quick Start Test Execution

### Option 1: Run All Tests (Recommended)

```bash
# Terminal 1: Start Backend
cd backend
php artisan serve

# Terminal 2: Start Frontend
cd frontend
npm run dev

# Terminal 3: Run Backend Tests
cd backend
php artisan test tests/Feature/Admin/EmailTemplateDebugTest.php --testdox

# Terminal 4: Run Frontend Tests
cd frontend
npm run cypress:open
# Then select: email-templates-debug.cy.ts
```

### Option 2: Backend Only

```bash
cd backend

# Ensure database is set up
php artisan migrate:fresh --seed

# Run debug tests with verbose output
php artisan test tests/Feature/Admin/EmailTemplateDebugTest.php --testdox -v

# Or run all email template tests
php artisan test tests/Feature/Admin/EmailTemplateApiTest.php --testdox
```

### Option 3: Frontend Only

```bash
cd frontend

# Ensure backend is running on http://localhost:8080
# Ensure frontend is running on http://localhost:3000

# Open Cypress Test Runner
npm run cypress:open

# Select: email-templates-debug.cy.ts from admin folder

# Or run headless
npm run cypress:run -- --spec "cypress/e2e/admin/email-templates-debug.cy.ts"
```

### Option 4: Manual Testing (Most Detailed)

Follow the instructions in:
**frontend/cypress/e2e/admin/EMAIL_TEMPLATE_DEBUG_INSTRUCTIONS.md**

## Test Scenarios Covered

### 1. Template Save Tests

**Backend (Pest):**
- ✅ Complete payload debugging
- ✅ Validation error handling
- ✅ Partial updates
- ✅ HTML content integrity
- ✅ Version snapshot creation
- ✅ Authorization checks

**Frontend (Cypress):**
- ✅ Edit modal interaction
- ✅ Tiptap editor content modification
- ✅ Network request/response inspection
- ✅ Success toast verification
- ✅ Data persistence after reload
- ✅ Client-side validation

### 2. Template Preview Tests

**Backend (Pest):**
- ✅ Sample variables generation
- ✅ Custom variables handling
- ✅ Undefined variables handling
- ✅ Response format verification
- ✅ HTML rendering

**Frontend (Cypress):**
- ✅ Preview button interaction
- ✅ Network request/response analysis
- ✅ Response structure detection
- ✅ Iframe srcDoc content
- ✅ Modal display verification
- ✅ Console error detection

### 3. Integration Tests

**End-to-End Flow:**
- ✅ Login → Navigate → Edit → Save → Verify
- ✅ Login → Navigate → Preview → Inspect → Close
- ✅ Authentication token handling
- ✅ Data integrity across reloads

## Understanding Test Output

### Backend Test Output

**Successful Save:**
```
🔍 PHASE 1: Initial Template State
  ID: 1
  Subject: Original Subject
  Version: 1
  Active: true

🔍 PHASE 2: Update Payload
  Subject: Updated Subject - Test
  HTML Length: 58 chars
  Fallback Length: 34 chars

🔍 PHASE 3: API Response
  Status Code: 200
  ✅ Response structure validated
  ✅ Response data validated

🔍 PHASE 4: Database State After Update
  Subject: Updated Subject - Test
  Version: 2
  ✅ Database updated correctly

🔍 PHASE 5: Version Snapshot Verification
  Total Versions: 1
  ✅ Version snapshot created

🎉 ALL SAVE TESTS PASSED
```

**Successful Preview:**
```
🔍 PHASE 1: Template Setup
  Subject: Hello {{user.name}}
  HTML: <p>Welcome {{user.name}} to {{class.title}}</p>

🔍 PHASE 2: Sending Preview Request
  Status Code: 200

🔍 PHASE 3: Response Data Analysis
  ✅ Response structure validated

🔍 PHASE 4: Preview Content Analysis
  Preview Type: string
  Preview Length: 65 chars
  Contains HTML tags: yes
  Contains variables ({{}}): NO (replaced)

🔍 PHASE 5: Variables Used Analysis
  Variables Type: array
  ✅ Variable replacement validated

🎉 ALL PREVIEW TESTS PASSED
```

### Frontend Test Output

**Successful Save:**
```
✅ Edit dialog is visible
✅ Subject field is populated
✅ Subject changed to: Updated Registration Email - Test
✅ Added content to Tiptap editor
✅ Fallback body changed to: Updated fallback text for testing
✅ Active switch is present
✅ Request payload has required fields
✅ Save successful - Status 200
✅ Edit dialog closed
✅ Success toast appeared
✅ Updated subject visible in table
```

**Successful Preview:**
```
✅ Preview API successful - Status 200
📋 Response format: { data: { preview: string } }
✅ Preview dialog is visible
✅ Preview iframe found
✅ iframe srcDoc length: 1234 characters
✅ srcDoc contains valid HTML
✅ iframe body content length: 1234 characters
```

### Failure Indicators

**Save Failures:**
```
❌ Save failed - Status 422
❌ VALIDATION ERRORS: { "subject": ["Required"] }

or

❌ Save failed - Status 401
  Unauthorized - Check auth token

or

❌ Save failed - Status 500
  Server error - Check backend logs
```

**Preview Failures:**
```
❌ Preview API failed - Status 500

or

❌ BUG DETECTED: srcDoc is "[object Object]"
This indicates the response was not properly extracted

or

❌ iframe body is empty!
```

## Common Issues & Solutions

### Issue: Save Returns 422 Validation Error

**Check:**
1. Request payload structure in Network tab
2. Backend validation rules in `UpdateEmailTemplateRequest.php`
3. Frontend form validation

**Solution:**
- Ensure all required fields are sent: `subject`, `html_body`, `fallback_body`, `is_active`
- Check field data types match expectations

### Issue: Save Returns 401 Unauthorized

**Check:**
1. localStorage contains `auth_token`
2. Token is valid (not expired)
3. Authorization header in request

**Solution:**
- Re-login to get fresh token
- Check token expiration settings

### Issue: Preview Shows [object Object]

**Check:**
1. Backend response structure in Network tab
2. EmailTemplatesPage.tsx line 124 response extraction logic

**Current Fix Applied:**
```typescript
const htmlContent = typeof data === 'string'
  ? data
  : (data.preview || data.html || data.data?.preview || data.data?.html || '')
```

**Solution:**
- Verify backend returns `{ data: { preview: string } }`
- Update frontend extraction if backend format changed

### Issue: Preview Iframe Empty

**Check:**
1. `srcDoc` attribute on iframe element
2. Browser console for errors
3. Network response contains HTML

**Solution:**
- Verify `previewHtml` state is set correctly
- Check `previewMutation.onSuccess` handler

### Issue: Backend Logs Show Errors

**Check Backend Logs:**
```bash
cd backend
tail -f storage/logs/laravel.log
```

**Common Errors:**
- Database connection issues
- Missing migrations
- EmailTemplateService errors
- MailService preview generation errors

## Test Data Setup

### Ensure Database is Seeded

```bash
cd backend
php artisan migrate:fresh --seed
```

**Seeded Templates:**
- registration_confirmation
- booking_confirmation
- booking_cancelled
- booking_modified
- password_reset
- trainer_assigned
- trainer_removed
- class_cancelled
- credits_low

### Test Credentials

```
Admin:
  Email: admin@test.com
  Password: password

Staff:
  Email: staff@test.com
  Password: password

Client:
  Email: client@test.com
  Password: password
```

## Reporting Test Results

### What to Include in Bug Report

1. **Test Type:** Backend, Frontend, or Manual
2. **Failing Test:** Exact test name or scenario
3. **Error Output:** Copy full error message/stack trace
4. **Network Data:**
   - Request URL
   - Request method and payload
   - Response status code
   - Response body
5. **Screenshots:**
   - Network tab showing failed request
   - Console tab showing errors
   - Preview modal showing the issue
6. **Environment:**
   - Node version
   - PHP version
   - Database type
   - Browser (for frontend tests)

### Example Bug Report

```markdown
## Bug: Template Save Returns 422

**Test:** Frontend Cypress - Template Save Functionality
**Error:** Validation error on save

**Network Request:**
PUT http://localhost:8080/api/v1/admin/email-templates/1
Payload: {
  "subject": "Test",
  "html_body": "<p>Test</p>",
  "fallback_body": "Test",
  "is_active": true
}

**Response:**
Status: 422 Unprocessable Entity
Body: {
  "success": false,
  "errors": {
    "subject": ["The subject field is required."]
  }
}

**Screenshot:** [attached]

**Environment:**
- Frontend: Node 20.x
- Backend: PHP 8.2
- Browser: Chrome 120
```

## Next Steps Based on Results

### All Tests Pass
✅ Save and preview functionality is working correctly
- Consider adding more edge case tests
- Review code for potential improvements
- Update documentation

### Backend Tests Pass, Frontend Tests Fail
🔍 Issue is in frontend code or integration
- Check API client configuration
- Review frontend mutation handlers
- Inspect response extraction logic

### Backend Tests Fail
🔍 Issue is in backend code
- Check controller logic
- Review service implementations
- Verify database schema

### Specific Scenarios Fail
🔍 Issue is isolated to specific functionality
- Focus debugging on failing test
- Add more granular tests around failure point
- Check for race conditions or timing issues

## Additional Resources

- **Backend API Documentation:** backend/routes/api.php
- **Frontend Component:** frontend/src/pages/admin/EmailTemplatesPage.tsx
- **API Client:** frontend/src/api/admin.ts
- **Backend Controller:** backend/app/Http/Controllers/Api/Admin/EmailTemplateController.php
- **Email Service:** backend/app/Services/MailService.php
