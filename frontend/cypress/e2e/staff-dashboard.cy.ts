/// <reference types="cypress" />

describe('Staff Dashboard', () => {
  beforeEach(() => {
    cy.fixture('users').then((users) => {
      const staff = users.staff[0];
      cy.login(staff.email, staff.password);
    });
  });

  context('Dashboard Stats', () => {
    it('should display 4 KPI stat cards', () => {
      cy.visit('/staff');

      // Should show 4 stat cards
      cy.contains(/today.*sessions|mai.*alkalmak/i).should('be.visible');
      cy.contains(/today.*completed|ma.*befejezett/i).should('be.visible');
      cy.contains(/today.*remaining|ma.*hátralévő/i).should('be.visible');
      cy.contains(/week.*hours|heti.*órák/i).should('be.visible');
    });

    it('should load dashboard stats from API', () => {
      cy.intercept('GET', '**/api/v1/staff/dashboard/stats*').as('getStats');

      cy.visit('/staff');

      cy.waitForApi('@getStats').then((interception) => {
        expect(interception.response?.statusCode).to.eq(200);
        expect(interception.response?.body).to.have.property('today_total');
      });
    });

    it('should display correct stat values', () => {
      cy.intercept('GET', '**/api/v1/staff/dashboard/stats*', {
        statusCode: 200,
        body: {
          today_total: 5,
          today_completed: 3,
          today_remaining: 2,
          week_total_hours: 18.5,
        },
      }).as('getStats');

      cy.visit('/staff');

      cy.wait('@getStats');

      // Should show stat values
      cy.contains('5').should('be.visible'); // today_total
      cy.contains('3').should('be.visible'); // today_completed
      cy.contains('2').should('be.visible'); // today_remaining
      cy.contains('18.5').should('be.visible'); // week_total_hours
    });

    it('should auto-refresh stats every 60 seconds', () => {
      cy.intercept('GET', '**/api/v1/staff/dashboard/stats*').as('getStats');

      cy.visit('/staff');

      cy.wait('@getStats');

      // Wait for auto-refresh (60 seconds)
      cy.wait(61000);

      // Should trigger another API call
      cy.wait('@getStats');
    });

    it('should show loading state while fetching stats', () => {
      cy.intercept('GET', '**/api/v1/staff/dashboard/stats*', {
        delay: 1000,
      }).as('getStats');

      cy.visit('/staff');

      // Should show skeleton or loading indicator
      cy.get('[data-testid="skeleton"]').should('exist');

      cy.wait('@getStats');

      cy.get('[data-testid="skeleton"]').should('not.exist');
    });
  });

  context('Upcoming Session Card', () => {
    it('should display next upcoming session', () => {
      cy.intercept('GET', '**/api/v1/staff/my-events*').as('getEvents');

      cy.visit('/staff');

      cy.waitForApi('@getEvents');

      // Should show upcoming session card
      cy.contains(/next.*session|következő.*alkalom/i).should('be.visible');
    });

    it('should show client name for 1:1 sessions', () => {
      cy.intercept('GET', '**/api/v1/staff/my-events*', {
        statusCode: 200,
        body: [
          {
            id: 1,
            type: 'INDIVIDUAL',
            starts_at: '2025-11-20T14:00:00+01:00',
            ends_at: '2025-11-20T15:00:00+01:00',
            client: { full_name: 'Anna Szabó' },
            room: { name: 'Gym' },
          },
        ],
      }).as('getEvents');

      cy.visit('/staff');

      cy.wait('@getEvents');

      cy.contains('Anna Szabó').should('be.visible');
    });

    it('should show "Block Time" for BLOCK type events', () => {
      cy.intercept('GET', '**/api/v1/staff/my-events*', {
        statusCode: 200,
        body: [
          {
            id: 1,
            type: 'BLOCK',
            starts_at: '2025-11-20T12:00:00+01:00',
            ends_at: '2025-11-20T13:00:00+01:00',
            notes: 'Lunch break',
            room: { name: 'Office' },
          },
        ],
      }).as('getEvents');

      cy.visit('/staff');

      cy.wait('@getEvents');

      cy.contains(/block.*time|blokkolva/i).should('be.visible');
    });

    it('should display event time and duration', () => {
      cy.visit('/staff');

      // Should show start time, end time, and duration
      cy.contains(/\d{2}:\d{2}/).should('be.visible'); // Time format
      cy.contains(/60.*min|1.*hour/).should('exist'); // Duration
    });

    it('should display room name', () => {
      cy.visit('/staff');

      // Should show room name
      cy.contains(/room|terem/i).should('be.visible');
    });

    it('should show empty state when no upcoming sessions', () => {
      cy.intercept('GET', '**/api/v1/staff/my-events*', {
        statusCode: 200,
        body: [],
      }).as('getEvents');

      cy.visit('/staff');

      cy.wait('@getEvents');

      cy.contains(/no.*upcoming|nincs.*közelgő/i).should('be.visible');
    });
  });

  context('Export Payout Report', () => {
    it('should display export section with date range inputs', () => {
      cy.visit('/staff');

      // Should show export form
      cy.contains(/export|exportál/i).should('be.visible');
      cy.get('input[name="date_from"]').should('exist');
      cy.get('input[name="date_to"]').should('exist');
    });

    it('should successfully download payout report as XLSX', () => {
      cy.intercept('GET', '**/api/v1/staff/exports/payout*', {
        statusCode: 200,
        headers: {
          'content-type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          'content-disposition': 'attachment; filename=payout_2025-11-01_2025-11-15.xlsx',
        },
        body: 'mock-xlsx-content',
      }).as('downloadPayout');

      cy.visit('/staff');

      // Set date range
      cy.get('input[name="date_from"]').type('2025-11-01');
      cy.get('input[name="date_to"]').type('2025-11-15');

      // Select report type
      cy.get('select[name="report_type"]').select('payout');

      // Click export button
      cy.contains(/export|download|letöltés/i).click();

      cy.waitForApi('@downloadPayout');

      // Should show success toast
      cy.contains(/download.*started|letöltés.*kezdődött/i).should('be.visible');
    });

    it('should validate date range (from <= to)', () => {
      cy.visit('/staff');

      // Set invalid date range (to < from)
      cy.get('input[name="date_from"]').type('2025-11-15');
      cy.get('input[name="date_to"]').type('2025-11-01');

      cy.contains(/export|download|letöltés/i).click();

      // Should show validation error
      cy.contains(/invalid.*date.*range|érvénytelen.*dátum/i).should('be.visible');
    });

    it('should require date range selection', () => {
      cy.visit('/staff');

      // Try to export without date range
      cy.contains(/export|download|letöltés/i).click();

      // Should show validation error
      cy.contains(/required|kötelező/i).should('be.visible');
    });

    it('should download file with correct filename format', () => {
      cy.intercept('GET', '**/api/v1/staff/exports/payout*').as('downloadPayout');

      cy.visit('/staff');

      cy.get('input[name="date_from"]').type('2025-11-01');
      cy.get('input[name="date_to"]').type('2025-11-15');
      cy.get('select[name="report_type"]').select('payout');

      cy.contains(/export|download|letöltés/i).click();

      cy.waitForApi('@downloadPayout').then((interception) => {
        const contentDisposition = interception.response?.headers['content-disposition'];
        expect(contentDisposition).to.include('payout_2025-11-01_2025-11-15.xlsx');
      });
    });
  });

  context('Export Attendance Report', () => {
    it('should successfully download attendance report as XLSX', () => {
      cy.intercept('GET', '**/api/v1/staff/exports/attendance*', {
        statusCode: 200,
        headers: {
          'content-type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          'content-disposition': 'attachment; filename=attendance_2025-11-01_2025-11-15.xlsx',
        },
        body: 'mock-xlsx-content',
      }).as('downloadAttendance');

      cy.visit('/staff');

      cy.get('input[name="date_from"]').type('2025-11-01');
      cy.get('input[name="date_to"]').type('2025-11-15');

      cy.get('select[name="report_type"]').select('attendance');

      cy.contains(/export|download|letöltés/i).click();

      cy.waitForApi('@downloadAttendance');

      cy.contains(/download.*started|letöltés.*kezdődött/i).should('be.visible');
    });

    it('should handle download errors gracefully', () => {
      cy.intercept('GET', '**/api/v1/staff/exports/attendance*', {
        statusCode: 500,
        body: {
          message: 'Failed to generate report',
        },
      }).as('downloadAttendance');

      cy.visit('/staff');

      cy.get('input[name="date_from"]').type('2025-11-01');
      cy.get('input[name="date_to"]').type('2025-11-15');
      cy.get('select[name="report_type"]').select('attendance');

      cy.contains(/export|download|letöltés/i).click();

      cy.wait('@downloadAttendance');

      // Should show error toast
      cy.contains(/error|failed|hiba/i).should('be.visible');
    });
  });

  context('Check-in from Event Details', () => {
    it('should open event details modal from dashboard', () => {
      cy.visit('/staff');

      // Click on upcoming session card
      cy.contains(/next.*session|következő.*alkalom/i).parents('[data-testid^="session-card"]').click();

      // Should open event details modal
      cy.get('[role="dialog"]').should('be.visible');
    });

    it('should show check-in section for past events', () => {
      // Mock a past event
      cy.intercept('GET', '**/api/v1/staff/my-events*', {
        statusCode: 200,
        body: [
          {
            id: 1,
            type: 'INDIVIDUAL',
            starts_at: '2025-11-15T10:00:00+01:00',
            ends_at: '2025-11-15T11:00:00+01:00',
            status: 'scheduled',
            client: { full_name: 'Anna Szabó' },
          },
        ],
      }).as('getEvents');

      cy.visit('/staff');
      cy.wait('@getEvents');

      // Navigate to calendar to see past event
      cy.visit('/calendar');

      cy.get('.fc-event').first().click();

      // Should show check-in buttons
      cy.contains(/mark.*attended|check.*in/i).should('be.visible');
      cy.contains(/mark.*no.*show/i).should('be.visible');
    });

    it('should successfully mark client as attended with notes', () => {
      cy.intercept('POST', '**/api/v1/events/*/checkin', {
        statusCode: 200,
        body: {
          attended: true,
          pass_credit_deducted: true,
        },
      }).as('checkIn');

      cy.visit('/calendar');

      cy.get('.fc-event').first().click();

      // Add check-in notes
      cy.get('textarea[placeholder*="notes"]').type('Excellent session');

      cy.contains(/mark.*attended/i).click();

      cy.waitForApi('@checkIn').then((interception) => {
        expect(interception.request.body).to.have.property('attended', true);
        expect(interception.request.body.notes).to.eq('Excellent session');
      });

      cy.contains(/success|sikeres/i).should('be.visible');
    });

    it('should show pass credit deduction notification', () => {
      cy.intercept('POST', '**/api/v1/events/*/checkin', {
        statusCode: 200,
        body: {
          attended: true,
          pass_credit_deducted: true,
          credits_used: 1,
        },
      }).as('checkIn');

      cy.visit('/calendar');

      cy.get('.fc-event').first().click();

      cy.contains(/mark.*attended/i).click();

      cy.wait('@checkIn');

      // Should mention credit deduction
      cy.contains(/credit.*deducted|kredit.*levonva/i).should('be.visible');
    });

    it('should successfully mark client as no-show', () => {
      cy.intercept('POST', '**/api/v1/events/*/checkin', {
        statusCode: 200,
        body: {
          attended: false,
          pass_credit_deducted: true,
        },
      }).as('checkIn');

      cy.visit('/calendar');

      cy.get('.fc-event').first().click();

      cy.get('textarea[placeholder*="notes"]').type('Client did not show up');

      cy.contains(/mark.*no.*show/i).click();

      cy.waitForApi('@checkIn').then((interception) => {
        expect(interception.request.body).to.have.property('attended', false);
      });

      cy.contains(/success|sikeres/i).should('be.visible');
    });
  });

  context('Navigation', () => {
    it('should navigate to calendar from staff dashboard', () => {
      cy.visit('/staff');

      cy.contains(/calendar|naptár/i).click();

      cy.url().should('include', '/calendar');
    });

    it('should show staff menu link in navigation', () => {
      cy.visit('/staff');

      // Staff link should be visible in sidebar
      cy.get('a[href="/staff"]').should('be.visible');
    });
  });

  context('Responsive Design', () => {
    it('should display properly on mobile viewport', () => {
      cy.viewport('iphone-x');

      cy.visit('/staff');

      // Stats cards should stack vertically
      cy.contains(/today.*sessions|mai.*alkalmak/i).should('be.visible');
      cy.contains(/export|exportál/i).should('be.visible');
    });

    it('should display properly on tablet viewport', () => {
      cy.viewport('ipad-2');

      cy.visit('/staff');

      // Should show all elements properly
      cy.get('[data-testid^="stat-card-"]').should('have.length', 4);
    });
  });

  context('Error Handling', () => {
    it('should show error state when stats API fails', () => {
      cy.intercept('GET', '**/api/v1/staff/dashboard/stats*', {
        statusCode: 500,
        body: {
          message: 'Internal server error',
        },
      }).as('getStats');

      cy.visit('/staff');

      cy.wait('@getStats');

      // Should show error message
      cy.contains(/error|failed|hiba/i).should('be.visible');
    });

    it('should show error state when events API fails', () => {
      cy.intercept('GET', '**/api/v1/staff/my-events*', {
        statusCode: 500,
      }).as('getEvents');

      cy.visit('/staff');

      cy.wait('@getEvents');

      cy.contains(/error|failed|hiba/i).should('be.visible');
    });
  });
});
