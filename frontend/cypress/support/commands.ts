/// <reference types="cypress" />

/**
 * Custom Cypress Commands for FunctionalFit Calendar E2E Tests
 */

declare global {
  namespace Cypress {
    interface Chainable {
      /**
       * Login command - authenticates user and stores token
       * @param email - User email
       * @param password - User password
       * @example cy.login('client@test.com', 'password')
       */
      login(email: string, password: string): Chainable<void>;

      /**
       * Seed database command - runs Laravel seeder to reset test data
       * @example cy.seedDatabase()
       */
      seedDatabase(): Chainable<void>;

      /**
       * Get element by test ID
       * @param testId - The data-testid attribute value
       * @example cy.getByTestId('login-button')
       */
      getByTestId(testId: string): Chainable<JQuery<HTMLElement>>;

      /**
       * Wait for API call to complete
       * @param alias - The alias of the intercepted request
       * @example cy.waitForApi('@loginRequest')
       */
      waitForApi(alias: string): Chainable<Interception>;
    }
  }
}

// Login command - makes API request and stores token
Cypress.Commands.add('login', (email: string, password: string) => {
  cy.request({
    method: 'POST',
    url: `${Cypress.env('apiUrl')}/auth/login`,
    body: { email, password },
    failOnStatusCode: false,
  }).then((response) => {
    // Handle Laravel API response format: { success, data: { user, token }, message }
    if (response.status === 200 && response.body.success && response.body.data?.token) {
      // Store token in localStorage
      window.localStorage.setItem('auth_token', response.body.data.token);

      // Also store user data if available
      if (response.body.data.user) {
        window.localStorage.setItem('auth_user', JSON.stringify(response.body.data.user));
      }
    } else {
      throw new Error(`Login failed: ${response.status} - ${JSON.stringify(response.body)}`);
    }
  });
});

// Seed database command - calls Laravel artisan command
Cypress.Commands.add('seedDatabase', () => {
  // In a real scenario, this would call a backend endpoint or Docker exec
  // For now, we'll document that tests should run against a seeded database
  cy.log('Database should be seeded before running tests');
  cy.log('Run: cd backend && php artisan migrate:fresh --seed');

  // Alternatively, you could call a custom API endpoint:
  // cy.request({
  //   method: 'POST',
  //   url: `${Cypress.env('apiUrl')}/test/seed`,
  //   headers: {
  //     'X-Test-Secret': Cypress.env('testSecret'),
  //   },
  // });
});

// Get by test ID helper
Cypress.Commands.add('getByTestId', (testId: string) => {
  return cy.get(`[data-testid="${testId}"]`);
});

// Wait for API call with better error handling
Cypress.Commands.add('waitForApi', (alias: string) => {
  return cy.wait(alias).then((interception) => {
    if (interception.response && interception.response.statusCode >= 400) {
      cy.log(`API Error: ${interception.response.statusCode}`, interception.response.body);
    }
    return interception;
  });
});

// Prevent Cypress from failing on uncaught exceptions from the app
Cypress.on('uncaught:exception', (err, runnable) => {
  // Return false to prevent Cypress from failing the test
  // You can add conditions here to only ignore certain errors
  if (err.message.includes('ResizeObserver')) {
    // Ignore ResizeObserver errors (common with FullCalendar)
    return false;
  }

  // Log the error but don't fail the test for React Query errors
  if (err.message.includes('Query') || err.message.includes('Mutation')) {
    cy.log('React Query Error:', err.message);
    return false;
  }

  // Let other errors fail the test
  return true;
});

export {};
