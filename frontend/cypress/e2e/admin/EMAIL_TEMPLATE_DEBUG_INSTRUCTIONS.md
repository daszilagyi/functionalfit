# Email Template Save & Preview Debug Instructions

## Quick Start

### 1. Prerequisites
- Backend running on http://localhost:8080
- Frontend running on http://localhost:3000
- Database seeded with test data

### 2. Run the Debug Test

```bash
# From frontend directory
cd frontend

# Open Cypress Test Runner (Interactive Mode)
npm run cypress:open

# Or run headless
npm run cypress:run -- --spec "cypress/e2e/admin/email-templates-debug.cy.ts"
```

### 3. Manual Testing Steps

If you prefer to test manually with detailed observation:

#### Step 1: Login
1. Open http://localhost:3000
2. Login as admin: `admin@test.com` / `password`
3. Navigate to `/admin/email-templates`

#### Step 2: Test Save Functionality

**Network Tab Monitoring:**
- Open DevTools (F12)
- Go to Network tab
- Filter by "XHR" or "Fetch"

**Test Actions:**
1. Click Edit button on first template (e.g., registration_confirmation)
2. Modify the subject: Add " - TEST EDIT"
3. In Tiptap editor, add some bold text or a heading
4. Modify fallback body: Add "Test modification"
5. Click Save button

**What to Check:**
- [ ] Network tab shows PUT request to `/api/v1/admin/email-templates/{id}`
- [ ] Request payload contains:
  ```json
  {
    "subject": "...",
    "html_body": "...",
    "fallback_body": "...",
    "is_active": true/false
  }
  ```
- [ ] Response status is 200 OK
- [ ] Response body structure:
  ```json
  {
    "success": true,
    "data": {
      "id": 1,
      "subject": "Updated Subject",
      ...
    },
    "message": "..."
  }
  ```
- [ ] Success toast appears
- [ ] Modal closes
- [ ] Template list refreshes with updated data

**Common Save Issues:**

| Issue | Check | Fix |
|-------|-------|-----|
| Request not sent | Authorization header | Check localStorage for auth_token |
| 401 Unauthorized | Token validity | Re-login |
| 422 Validation Error | Request payload | Check backend validation rules |
| 500 Server Error | Backend logs | Check Laravel logs at backend/storage/logs/ |
| Toast doesn't show | Frontend error | Check browser console |
| Modal doesn't close | Frontend error | Check browser console |

#### Step 3: Test Preview Functionality

**Network Tab Monitoring:**
- Keep Network tab open
- Clear previous requests (optional)

**Test Actions:**
1. Click Preview button (eye icon) on any template
2. Observe the network request
3. Wait for preview modal to open
4. Inspect the iframe content

**What to Check:**
- [ ] Network tab shows POST request to `/api/v1/admin/email-templates/{id}/preview`
- [ ] Request body is empty `{}` or contains sample variables
- [ ] Response status is 200 OK
- [ ] Response body structure - check which format is returned:
  ```json
  // Format 1: Direct string
  "preview": "<html>...</html>"

  // Format 2: Nested in data
  {
    "data": {
      "preview": "<html>...</html>",
      "variables_used": {...}
    }
  }

  // Format 3: Direct object
  {
    "preview": "<html>...</html>",
    "variables_used": {...}
  }
  ```
- [ ] Preview modal opens
- [ ] Iframe `srcDoc` attribute is NOT "[object Object]"
- [ ] Iframe displays rendered HTML
- [ ] No JavaScript errors in console

**Common Preview Issues:**

| Issue | Check | Solution |
|-------|-------|----------|
| [object Object] in iframe | Response extraction | Check EmailTemplatesPage.tsx line 124 |
| Empty iframe | srcDoc attribute | Check if HTML is properly assigned |
| Network error | Backend endpoint | Verify route exists |
| Variables not replaced | Backend processing | Check EmailTemplateService |
| Modal doesn't open | previewMutation onSuccess | Check mutation handler |

#### Step 4: Browser Console Errors

**Check Console Tab:**
- Any React errors?
- Any API errors?
- Any mutation errors?

**Common Console Errors:**

```javascript
// Error 1: Preview extraction failed
"Cannot read property 'preview' of undefined"
// Solution: Check API response structure

// Error 2: Mutation error
"Mutation failed: ..."
// Solution: Check error.response.data in Network tab

// Error 3: Tiptap editor error
"Cannot read property 'commands' of undefined"
// Solution: Check TiptapEditor component initialization
```

## Detailed Debug Checklist

### Backend Verification

```bash
# Check backend is running
curl http://localhost:8080/api/v1/health

# Check auth works
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@test.com","password":"password"}'

# Get templates list (replace TOKEN)
curl http://localhost:8080/api/v1/admin/email-templates \
  -H "Authorization: Bearer YOUR_TOKEN"

# Test preview endpoint (replace TOKEN and ID)
curl -X POST http://localhost:8080/api/v1/admin/email-templates/1/preview \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'
```

### Frontend Verification

```bash
# Check frontend is running
curl http://localhost:3000

# Check build has no errors
cd frontend
npm run build

# Check TypeScript errors
npm run type-check
```

### Database Verification

```bash
cd backend

# Check email_templates table
php artisan tinker
>>> \DB::table('email_templates')->count();
>>> \DB::table('email_templates')->first();
```

## Automated Test Output Analysis

The Cypress test will produce detailed logs for each phase:

### Save Test Phases:
1. Selecting template to edit
2. Verifying edit modal opened
3. Making changes to template
4. Intercepting save request
5. Clicking Save button
6. Waiting for API response
7. Verifying UI feedback
8. Verifying template was updated

### Preview Test Phases:
1. Setting up preview request interception
2. Clicking Preview button
3. Waiting for preview API response
4. Verifying preview modal opened
5. Inspecting iframe content
6. Checking browser console for errors
7. Verifying preview can be closed

## Expected Test Results

### Passing Test Output:
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

✅ Preview API successful - Status 200
✅ Preview dialog is visible
✅ Preview iframe found
✅ iframe srcDoc length: 1234 characters
✅ srcDoc contains valid HTML
✅ iframe body content length: 1234 characters
```

### Failing Test Output Examples:

**Save Failure:**
```
❌ Save failed - Status 422
❌ VALIDATION ERRORS: {
  "subject": ["The subject field is required."],
  "html_body": ["The html body field is required."]
}
```

**Preview Failure:**
```
❌ Preview API failed - Status 500
❌ BUG DETECTED: srcDoc is "[object Object]"
This indicates the response was not properly extracted
```

## Next Steps Based on Findings

### If Save Fails:

1. **422 Validation Error**: Check request payload structure
2. **401 Unauthorized**: Re-login and check token
3. **500 Server Error**: Check backend logs
4. **Network Error**: Check CORS settings

### If Preview Fails:

1. **[object Object] in iframe**: Fix response extraction in EmailTemplatesPage.tsx
2. **Empty preview**: Check backend preview generation
3. **Variables not replaced**: Check EmailTemplateService
4. **Modal doesn't open**: Check previewMutation success handler

## Reporting Results

When reporting test results, please include:

1. Test run summary (passed/failed)
2. Network request/response details (from Network tab)
3. Console errors (from Console tab)
4. Screenshots of:
   - Failed save with error toast
   - Preview modal showing the issue
   - Network tab showing request/response
5. Backend logs (if 500 error)

## File Locations

- Frontend component: `frontend/src/pages/admin/EmailTemplatesPage.tsx`
- API client: `frontend/src/api/admin.ts`
- Tiptap editor: `frontend/src/components/ui/tiptap-editor.tsx`
- Backend controller: `backend/app/Http/Controllers/Api/Admin/EmailTemplateController.php`
- Backend service: `backend/app/Services/EmailTemplateService.php`
- Backend model: `backend/app/Models/EmailTemplate.php`
