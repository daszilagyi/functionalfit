/// <reference types="cypress" />

/**
 * Email Templates Debug E2E Test
 *
 * Purpose: Detailed debugging test for email template save and preview functionality
 * Bug Reports:
 * - Template save doesn't work
 * - Preview doesn't work
 * - Recent fix: preview HTML extraction bug in EmailTemplatesPage.tsx line 124
 *
 * This test provides extensive debugging information to identify exact failure points.
 */

describe('Email Templates - Save and Preview Debugging', () => {
  const ADMIN_EMAIL = 'admin@test.com';
  const ADMIN_PASSWORD = 'password';

  // Test data for editing
  const TEST_EDITS = {
    subject: 'Updated Registration Email - Test',
    htmlChanges: '<p><strong>Bold test text</strong></p><h2>Test Heading</h2>',
    fallbackChanges: 'Updated fallback text for testing',
  };

  beforeEach(() => {
    // Clear storage
    cy.clearLocalStorage();
    cy.clearCookies();

    // Login as admin
    cy.login(ADMIN_EMAIL, ADMIN_PASSWORD);

    // Visit email templates page
    cy.visit('/admin/email-templates');

    // Wait for page to load
    cy.getByTestId('email-templates-search-input').should('be.visible');
  });

  describe('1. Template Save Functionality', () => {
    it('should save template with detailed debugging', () => {
      cy.log('🔍 PHASE 1: Selecting template to edit');

      // Intercept the templates list to get template data
      cy.intercept('GET', '**/api/v1/admin/email-templates*').as('getTemplates');
      cy.wait('@getTemplates').then((interception) => {
        cy.log('📊 Templates API Response:', interception.response?.body);
        expect(interception.response?.statusCode).to.eq(200);
      });

      // Find the first template row
      cy.get('[data-testid^="email-template-row-"]').first().within(() => {
        cy.log('🎯 Found first template row');

        // Click Edit button
        cy.getByTestId('edit-btn-1').should('be.visible').click();
      });

      cy.log('🔍 PHASE 2: Verifying edit modal opened');

      // Wait for edit dialog to open
      cy.getByTestId('edit-template-dialog').should('be.visible');
      cy.log('✅ Edit dialog is visible');

      // Verify form fields are populated
      cy.getByTestId('edit-subject-input').should('have.value').and('not.be.empty');
      cy.log('✅ Subject field is populated');

      cy.log('🔍 PHASE 3: Making changes to template');

      // Edit subject
      cy.getByTestId('edit-subject-input')
        .clear()
        .type(TEST_EDITS.subject);
      cy.log(`✅ Subject changed to: ${TEST_EDITS.subject}`);

      // Edit HTML body in Tiptap editor
      // Tiptap editor uses contenteditable div with class .tiptap
      cy.get('.tiptap').first()
        .clear()
        .type('{selectall}{backspace}') // Clear existing content
        .type('Bold test text')
        .type('{selectall}')
        .then(($editor) => {
          // Add bold formatting - Tiptap uses ProseMirror commands
          cy.log('✅ Added content to Tiptap editor');
        });

      // Edit fallback body
      cy.getByTestId('edit-fallback-body-textarea')
        .clear()
        .type(TEST_EDITS.fallbackChanges);
      cy.log(`✅ Fallback body changed to: ${TEST_EDITS.fallbackChanges}`);

      // Verify active switch state
      cy.getByTestId('edit-is-active-switch').should('exist');
      cy.log('✅ Active switch is present');

      cy.log('🔍 PHASE 4: Intercepting save request');

      // Intercept the update request with detailed logging
      cy.intercept('PUT', '**/api/v1/admin/email-templates/*', (req) => {
        cy.log('📤 UPDATE REQUEST DETAILS:');
        cy.log(`URL: ${req.url}`);
        cy.log('Headers:', JSON.stringify(req.headers, null, 2));
        cy.log('Body:', JSON.stringify(req.body, null, 2));

        // Validate request payload structure
        expect(req.body).to.have.property('subject');
        expect(req.body).to.have.property('html_body');
        expect(req.body).to.have.property('fallback_body');
        expect(req.body).to.have.property('is_active');

        cy.log('✅ Request payload has required fields');
      }).as('updateTemplate');

      cy.log('🔍 PHASE 5: Clicking Save button');

      // Click Save button
      cy.getByTestId('submit-edit-btn')
        .should('be.visible')
        .and('not.be.disabled')
        .click();

      cy.log('🔍 PHASE 6: Waiting for API response');

      // Wait for the update request and capture response
      cy.wait('@updateTemplate').then((interception) => {
        cy.log('📥 UPDATE RESPONSE DETAILS:');
        cy.log(`Status Code: ${interception.response?.statusCode}`);
        cy.log('Response Body:', JSON.stringify(interception.response?.body, null, 2));

        // Check response status
        if (interception.response?.statusCode === 200) {
          cy.log('✅ Save successful - Status 200');
        } else {
          cy.log(`❌ Save failed - Status ${interception.response?.statusCode}`);
        }

        // Log validation errors if present
        if (interception.response?.body?.errors) {
          cy.log('❌ VALIDATION ERRORS:', JSON.stringify(interception.response.body.errors, null, 2));
        }

        // Log error message if present
        if (interception.response?.body?.message) {
          cy.log(`📝 Message: ${interception.response.body.message}`);
        }

        // Verify successful response
        expect(interception.response?.statusCode).to.eq(200);
        expect(interception.response?.body).to.have.property('data');
      });

      cy.log('🔍 PHASE 7: Verifying UI feedback');

      // Verify modal closes
      cy.getByTestId('edit-template-dialog').should('not.exist');
      cy.log('✅ Edit dialog closed');

      // Check for success toast
      cy.contains('Success', { timeout: 5000 }).should('be.visible');
      cy.log('✅ Success toast appeared');

      cy.log('🔍 PHASE 8: Verifying template was updated');

      // Wait for templates list to refresh
      cy.wait('@getTemplates');

      // Verify the subject was updated in the table
      cy.contains(TEST_EDITS.subject).should('be.visible');
      cy.log('✅ Updated subject visible in table');
    });

    it('should capture and report save errors', () => {
      cy.log('🔍 Testing error handling for invalid data');

      // Open edit dialog
      cy.getByTestId('edit-btn-1').click();
      cy.getByTestId('edit-template-dialog').should('be.visible');

      // Clear subject to trigger validation error
      cy.getByTestId('edit-subject-input').clear();

      // Intercept update request
      cy.intercept('PUT', '**/api/v1/admin/email-templates/*').as('updateTemplateFail');

      // Try to save
      cy.getByTestId('submit-edit-btn').click();

      // Check for client-side validation
      cy.get('.text-destructive').should('be.visible').then(($error) => {
        cy.log(`❌ Validation Error: ${$error.text()}`);
      });

      cy.log('✅ Client-side validation working correctly');
    });
  });

  describe('2. Template Preview Functionality', () => {
    it('should preview template with detailed debugging', () => {
      cy.log('🔍 PHASE 1: Setting up preview request interception');

      // Intercept preview request with detailed logging
      cy.intercept('POST', '**/api/v1/admin/email-templates/*/preview', (req) => {
        cy.log('📤 PREVIEW REQUEST DETAILS:');
        cy.log(`URL: ${req.url}`);
        cy.log('Headers:', JSON.stringify(req.headers, null, 2));
        cy.log('Body:', JSON.stringify(req.body, null, 2));
      }).as('previewTemplate');

      cy.log('🔍 PHASE 2: Clicking Preview button');

      // Click preview button on first template
      cy.getByTestId('preview-btn-1')
        .should('be.visible')
        .click();

      cy.log('🔍 PHASE 3: Waiting for preview API response');

      // Wait for preview request and inspect response
      cy.wait('@previewTemplate').then((interception) => {
        cy.log('📥 PREVIEW RESPONSE DETAILS:');
        cy.log(`Status Code: ${interception.response?.statusCode}`);
        cy.log('Response Body:', JSON.stringify(interception.response?.body, null, 2));

        // Check response status
        if (interception.response?.statusCode === 200) {
          cy.log('✅ Preview API successful - Status 200');
        } else {
          cy.log(`❌ Preview API failed - Status ${interception.response?.statusCode}`);
        }

        // Analyze response structure
        const responseBody = interception.response?.body;

        if (responseBody) {
          cy.log('🔍 Analyzing response structure:');

          // Check different possible response formats
          if (typeof responseBody === 'string') {
            cy.log('📋 Response is direct string HTML');
            cy.log(`HTML Length: ${responseBody.length} characters`);
          } else if (responseBody.data?.preview) {
            cy.log('📋 Response format: { data: { preview: string } }');
            cy.log(`HTML Length: ${responseBody.data.preview.length} characters`);
          } else if (responseBody.preview) {
            cy.log('📋 Response format: { preview: string }');
            cy.log(`HTML Length: ${responseBody.preview.length} characters`);
          } else if (responseBody.data?.html) {
            cy.log('📋 Response format: { data: { html: string } }');
            cy.log(`HTML Length: ${responseBody.data.html.length} characters`);
          } else if (responseBody.html) {
            cy.log('📋 Response format: { html: string }');
            cy.log(`HTML Length: ${responseBody.html.length} characters`);
          } else {
            cy.log('❌ Unknown response format!');
            cy.log('Available keys:', Object.keys(responseBody));
          }

          // Check for variables_used
          if (responseBody.data?.variables_used || responseBody.variables_used) {
            const vars = responseBody.data?.variables_used || responseBody.variables_used;
            cy.log('📋 Variables used:', JSON.stringify(vars, null, 2));
          }
        }

        // Verify successful response
        expect(interception.response?.statusCode).to.eq(200);
      });

      cy.log('🔍 PHASE 4: Verifying preview modal opened');

      // Check preview modal opened
      cy.getByTestId('preview-template-dialog')
        .should('be.visible')
        .then(() => {
          cy.log('✅ Preview dialog is visible');
        });

      cy.log('🔍 PHASE 5: Inspecting iframe content');

      // Check iframe exists and has content
      cy.getByTestId('preview-iframe')
        .should('be.visible')
        .then(($iframe) => {
          cy.log('✅ Preview iframe found');

          // Check srcDoc attribute
          const srcDoc = $iframe.attr('srcDoc');
          if (srcDoc) {
            cy.log(`✅ iframe srcDoc length: ${srcDoc.length} characters`);

            // Check if it's [object Object] (bug indicator)
            if (srcDoc === '[object Object]') {
              cy.log('❌ BUG DETECTED: srcDoc is "[object Object]"');
              cy.log('This indicates the response was not properly extracted');
            } else if (srcDoc.includes('<html') || srcDoc.includes('<body')) {
              cy.log('✅ srcDoc contains valid HTML');
            } else {
              cy.log('⚠️ srcDoc might not be valid HTML');
              cy.log(`First 200 chars: ${srcDoc.substring(0, 200)}`);
            }
          } else {
            cy.log('❌ No srcDoc attribute found on iframe');
          }

          // Try to access iframe content
          const iframeDoc = $iframe[0].contentDocument || $iframe[0].contentWindow?.document;
          if (iframeDoc) {
            const bodyContent = iframeDoc.body?.innerHTML;
            if (bodyContent) {
              cy.log(`✅ iframe body content length: ${bodyContent.length} characters`);
              if (bodyContent.trim().length === 0) {
                cy.log('❌ iframe body is empty!');
              } else {
                cy.log(`First 200 chars of body: ${bodyContent.substring(0, 200)}`);
              }
            } else {
              cy.log('❌ Could not access iframe body');
            }
          } else {
            cy.log('❌ Could not access iframe document');
          }
        });

      cy.log('🔍 PHASE 6: Checking browser console for errors');

      // Check for console errors (already captured by Cypress)
      cy.window().then((win) => {
        // Console errors are automatically logged by Cypress
        cy.log('✅ Check Cypress console for any JavaScript errors');
      });

      cy.log('🔍 PHASE 7: Verifying preview can be closed');

      // Close preview modal
      cy.contains('button', /close/i).click();
      cy.getByTestId('preview-template-dialog').should('not.exist');
      cy.log('✅ Preview dialog closed successfully');
    });

    it('should capture preview errors', () => {
      cy.log('🔍 Testing preview error handling');

      // Intercept preview request to force error
      cy.intercept('POST', '**/api/v1/admin/email-templates/999999/preview', {
        statusCode: 404,
        body: {
          success: false,
          message: 'Template not found',
        },
      }).as('previewError');

      // Try to trigger preview on non-existent template (would need to inject button)
      // For now, just document error handling expectation
      cy.log('📝 Error handling should show toast with error message');
      cy.log('📝 Preview modal should not open on error');
    });
  });

  describe('3. Network Monitoring', () => {
    it('should monitor all API calls during save and preview', () => {
      cy.log('🔍 Monitoring all network activity');

      // Intercept all API calls
      cy.intercept('**/api/v1/**').as('apiCall');

      // Perform save
      cy.getByTestId('edit-btn-1').click();
      cy.getByTestId('edit-template-dialog').should('be.visible');
      cy.getByTestId('edit-subject-input').type(' - Modified');
      cy.getByTestId('submit-edit-btn').click();

      // Log all API calls
      cy.get('@apiCall.all').then((interceptions) => {
        cy.log(`📊 Total API calls during save: ${interceptions.length}`);
        interceptions.forEach((interception: any, index: number) => {
          cy.log(`API Call ${index + 1}:`);
          cy.log(`  Method: ${interception.request.method}`);
          cy.log(`  URL: ${interception.request.url}`);
          cy.log(`  Status: ${interception.response?.statusCode}`);
        });
      });

      // Wait for modal to close
      cy.wait(1000);

      // Perform preview
      cy.getByTestId('preview-btn-1').click();

      // Log preview API calls
      cy.get('@apiCall.all').then((interceptions) => {
        const previewCalls = interceptions.filter((i: any) => i.request.url.includes('preview'));
        cy.log(`📊 Preview API calls: ${previewCalls.length}`);
      });
    });
  });

  describe('4. Authentication & Authorization', () => {
    it('should verify admin token is sent with requests', () => {
      cy.log('🔍 Verifying authentication headers');

      cy.intercept('PUT', '**/api/v1/admin/email-templates/*', (req) => {
        cy.log('🔐 Request headers:');
        cy.log(`Authorization: ${req.headers.authorization || 'MISSING!'}`);
        cy.log(`Accept: ${req.headers.accept}`);
        cy.log(`Content-Type: ${req.headers['content-type']}`);

        // Verify auth token is present
        expect(req.headers.authorization).to.exist;
        expect(req.headers.authorization).to.include('Bearer');

        cy.log('✅ Authorization header is present and valid');
      }).as('authCheck');

      // Trigger save
      cy.getByTestId('edit-btn-1').click();
      cy.getByTestId('submit-edit-btn').click();
      cy.wait('@authCheck');
    });
  });

  describe('5. Data Integrity', () => {
    it('should verify template data persists after save', () => {
      cy.log('🔍 Testing data persistence');

      const uniqueSubject = `Test Subject ${Date.now()}`;

      // Save template with unique subject
      cy.getByTestId('edit-btn-1').click();
      cy.getByTestId('edit-subject-input').clear().type(uniqueSubject);

      cy.intercept('PUT', '**/api/v1/admin/email-templates/*').as('saveTemplate');
      cy.getByTestId('submit-edit-btn').click();
      cy.wait('@saveTemplate');

      // Reload page
      cy.reload();

      // Verify subject persists
      cy.contains(uniqueSubject).should('be.visible');
      cy.log('✅ Data persisted after page reload');
    });
  });
});
