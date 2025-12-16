/// <reference types="cypress" />

/**
 * Email Template Management E2E Tests
 *
 * Tests the complete email template CRUD workflow including:
 * - Listing templates
 * - Creating new templates
 * - Editing templates with versioning
 * - Testing email preview
 * - Sending test emails
 * - Version history and restoration
 */

describe('Email Template Management', () => {
  beforeEach(() => {
    // Login as admin
    cy.request('POST', 'http://localhost:8000/api/v1/auth/login', {
      email: 'admin@functionalfit.hu',
      password: 'password'
    }).then((response) => {
      window.localStorage.setItem('auth_token', response.body.data.token);
      window.localStorage.setItem('auth_user', JSON.stringify(response.body.data.user));
    });

    cy.visit('/admin/email-templates');
  });

  describe('Template List View', () => {
    it('displays list of email templates', () => {
      cy.get('[data-testid="email-templates-list"]').should('exist');
      cy.get('[data-testid="email-template-item"]').should('have.length.greaterThan', 0);
    });

    it('shows template basic information', () => {
      cy.get('[data-testid="email-template-item"]').first().within(() => {
        cy.get('[data-testid="template-subject"]').should('be.visible');
        cy.get('[data-testid="template-slug"]').should('be.visible');
        cy.get('[data-testid="template-status"]').should('be.visible');
      });
    });

    it('filters templates by active status', () => {
      cy.get('[data-testid="filter-active"]').click();
      cy.get('[data-testid="email-template-item"]').each(($el) => {
        cy.wrap($el).find('[data-testid="template-status"]')
          .should('contain', 'Active');
      });
    });

    it('searches templates by slug or subject', () => {
      cy.get('[data-testid="search-input"]').type('registration');
      cy.get('[data-testid="email-template-item"]').should('have.length.lessThan', 10);
      cy.get('[data-testid="template-slug"]').first()
        .should('contain', 'registration');
    });

    it('navigates to create template page', () => {
      cy.get('[data-testid="create-template-btn"]').click();
      cy.url().should('include', '/admin/email-templates/new');
    });
  });

  describe('Create Template', () => {
    beforeEach(() => {
      cy.get('[data-testid="create-template-btn"]').click();
    });

    it('displays template creation form', () => {
      cy.get('[data-testid="template-form"]').should('exist');
      cy.get('[data-testid="input-slug"]').should('be.visible');
      cy.get('[data-testid="input-subject"]').should('be.visible');
      cy.get('[data-testid="editor-html-body"]').should('be.visible');
    });

    it('shows available variables panel', () => {
      cy.get('[data-testid="variables-panel"]').should('exist');
      cy.get('[data-testid="variable-item"]').should('have.length.greaterThan', 0);
    });

    it('validates required fields', () => {
      cy.get('[data-testid="save-template-btn"]').click();

      cy.get('[data-testid="error-slug"]').should('be.visible');
      cy.get('[data-testid="error-subject"]').should('be.visible');
      cy.get('[data-testid="error-html-body"]').should('be.visible');
    });

    it('validates slug format', () => {
      cy.get('[data-testid="input-slug"]').type('Invalid Slug!');
      cy.get('[data-testid="save-template-btn"]').click();

      cy.get('[data-testid="error-slug"]')
        .should('contain', 'slug must be lowercase');
    });

    it('successfully creates new template', () => {
      const slug = `test-template-${Date.now()}`;

      cy.get('[data-testid="input-slug"]').type(slug);
      cy.get('[data-testid="input-subject"]').type('Test Email Template');

      // Use Tiptap editor
      cy.get('[data-testid="editor-html-body"]')
        .find('.tiptap').type('Hello {{user.name}}, welcome!');

      cy.get('[data-testid="input-fallback-body"]')
        .type('Hello {{user.name}}, welcome!');

      cy.get('[data-testid="save-template-btn"]').click();

      cy.url().should('include', '/admin/email-templates');
      cy.get('[data-testid="success-message"]')
        .should('contain', 'Template created successfully');
    });

    it('inserts variables via click', () => {
      cy.get('[data-testid="variable-item"]').first().click();

      cy.get('[data-testid="editor-html-body"]')
        .find('.tiptap')
        .should('contain', '{{');
    });

    it('previews template before saving', () => {
      cy.get('[data-testid="input-subject"]').type('Test Subject {{user.name}}');
      cy.get('[data-testid="editor-html-body"]')
        .find('.tiptap').type('Hello {{user.name}}');

      cy.get('[data-testid="preview-btn"]').click();

      cy.get('[data-testid="preview-modal"]').should('be.visible');
      cy.get('[data-testid="preview-subject"]').should('not.contain', '{{user.name}}');
      cy.get('[data-testid="preview-body"]').should('not.contain', '{{user.name}}');
    });
  });

  describe('Edit Template', () => {
    beforeEach(() => {
      cy.get('[data-testid="email-template-item"]').first().click();
    });

    it('displays template details', () => {
      cy.get('[data-testid="template-form"]').should('exist');
      cy.get('[data-testid="input-subject"]').should('not.be.empty');
      cy.get('[data-testid="editor-html-body"]').should('exist');
    });

    it('shows current version number', () => {
      cy.get('[data-testid="version-badge"]').should('be.visible');
      cy.get('[data-testid="version-number"]').should('exist');
    });

    it('successfully updates template', () => {
      const newSubject = `Updated Subject ${Date.now()}`;

      cy.get('[data-testid="input-subject"]').clear().type(newSubject);
      cy.get('[data-testid="save-template-btn"]').click();

      cy.get('[data-testid="success-message"]')
        .should('contain', 'Template updated successfully');

      cy.get('[data-testid="input-subject"]').should('have.value', newSubject);
    });

    it('increments version after update', () => {
      cy.get('[data-testid="version-number"]').invoke('text').then((versionBefore) => {
        cy.get('[data-testid="input-subject"]').clear().type('Modified Subject');
        cy.get('[data-testid="save-template-btn"]').click();

        cy.get('[data-testid="version-number"]').should('not.contain', versionBefore);
      });
    });

    it('toggles active status', () => {
      cy.get('[data-testid="toggle-active"]').click();
      cy.get('[data-testid="save-template-btn"]').click();

      cy.get('[data-testid="success-message"]').should('be.visible');
      cy.get('[data-testid="template-status"]').should('contain', 'Inactive');
    });
  });

  describe('Template Preview & Test Email', () => {
    beforeEach(() => {
      cy.get('[data-testid="email-template-item"]').first().click();
    });

    it('opens preview modal', () => {
      cy.get('[data-testid="preview-btn"]').click();
      cy.get('[data-testid="preview-modal"]').should('be.visible');
    });

    it('renders variables in preview', () => {
      cy.get('[data-testid="preview-btn"]').click();

      // Check that variables are replaced with sample data
      cy.get('[data-testid="preview-body"]').should('not.contain', '{{');
      cy.get('[data-testid="preview-subject"]').should('not.contain', '{{');
    });

    it('allows custom variables for preview', () => {
      cy.get('[data-testid="preview-btn"]').click();
      cy.get('[data-testid="custom-variables-toggle"]').click();

      cy.get('[data-testid="variable-user-name"]').clear().type('Custom Name');
      cy.get('[data-testid="update-preview-btn"]').click();

      cy.get('[data-testid="preview-body"]').should('contain', 'Custom Name');
    });

    it('sends test email', () => {
      cy.get('[data-testid="send-test-btn"]').click();

      cy.get('[data-testid="test-email-modal"]').should('be.visible');
      cy.get('[data-testid="input-test-email"]').type('test@example.com');
      cy.get('[data-testid="confirm-send-test-btn"]').click();

      cy.get('[data-testid="success-message"]')
        .should('contain', 'Test email queued successfully');
    });

    it('validates email address for test email', () => {
      cy.get('[data-testid="send-test-btn"]').click();
      cy.get('[data-testid="input-test-email"]').type('invalid-email');
      cy.get('[data-testid="confirm-send-test-btn"]').click();

      cy.get('[data-testid="error-test-email"]')
        .should('contain', 'valid email');
    });
  });

  describe('Version History', () => {
    beforeEach(() => {
      cy.get('[data-testid="email-template-item"]').first().click();
    });

    it('displays version history panel', () => {
      cy.get('[data-testid="version-history-btn"]').click();
      cy.get('[data-testid="version-history-panel"]').should('be.visible');
    });

    it('lists previous versions', () => {
      cy.get('[data-testid="version-history-btn"]').click();
      cy.get('[data-testid="version-item"]').should('have.length.greaterThan', 0);
    });

    it('shows version details on click', () => {
      cy.get('[data-testid="version-history-btn"]').click();
      cy.get('[data-testid="version-item"]').first().click();

      cy.get('[data-testid="version-details"]').should('be.visible');
      cy.get('[data-testid="version-subject"]').should('exist');
      cy.get('[data-testid="version-created-at"]').should('exist');
    });

    it('restores from previous version', () => {
      cy.get('[data-testid="version-history-btn"]').click();
      cy.get('[data-testid="version-item"]').first().click();

      cy.get('[data-testid="restore-version-btn"]').click();
      cy.get('[data-testid="confirm-restore-btn"]').click();

      cy.get('[data-testid="success-message"]')
        .should('contain', 'Template restored');
    });

    it('shows confirmation before restore', () => {
      cy.get('[data-testid="version-history-btn"]').click();
      cy.get('[data-testid="version-item"]').first().click();
      cy.get('[data-testid="restore-version-btn"]').click();

      cy.get('[data-testid="confirm-modal"]').should('be.visible');
      cy.get('[data-testid="confirm-message"]')
        .should('contain', 'restore this version');
    });
  });

  describe('Tiptap Editor Features', () => {
    beforeEach(() => {
      cy.get('[data-testid="create-template-btn"]').click();
    });

    it('shows formatting toolbar', () => {
      cy.get('[data-testid="editor-toolbar"]').should('be.visible');
      cy.get('[data-testid="btn-bold"]').should('exist');
      cy.get('[data-testid="btn-italic"]').should('exist');
      cy.get('[data-testid="btn-link"]').should('exist');
    });

    it('applies bold formatting', () => {
      cy.get('[data-testid="editor-html-body"]')
        .find('.tiptap').type('Bold text');

      cy.get('[data-testid="editor-html-body"]')
        .find('.tiptap').selectAll();

      cy.get('[data-testid="btn-bold"]').click();

      cy.get('[data-testid="editor-html-body"]')
        .find('.tiptap strong').should('exist');
    });

    it('inserts links', () => {
      cy.get('[data-testid="btn-link"]').click();
      cy.get('[data-testid="input-link-url"]').type('https://functionalfit.hu');
      cy.get('[data-testid="input-link-text"]').type('Visit our site');
      cy.get('[data-testid="insert-link-btn"]').click();

      cy.get('[data-testid="editor-html-body"]')
        .find('.tiptap a').should('have.attr', 'href', 'https://functionalfit.hu');
    });

    it('inserts images', () => {
      cy.get('[data-testid="btn-image"]').click();
      cy.get('[data-testid="input-image-url"]').type('https://example.com/logo.png');
      cy.get('[data-testid="input-image-alt"]').type('Logo');
      cy.get('[data-testid="insert-image-btn"]').click();

      cy.get('[data-testid="editor-html-body"]')
        .find('.tiptap img').should('have.attr', 'src', 'https://example.com/logo.png');
    });

    it('creates bullet lists', () => {
      cy.get('[data-testid="btn-bullet-list"]').click();
      cy.get('[data-testid="editor-html-body"]')
        .find('.tiptap').type('Item 1{enter}Item 2{enter}Item 3');

      cy.get('[data-testid="editor-html-body"]')
        .find('.tiptap ul li').should('have.length', 3);
    });

    it('creates numbered lists', () => {
      cy.get('[data-testid="btn-ordered-list"]').click();
      cy.get('[data-testid="editor-html-body"]')
        .find('.tiptap').type('First{enter}Second{enter}Third');

      cy.get('[data-testid="editor-html-body"]')
        .find('.tiptap ol li').should('have.length', 3);
    });
  });

  describe('Delete Template', () => {
    it('shows confirmation before delete', () => {
      cy.get('[data-testid="email-template-item"]').first().click();
      cy.get('[data-testid="delete-template-btn"]').click();

      cy.get('[data-testid="confirm-modal"]').should('be.visible');
      cy.get('[data-testid="confirm-message"]')
        .should('contain', 'delete this template');
    });

    it('successfully deletes template', () => {
      cy.get('[data-testid="email-template-item"]').first()
        .invoke('attr', 'data-template-slug').then((slug) => {
          cy.get('[data-testid="email-template-item"]').first().click();
          cy.get('[data-testid="delete-template-btn"]').click();
          cy.get('[data-testid="confirm-delete-btn"]').click();

          cy.url().should('include', '/admin/email-templates');
          cy.get('[data-testid="success-message"]')
            .should('contain', 'Template deleted');

          cy.get(`[data-template-slug="${slug}"]`).should('not.exist');
        });
    });

    it('cancels delete on cancel button', () => {
      cy.get('[data-testid="email-template-item"]').first().click();
      cy.get('[data-testid="delete-template-btn"]').click();
      cy.get('[data-testid="cancel-delete-btn"]').click();

      cy.get('[data-testid="confirm-modal"]').should('not.exist');
      cy.get('[data-testid="template-form"]').should('be.visible');
    });
  });

  describe('Permission & Error Handling', () => {
    it('requires admin role to access templates', () => {
      // Logout and login as staff
      cy.clearLocalStorage();
      cy.request('POST', 'http://localhost:8000/api/v1/auth/login', {
        email: 'staff@functionalfit.hu',
        password: 'password'
      }).then((response) => {
        window.localStorage.setItem('auth_token', response.body.data.token);
      });

      cy.visit('/admin/email-templates');
      cy.url().should('not.include', '/admin/email-templates');
      cy.get('[data-testid="error-forbidden"]').should('be.visible');
    });

    it('handles API errors gracefully', () => {
      cy.intercept('GET', '/api/v1/admin/email-templates', {
        statusCode: 500,
        body: { message: 'Internal Server Error' }
      }).as('getTemplatesError');

      cy.visit('/admin/email-templates');
      cy.wait('@getTemplatesError');

      cy.get('[data-testid="error-message"]')
        .should('contain', 'Failed to load templates');
    });

    it('shows loading state while fetching templates', () => {
      cy.intercept('GET', '/api/v1/admin/email-templates', (req) => {
        req.on('response', (res) => {
          res.setDelay(1000);
        });
      }).as('getTemplates');

      cy.visit('/admin/email-templates');
      cy.get('[data-testid="loading-spinner"]').should('be.visible');
      cy.wait('@getTemplates');
      cy.get('[data-testid="loading-spinner"]').should('not.exist');
    });
  });

  describe('Accessibility', () => {
    it('has proper heading hierarchy', () => {
      cy.get('h1').should('have.length', 1);
      cy.get('h1').should('contain', 'Email Templates');
    });

    it('has accessible form labels', () => {
      cy.get('[data-testid="create-template-btn"]').click();

      cy.get('label[for="slug"]').should('exist');
      cy.get('label[for="subject"]').should('exist');
      cy.get('label[for="html-body"]').should('exist');
    });

    it('supports keyboard navigation', () => {
      cy.get('[data-testid="create-template-btn"]').focus().type('{enter}');
      cy.url().should('include', '/admin/email-templates/new');

      cy.get('[data-testid="input-slug"]').should('have.focus');
    });

    it('has ARIA labels for icon buttons', () => {
      cy.get('[data-testid="create-template-btn"]').click();

      cy.get('[data-testid="btn-bold"]')
        .should('have.attr', 'aria-label', 'Bold');
      cy.get('[data-testid="btn-italic"]')
        .should('have.attr', 'aria-label', 'Italic');
    });
  });
});
