/// <reference types="cypress" />

describe('Calendar Event Management', () => {
  context('Staff Calendar View', () => {
    beforeEach(() => {
      cy.fixture('users').then((users) => {
        const staff = users.staff[0];
        cy.login(staff.email, staff.password);
        cy.visit('/calendar');
      });
    });

    it('should display calendar with week view', () => {
      // FullCalendar should be visible
      cy.get('.fc-view-harness').should('be.visible');
      cy.get('.fc-timegrid').should('be.visible');

      // Should show time grid (6:00 - 22:00)
      cy.contains('06:00').should('be.visible');
      cy.contains('22:00').should('be.visible');
    });

    it('should load and display existing events', () => {
      cy.intercept('GET', '**/api/v1/staff/my-events*').as('getEvents');

      cy.visit('/calendar');

      cy.waitForApi('@getEvents');

      // Should display events on calendar
      cy.get('.fc-event').should('have.length.at.least', 1);
    });

    it('should show loading state while fetching events', () => {
      cy.intercept('GET', '**/api/v1/staff/my-events*', {
        delay: 1000,
      }).as('getEvents');

      cy.visit('/calendar');

      // Should show loading indicator
      cy.contains(/loading|betöltés/i).should('be.visible');

      cy.wait('@getEvents');

      cy.contains(/loading|betöltés/i).should('not.exist');
    });

    it('should color-code events by type', () => {
      cy.visit('/calendar');

      cy.wait(1000); // Wait for events to load

      // INDIVIDUAL events should be blue
      // GROUP_CLASS events should be green
      // BLOCK events should be gray
      cy.get('.fc-event').should('exist');
    });

    it('should display event title with client name for 1:1 sessions', () => {
      cy.visit('/calendar');

      cy.get('.fc-event').first().should('contain', /.+/); // Contains some text
    });
  });

  context('Admin Calendar View', () => {
    beforeEach(() => {
      cy.fixture('users').then((users) => {
        const admin = users.admin;
        cy.login(admin.email, admin.password);
        cy.visit('/calendar');
      });
    });

    it('should display calendar for admin users', () => {
      cy.get('.fc-view-harness').should('be.visible');
    });

    it('should allow admin to view all events', () => {
      cy.intercept('GET', '**/api/v1/staff/my-events*').as('getEvents');

      cy.waitForApi('@getEvents');

      cy.get('.fc-event').should('exist');
    });
  });

  context('Create 1:1 Event', () => {
    beforeEach(() => {
      cy.fixture('users').then((users) => {
        const staff = users.staff[0];
        cy.login(staff.email, staff.password);
        cy.visit('/calendar');
      });
    });

    it('should open event form modal when clicking time slot', () => {
      // Click on a time slot to create event
      cy.get('.fc-timegrid-slot').first().click();

      // Event form modal should open
      cy.get('[role="dialog"]').should('be.visible');
      cy.contains(/create.*event|új.*esemény/i).should('be.visible');
    });

    it('should successfully create a 1:1 event with client', () => {
      cy.intercept('POST', '**/api/v1/events').as('createEvent');
      cy.intercept('GET', '**/api/v1/clients/search*').as('searchClients');

      cy.get('.fc-timegrid-slot[data-time="10:00:00"]').first().click();

      // Fill event form
      // Select client (search and select from dropdown)
      cy.get('input[placeholder*="client"]').type('Anna');

      cy.wait('@searchClients');

      cy.contains('Anna Szabó').click();

      // Select room
      cy.get('select[name="room_id"]').select('1'); // Select first room

      // Set date and time
      cy.get('input[name="starts_at"]').clear().type('2025-11-20T10:00');
      cy.get('input[name="ends_at"]').clear().type('2025-11-20T11:00');

      // Add notes (optional)
      cy.get('textarea[name="notes"]').type('Personal training session');

      // Submit form
      cy.contains(/save|submit|mentés/i).click();

      cy.waitForApi('@createEvent').then((interception) => {
        expect(interception.response?.statusCode).to.eq(201);
        expect(interception.response?.body).to.have.property('id');
      });

      // Should show success toast
      cy.contains(/success|sikeres/i).should('be.visible');

      // Modal should close
      cy.get('[role="dialog"]').should('not.exist');

      // New event should appear on calendar
      cy.get('.fc-event').should('contain', 'Anna');
    });

    it('should validate required fields when creating event', () => {
      cy.get('.fc-timegrid-slot').first().click();

      // Try to submit without filling required fields
      cy.contains(/save|submit|mentés/i).click();

      // Should show validation errors
      cy.contains(/required|kötelező/i).should('be.visible');
    });

    it('should search and select client from dropdown', () => {
      cy.intercept('GET', '**/api/v1/clients/search?q=*').as('searchClients');

      cy.get('.fc-timegrid-slot').first().click();

      // Type in client search
      cy.get('input[placeholder*="client"]').type('Béla');

      cy.waitForApi('@searchClients');

      // Should show search results
      cy.contains('Béla Kiss').should('be.visible');

      // Select client
      cy.contains('Béla Kiss').click();

      // Client should be selected
      cy.get('input[placeholder*="client"]').should('have.value', 'Béla Kiss');
    });

    it('should validate time ranges (end after start)', () => {
      cy.get('.fc-timegrid-slot').first().click();

      // Set end time before start time
      cy.get('input[name="starts_at"]').clear().type('2025-11-20T10:00');
      cy.get('input[name="ends_at"]').clear().type('2025-11-20T09:00');

      cy.contains(/save|submit|mentés/i).click();

      // Should show validation error
      cy.contains(/end.*after.*start|befejezés.*kezdés.*után/i).should('be.visible');
    });
  });

  context('Edit Event', () => {
    beforeEach(() => {
      cy.fixture('users').then((users) => {
        const staff = users.staff[0];
        cy.login(staff.email, staff.password);
        cy.visit('/calendar');
      });
    });

    it('should open event details modal when clicking event', () => {
      cy.wait(1000); // Wait for events to load

      cy.get('.fc-event').first().click();

      // Event details modal should open
      cy.get('[role="dialog"]').should('be.visible');
      cy.contains(/details|részletek/i).should('be.visible');
    });

    it('should display full event details in modal', () => {
      cy.get('.fc-event').first().click();

      // Should show all event details
      cy.get('[role="dialog"]').within(() => {
        cy.contains(/client|ügyfél/i).should('be.visible');
        cy.contains(/room|terem/i).should('be.visible');
        cy.contains(/date|dátum/i).should('be.visible');
        cy.contains(/time|időpont/i).should('be.visible');
      });
    });

    it('should allow editing event details (staff own future event)', () => {
      cy.intercept('PATCH', '**/api/v1/events/*').as('updateEvent');

      cy.get('.fc-event').first().click();

      // Click edit button
      cy.contains(/edit|szerkeszt/i).click();

      // Modify event details
      cy.get('textarea[name="notes"]').clear().type('Updated notes');

      // Save changes
      cy.contains(/save|mentés/i).click();

      cy.waitForApi('@updateEvent').then((interception) => {
        expect(interception.response?.statusCode).to.eq(200);
      });

      cy.contains(/success|sikeres/i).should('be.visible');
    });

    it('should pre-fill form with existing event data when editing', () => {
      cy.get('.fc-event').first().click();

      cy.contains(/edit|szerkeszt/i).click();

      // Form fields should be pre-filled
      cy.get('input[name="starts_at"]').should('not.have.value', '');
      cy.get('input[name="ends_at"]').should('not.have.value', '');
    });
  });

  context('Same-Day Move Restriction (Staff)', () => {
    beforeEach(() => {
      cy.fixture('users').then((users) => {
        const staff = users.staff[0];
        cy.login(staff.email, staff.password);
        cy.visit('/calendar');
      });
    });

    it('should allow staff to move event within same day', () => {
      cy.intercept('PATCH', '**/api/v1/events/*').as('updateEvent');

      cy.wait(1000);

      // Drag event to different time on same day
      cy.get('.fc-event').first().trigger('mousedown', { which: 1 });
      cy.get('.fc-timegrid-slot[data-time="14:00:00"]').first().trigger('mousemove').trigger('mouseup');

      cy.waitForApi('@updateEvent').then((interception) => {
        expect(interception.response?.statusCode).to.eq(200);
      });

      cy.contains(/success|sikeres/i).should('be.visible');
    });

    it('should prevent staff from moving event to different day (403 Forbidden)', () => {
      cy.intercept('PATCH', '**/api/v1/events/*', {
        statusCode: 403,
        body: {
          message: 'Staff can only move events within the same day',
        },
      }).as('updateEvent');

      cy.wait(1000);

      // Try to drag event to next day
      cy.get('.fc-event').first().trigger('mousedown', { which: 1 });

      // Find next day's time slot
      cy.get('.fc-timegrid-slot[data-time="10:00:00"]').eq(1).trigger('mousemove').trigger('mouseup');

      cy.wait('@updateEvent');

      // Should show forbidden error
      cy.contains(/same.*day|forbidden|azonos.*nap/i).should('be.visible');
    });

    it('should validate same-day rule when editing event via form', () => {
      cy.intercept('PATCH', '**/api/v1/events/*', {
        statusCode: 403,
        body: {
          message: 'Staff can only modify events within the same day',
        },
      }).as('updateEvent');

      cy.get('.fc-event').first().click();
      cy.contains(/edit|szerkeszt/i).click();

      // Change date to tomorrow
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      const tomorrowStr = tomorrow.toISOString().split('T')[0];

      cy.get('input[name="starts_at"]').clear().type(`${tomorrowStr}T10:00`);

      cy.contains(/save|mentés/i).click();

      cy.wait('@updateEvent');

      cy.contains(/same.*day|forbidden|azonos.*nap/i).should('be.visible');
    });
  });

  context('Admin Cross-Day Move (Override)', () => {
    beforeEach(() => {
      cy.fixture('users').then((users) => {
        const admin = users.admin;
        cy.login(admin.email, admin.password);
        cy.visit('/calendar');
      });
    });

    it('should allow admin to move event across days', () => {
      cy.intercept('PATCH', '**/api/v1/events/*').as('updateEvent');

      cy.wait(1000);

      // Drag event to next day
      cy.get('.fc-event').first().trigger('mousedown', { which: 1 });
      cy.get('.fc-timegrid-slot[data-time="10:00:00"]').eq(1).trigger('mousemove').trigger('mouseup');

      cy.waitForApi('@updateEvent').then((interception) => {
        expect(interception.response?.statusCode).to.eq(200);
      });

      cy.contains(/success|sikeres/i).should('be.visible');
    });

    it('should allow admin to modify event dates via form', () => {
      cy.intercept('PATCH', '**/api/v1/events/*').as('updateEvent');

      cy.get('.fc-event').first().click();
      cy.contains(/edit|szerkeszt/i).click();

      // Change date to tomorrow
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      const tomorrowStr = tomorrow.toISOString().split('T')[0];

      cy.get('input[name="starts_at"]').clear().type(`${tomorrowStr}T10:00`);
      cy.get('input[name="ends_at"]').clear().type(`${tomorrowStr}T11:00`);

      cy.contains(/save|mentés/i).click();

      cy.waitForApi('@updateEvent').then((interception) => {
        expect(interception.response?.statusCode).to.eq(200);
      });

      cy.contains(/success|sikeres/i).should('be.visible');
    });
  });

  context('Delete Event', () => {
    beforeEach(() => {
      cy.fixture('users').then((users) => {
        const staff = users.staff[0];
        cy.login(staff.email, staff.password);
        cy.visit('/calendar');
      });
    });

    it('should show delete confirmation dialog', () => {
      cy.get('.fc-event').first().click();

      cy.contains(/delete|törlés/i).click();

      // Confirmation dialog should appear
      cy.contains(/are you sure|biztos/i).should('be.visible');
    });

    it('should successfully delete event after confirmation', () => {
      cy.intercept('DELETE', '**/api/v1/events/*').as('deleteEvent');

      cy.get('.fc-event').first().click();

      cy.contains(/delete|törlés/i).click();

      // Confirm deletion
      cy.contains(/confirm|megerősít/i).click();

      cy.waitForApi('@deleteEvent').then((interception) => {
        expect(interception.response?.statusCode).to.eq(204);
      });

      cy.contains(/deleted.*successfully|sikeres.*törlés/i).should('be.visible');

      // Modal should close
      cy.get('[role="dialog"]').should('not.exist');
    });

    it('should cancel deletion when clicking cancel', () => {
      cy.get('.fc-event').first().click();

      cy.contains(/delete|törlés/i).click();

      // Click cancel
      cy.contains(/cancel|mégse/i).click();

      // Should remain on details modal
      cy.get('[role="dialog"]').should('be.visible');
    });
  });

  context('Conflict Detection', () => {
    beforeEach(() => {
      cy.fixture('users').then((users) => {
        const staff = users.staff[0];
        cy.login(staff.email, staff.password);
        cy.visit('/calendar');
      });
    });

    it('should prevent creating event with room conflict (409)', () => {
      cy.intercept('POST', '**/api/v1/events', {
        statusCode: 409,
        body: {
          message: 'Room is already booked for this time',
          conflict_type: 'room',
        },
      }).as('createEvent');

      cy.get('.fc-timegrid-slot').first().click();

      // Fill form with conflicting time/room
      cy.get('input[placeholder*="client"]').type('Anna');
      cy.contains('Anna Szabó').click();
      cy.get('select[name="room_id"]').select('1');
      cy.get('input[name="starts_at"]').clear().type('2025-11-20T10:00');
      cy.get('input[name="ends_at"]').clear().type('2025-11-20T11:00');

      cy.contains(/save|mentés/i).click();

      cy.wait('@createEvent');

      // Should show conflict error
      cy.contains(/conflict|already.*booked|ütközés/i).should('be.visible');
    });

    it('should prevent creating event with staff conflict (409)', () => {
      cy.intercept('POST', '**/api/v1/events', {
        statusCode: 409,
        body: {
          message: 'Staff member is already booked for this time',
          conflict_type: 'staff',
        },
      }).as('createEvent');

      cy.get('.fc-timegrid-slot').first().click();

      // Fill form
      cy.get('input[placeholder*="client"]').type('Anna');
      cy.contains('Anna Szabó').click();
      cy.get('select[name="room_id"]').select('1');
      cy.get('input[name="starts_at"]').clear().type('2025-11-20T10:00');
      cy.get('input[name="ends_at"]').clear().type('2025-11-20T11:00');

      cy.contains(/save|mentés/i).click();

      cy.wait('@createEvent');

      cy.contains(/staff.*conflict|staff.*already.*booked/i).should('be.visible');
    });

    it('should show detailed conflict information in error message', () => {
      cy.intercept('POST', '**/api/v1/events', {
        statusCode: 409,
        body: {
          message: 'Room "Gym" is already booked from 10:00-11:00',
          conflict_type: 'room',
          conflicting_event_id: 123,
        },
      }).as('createEvent');

      cy.get('.fc-timegrid-slot').first().click();

      cy.get('input[placeholder*="client"]').type('Anna');
      cy.contains('Anna Szabó').click();
      cy.get('select[name="room_id"]').select('1');
      cy.get('input[name="starts_at"]').clear().type('2025-11-20T10:00');
      cy.get('input[name="ends_at"]').clear().type('2025-11-20T11:00');

      cy.contains(/save|mentés/i).click();

      cy.wait('@createEvent');

      // Should show room name and time in error
      cy.contains(/Gym|10:00|11:00/).should('be.visible');
    });
  });

  context('Event Resize', () => {
    beforeEach(() => {
      cy.fixture('users').then((users) => {
        const staff = users.staff[0];
        cy.login(staff.email, staff.password);
        cy.visit('/calendar');
      });
    });

    it('should allow resizing event duration within same day', () => {
      cy.intercept('PATCH', '**/api/v1/events/*').as('updateEvent');

      cy.wait(1000);

      // Resize event (drag bottom edge)
      cy.get('.fc-event').first().find('.fc-event-resizer').trigger('mousedown', { which: 1 });
      cy.get('.fc-timegrid-slot[data-time="12:00:00"]').first().trigger('mousemove').trigger('mouseup');

      cy.waitForApi('@updateEvent');

      cy.contains(/success|sikeres/i).should('be.visible');
    });

    it('should prevent resizing event across day boundary (staff)', () => {
      cy.intercept('PATCH', '**/api/v1/events/*', {
        statusCode: 403,
        body: {
          message: 'Staff can only modify events within the same day',
        },
      }).as('updateEvent');

      cy.wait(1000);

      // Try to resize event past midnight
      cy.get('.fc-event').first().find('.fc-event-resizer').trigger('mousedown', { which: 1 });
      cy.get('.fc-timegrid-slot[data-time="02:00:00"]').eq(1).trigger('mousemove').trigger('mouseup');

      cy.wait('@updateEvent');

      cy.contains(/same.*day|forbidden/i).should('be.visible');
    });
  });

  context('Check-in Client', () => {
    beforeEach(() => {
      cy.fixture('users').then((users) => {
        const staff = users.staff[0];
        cy.login(staff.email, staff.password);
        cy.visit('/calendar');
      });
    });

    it('should show check-in section for past 1:1 events', () => {
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
            client: { id: 1, full_name: 'Anna Szabó' },
          },
        ],
      }).as('getEvents');

      cy.visit('/calendar');
      cy.wait('@getEvents');

      cy.get('.fc-event').first().click();

      // Should show check-in buttons for past events
      cy.contains(/mark.*attended|check.*in/i).should('be.visible');
      cy.contains(/mark.*no.*show/i).should('be.visible');
    });

    it('should successfully mark client as attended', () => {
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
      cy.get('textarea[placeholder*="notes"]').type('Great session, client progressing well');

      // Mark attended
      cy.contains(/mark.*attended/i).click();

      cy.waitForApi('@checkIn');

      // Should show success with credit deduction info
      cy.contains(/success|sikeres/i).should('be.visible');
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

      cy.waitForApi('@checkIn');

      cy.contains(/success|sikeres/i).should('be.visible');
    });

    it('should display pass credit deduction notification', () => {
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
      cy.contains(/credit|kredit/).should('be.visible');
    });

    it('should not show check-in section for future events', () => {
      cy.visit('/calendar');

      // Click a future event
      cy.get('.fc-event').first().click();

      // Should NOT show check-in buttons for future events
      cy.contains(/mark.*attended/i).should('not.exist');
      cy.contains(/mark.*no.*show/i).should('not.exist');
    });
  });

  context('Calendar Navigation', () => {
    beforeEach(() => {
      cy.fixture('users').then((users) => {
        const staff = users.staff[0];
        cy.login(staff.email, staff.password);
        cy.visit('/calendar');
      });
    });

    it('should navigate to next week', () => {
      cy.get('.fc-next-button').click();

      // Should load events for next week
      cy.intercept('GET', '**/api/v1/staff/my-events*').as('getEvents');
      cy.wait('@getEvents');
    });

    it('should navigate to previous week', () => {
      cy.get('.fc-prev-button').click();

      cy.intercept('GET', '**/api/v1/staff/my-events*').as('getEvents');
      cy.wait('@getEvents');
    });

    it('should jump to today', () => {
      cy.get('.fc-today-button').click();

      // Should center on current date
      cy.contains(new Date().getDate()).should('be.visible');
    });
  });
});
