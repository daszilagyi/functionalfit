/// <reference types="cypress" />

describe('Authentication Flow', () => {
  beforeEach(() => {
    // Visit login page before each test
    cy.visit('/login');
  });

  context('Login with Valid Credentials', () => {
    it('should login successfully with client credentials', () => {
      cy.fixture('users').then((users) => {
        const client = users.clients[0];

        // Intercept login API call
        cy.intercept('POST', '**/api/v1/auth/login').as('loginRequest');

        // Fill login form
        cy.get('input[type="email"]').type(client.email);
        cy.get('input[type="password"]').type(client.password);
        cy.get('button[type="submit"]').click();

        // Wait for API response
        cy.waitForApi('@loginRequest').then((interception) => {
          expect(interception.response?.statusCode).to.eq(200);
          expect(interception.response?.body).to.have.property('token');
          expect(interception.response?.body.user).to.have.property('role', 'client');
        });

        // Should redirect to classes page (client default)
        cy.url().should('include', '/classes');

        // Should store token in localStorage
        cy.window().then((win) => {
          expect(win.localStorage.getItem('auth_token')).to.exist;
        });
      });
    });

    it('should login successfully with staff credentials', () => {
      cy.fixture('users').then((users) => {
        const staff = users.staff[0];

        cy.intercept('POST', '**/api/v1/auth/login').as('loginRequest');

        cy.get('input[type="email"]').type(staff.email);
        cy.get('input[type="password"]').type(staff.password);
        cy.get('button[type="submit"]').click();

        cy.waitForApi('@loginRequest');

        // Staff should see calendar by default
        cy.url().should('include', '/calendar');
      });
    });

    it('should login successfully with admin credentials', () => {
      cy.fixture('users').then((users) => {
        const admin = users.admin;

        cy.intercept('POST', '**/api/v1/auth/login').as('loginRequest');

        cy.get('input[type="email"]').type(admin.email);
        cy.get('input[type="password"]').type(admin.password);
        cy.get('button[type="submit"]').click();

        cy.waitForApi('@loginRequest');

        // Admin should see dashboard
        cy.url().should('include', '/dashboard');
      });
    });

    it('should display user name in navigation after login', () => {
      cy.fixture('users').then((users) => {
        const client = users.clients[0];

        cy.get('input[type="email"]').type(client.email);
        cy.get('input[type="password"]').type(client.password);
        cy.get('button[type="submit"]').click();

        // Wait for navigation
        cy.url().should('not.include', '/login');

        // Should show user name in layout
        cy.contains(client.name).should('be.visible');
      });
    });
  });

  context('Login with Invalid Credentials', () => {
    it('should show error message for incorrect password', () => {
      cy.fixture('users').then((users) => {
        const client = users.clients[0];

        cy.intercept('POST', '**/api/v1/auth/login').as('loginRequest');

        cy.get('input[type="email"]').type(client.email);
        cy.get('input[type="password"]').type('wrongpassword');
        cy.get('button[type="submit"]').click();

        cy.waitForApi('@loginRequest').then((interception) => {
          expect(interception.response?.statusCode).to.eq(401);
        });

        // Should show error toast or message
        cy.contains(/invalid.*credentials|incorrect.*password/i).should('be.visible');

        // Should remain on login page
        cy.url().should('include', '/login');
      });
    });

    it('should show error message for non-existent user', () => {
      cy.intercept('POST', '**/api/v1/auth/login').as('loginRequest');

      cy.get('input[type="email"]').type('nonexistent@example.com');
      cy.get('input[type="password"]').type('password');
      cy.get('button[type="submit"]').click();

      cy.waitForApi('@loginRequest').then((interception) => {
        expect(interception.response?.statusCode).to.eq(401);
      });

      cy.contains(/invalid.*credentials/i).should('be.visible');
    });

    it('should show validation error for invalid email format', () => {
      cy.get('input[type="email"]').type('notanemail');
      cy.get('input[type="password"]').type('password');
      cy.get('button[type="submit"]').click();

      // HTML5 validation or custom error message
      cy.get('input[type="email"]:invalid').should('exist');
    });

    it('should show validation error for empty fields', () => {
      cy.get('button[type="submit"]').click();

      // Should show required field errors
      cy.get('input[type="email"]:invalid').should('exist');
      cy.get('input[type="password"]:invalid').should('exist');
    });
  });

  context('Logout', () => {
    it('should logout successfully and clear session', () => {
      cy.fixture('users').then((users) => {
        const client = users.clients[0];

        // Login first
        cy.login(client.email, client.password);
        cy.visit('/classes');

        // Intercept logout API call
        cy.intercept('POST', '**/api/v1/auth/logout').as('logoutRequest');

        // Click user menu and logout
        cy.contains(client.name).click();
        cy.contains(/logout|kijelentkezés/i).click();

        cy.waitForApi('@logoutRequest');

        // Should redirect to login page
        cy.url().should('include', '/login');

        // Should clear localStorage
        cy.window().then((win) => {
          expect(win.localStorage.getItem('auth_token')).to.be.null;
        });
      });
    });

    it('should prevent access to protected routes after logout', () => {
      cy.fixture('users').then((users) => {
        const client = users.clients[0];

        // Login and logout
        cy.login(client.email, client.password);
        cy.visit('/classes');
        cy.contains(client.name).click();
        cy.contains(/logout|kijelentkezés/i).click();

        // Try to visit protected route
        cy.visit('/classes');

        // Should redirect to login
        cy.url().should('include', '/login');
      });
    });
  });

  context('Protected Routes', () => {
    it('should redirect unauthenticated users to login page', () => {
      // Try to access protected routes without login
      const protectedRoutes = ['/classes', '/calendar', '/dashboard', '/client/activity', '/staff', '/admin'];

      protectedRoutes.forEach((route) => {
        cy.visit(route);
        cy.url().should('include', '/login');
      });
    });

    it('should allow access to protected routes after login', () => {
      cy.fixture('users').then((users) => {
        const admin = users.admin;

        cy.login(admin.email, admin.password);

        // Should be able to access all routes
        cy.visit('/dashboard');
        cy.url().should('include', '/dashboard');

        cy.visit('/calendar');
        cy.url().should('include', '/calendar');

        cy.visit('/classes');
        cy.url().should('include', '/classes');
      });
    });
  });

  context('Role-Based Navigation', () => {
    it('should show client menu items for client role', () => {
      cy.fixture('users').then((users) => {
        const client = users.clients[0];

        cy.login(client.email, client.password);
        cy.visit('/classes');

        // Should see client navigation links
        cy.contains(/classes|órarend/i).should('be.visible');
        cy.contains(/my activity|aktivitás/i).should('be.visible');

        // Should NOT see staff/admin links
        cy.contains(/admin|dashboard/i).should('not.exist');
      });
    });

    it('should show staff menu items for staff role', () => {
      cy.fixture('users').then((users) => {
        const staff = users.staff[0];

        cy.login(staff.email, staff.password);
        cy.visit('/calendar');

        // Should see staff navigation links
        cy.contains(/calendar|naptár/i).should('be.visible');
        cy.contains(/staff|dolgozó/i).should('be.visible');

        // Should NOT see admin links
        cy.get('a[href="/admin"]').should('not.exist');
      });
    });

    it('should show admin menu items for admin role', () => {
      cy.fixture('users').then((users) => {
        const admin = users.admin;

        cy.login(admin.email, admin.password);
        cy.visit('/dashboard');

        // Should see admin navigation links
        cy.get('a[href="/admin"]').should('be.visible');
        cy.contains(/dashboard/i).should('be.visible');
        cy.contains(/calendar/i).should('be.visible');
      });
    });
  });

  context('Session Persistence', () => {
    it('should maintain session across page refreshes', () => {
      cy.fixture('users').then((users) => {
        const client = users.clients[0];

        cy.login(client.email, client.password);
        cy.visit('/classes');

        // Refresh page
        cy.reload();

        // Should still be logged in
        cy.url().should('include', '/classes');
        cy.contains(client.name).should('be.visible');
      });
    });

    it('should restore session from localStorage on app load', () => {
      cy.fixture('users').then((users) => {
        const client = users.clients[0];

        cy.login(client.email, client.password);

        // Store token manually
        cy.window().then((win) => {
          const token = win.localStorage.getItem('auth_token');
          expect(token).to.exist;

          // Clear and set again
          win.localStorage.clear();
          win.localStorage.setItem('auth_token', token as string);
        });

        // Visit app
        cy.visit('/classes');

        // Should be authenticated
        cy.url().should('include', '/classes');
      });
    });
  });
});
