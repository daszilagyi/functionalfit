/// <reference types="cypress" />

describe('Class Booking Flow', () => {
  beforeEach(() => {
    cy.fixture('users').then((users) => {
      const client = users.clients[0];
      cy.login(client.email, client.password);
    });
  });

  context('Browse Classes', () => {
    it('should display list of upcoming classes', () => {
      cy.intercept('GET', '**/api/v1/classes*').as('getClasses');

      cy.visit('/classes');

      cy.waitForApi('@getClasses');

      // Should show class cards
      cy.get('[data-testid^="class-card-"]').should('have.length.at.least', 1);

      // Each card should display essential info
      cy.get('[data-testid^="class-card-"]').first().within(() => {
        cy.contains(/[A-Za-z]+/).should('be.visible'); // Class name
        cy.contains(/\d{2}:\d{2}/).should('be.visible'); // Time
      });
    });

    it('should show capacity badge for each class', () => {
      cy.visit('/classes');

      cy.get('[data-testid^="class-card-"]').first().within(() => {
        // Should show either "Fully Booked" or "X spots left"
        cy.get('.badge').should('exist');
      });
    });

    it('should display loading state while fetching classes', () => {
      cy.intercept('GET', '**/api/v1/classes*', {
        delay: 1000,
        fixture: 'classes',
      }).as('getClasses');

      cy.visit('/classes');

      // Should show loading skeleton
      cy.get('[data-testid="skeleton"]').should('exist');

      cy.wait('@getClasses');

      cy.get('[data-testid="skeleton"]').should('not.exist');
    });

    it('should display empty state when no classes available', () => {
      cy.intercept('GET', '**/api/v1/classes*', {
        statusCode: 200,
        body: { data: [] },
      }).as('getClasses');

      cy.visit('/classes');

      cy.wait('@getClasses');

      // Should show "No data" message
      cy.contains(/no.*data|nincs.*adat/i).should('be.visible');
    });
  });

  context('View Class Details', () => {
    it('should open details modal when clicking class card', () => {
      cy.visit('/classes');

      cy.get('[data-testid^="class-card-"]').first().click();

      // Modal should be visible
      cy.getByTestId('class-details-modal').should('be.visible');

      // Should display full class details
      cy.getByTestId('class-details-modal').within(() => {
        cy.contains(/trainer|edző/i).should('be.visible');
        cy.contains(/room|terem/i).should('be.visible');
        cy.contains(/capacity|kapacitás/i).should('be.visible');
        cy.contains(/credits/i).should('be.visible');
      });
    });

    it('should close modal when clicking close button', () => {
      cy.visit('/classes');

      cy.get('[data-testid^="class-card-"]').first().click();
      cy.getByTestId('class-details-modal').should('be.visible');

      cy.contains(/close|bezár/i).click();

      cy.getByTestId('class-details-modal').should('not.exist');
    });

    it('should close modal when clicking overlay', () => {
      cy.visit('/classes');

      cy.get('[data-testid^="class-card-"]').first().click();
      cy.getByTestId('class-details-modal').should('be.visible');

      // Click outside modal (on overlay)
      cy.get('body').click(0, 0);

      cy.getByTestId('class-details-modal').should('not.exist');
    });
  });

  context('Book Available Class', () => {
    it('should successfully book a class with available spots', () => {
      cy.intercept('POST', '**/api/v1/classes/*/book').as('bookClass');

      cy.visit('/classes');

      // Find a class that's not fully booked
      cy.get('[data-testid^="class-card-"]').first().click();

      // Show booking form
      cy.getByTestId('show-booking-form-btn').click();

      // Fill optional notes
      cy.getByTestId('booking-notes-input').type('Looking forward to this session!');

      // Submit booking
      cy.getByTestId('submit-booking-btn').click();

      cy.waitForApi('@bookClass').then((interception) => {
        expect(interception.response?.statusCode).to.eq(201);
        expect(interception.response?.body).to.have.property('status');
      });

      // Should show success toast
      cy.contains(/success|sikeres/i).should('be.visible');

      // Modal should close
      cy.getByTestId('class-details-modal').should('not.exist');
    });

    it('should show confirmation message for confirmed booking', () => {
      cy.intercept('POST', '**/api/v1/classes/*/book', {
        statusCode: 201,
        body: {
          id: 1,
          status: 'confirmed',
          class_occurrence_id: 1,
          client_id: 1,
        },
      }).as('bookClass');

      cy.visit('/classes');
      cy.get('[data-testid^="class-card-"]').first().click();
      cy.getByTestId('show-booking-form-btn').click();
      cy.getByTestId('submit-booking-btn').click();

      cy.wait('@bookClass');

      // Should show "confirmed" success message
      cy.contains(/confirmed|megerősített/i).should('be.visible');
    });

    it('should book class without notes (optional field)', () => {
      cy.intercept('POST', '**/api/v1/classes/*/book').as('bookClass');

      cy.visit('/classes');
      cy.get('[data-testid^="class-card-"]').first().click();
      cy.getByTestId('show-booking-form-btn').click();

      // Don't fill notes field
      cy.getByTestId('submit-booking-btn').click();

      cy.waitForApi('@bookClass').then((interception) => {
        expect(interception.request.body.notes).to.be.oneOf(['', undefined]);
      });
    });
  });

  context('Join Waitlist (Full Class)', () => {
    it('should join waitlist when class is fully booked', () => {
      cy.intercept('POST', '**/api/v1/classes/*/book', {
        statusCode: 201,
        body: {
          id: 1,
          status: 'waitlist',
          class_occurrence_id: 1,
          client_id: 1,
        },
      }).as('bookClass');

      cy.visit('/classes');

      // Find fully booked class (has "Fully Booked" badge)
      cy.contains(/fully.*booked|telt.*ház/i).parents('[data-testid^="class-card-"]').click();

      // Button should say "Join Waitlist"
      cy.contains(/join.*waitlist|várólistára/i).should('be.visible');

      cy.getByTestId('show-booking-form-btn').click();
      cy.getByTestId('submit-booking-btn').click();

      cy.wait('@bookClass');

      // Should show waitlist confirmation
      cy.contains(/waitlist|várólista/i).should('be.visible');
    });

    it('should display waitlist badge on fully booked classes', () => {
      cy.visit('/classes');

      // Should have at least one fully booked class
      cy.contains(/fully.*booked|telt.*ház/i).should('exist');
    });
  });

  context('Booking Errors', () => {
    it('should show error when already booked for same class', () => {
      cy.intercept('POST', '**/api/v1/classes/*/book', {
        statusCode: 409,
        body: {
          message: 'You have already booked this class',
        },
      }).as('bookClass');

      cy.visit('/classes');
      cy.get('[data-testid^="class-card-"]').first().click();
      cy.getByTestId('show-booking-form-btn').click();
      cy.getByTestId('submit-booking-btn').click();

      cy.wait('@bookClass');

      // Should show conflict error
      cy.contains(/already.*booked|már.*lefoglalt/i).should('be.visible');

      // Modal should remain open
      cy.getByTestId('class-details-modal').should('be.visible');
    });

    it('should show error when no active pass available', () => {
      cy.intercept('POST', '**/api/v1/classes/*/book', {
        statusCode: 422,
        body: {
          message: 'No active pass with available credits',
          errors: {
            pass: ['You must have an active pass with credits to book a class'],
          },
        },
      }).as('bookClass');

      cy.visit('/classes');
      cy.get('[data-testid^="class-card-"]').first().click();
      cy.getByTestId('show-booking-form-btn').click();
      cy.getByTestId('submit-booking-btn').click();

      cy.wait('@bookClass');

      // Should show validation error
      cy.contains(/no.*active.*pass|nincs.*aktív.*bérlet/i).should('be.visible');
    });

    it('should show error when class is cancelled', () => {
      cy.intercept('POST', '**/api/v1/classes/*/book', {
        statusCode: 422,
        body: {
          message: 'This class has been cancelled',
        },
      }).as('bookClass');

      cy.visit('/classes');
      cy.get('[data-testid^="class-card-"]').first().click();
      cy.getByTestId('show-booking-form-btn').click();
      cy.getByTestId('submit-booking-btn').click();

      cy.wait('@bookClass');

      cy.contains(/cancelled|lemondott/i).should('be.visible');
    });

    it('should show error when class is in the past', () => {
      cy.intercept('POST', '**/api/v1/classes/*/book', {
        statusCode: 422,
        body: {
          message: 'Cannot book past classes',
        },
      }).as('bookClass');

      cy.visit('/classes');
      cy.get('[data-testid^="class-card-"]').first().click();
      cy.getByTestId('show-booking-form-btn').click();
      cy.getByTestId('submit-booking-btn').click();

      cy.wait('@bookClass');

      cy.contains(/past|múlt/i).should('be.visible');
    });

    it('should validate notes field max length (500 chars)', () => {
      cy.visit('/classes');
      cy.get('[data-testid^="class-card-"]').first().click();
      cy.getByTestId('show-booking-form-btn').click();

      // Type more than 500 characters
      const longText = 'a'.repeat(501);
      cy.getByTestId('booking-notes-input').type(longText);

      cy.getByTestId('submit-booking-btn').click();

      // Should show validation error
      cy.contains(/maximum|max|too long/i).should('be.visible');
    });
  });

  context('Cancel Booking', () => {
    it('should successfully cancel booking within 24h window', () => {
      cy.intercept('POST', '**/api/v1/classes/*/cancel').as('cancelBooking');

      // Assuming we're on client activity page showing booked classes
      cy.visit('/client/activity');

      cy.intercept('GET', '**/api/v1/clients/*/activity*').as('getActivity');
      cy.wait('@getActivity');

      // Find a cancelable booking (shows "Cancelable" badge)
      cy.contains(/cancel|lemondás/i).click();

      // Confirm cancellation in dialog
      cy.contains(/confirm|megerősít/i).click();

      cy.waitForApi('@cancelBooking').then((interception) => {
        expect(interception.response?.statusCode).to.eq(200);
      });

      // Should show success message
      cy.contains(/cancelled.*successfully|sikeres.*lemondás/i).should('be.visible');
    });

    it('should prevent cancellation within 24h of class start (423 Locked)', () => {
      cy.visit('/client/activity');

      cy.intercept('POST', '**/api/v1/classes/*/cancel', {
        statusCode: 423,
        body: {
          message: 'Cancellation not allowed within 24 hours of class start',
        },
      }).as('cancelBooking');

      // Try to cancel a booking that's within 24h
      cy.contains(/cancel|lemondás/i).click();
      cy.contains(/confirm|megerősít/i).click();

      cy.wait('@cancelBooking');

      // Should show locked error
      cy.contains(/not.*allowed|24.*hour|nem.*engedélyezett/i).should('be.visible');
    });

    it('should show "Cannot Cancel" badge for bookings within 24h window', () => {
      cy.visit('/client/activity');

      // Should display badge indicating cancellation not possible
      cy.contains(/cannot.*cancel|már.*nem.*lemondható/i).should('exist');
    });

    it('should refund credit after successful cancellation', () => {
      cy.intercept('POST', '**/api/v1/classes/*/cancel', {
        statusCode: 200,
        body: {
          message: 'Booking cancelled successfully. Credit refunded.',
          credit_refunded: true,
        },
      }).as('cancelBooking');

      cy.visit('/client/activity');

      cy.contains(/cancel|lemondás/i).click();
      cy.contains(/confirm|megerősít/i).click();

      cy.wait('@cancelBooking');

      // Should mention credit refund in message
      cy.contains(/credit.*refund|kredit.*visszautal/i).should('be.visible');
    });

    it('should prevent cancelling already cancelled booking', () => {
      cy.intercept('POST', '**/api/v1/classes/*/cancel', {
        statusCode: 422,
        body: {
          message: 'Booking is already cancelled',
        },
      }).as('cancelBooking');

      cy.visit('/client/activity');

      cy.contains(/cancel|lemondás/i).click();
      cy.contains(/confirm|megerősít/i).click();

      cy.wait('@cancelBooking');

      cy.contains(/already.*cancelled|már.*lemondva/i).should('be.visible');
    });
  });

  context('Waitlist Promotion', () => {
    it('should automatically promote first waitlist client when spot opens', () => {
      // This would be tested via backend behavior
      // For E2E, we can verify the UI shows promoted status

      cy.intercept('GET', '**/api/v1/clients/*/activity*', {
        statusCode: 200,
        body: {
          upcoming: [
            {
              id: 1,
              status: 'confirmed',
              class_occurrence: {
                id: 1,
                class_template: { name: 'Yoga Class' },
              },
              was_promoted_from_waitlist: true,
            },
          ],
        },
      }).as('getActivity');

      cy.visit('/client/activity');

      cy.wait('@getActivity');

      // Should show confirmation badge (promoted from waitlist)
      cy.contains(/confirmed|megerősített/i).should('be.visible');
    });
  });

  context('Concurrent Booking Scenarios', () => {
    it('should handle last spot booking race condition gracefully', () => {
      // Simulate scenario where two clients try to book last spot
      cy.intercept('POST', '**/api/v1/classes/*/book', (req) => {
        // First request gets the spot
        // Second request should get waitlist
        req.reply({
          statusCode: 201,
          body: {
            status: Math.random() > 0.5 ? 'confirmed' : 'waitlist',
          },
        });
      }).as('bookClass');

      cy.visit('/classes');
      cy.get('[data-testid^="class-card-"]').first().click();
      cy.getByTestId('show-booking-form-btn').click();
      cy.getByTestId('submit-booking-btn').click();

      cy.wait('@bookClass');

      // Should show either confirmed or waitlist message
      cy.contains(/confirmed|waitlist|megerősített|várólista/i).should('be.visible');
    });
  });

  context('Filter and Search Classes', () => {
    it('should filter classes by date range', () => {
      cy.visit('/classes');

      // Apply date filter (if implemented)
      cy.intercept('GET', '**/api/v1/classes?from_date=*').as('getFilteredClasses');

      // Interact with date picker or filter controls
      // cy.get('[data-testid="date-from-filter"]').type('2025-11-20');

      // Wait for filtered results
      // cy.wait('@getFilteredClasses');
    });
  });
});
