# FunctionalFit Calendar - Backend Test Suite

## Quick Start

```bash
# Run all tests
php artisan test

# Run with coverage report
php artisan test --coverage --min=70

# Run specific test suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run specific file
php artisan test tests/Unit/Services/ConflictDetectionServiceTest.php

# Run in parallel (faster)
php artisan test --parallel
```

## Test Summary

### Implemented (238 tests, ~60% coverage)

**Unit Tests (156 tests)**
- ✅ ConflictDetectionService (68 tests) - Room/staff conflicts, pessimistic locking
- ✅ PassCreditService (47 tests) - Credit deduction/refund, expiry priority
- ✅ EventPolicy (41 tests) - Same-day-only move rule, RBAC

**Feature Tests (82 tests)**
- ✅ Authentication (45 tests) - Register, login, logout, me endpoint
- ✅ Class Booking Flow (37 tests) - Browse, book, waitlist, cancel

**Factories (9 complete)**
- ✅ User, Client, StaffProfile, Room, Event, Pass, ClassTemplate, ClassOccurrence, ClassRegistration

### Pending

**Feature Tests (To Implement)**
- ⏳ Staff Events - Create, update (same-day), check-in, conflict detection
- ⏳ Admin Operations - User/Room/ClassTemplate CRUD, force-move override
- ⏳ Webhooks - WooCommerce/Stripe signature verification, idempotency

**Integration Tests (To Implement)**
- ⏳ Queue Jobs - SendBookingConfirmation, SendClassReminder execution
- ⏳ Email Sending - Mailable tests
- ⏳ Webhook Processing - End-to-end webhook handling

## Critical Business Logic Covered

### Conflict Detection
- ✅ Room double-booking prevention (events vs events, events vs class occurrences)
- ✅ Staff double-booking prevention
- ✅ Pessimistic locking (lockForUpdate) for race condition prevention
- ✅ Cancelled event exclusion
- ✅ Overlapping time range detection (all scenarios)

### Pass Credit Management
- ✅ Expiry priority (uses soonest-expiring pass first)
- ✅ Transaction safety with pessimistic locking
- ✅ Automatic status management (active ↔ used_up)
- ✅ Credit deduction and refund logic
- ✅ Total available credits calculation

### Authorization & Business Rules
- ✅ Same-day-only move rule for staff (T+0 restriction)
- ✅ Admin override for cross-day moves
- ✅ RBAC enforcement (admin, staff, client permissions)
- ✅ Past event modification prevention
- ✅ 24h cancellation window for class bookings

### Booking Flow
- ✅ Confirmed booking with capacity checks
- ✅ Waitlist placement when full
- ✅ Double booking prevention
- ✅ Waitlist promotion on cancellation
- ✅ Credit refund on cancellation (≥24h)
- ✅ Cancellation lock (<24h returns 423)

## Test Quality Principles

1. **Independence** - Tests run successfully in any order
2. **Repeatability** - Same results every time (RefreshDatabase trait)
3. **Fast** - <100ms per test average
4. **Clear** - Descriptive test names explain intent
5. **Comprehensive** - Both success and failure paths
6. **Maintainable** - Uses factories, follows conventions

## Directory Structure

```
tests/
├── Feature/
│   ├── Auth/
│   │   └── AuthenticationTest.php          (45 tests)
│   └── Classes/
│       └── ClassBookingFlowTest.php        (37 tests)
├── Unit/
│   ├── Policies/
│   │   └── EventPolicyTest.php             (41 tests)
│   └── Services/
│       ├── ConflictDetectionServiceTest.php (68 tests)
│       └── PassCreditServiceTest.php       (47 tests)
├── TestCase.php
├── README.md                               (this file)
└── TEST_IMPLEMENTATION_SUMMARY.md          (detailed docs)
```

## Next Steps

1. **Implement remaining Feature Tests** (Staff Events, Admin Ops, Webhooks)
2. **Add Integration Tests** (Queue jobs, email sending)
3. **Setup CI/CD** (GitHub Actions with coverage reporting)
4. **Reach 70% coverage target** (currently ~60%)

## Documentation

See [TEST_IMPLEMENTATION_SUMMARY.md](./TEST_IMPLEMENTATION_SUMMARY.md) for:
- Detailed test descriptions
- Coverage goals by component
- Running instructions
- CI/CD setup recommendations
- Test patterns and conventions

## Troubleshooting

**Tests fail with database errors**
- Ensure test database is configured in `.env.testing`
- Run `php artisan migrate:fresh` in test environment

**Tests are slow**
- Use `--parallel` flag for parallel execution
- Check for N+1 queries in factories
- Reduce test database seeding

**Coverage report issues**
- Install Xdebug or PCOV extension
- Configure `phpunit.xml` with coverage filter
- Use `--coverage-html` for detailed HTML reports

## Contact

For questions about the test suite, see:
- Project docs: `docs/`
- OpenMemory guide: `openmemory.md`
- Backend docs: `backend/IMPLEMENTATION_SUMMARY.md`
