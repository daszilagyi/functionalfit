# Cypress E2E Tests - FunctionalFit Calendar

Comprehensive end-to-end test suite for the FunctionalFit Calendar booking system using Cypress 13+.

## Table of Contents
- [Overview](#overview)
- [Installation](#installation)
- [Running Tests](#running-tests)
- [Test Structure](#test-structure)
- [Test Coverage](#test-coverage)
- [Custom Commands](#custom-commands)
- [Test Data](#test-data)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)
- [CI/CD Integration](#cicd-integration)

## Overview

This E2E test suite covers all critical user journeys for the FunctionalFit Calendar application:

- **Authentication**: Login, logout, role-based access, session persistence
- **Class Booking**: Browse classes, book/cancel, waitlist management, 24h rule enforcement
- **Calendar Events**: Create/edit/delete 1:1 events, same-day move restriction, conflict detection, check-in
- **Client Activity**: View bookings, manage passes, activity history with filters
- **Staff Dashboard**: View stats, export reports, check-in clients
- **Admin Panel**: Full CRUD operations on users, rooms, class templates, reports

**Total Test Count**: 90+ comprehensive E2E tests

## Installation

### Step 1: Install Cypress Binary

**IMPORTANT**: Due to Cypress download server issues during initial setup, you'll need to manually install the Cypress binary:

```bash
# Option 1: Install via npm (recommended)
cd frontend
npm install cypress@13.17.0

# Option 2: If download fails, try with cache
CYPRESS_INSTALL_BINARY=0 npm install cypress@13.17.0
npx cypress install

# Option 3: Download binary manually
# Visit: https://download.cypress.io/desktop/13.17.0
# Extract to: C:\Users\<username>\AppData\Local\Cypress\Cache\13.17.0
```

### Step 2: Verify Installation

```bash
npx cypress verify
```

You should see:
```
✔ Verified Cypress! C:\Users\<username>\AppData\Local\Cypress\Cache\13.17.0\Cypress
```

## Running Tests

### Prerequisites

Before running tests, ensure:

1. **Backend is running**: `cd backend && php artisan serve` (http://localhost:8080)
2. **Frontend is running**: `cd frontend && npm run dev` (http://localhost:3000)
3. **Database is seeded**: `cd backend && php artisan migrate:fresh --seed`

### Run All Tests (Headless)

```bash
npm run test:e2e
# or
npm run cypress:run
```

### Run Tests in Interactive Mode

```bash
npm run cypress:open
```

This opens the Cypress Test Runner where you can:
- Select individual test files to run
- See tests execute in real-time
- Use time-travel debugging
- View screenshots and videos

### Run Specific Test Files

```bash
# Run only authentication tests
npx cypress run --spec "cypress/e2e/auth.cy.ts"

# Run multiple specific tests
npx cypress run --spec "cypress/e2e/auth.cy.ts,cypress/e2e/class-booking.cy.ts"

# Run tests in headed mode (see browser)
npm run test:e2e:headed
```

### Run with Specific Browser

```bash
# Chrome
npm run cypress:run:chrome

# Firefox
npm run cypress:run:firefox

# Edge
npx cypress run --browser edge
```

## Test Structure

```
frontend/cypress/
├── e2e/                          # E2E test files
│   ├── auth.cy.ts                # Authentication flow (10 tests)
│   ├── class-booking.cy.ts       # Class booking flow (15 tests)
│   ├── calendar-events.cy.ts     # Calendar event management (20 tests)
│   ├── client-activity.cy.ts    # Client portal (12 tests)
│   ├── staff-dashboard.cy.ts    # Staff dashboard (10 tests)
│   └── admin-panel.cy.ts         # Admin panel CRUD (25 tests)
├── fixtures/                     # Test data
│   └── users.json                # Seeded user credentials
├── support/                      # Custom commands and config
│   ├── commands.ts               # Custom Cypress commands
│   ├── e2e.ts                    # Global hooks
│   └── index.d.ts                # TypeScript declarations
├── cypress.config.ts             # Cypress configuration
├── tsconfig.json                 # TypeScript config for tests
└── README.md                     # This file
```

## Test Coverage

### Authentication Flow (10 tests)
- ✅ Login with valid credentials (client/staff/admin)
- ✅ Login with invalid credentials (error handling)
- ✅ Logout and session clearing
- ✅ Protected route access control
- ✅ Role-based navigation visibility
- ✅ Session persistence across page refreshes

### Class Booking Flow (15 tests)
- ✅ Browse and display upcoming classes
- ✅ View class details modal
- ✅ Book available class with confirmation
- ✅ Join waitlist when class is full
- ✅ Cancel booking within 24h window
- ✅ Prevent cancellation <24h (423 Locked)
- ✅ Double booking prevention (409 Conflict)
- ✅ No active pass error (422 Validation)
- ✅ Waitlist promotion when spot opens
- ✅ Concurrent booking race condition handling

### Calendar Event Management (20 tests)
- ✅ View personal calendar (staff/admin)
- ✅ Create 1:1 event with client picker
- ✅ Edit event with pre-filled form
- ✅ Same-day-only move restriction (staff)
- ✅ Admin cross-day move override
- ✅ Delete event with confirmation
- ✅ Drag & drop event within same day
- ✅ Event resize validation
- ✅ Conflict detection (room/staff 409)
- ✅ Check-in client (attended/no-show)
- ✅ Pass credit deduction notification
- ✅ Calendar navigation (prev/next week)

### Client Activity Portal (12 tests)
- ✅ View upcoming bookings tab
- ✅ View passes tab with progress bars
- ✅ View activity history tab
- ✅ Apply date range filter
- ✅ Apply type filter (classes/1:1)
- ✅ Apply attendance filter
- ✅ Clear filters button
- ✅ Cancel upcoming booking
- ✅ View pass expiry dates
- ✅ Calculate attendance rate
- ✅ Empty states for all tabs

### Staff Dashboard (10 tests)
- ✅ View dashboard stats (auto-refresh 60s)
- ✅ View upcoming session card
- ✅ Export payout report (XLSX)
- ✅ Export attendance report (XLSX)
- ✅ Check-in from event details
- ✅ Check-in notes (optional)
- ✅ Pass credit deduction notification
- ✅ Error handling for failed exports

### Admin Panel (25 tests)
- ✅ **Dashboard**: Overview stats, quick actions, charts
- ✅ **Users**: List, create, edit, delete with validation
- ✅ **Rooms**: Full CRUD with capacity validation
- ✅ **Class Templates**: Full CRUD with duration validation
- ✅ **Reports**: 5 tabs with date filters and Excel export
- ✅ **Search & Filters**: For all list pages
- ✅ **Pagination**: Cursor-based for large datasets
- ✅ **RBAC**: 403 for non-admin access
- ✅ **Error Handling**: 409/422/500 with user-friendly messages

## Custom Commands

### `cy.login(email, password)`
Authenticates user via API and stores token in localStorage.

```typescript
cy.login('client@test.com', 'password');
cy.visit('/classes');
```

### `cy.getByTestId(testId)`
Shorthand for selecting elements by `data-testid` attribute.

```typescript
cy.getByTestId('class-card-1').click();
```

### `cy.waitForApi(alias)`
Waits for intercepted API call and logs errors.

```typescript
cy.intercept('GET', '**/api/v1/classes').as('getClasses');
cy.waitForApi('@getClasses');
```

### `cy.seedDatabase()`
*Note: Currently logs a reminder to seed manually. Can be extended to call a test endpoint.*

```typescript
cy.seedDatabase(); // Logs: Run backend seeder
```

## Test Data

### Seeded Users (backend/database/seeders/UserSeeder.php)

All users have password: `password`

**Admin:**
- Email: `admin@functionalfit.hu`
- Role: admin

**Staff:**
- `janos.kovacs@functionalfit.hu` (SASAD)
- `eva.nagy@functionalfit.hu` (TB)
- `peter.toth@functionalfit.hu` (ÚJBUDA)

**Clients:**
- `anna.szabo@example.com`
- `bela.kiss@example.com`
- `csilla.varga@example.com`

### Fixtures

Test data is stored in `cypress/fixtures/users.json`:

```json
{
  "admin": { "email": "...", "password": "password" },
  "staff": [...],
  "clients": [...]
}
```

Load in tests:
```typescript
cy.fixture('users').then((users) => {
  cy.login(users.admin.email, users.admin.password);
});
```

## Best Practices

### 1. Test Independence
Each test should be runnable in isolation:
```typescript
beforeEach(() => {
  cy.clearLocalStorage();
  cy.login(user.email, user.password);
  cy.visit('/target-page');
});
```

### 2. Use Intercepts for API Mocking
```typescript
cy.intercept('POST', '**/api/v1/classes/*/book', {
  statusCode: 201,
  body: { status: 'confirmed' }
}).as('bookClass');
```

### 3. Leverage Test IDs
Always use `data-testid` attributes:
```typescript
// Component
<Button data-testid="submit-booking-btn">Submit</Button>

// Test
cy.getByTestId('submit-booking-btn').click();
```

### 4. Clear Assertions
```typescript
// Good
cy.contains(/success|sikeres/i).should('be.visible');

// Bad
cy.contains('Success'); // Breaks with i18n
```

### 5. Avoid Hard Waits
```typescript
// Bad
cy.wait(5000);

// Good
cy.waitForApi('@getClasses');
```

## Troubleshooting

### Tests Fail with "Cannot find auth token"
**Solution**: Ensure backend is seeded with test users:
```bash
cd backend
php artisan migrate:fresh --seed
```

### API Calls Return 401/403
**Solution**: Check that `cy.login()` is called in `beforeEach()` hook.

### Tests Pass Locally but Fail in CI
**Solution**:
- Ensure database is seeded in CI pipeline
- Check viewport size consistency
- Add `cy.viewport(1280, 720)` in tests

### Cypress Binary Not Found
**Solution**:
```bash
npx cypress install
npx cypress verify
```

### Tests Are Flaky
**Solution**:
- Use `cy.intercept()` with aliases instead of `cy.wait(milliseconds)`
- Increase `defaultCommandTimeout` in `cypress.config.ts`
- Add `.should('be.visible')` before interactions

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Cypress E2E Tests

on: [push, pull_request]

jobs:
  cypress-run:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v3

      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: 18

      - name: Install Dependencies
        run: |
          cd frontend
          npm install

      - name: Start Backend
        run: |
          cd backend
          php artisan migrate:fresh --seed
          php artisan serve &

      - name: Start Frontend
        run: |
          cd frontend
          npm run dev &

      - name: Run Cypress Tests
        run: |
          cd frontend
          npm run test:e2e

      - name: Upload Screenshots
        if: failure()
        uses: actions/upload-artifact@v3
        with:
          name: cypress-screenshots
          path: frontend/cypress/screenshots

      - name: Upload Videos
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: cypress-videos
          path: frontend/cypress/videos
```

### Docker Compose Example

```yaml
version: '3.8'
services:
  cypress:
    image: cypress/included:13.17.0
    working_dir: /app/frontend
    volumes:
      - ./:/app
    environment:
      - CYPRESS_baseUrl=http://functionalfit_frontend:3000
      - CYPRESS_apiUrl=http://functionalfit_nginx:8080/api/v1
    command: npm run test:e2e
    depends_on:
      - functionalfit_frontend
      - functionalfit_nginx
```

## Test Maintenance

### Adding New Tests

1. Create test file in `cypress/e2e/` with `.cy.ts` extension
2. Use `describe()` and `context()` for grouping
3. Add `beforeEach()` hook for setup
4. Follow naming convention: `feature-name.cy.ts`

Example:
```typescript
/// <reference types="cypress" />

describe('New Feature', () => {
  beforeEach(() => {
    cy.fixture('users').then((users) => {
      cy.login(users.client.email, users.client.password);
      cy.visit('/new-feature');
    });
  });

  context('Happy Path', () => {
    it('should perform feature action successfully', () => {
      // Test implementation
    });
  });
});
```

### Updating Fixtures

When backend seeders change, update `cypress/fixtures/users.json`:
```bash
cd backend
php artisan db:seed --class=UserSeeder
# Copy updated credentials to fixtures
```

## Performance

- Average test execution time: **2-3 seconds per test**
- Full suite execution: **5-8 minutes** (92 tests)
- Parallel execution (CI): **2-3 minutes** (4 workers)

## Support

For issues or questions:
- Check [Cypress Documentation](https://docs.cypress.io)
- Review existing test patterns in this repo
- Consult `frontend/IMPLEMENTATION_SUMMARY.md` for component details
- Check `backend/tests/` for backend test examples

---

**Last Updated**: 2025-11-18
**Cypress Version**: 13.17.0
**Test Count**: 92 tests across 6 files
