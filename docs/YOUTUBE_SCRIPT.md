# YouTube Script: Building a Restaurant Reservation System with Laravel, Livewire & Flux UI

**Target length:** ~8–10 minutes
**Audience:** Laravel developers, intermediate level
**Tone:** Casual, energetic, demo-first

---

## [0:00 – 0:20] Cold Open / Hook

> "What if I told you that in under an hour, with zero JavaScript framework, you can build a full restaurant reservation system — guest bookings, admin dashboards, conflict detection, the whole thing? That's exactly what we're shipping today."

**[Screen: fast cuts of the finished app — booking wizard, admin table, dashboard stats]**

---

## [0:20 – 0:50] Intro & Stack

> "Hey, welcome back. Today we're building on top of the Laravel 13 Livewire starter kit, and we're turning it into a real product — a restaurant reservation system. Our stack is lean:"

- **Laravel 13** for the backend
- **Livewire 4** for reactivity (with the new SFC `⚡` convention)
- **Flux UI 2** for polished components out of the box
- **Pest 4** for the tests
- **SQLite** because we're not fancy

> "No React. No Vue. No build-step drama. Let's go."

---

## [0:50 – 1:40] What We're Building (The PRD in 45 Seconds)

**[Screen: docs/PLAN.md highlights]**

> "Three user roles, three experiences:"

1. **Guests** — anyone can land on `/book`, pick a date and party size, and walk through a 4-step wizard ending with a confirmation code.
2. **Authenticated users** — they get `/reservations`, where they can see past, upcoming, and all of their bookings, and cancel the ones that haven't happened yet.
3. **Admins** — they manage the physical tables themselves, and they see every reservation in the system with filters, search, and one-click status transitions.

> "Plus a dashboard with today's stats. Let's look at how it comes together."

---

## [1:40 – 3:00] The Data Model

**[Screen: migration files side by side]**

> "Three migrations. First, we tack `is_admin` onto the users table — a single boolean. Then, `restaurant_tables` — table number, capacity, a section like indoor or patio, and an is-active flag so admins can take a table offline without deleting history."

> "Then the star of the show: `reservations`. It nullable-references a user, so guests can book without signing up. It stores the guest's name, email, phone, party size, reservation date, start time, a status — pending, confirmed, cancelled, completed — special notes, and a unique confirmation code."

**[Screen: zoom into the indexes]**

> "Notice the compound index on `[restaurant_table_id, reservation_date, start_time]` — that's the one we hit on every availability lookup. This is the difference between a 2ms query and a 200ms query at scale."

---

## [3:00 – 4:30] The Availability Engine

**[Screen: `app/Models/Reservation.php`, scroll to `isTableAvailable`]**

> "This is the brain of the app. Given a table, a date, and a start time, is the table free? We use a **90-minute buffer window** — if someone's already booked that table within an hour and a half on either side, it's a no."

```php
public static function isTableAvailable(int $tableId, string $date, string $startTime, ...): bool
```

> "The logic is a half-open interval: `[requested - 90min, requested + 90min)`. Tight left, loose right. That way two back-to-back bookings exactly 90 minutes apart don't collide."

> "Built on top of that, we have `availableTablesForSlot`, which returns every active table that fits the party size and isn't busy. And `availableSlotsForDate`, which walks every 30-minute slot from 11 AM to 9:30 PM and tells you how many tables are open."

> "That's the whole search engine, in about 40 lines."

---

## [4:30 – 6:00] The Booking Wizard (Livewire SFC)

**[Screen: `resources/views/pages/⚡book.blade.php`]**

> "Here's something slick. Laravel's Livewire 4 lets us write full-page components as **single-file anonymous classes** — the filename starts with a lightning bolt emoji, and inside we have a PHP class and its Blade template in one file."

> "The booking page uses a `$step` property to drive a four-state wizard:"

1. **Step 1** — pick a date and party size.
2. **Step 2** — we call `availableSlotsForDate` and render the times with how many tables each has. Disabled when zero.
3. **Step 3** — guest details. If you're logged in, your name and email are prefilled.
4. **Step 4** — confirmation with an 8-character code.

> "And here's the critical piece — when the user hits submit, we **re-check availability**. Because between step 2 and step 4, someone else could have grabbed the slot. If that happens, we boot them back to step 2 with a toast. No double-bookings, no crying."

> "Oh, and we auto-assign the *smallest* table that fits. Two people don't get the ten-top when a two-top is free."

---

## [6:00 – 7:00] The Admin Side

**[Screen: admin tables page]**

> "Admins get two screens. The first is table management — a Flux table with counts, a modal form for create and edit, and a toggle-active button. Standard CRUD, looks great out of the box because Flux does the heavy lifting."

**[Screen: admin reservations page]**

> "The second is all-reservations. Search by name or email — debounced, URL-bound. Filter by status or date. Sort any column. And contextual inline actions: confirm a pending one, complete a confirmed one, cancel anything that isn't already cancelled or completed."

> "The whole thing is guarded by `abort_unless(auth()->user()?->isAdmin(), 403)` in the mount method. Two lines, and a normal user who guesses the URL gets a 403."

---

## [7:00 – 7:45] The Dashboard

**[Screen: dashboard]**

> "Converted from a static view into a full Livewire component. Three computed properties — total today, confirmed today, pending today — and a table sorted by start time. Every one uses the `#[Computed]` attribute, which means Livewire caches them for the request and re-runs them only when state changes."

---

## [7:45 – 8:30] Tests

**[Screen: terminal running `php artisan test --compact`]**

> "Seventy-one tests. All green. About two seconds to run."

- **Unit** — status helpers, `canBeCancelled`, confirmation code format.
- **Feature/Availability** — buffer logic, party-size filtering, cancelled reservations don't block.
- **Feature/Booking** — guest flow, auth flow, prefill, race-condition guard.
- **Feature/MyReservations** — ownership checks, cancel rules, filters.
- **Feature/Admin** — 403 guards, search, status filter, transitions.
- **Feature/Tables** — CRUD, uniqueness, toggle.

> "Test the happy path. Test the forbidden path. Test the race. That's it."

---

## [8:30 – 9:15] The Bits I'd Cut Without

> "If you're following along, a couple of things that punched above their weight:"

- **The `⚡` SFC convention.** No separate class file, no view file, no wiring — one page, one file, done.
- **Flux tables and modals.** The admin UIs are 95% markup, zero custom CSS.
- **Livewire's `#[Url]` attribute** on filter state. Share a link to a filtered view, and it just works.
- **Sanity tests over exhaustive ones.** Don't test every getter — test the behaviors that break production.

---

## [9:15 – 9:45] What's Next

> "To turn this into a real SaaS, you'd want:"

- **Email confirmations** — fire a Mailable on booking.
- **Policies** — replace the inline abort checks with `ReservationPolicy`.
- **Calendar view** for admins — Flux has a calendar component begging for this job.
- **Notifications** — remind guests the day before.
- **Per-table layouts and floor plans** — the section field is ready for that.

---

## [9:45 – 10:00] Outro

> "That's it. Full source is in the description. Star the repo if it helped, drop a comment with what you'd build next, and I'll see you in the next one."

**[End card: subscribe + next video thumbnail]**

---

## Shot List / B-Roll Notes

- Record the booking wizard once, top to bottom, clean take.
- Record a "race condition" take: open two browsers, book the same slot in both, show the second one getting bumped back.
- Terminal shot of `php artisan test --compact` — full green output.
- Close-up of `⚡book.blade.php` in the editor, scrolling slowly.
- Split screen: migration file on left, generated schema on right via `php artisan db:show`.

## Key Captions / On-Screen Text

- "Livewire 4 SFC → one file per page"
- "90-min buffer, half-open interval"
- "Guest bookings: `user_id` is nullable on purpose"
- "71 tests, 2.4s"
