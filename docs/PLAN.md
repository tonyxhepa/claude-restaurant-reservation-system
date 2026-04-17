# Restaurant Reservation System

## Context

This is a fresh Laravel 13 + Livewire 4 + Flux UI 2 starter kit with only authentication/user management in place. The goal is to add a full restaurant reservation system: guests can book tables, authenticated users can manage their bookings, and admins can manage tables and all reservations.

---

## Database Migrations

**Run in order:**

### 1. `add_is_admin_to_users_table`
```
users: + is_admin (boolean, default false)
```
Update `User` model: add `is_admin` to `$fillable`, add `isAdmin(): bool` method, add `reservations()` hasMany.

### 2. `create_restaurant_tables_table`
```
restaurant_tables:
  id, table_number (string, unique), capacity (tinyInt),
  section (string: indoor|outdoor|bar|patio), is_active (bool, default true),
  notes (text, nullable), timestamps
  indexes: [section], [is_active], [capacity]
```

### 3. `create_reservations_table`
```
reservations:
  id, user_id (FK nullable → users, nullOnDelete),
  restaurant_table_id (FK → restaurant_tables, cascadeOnDelete),
  guest_name, guest_email, guest_phone (nullable),
  party_size (tinyInt), reservation_date (date), start_time (time),
  status (string: pending|confirmed|cancelled|completed, default pending),
  special_notes (text, nullable), confirmation_code (string, unique),
  cancelled_at (timestamp, nullable), completed_at (timestamp, nullable),
  timestamps
  indexes: [reservation_date, start_time], [restaurant_table_id, reservation_date, start_time],
           [status], [guest_email], [confirmation_code], [user_id]
```

---

## Models

### `app/Models/RestaurantTable.php`
- Fillable: `table_number`, `capacity`, `section`, `is_active`, `notes`
- Cast: `is_active` → boolean
- Relationships: `reservations()` hasMany
- Scopes: `scopeActive()`, `scopeSection(string)`, `scopeMinCapacity(int)`
- Static: `sections(): array` → `['indoor', 'outdoor', 'bar', 'patio']`

### `app/Models/Reservation.php`
- Casts: `reservation_date` → date, `cancelled_at`/`completed_at` → datetime
- Relationships: `user()` belongsTo, `table()` belongsTo
- Scopes: `scopeForDate()`, `scopeToday()`, `scopeUpcoming()`, `scopeActive()` (pending+confirmed), `scopeForUser(int)`, `scopeForGuest(string $email)`
- Helpers: `isPending()`, `isConfirmed()`, `isCancelled()`, `isCompleted()`, `canBeCancelled()`, `endTime()`, `formattedDate()`, `formattedTime()`
- Static: `generateConfirmationCode(): string` (8 uppercase chars via `Str::upper(Str::random(8))`)

**Key availability methods (static):**

```php
// Check if a table is free for a given slot (90-min buffer window)
static function isTableAvailable(int $tableId, string $date, string $startTime, int $bufferMinutes = 90, ?int $excludeId = null): bool
// Finds active tables (capacity >= partySize) with no conflicting reservation
static function availableTablesForSlot(string $date, string $startTime, int $partySize): Collection
// Generates 30-min slots from 11:00–21:30, returns slots with available table count
static function availableSlotsForDate(string $date, int $partySize): Collection
```

Buffer logic: query for existing `pending|confirmed` reservations where `start_time` falls within `[requested - 90min, requested + 90min)` window.

---

## Factories & Seeder

### `RestaurantTableFactory`
States: `indoor()`, `outdoor()`, `bar()`, `patio()`, `inactive()`

### `ReservationFactory`
States: `pending()`, `confirmed()`, `cancelled()`, `completed()`, `forToday()`, `forDate(Carbon)`, `forGuest()`, `forUser(User)`

### `DatabaseSeeder` additions
```
- admin@restaurant.com (is_admin = true)
- 4 indoor tables (cap 2,2,4,6), 2 outdoor (4,8), 3 bar (1,2,2), 2 patio (6,10)
- 5 reservations for today (mix pending/confirmed)
- 10 for next 7 days
- 5 past completed reservations
```

---

## Livewire Full-Page Components

All files use the `⚡` naming convention (anonymous inline class syntax). Admin pages use `abort_unless(auth()->user()?->isAdmin(), 403)` in `mount()`.

| Route                     | File                                  | Auth        | Description                      |
| ------------------------- | ------------------------------------- | ----------- | -------------------------------- |
| `GET /book`               | `pages/⚡book.blade.php`               | No          | Multi-step public booking        |
| `GET /reservations`       | `pages/⚡reservations.blade.php`       | Yes         | My reservations                  |
| `GET /admin/reservations` | `pages/admin/⚡reservations.blade.php` | Yes + admin | All reservations list            |
| `GET /admin/tables`       | `pages/admin/⚡tables.blade.php`       | Yes + admin | Table management                 |
| `GET /dashboard`          | `pages/⚡dashboard.blade.php`          | Yes         | Convert existing view → Livewire |

### `⚡book.blade.php` — 4-step booking wizard
- **Step 1:** Date + party size search form
- **Step 2:** Available time slots list (slot + "N tables available")
- **Step 3:** Guest details form (name, email, phone, notes) — pre-filled if auth user
- **Step 4:** Confirmation screen with confirmation code
- On submit: re-check availability (race condition guard), auto-assign smallest fitting table, generate confirmation code, save reservation

### `⚡reservations.blade.php` — My Reservations
- Filter tabs: upcoming / past / all
- Shows status badge, table info, date/time, party size
- Cancel action (pending or confirmed only, own reservations only)

### `admin/⚡reservations.blade.php` — Admin List
- Search (guest name/email), status filter, date filter, sortable columns
- `flux:table` with status badges (`pending`→warning, `confirmed`→success, `cancelled`→danger, `completed`→zinc)
- Inline actions: Confirm / Cancel / Complete (contextual per status)

### `admin/⚡tables.blade.php` — Table Management
- List all tables with reservation count
- Create/edit via `flux:modal` form
- Toggle active/inactive

### `⚡dashboard.blade.php` — Dashboard (converted)
- `#[Computed]` stats: total today, confirmed today, pending today
- Table of today's reservations sorted by `start_time`

---

## Routes (`routes/web.php` additions)

```php
Route::livewire('book', 'pages::book')->name('reservations.book');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('reservations', 'pages::reservations')->name('reservations.index');
    Route::livewire('admin/reservations', 'pages::admin.reservations')->name('admin.reservations.index');
    Route::livewire('admin/tables', 'pages::admin.tables')->name('admin.tables.index');
});
```

---

## Sidebar Navigation (`resources/views/layouts/app/sidebar.blade.php`)

Add to existing "Platform" group: **Book a Table** (calendar icon), **My Reservations** (bookmark icon, `@auth` only).

Add new **Admin** group (visible only to `auth()->user()?->isAdmin()`): **All Reservations** (table-cells icon), **Tables** (squares-2x2 icon).

---

## Tests

| File                                      | Key Scenarios                                                               |
| ----------------------------------------- | --------------------------------------------------------------------------- |
| `Feature/RestaurantTableTest.php`         | Admin CRUD, 403 for non-admin, unique table_number                          |
| `Feature/ReservationAvailabilityTest.php` | Buffer logic, party size filtering, cancelled/completed don't block         |
| `Feature/BookingTest.php`                 | Guest + auth booking, race condition re-check, confirmation code generation |
| `Feature/MyReservationsTest.php`          | Ownership checks, cancel rules, filter tabs                                 |
| `Feature/AdminReservationsTest.php`       | All filters, status transitions, 403 guards                                 |
| `Unit/ReservationModelTest.php`           | Status helpers, `canBeCancelled()`, `generateConfirmationCode()`            |

---

## Implementation Order

1. Migration + User model update (`is_admin`)
2. `RestaurantTable` migration, model, factory
3. `Reservation` migration, model, factory (availability logic here)
4. Update `DatabaseSeeder`
5. `admin/⚡tables.blade.php` + route
6. `⚡book.blade.php` + route (core feature)
7. `⚡reservations.blade.php` + route
8. `admin/⚡reservations.blade.php` + route
9. Convert `dashboard.blade.php` → Livewire + update route
10. Update sidebar navigation
11. Write all tests
12. Run `vendor/bin/pint --dirty --format agent`

---

## Verification

1. `php artisan migrate:fresh --seed` — database seeded with admin, tables, reservations
2. Visit `/book` as guest → complete a 4-step booking → confirmation code shown
3. Login as `test@example.com` → visit `/book` → guest fields pre-filled
4. Visit `/reservations` → see bookings → cancel one → status updates
5. Login as `admin@restaurant.com` → visit `/admin/reservations` → confirm/cancel/complete
6. Visit `/admin/tables` → create a table → edit it → toggle inactive
7. Visit `/dashboard` → today's stats and reservation list visible
8. `php artisan test --compact` — all tests pass