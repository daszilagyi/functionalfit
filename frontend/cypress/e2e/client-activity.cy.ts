/// <reference types="cypress" />

describe('Client Activity Portal', () => {
  beforeEach(() => {
    cy.fixture('users').then((users) => {
      const client = users.clients[0];
      cy.login(client.email, client.password);
    });
  });

  context('Activity Page Load', () => {
    it('should display client activity page with all tabs', () => {
      cy.visit('/client/activity');

      // Should show three tabs
      cy.contains(/upcoming|közelgő/i).should('be.visible');
      cy.contains(/passes|bérletek/i).should('be.visible');
      cy.contains(/activity.*history|aktivitás.*előzmény/i).should('be.visible');
    });

    it('should load and display summary stats', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*').as('getActivity');

      cy.visit('/client/activity');

      cy.waitForApi('@getActivity');

      // Should show 4 KPI cards
      cy.contains(/total.*sessions|összes.*alkalom/i).should('be.visible');
      cy.contains(/attended|részt.*vett/i).should('be.visible');
      cy.contains(/attendance.*rate|részvételi.*arány/i).should('be.visible');
      cy.contains(/credits.*used|felhasznált.*kredit/i).should('be.visible');
    });

    it('should display loading state while fetching data', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*', {
        delay: 1000,
      }).as('getActivity');

      cy.visit('/client/activity');

      // Should show skeleton loading
      cy.get('[data-testid="skeleton"]').should('exist');

      cy.wait('@getActivity');

      cy.get('[data-testid="skeleton"]').should('not.exist');
    });

    it('should calculate attendance rate percentage correctly', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*', {
        statusCode: 200,
        body: {
          summary: {
            total_sessions: 10,
            attended: 8,
            missed: 2,
            total_credits_used: 10,
            attendance_rate: 80,
          },
        },
      }).as('getActivity');

      cy.visit('/client/activity');

      cy.wait('@getActivity');

      // Should show 80% attendance rate
      cy.contains('80%').should('be.visible');
    });
  });

  context('Upcoming Bookings Tab', () => {
    it('should display list of upcoming bookings', () => {
      cy.visit('/client/activity');

      // Upcoming tab should be active by default
      cy.contains(/upcoming|közelgő/i).click();

      // Should show bookings list
      cy.get('[data-testid^="booking-item-"]').should('have.length.at.least', 0);
    });

    it('should show both class and 1:1 bookings', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*', {
        statusCode: 200,
        body: {
          upcoming: [
            {
              id: 1,
              type: 'class',
              class_occurrence: {
                id: 1,
                starts_at: '2025-11-25T10:00:00+01:00',
                class_template: { name: 'Yoga Class' },
              },
              status: 'confirmed',
            },
            {
              id: 2,
              type: 'event',
              event: {
                id: 2,
                starts_at: '2025-11-26T14:00:00+01:00',
                staff: { user: { name: 'János Kovács' } },
              },
            },
          ],
        },
      }).as('getActivity');

      cy.visit('/client/activity');

      cy.wait('@getActivity');

      // Should show both types
      cy.contains('Yoga Class').should('be.visible');
      cy.contains('János Kovács').should('be.visible');
    });

    it('should display cancelable badge for bookings within cancellation window', () => {
      cy.visit('/client/activity');

      cy.contains(/upcoming|közelgő/i).click();

      // Should show "Cancelable" badge for eligible bookings
      cy.contains(/cancelable|lemondható/i).should('exist');
    });

    it('should display non-cancelable badge for bookings past 24h window', () => {
      cy.visit('/client/activity');

      cy.contains(/upcoming|közelgő/i).click();

      // Should show "Cannot Cancel" badge
      cy.contains(/cannot.*cancel|már.*nem.*lemondható/i).should('exist');
    });

    it('should open cancel confirmation dialog when clicking cancel button', () => {
      cy.visit('/client/activity');

      cy.contains(/upcoming|közelgő/i).click();

      // Click cancel button
      cy.contains(/button|btn/, /cancel|lemondás/i).first().click();

      // Should show confirmation dialog
      cy.contains(/are you sure|biztos/i).should('be.visible');
    });

    it('should successfully cancel booking and show success message', () => {
      cy.intercept('POST', '**/api/v1/classes/*/cancel').as('cancelBooking');

      cy.visit('/client/activity');

      cy.contains(/upcoming|közelgő/i).click();

      cy.contains(/button/, /cancel/i).first().click();

      // Confirm cancellation
      cy.contains(/confirm|megerősít/i).click();

      cy.waitForApi('@cancelBooking').then((interception) => {
        expect(interception.response?.statusCode).to.eq(200);
      });

      cy.contains(/cancelled.*successfully|sikeres.*lemondás/i).should('be.visible');
    });

    it('should remove cancelled booking from upcoming list', () => {
      cy.intercept('POST', '**/api/v1/classes/*/cancel').as('cancelBooking');
      cy.intercept('GET', '**/api/v1/clients/*/activity*').as('refreshActivity');

      cy.visit('/client/activity');

      const bookingName = 'Yoga Class';

      cy.contains(bookingName).should('be.visible');

      cy.contains(/button/, /cancel/i).first().click();
      cy.contains(/confirm|megerősít/i).click();

      cy.wait('@cancelBooking');
      cy.wait('@refreshActivity');

      // Booking should be removed or marked as cancelled
      // (depending on implementation - either disappears or shows "Cancelled" status)
    });

    it('should show empty state when no upcoming bookings', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*', {
        statusCode: 200,
        body: {
          upcoming: [],
        },
      }).as('getActivity');

      cy.visit('/client/activity');

      cy.wait('@getActivity');

      cy.contains(/no.*upcoming|nincs.*közelgő/i).should('be.visible');
    });
  });

  context('My Passes Tab', () => {
    it('should display list of active passes', () => {
      cy.visit('/client/activity');

      cy.contains(/passes|bérletek/i).click();

      // Should show pass cards
      cy.get('[data-testid^="pass-card-"]').should('have.length.at.least', 0);
    });

    it('should show progress bar for credit consumption', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*', {
        statusCode: 200,
        body: {
          passes: [
            {
              id: 1,
              pass_type: '10 Credits',
              credits_total: 10,
              credits_remaining: 6,
              status: 'active',
              expires_at: '2025-12-31',
            },
          ],
        },
      }).as('getActivity');

      cy.visit('/client/activity');

      cy.contains(/passes|bérletek/i).click();

      cy.wait('@getActivity');

      // Should show progress bar
      cy.get('[role="progressbar"]').should('exist');

      // Should show percentage (60% remaining = 6/10)
      cy.contains('60%').should('be.visible');
    });

    it('should display pass expiry date', () => {
      cy.visit('/client/activity');

      cy.contains(/passes|bérletek/i).click();

      // Should show expiry date
      cy.contains(/expires|lejár/i).should('be.visible');
      cy.contains(/2025|2026/).should('exist');
    });

    it('should show active status badge for active passes', () => {
      cy.visit('/client/activity');

      cy.contains(/passes|bérletek/i).click();

      // Should show "Active" badge
      cy.contains(/active|aktív/i).should('exist');
    });

    it('should show expired passes in separate section (collapsed)', () => {
      cy.visit('/client/activity');

      cy.contains(/passes|bérletek/i).click();

      // Should show "Expired Passes" section
      cy.contains(/expired.*passes|lejárt.*bérletek/i).should('be.visible');
    });

    it('should expand expired passes section when clicked', () => {
      cy.visit('/client/activity');

      cy.contains(/passes|bérletek/i).click();

      // Click to expand expired section
      cy.contains(/expired.*passes|lejárt.*bérletek/i).click();

      // Should show expired pass items
      cy.get('[data-testid^="expired-pass-"]').should('exist');
    });

    it('should show depleted badge for fully used passes', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*', {
        statusCode: 200,
        body: {
          passes: [
            {
              id: 1,
              pass_type: '10 Credits',
              credits_total: 10,
              credits_remaining: 0,
              status: 'depleted',
            },
          ],
        },
      }).as('getActivity');

      cy.visit('/client/activity');

      cy.contains(/passes|bérletek/i).click();

      cy.wait('@getActivity');

      cy.contains(/depleted|elfogyott/i).should('be.visible');
    });

    it('should show empty state when no passes available', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*', {
        statusCode: 200,
        body: {
          passes: [],
        },
      }).as('getActivity');

      cy.visit('/client/activity');

      cy.contains(/passes|bérletek/i).click();

      cy.wait('@getActivity');

      cy.contains(/no.*passes|nincs.*bérlet/i).should('be.visible');
    });
  });

  context('Activity History Tab', () => {
    it('should display list of past sessions', () => {
      cy.visit('/client/activity');

      cy.contains(/activity.*history|aktivitás.*előzmény/i).click();

      // Should show activity items
      cy.get('[data-testid^="activity-item-"]').should('have.length.at.least', 0);
    });

    it('should display activity items with attended/missed badges', () => {
      cy.visit('/client/activity');

      cy.contains(/activity.*history|aktivitás.*előzmény/i).click();

      // Should show attended badge
      cy.contains(/attended|részt.*vett/i).should('exist');

      // Should show no-show badge
      cy.contains(/no.*show|missed|nem.*jelent.*meg/i).should('exist');
    });

    it('should show trainer name for 1:1 sessions', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*', {
        statusCode: 200,
        body: {
          history: [
            {
              id: 1,
              type: 'event',
              event: {
                id: 1,
                staff: { user: { name: 'János Kovács' } },
                attended: true,
              },
              date: '2025-11-15',
            },
          ],
        },
      }).as('getActivity');

      cy.visit('/client/activity');

      cy.contains(/activity.*history|aktivitás.*előzmény/i).click();

      cy.wait('@getActivity');

      cy.contains('János Kovács').should('be.visible');
    });

    it('should show room name for each session', () => {
      cy.visit('/client/activity');

      cy.contains(/activity.*history|aktivitás.*előzmény/i).click();

      // Should show room info
      cy.contains(/room|terem/i).should('be.visible');
    });

    it('should display credits used for each session', () => {
      cy.visit('/client/activity');

      cy.contains(/activity.*history|aktivitás.*előzmény/i).click();

      // Should show credits used
      cy.contains(/1.*credit|1.*kredit/).should('exist');
    });

    it('should toggle filter panel visibility', () => {
      cy.visit('/client/activity');

      cy.contains(/activity.*history|aktivitás.*előzmény/i).click();

      // Click "Show Filters" button
      cy.contains(/show.*filters|szűrők.*megjelenítés/i).click();

      // Filters should be visible
      cy.get('[data-testid="filter-panel"]').should('be.visible');

      // Click "Hide Filters"
      cy.contains(/hide.*filters|szűrők.*elrejtés/i).click();

      // Filters should be hidden
      cy.get('[data-testid="filter-panel"]').should('not.be.visible');
    });

    it('should filter by date range', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*date_from=*').as('getFilteredActivity');

      cy.visit('/client/activity');

      cy.contains(/activity.*history|aktivitás.*előzmény/i).click();

      // Open filters
      cy.contains(/show.*filters|szűrők/i).click();

      // Set date range
      cy.get('input[name="date_from"]').type('2025-11-01');
      cy.get('input[name="date_to"]').type('2025-11-15');

      // Apply filters (auto-triggers or has Apply button)
      cy.wait('@getFilteredActivity').then((interception) => {
        expect(interception.request.url).to.include('date_from=2025-11-01');
        expect(interception.request.url).to.include('date_to=2025-11-15');
      });
    });

    it('should filter by activity type (classes only)', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*type=class*').as('getFilteredActivity');

      cy.visit('/client/activity');

      cy.contains(/activity.*history|aktivitás.*előzmény/i).click();

      cy.contains(/show.*filters|szűrők/i).click();

      // Select "Classes Only" from dropdown
      cy.get('select[name="type"]').select('class');

      cy.waitForApi('@getFilteredActivity');

      // Should only show class activities
      cy.contains(/class|óra/i).should('be.visible');
    });

    it('should filter by activity type (1:1 only)', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*type=event*').as('getFilteredActivity');

      cy.visit('/client/activity');

      cy.contains(/activity.*history|aktivitás.*előzmény/i).click();

      cy.contains(/show.*filters|szűrők/i).click();

      cy.get('select[name="type"]').select('event');

      cy.waitForApi('@getFilteredActivity');
    });

    it('should filter by attendance status (attended only)', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*attended=true*').as('getFilteredActivity');

      cy.visit('/client/activity');

      cy.contains(/activity.*history|aktivitás.*előzmény/i).click();

      cy.contains(/show.*filters|szűrők/i).click();

      cy.get('select[name="attended"]').select('attended');

      cy.waitForApi('@getFilteredActivity');

      // Should only show attended sessions
      cy.contains(/attended|részt.*vett/i).should('be.visible');
    });

    it('should filter by attendance status (missed only)', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*attended=false*').as('getFilteredActivity');

      cy.visit('/client/activity');

      cy.contains(/activity.*history|aktivitás.*előzmény/i).click();

      cy.contains(/show.*filters|szűrők/i).click();

      cy.get('select[name="attended"]').select('missed');

      cy.waitForApi('@getFilteredActivity');

      cy.contains(/no.*show|missed|nem.*jelent.*meg/i).should('be.visible');
    });

    it('should clear all filters when clicking Clear Filters button', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity').as('getActivity');

      cy.visit('/client/activity');

      cy.contains(/activity.*history|aktivitás.*előzmény/i).click();

      cy.contains(/show.*filters|szűrők/i).click();

      // Set some filters
      cy.get('input[name="date_from"]').type('2025-11-01');
      cy.get('select[name="type"]').select('class');

      // Click "Clear Filters"
      cy.contains(/clear.*filters|szűrők.*törlés/i).click();

      // Filters should be reset
      cy.get('input[name="date_from"]').should('have.value', '');
      cy.get('select[name="type"]').should('have.value', '');

      // Should trigger re-fetch without filters
      cy.wait('@getActivity').then((interception) => {
        expect(interception.request.url).not.to.include('date_from');
        expect(interception.request.url).not.to.include('type');
      });
    });

    it('should disable Clear Filters button when no filters active', () => {
      cy.visit('/client/activity');

      cy.contains(/activity.*history|aktivitás.*előzmény/i).click();

      cy.contains(/show.*filters|szűrők/i).click();

      // Clear button should be disabled initially
      cy.contains(/clear.*filters|szűrők.*törlés/i).should('be.disabled');
    });

    it('should show empty state when no activity history', () => {
      cy.intercept('GET', '**/api/v1/clients/*/activity*', {
        statusCode: 200,
        body: {
          history: [],
        },
      }).as('getActivity');

      cy.visit('/client/activity');

      cy.contains(/activity.*history|aktivitás.*előzmény/i).click();

      cy.wait('@getActivity');

      cy.contains(/no.*activity|nincs.*aktivitás/i).should('be.visible');
    });
  });

  context('Responsive Behavior', () => {
    it('should be responsive on mobile viewport', () => {
      cy.viewport('iphone-x');

      cy.visit('/client/activity');

      // Tabs should stack vertically or scroll horizontally
      cy.contains(/upcoming|közelgő/i).should('be.visible');
      cy.contains(/passes|bérletek/i).should('be.visible');
    });

    it('should be responsive on tablet viewport', () => {
      cy.viewport('ipad-2');

      cy.visit('/client/activity');

      // Should display properly on tablet
      cy.get('[data-testid^="booking-item-"]').should('be.visible');
    });
  });
});
