/// <reference types="cypress" />

describe('Admin Panel', () => {
  beforeEach(() => {
    cy.fixture('users').then((users) => {
      const admin = users.admin;
      cy.login(admin.email, admin.password);
    });
  });

  context('Admin Dashboard', () => {
    it('should display admin dashboard with overview stats', () => {
      cy.visit('/admin/dashboard');

      // Should show 4 stat cards
      cy.contains(/total.*users|összes.*felhasználó/i).should('be.visible');
      cy.contains(/total.*rooms|összes.*terem/i).should('be.visible');
      cy.contains(/total.*sessions|összes.*alkalom/i).should('be.visible');
      cy.contains(/attendance.*rate|részvételi.*arány/i).should('be.visible');
    });

    it('should display quick actions section', () => {
      cy.visit('/admin/dashboard');

      // Should show quick action buttons
      cy.contains(/create.*user|új.*felhasználó/i).should('be.visible');
      cy.contains(/create.*room|új.*terem/i).should('be.visible');
      cy.contains(/create.*template|új.*sablon/i).should('be.visible');
    });

    it('should auto-refresh stats every 60 seconds', () => {
      cy.intercept('GET', '**/api/v1/admin/dashboard/stats*').as('getStats');

      cy.visit('/admin/dashboard');

      cy.wait('@getStats');

      // Wait for auto-refresh
      cy.wait(61000);

      cy.wait('@getStats');
    });

    it('should display attendance breakdown chart', () => {
      cy.visit('/admin/dashboard');

      // Should show chart or breakdown
      cy.contains(/attended|részt.*vett/i).should('be.visible');
      cy.contains(/no.*show|nem.*jelent.*meg/i).should('be.visible');
    });

    it('should display session type breakdown', () => {
      cy.visit('/admin/dashboard');

      // Should show 1:1 vs Group class breakdown
      cy.contains(/1.*1|individual|egyéni/i).should('be.visible');
      cy.contains(/group|class|csoportos/i).should('be.visible');
    });

    it('should display top 5 staff members', () => {
      cy.visit('/admin/dashboard');

      cy.contains(/top.*staff|legjobb.*dolgozók/i).should('be.visible');
    });
  });

  context('Users Page - List', () => {
    it('should display users table with columns', () => {
      cy.visit('/admin/users');

      // Should show table headers
      cy.contains('th', /name|név/i).should('be.visible');
      cy.contains('th', /email/i).should('be.visible');
      cy.contains('th', /role|szerep/i).should('be.visible');
      cy.contains('th', /status/i).should('be.visible');
      cy.contains('th', /actions|műveletek/i).should('be.visible');
    });

    it('should load and display list of users', () => {
      cy.intercept('GET', '**/api/v1/admin/users*').as('getUsers');

      cy.visit('/admin/users');

      cy.waitForApi('@getUsers');

      // Should show user rows
      cy.get('tbody tr').should('have.length.at.least', 1);
    });

    it('should display user information in table cells', () => {
      cy.visit('/admin/users');

      // Each row should display user info
      cy.get('tbody tr').first().within(() => {
        cy.get('td').eq(0).should('not.be.empty'); // Name
        cy.get('td').eq(1).should('contain', '@'); // Email
        cy.get('td').eq(2).should('match', /(client|staff|admin)/i); // Role
      });
    });

    it('should show action buttons (Edit/Delete) for each user', () => {
      cy.visit('/admin/users');

      cy.get('tbody tr').first().within(() => {
        cy.contains(/edit|szerkeszt/i).should('be.visible');
        cy.contains(/delete|törlés/i).should('be.visible');
      });
    });
  });

  context('Users Page - Search & Filter', () => {
    it('should search users by name/email', () => {
      cy.intercept('GET', '**/api/v1/admin/users?search=*').as('searchUsers');

      cy.visit('/admin/users');

      // Type in search box
      cy.get('input[placeholder*="search"]').type('Anna');

      cy.waitForApi('@searchUsers').then((interception) => {
        expect(interception.request.url).to.include('search=Anna');
      });

      // Should show filtered results
      cy.contains('Anna').should('be.visible');
    });

    it('should filter users by role', () => {
      cy.intercept('GET', '**/api/v1/admin/users?role=client*').as('filterUsers');

      cy.visit('/admin/users');

      // Select role filter
      cy.get('select[name="role"]').select('client');

      cy.waitForApi('@filterUsers');

      // Should only show clients
      cy.contains('client').should('be.visible');
    });

    it('should clear search filter', () => {
      cy.intercept('GET', '**/api/v1/admin/users').as('getUsers');

      cy.visit('/admin/users');

      cy.get('input[placeholder*="search"]').type('Anna');
      cy.wait(500);

      // Clear search
      cy.get('input[placeholder*="search"]').clear();

      cy.wait('@getUsers').then((interception) => {
        expect(interception.request.url).not.to.include('search=');
      });
    });
  });

  context('Users Page - Create User', () => {
    it('should open create user modal when clicking Create button', () => {
      cy.visit('/admin/users');

      cy.contains(/create.*user|új.*felhasználó/i).click();

      // Modal should open
      cy.get('[role="dialog"]').should('be.visible');
      cy.contains(/create.*user|új.*felhasználó/i).should('be.visible');
    });

    it('should successfully create a new user', () => {
      cy.intercept('POST', '**/api/v1/admin/users').as('createUser');

      cy.visit('/admin/users');

      cy.contains(/create.*user|új.*felhasználó/i).click();

      // Fill form
      cy.get('input[name="name"]').type('Test User');
      cy.get('input[name="email"]').type('testuser@example.com');
      cy.get('input[name="password"]').type('SecurePassword123');
      cy.get('select[name="role"]').select('client');

      // Submit
      cy.contains(/save|submit|mentés/i).click();

      cy.waitForApi('@createUser').then((interception) => {
        expect(interception.response?.statusCode).to.eq(201);
        expect(interception.request.body).to.deep.include({
          name: 'Test User',
          email: 'testuser@example.com',
          role: 'client',
        });
      });

      // Should show success toast
      cy.contains(/created.*successfully|sikeres.*létrehozás/i).should('be.visible');

      // Modal should close
      cy.get('[role="dialog"]').should('not.exist');
    });

    it('should validate required fields', () => {
      cy.visit('/admin/users');

      cy.contains(/create.*user|új.*felhasználó/i).click();

      // Try to submit empty form
      cy.contains(/save|submit|mentés/i).click();

      // Should show validation errors
      cy.contains(/required|kötelező/i).should('be.visible');
    });

    it('should validate email format', () => {
      cy.visit('/admin/users');

      cy.contains(/create.*user|új.*felhasználó/i).click();

      cy.get('input[name="email"]').type('notanemail');

      cy.contains(/save|submit|mentés/i).click();

      // Should show email validation error
      cy.contains(/invalid.*email|érvénytelen.*email/i).should('be.visible');
    });

    it('should show error for duplicate email (409)', () => {
      cy.intercept('POST', '**/api/v1/admin/users', {
        statusCode: 409,
        body: {
          message: 'Email already exists',
        },
      }).as('createUser');

      cy.visit('/admin/users');

      cy.contains(/create.*user|új.*felhasználó/i).click();

      cy.get('input[name="name"]').type('Duplicate User');
      cy.get('input[name="email"]').type('anna.szabo@example.com');
      cy.get('input[name="password"]').type('password');
      cy.get('select[name="role"]').select('client');

      cy.contains(/save|submit|mentés/i).click();

      cy.wait('@createUser');

      cy.contains(/already.*exists|már.*létezik/i).should('be.visible');
    });

    it('should validate password strength', () => {
      cy.visit('/admin/users');

      cy.contains(/create.*user|új.*felhasználó/i).click();

      cy.get('input[name="password"]').type('123');

      cy.contains(/save|submit|mentés/i).click();

      // Should show password validation error (min 8 chars)
      cy.contains(/minimum.*8|legalább.*8/i).should('be.visible');
    });
  });

  context('Users Page - Edit User', () => {
    it('should open edit modal when clicking Edit button', () => {
      cy.visit('/admin/users');

      // Click edit on first user
      cy.get('tbody tr').first().contains(/edit|szerkeszt/i).click();

      // Modal should open
      cy.get('[role="dialog"]').should('be.visible');
      cy.contains(/edit.*user|felhasználó.*szerkeszt/i).should('be.visible');
    });

    it('should pre-fill form with existing user data', () => {
      cy.visit('/admin/users');

      cy.get('tbody tr').first().contains(/edit|szerkeszt/i).click();

      // Form fields should be pre-filled
      cy.get('input[name="name"]').should('not.have.value', '');
      cy.get('input[name="email"]').should('not.have.value', '');
      cy.get('select[name="role"]').should('not.have.value', '');
    });

    it('should successfully update user information', () => {
      cy.intercept('PUT', '**/api/v1/admin/users/*').as('updateUser');

      cy.visit('/admin/users');

      cy.get('tbody tr').first().contains(/edit|szerkeszt/i).click();

      // Modify fields
      cy.get('input[name="name"]').clear().type('Updated Name');

      cy.contains(/save|mentés/i).click();

      cy.waitForApi('@updateUser').then((interception) => {
        expect(interception.response?.statusCode).to.eq(200);
        expect(interception.request.body.name).to.eq('Updated Name');
      });

      cy.contains(/updated.*successfully|sikeres.*frissítés/i).should('be.visible');
    });

    it('should update user without changing password', () => {
      cy.intercept('PUT', '**/api/v1/admin/users/*').as('updateUser');

      cy.visit('/admin/users');

      cy.get('tbody tr').first().contains(/edit|szerkeszt/i).click();

      // Don't fill password field
      cy.get('input[name="name"]').clear().type('Updated Name');

      cy.contains(/save|mentés/i).click();

      cy.waitForApi('@updateUser').then((interception) => {
        // Password should not be in request body
        expect(interception.request.body).not.to.have.property('password');
      });
    });
  });

  context('Users Page - Delete User', () => {
    it('should show delete confirmation dialog', () => {
      cy.visit('/admin/users');

      cy.get('tbody tr').first().contains(/delete|törlés/i).click();

      // Confirmation dialog should appear
      cy.contains(/are you sure|biztos/i).should('be.visible');
      cy.contains(/cannot be undone|nem.*visszavonható/i).should('be.visible');
    });

    it('should successfully delete user after confirmation', () => {
      cy.intercept('DELETE', '**/api/v1/admin/users/*').as('deleteUser');

      cy.visit('/admin/users');

      cy.get('tbody tr').first().contains(/delete|törlés/i).click();

      // Confirm deletion
      cy.contains(/confirm|delete|megerősít/i).click();

      cy.waitForApi('@deleteUser').then((interception) => {
        expect(interception.response?.statusCode).to.eq(204);
      });

      cy.contains(/deleted.*successfully|sikeres.*törlés/i).should('be.visible');
    });

    it('should cancel deletion when clicking cancel', () => {
      cy.visit('/admin/users');

      cy.get('tbody tr').first().contains(/delete|törlés/i).click();

      // Cancel deletion
      cy.contains(/cancel|mégse/i).click();

      // Dialog should close, user still in list
      cy.get('[role="alertdialog"]').should('not.exist');
    });
  });

  context('Rooms Page - CRUD Operations', () => {
    it('should display rooms table', () => {
      cy.visit('/admin/rooms');

      cy.contains('th', /name|név/i).should('be.visible');
      cy.contains('th', /site|telephely/i).should('be.visible');
      cy.contains('th', /capacity|kapacitás/i).should('be.visible');
      cy.contains('th', /color|szín/i).should('be.visible');
    });

    it('should create new room', () => {
      cy.intercept('POST', '**/api/v1/admin/rooms').as('createRoom');

      cy.visit('/admin/rooms');

      cy.contains(/create.*room|új.*terem/i).click();

      cy.get('input[name="name"]').type('New Studio');
      cy.get('select[name="site"]').select('SASAD');
      cy.get('input[name="capacity"]').clear().type('15');
      cy.get('input[name="color"]').type('#FF5733');

      cy.contains(/save|mentés/i).click();

      cy.waitForApi('@createRoom').then((interception) => {
        expect(interception.response?.statusCode).to.eq(201);
        expect(interception.request.body).to.include({
          name: 'New Studio',
          site: 'SASAD',
          capacity: 15,
        });
      });

      cy.contains(/created.*successfully|sikeres.*létrehozás/i).should('be.visible');
    });

    it('should edit existing room', () => {
      cy.intercept('PUT', '**/api/v1/admin/rooms/*').as('updateRoom');

      cy.visit('/admin/rooms');

      cy.get('tbody tr').first().contains(/edit|szerkeszt/i).click();

      cy.get('input[name="capacity"]').clear().type('20');

      cy.contains(/save|mentés/i).click();

      cy.waitForApi('@updateRoom');

      cy.contains(/updated.*successfully|sikeres.*frissítés/i).should('be.visible');
    });

    it('should delete room with confirmation', () => {
      cy.intercept('DELETE', '**/api/v1/admin/rooms/*').as('deleteRoom');

      cy.visit('/admin/rooms');

      cy.get('tbody tr').first().contains(/delete|törlés/i).click();

      cy.contains(/confirm|megerősít/i).click();

      cy.waitForApi('@deleteRoom');

      cy.contains(/deleted.*successfully|sikeres.*törlés/i).should('be.visible');
    });

    it('should validate capacity (min 1)', () => {
      cy.visit('/admin/rooms');

      cy.contains(/create.*room|új.*terem/i).click();

      cy.get('input[name="capacity"]').clear().type('0');

      cy.contains(/save|mentés/i).click();

      cy.contains(/minimum.*1|legalább.*1/i).should('be.visible');
    });

    it('should show error when deleting room with active bookings', () => {
      cy.intercept('DELETE', '**/api/v1/admin/rooms/*', {
        statusCode: 409,
        body: {
          message: 'Room has active bookings',
        },
      }).as('deleteRoom');

      cy.visit('/admin/rooms');

      cy.get('tbody tr').first().contains(/delete|törlés/i).click();
      cy.contains(/confirm|megerősít/i).click();

      cy.wait('@deleteRoom');

      cy.contains(/active.*bookings|aktív.*foglalás/i).should('be.visible');
    });
  });

  context('Class Templates Page - CRUD Operations', () => {
    it('should display class templates table', () => {
      cy.visit('/admin/class-templates');

      cy.contains('th', /name|név/i).should('be.visible');
      cy.contains('th', /duration|időtartam/i).should('be.visible');
      cy.contains('th', /capacity|kapacitás/i).should('be.visible');
      cy.contains('th', /credits|kredit/i).should('be.visible');
      cy.contains('th', /status/i).should('be.visible');
    });

    it('should create new class template', () => {
      cy.intercept('POST', '**/api/v1/admin/class-templates').as('createTemplate');

      cy.visit('/admin/class-templates');

      cy.contains(/create.*template|új.*sablon/i).click();

      cy.get('input[name="name"]').type('New Yoga Class');
      cy.get('textarea[name="description"]').type('Relaxing yoga session');
      cy.get('input[name="duration_min"]').clear().type('60');
      cy.get('input[name="capacity"]').clear().type('12');
      cy.get('input[name="credits_required"]').clear().type('1');
      cy.get('input[name="color"]').type('#4CAF50');

      cy.contains(/save|mentés/i).click();

      cy.waitForApi('@createTemplate').then((interception) => {
        expect(interception.response?.statusCode).to.eq(201);
        expect(interception.request.body).to.include({
          name: 'New Yoga Class',
          duration_min: 60,
          capacity: 12,
          credits_required: 1,
        });
      });

      cy.contains(/created.*successfully|sikeres.*létrehozás/i).should('be.visible');
    });

    it('should edit class template', () => {
      cy.intercept('PUT', '**/api/v1/admin/class-templates/*').as('updateTemplate');

      cy.visit('/admin/class-templates');

      cy.get('tbody tr').first().contains(/edit|szerkeszt/i).click();

      cy.get('input[name="duration_min"]').clear().type('90');

      cy.contains(/save|mentés/i).click();

      cy.waitForApi('@updateTemplate');

      cy.contains(/updated.*successfully|sikeres.*frissítés/i).should('be.visible');
    });

    it('should toggle template active/inactive status', () => {
      cy.intercept('PATCH', '**/api/v1/admin/class-templates/*/status').as('toggleStatus');

      cy.visit('/admin/class-templates');

      // Click active/inactive switch
      cy.get('tbody tr').first().find('[role="switch"]').click();

      cy.waitForApi('@toggleStatus');

      cy.contains(/status.*updated|állapot.*frissítve/i).should('be.visible');
    });

    it('should delete class template', () => {
      cy.intercept('DELETE', '**/api/v1/admin/class-templates/*').as('deleteTemplate');

      cy.visit('/admin/class-templates');

      cy.get('tbody tr').first().contains(/delete|törlés/i).click();
      cy.contains(/confirm|megerősít/i).click();

      cy.waitForApi('@deleteTemplate');

      cy.contains(/deleted.*successfully|sikeres.*törlés/i).should('be.visible');
    });

    it('should validate duration range (15-480 min)', () => {
      cy.visit('/admin/class-templates');

      cy.contains(/create.*template|új.*sablon/i).click();

      cy.get('input[name="duration_min"]').clear().type('600');

      cy.contains(/save|mentés/i).click();

      cy.contains(/maximum.*480|maximum.*8.*hours/i).should('be.visible');
    });

    it('should show template name with color indicator', () => {
      cy.visit('/admin/class-templates');

      // Should show colored badge or indicator
      cy.get('tbody tr').first().find('[style*="background"]').should('exist');
    });
  });

  context('Reports Page', () => {
    it('should display reports page with 5 tabs', () => {
      cy.visit('/admin/reports');

      cy.contains(/attendance|részvétel/i).should('be.visible');
      cy.contains(/payouts|kifizetés/i).should('be.visible');
      cy.contains(/revenue|bevétel/i).should('be.visible');
      cy.contains(/utilization|kihasználtság/i).should('be.visible');
      cy.contains(/client.*activity|ügyfél.*aktivitás/i).should('be.visible');
    });

    it('should load attendance report data', () => {
      cy.intercept('GET', '**/api/v1/admin/reports/attendance*').as('getAttendance');

      cy.visit('/admin/reports');

      cy.contains(/attendance|részvétel/i).click();

      cy.waitForApi('@getAttendance');

      // Should show attendance stats
      cy.contains(/total.*sessions|összes.*alkalom/i).should('be.visible');
    });

    it('should apply date range filter to reports', () => {
      cy.intercept('GET', '**/api/v1/admin/reports/attendance*from=*').as('getFilteredReport');

      cy.visit('/admin/reports');

      cy.get('input[name="date_from"]').type('2025-11-01');
      cy.get('input[name="date_to"]').type('2025-11-15');

      cy.wait('@getFilteredReport').then((interception) => {
        expect(interception.request.url).to.include('from=2025-11-01');
        expect(interception.request.url).to.include('to=2025-11-15');
      });
    });

    it('should export report to Excel', () => {
      cy.intercept('GET', '**/api/v1/admin/reports/attendance/export*', {
        statusCode: 200,
        headers: {
          'content-type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        },
        body: 'mock-xlsx-content',
      }).as('exportReport');

      cy.visit('/admin/reports');

      cy.contains(/export|exportál/i).click();

      cy.waitForApi('@exportReport');

      cy.contains(/download|letöltés/i).should('be.visible');
    });

    it('should load payouts report', () => {
      cy.intercept('GET', '**/api/v1/admin/reports/payouts*').as('getPayouts');

      cy.visit('/admin/reports');

      cy.contains(/payouts|kifizetés/i).click();

      cy.waitForApi('@getPayouts');

      // Should show staff earnings table
      cy.contains(/staff|dolgozó/i).should('be.visible');
      cy.contains(/hours|órák/i).should('be.visible');
      cy.contains(/total|összes/i).should('be.visible');
    });

    it('should load revenue report', () => {
      cy.intercept('GET', '**/api/v1/admin/reports/revenue*').as('getRevenue');

      cy.visit('/admin/reports');

      cy.contains(/revenue|bevétel/i).click();

      cy.waitForApi('@getRevenue');

      cy.contains(/total.*revenue|összes.*bevétel/i).should('be.visible');
    });
  });

  context('Authorization (RBAC)', () => {
    it('should show 403 error when non-admin tries to access admin panel', () => {
      cy.fixture('users').then((users) => {
        const staff = users.staff[0];

        // Logout admin and login as staff
        cy.clearLocalStorage();
        cy.login(staff.email, staff.password);

        cy.visit('/admin');

        // Should show forbidden or redirect
        cy.url().should('not.include', '/admin');
      });
    });

    it('should hide admin menu link for non-admin users', () => {
      cy.fixture('users').then((users) => {
        const client = users.clients[0];

        cy.clearLocalStorage();
        cy.login(client.email, client.password);

        cy.visit('/classes');

        // Should NOT see admin link
        cy.get('a[href="/admin"]').should('not.exist');
      });
    });
  });

  context('Error Handling', () => {
    it('should show error toast when API call fails', () => {
      cy.intercept('GET', '**/api/v1/admin/users*', {
        statusCode: 500,
        body: {
          message: 'Internal server error',
        },
      }).as('getUsers');

      cy.visit('/admin/users');

      cy.wait('@getUsers');

      cy.contains(/error|failed|hiba/i).should('be.visible');
    });

    it('should handle validation errors (422) gracefully', () => {
      cy.intercept('POST', '**/api/v1/admin/users', {
        statusCode: 422,
        body: {
          errors: {
            email: ['The email field is required'],
            password: ['The password must be at least 8 characters'],
          },
        },
      }).as('createUser');

      cy.visit('/admin/users');

      cy.contains(/create.*user|új.*felhasználó/i).click();
      cy.get('input[name="name"]').type('Test');
      cy.contains(/save|mentés/i).click();

      cy.wait('@createUser');

      // Should show specific field errors
      cy.contains(/email.*required/i).should('be.visible');
      cy.contains(/password.*8.*characters/i).should('be.visible');
    });
  });

  context('Pagination', () => {
    it('should paginate users list', () => {
      cy.intercept('GET', '**/api/v1/admin/users?page=2*').as('getPage2');

      cy.visit('/admin/users');

      // Click next page
      cy.contains(/next|következő/i).click();

      cy.waitForApi('@getPage2').then((interception) => {
        expect(interception.request.url).to.include('page=2');
      });
    });

    it('should display page info (showing X-Y of Z)', () => {
      cy.visit('/admin/users');

      cy.contains(/showing|megjelenítve/i).should('be.visible');
    });
  });
});
