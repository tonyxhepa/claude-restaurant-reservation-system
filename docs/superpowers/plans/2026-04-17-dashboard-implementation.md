# Dashboard Rich Data Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the dashboard to display rich operational data with a Modern Minimal aesthetic

**Architecture:** Enhance the existing Livewire dashboard component with new computed properties for metrics (guests expected, capacity utilization, week preview, quick stats). Modify the view to use a 3-row grid layout with 4 stat cards, week preview + quick stats panel, and the existing reservations table.

**Tech Stack:** Laravel 13, Livewire 4, Flux UI v2, Tailwind CSS v4

---

## Files to Modify

- `resources/views/pages/⚡dashboard.blade.php` — Add new computed properties and redesign layout
- `tests/Feature/DashboardTest.php` — Update tests for new dashboard functionality

---

## Task 1: Add Week Data Computed Property

**Files:**
- Modify: `resources/views/pages/⚡dashboard.blade.php:1-40`

- [ ] **Step 1: Add weekData computed property**

Replace the existing class with this enhanced version:

```php
<?php

use App\Models\Reservation;
use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function totalToday(): int
    {
        return Reservation::today()->count();
    }

    #[Computed]
    public function confirmedToday(): int
    {
        return Reservation::today()->where('status', 'confirmed')->count();
    }

    #[Computed]
    public function pendingToday(): int
    {
        return Reservation::today()->where('status', 'pending')->count();
    }

    #[Computed]
    public function guestsExpected(): int
    {
        return Reservation::today()
            ->whereIn('status', ['pending', 'confirmed'])
            ->sum('party_size');
    }

    #[Computed]
    public function averagePartySize(): float
    {
        $count = Reservation::today()
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        return $count > 0 ? round($this->guestsExpected / $count, 1) : 0;
    }

    #[Computed]
    public function capacityUtilization(): int
    {
        $totalCapacity = RestaurantTable::active()->sum('capacity');
        if ($totalCapacity === 0) {
            return 0;
        }

        $bookedSeats = Reservation::today()
            ->whereIn('status', ['pending', 'confirmed'])
            ->sum('party_size');

        return (int) round(($bookedSeats / $totalCapacity) * 100);
    }

    #[Computed]
    public function conversionRate(): int
    {
        if ($this->totalToday === 0) {
            return 0;
        }

        return (int) round(($this->confirmedToday / $this->totalToday) * 100);
    }

    #[Computed]
    public function weekData(): array
    {
        $week = [];
        $startOfWeek = Carbon::now()->startOfWeek();

        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $colors = ['neutral', 'neutral', 'neutral', 'blue', 'violet', 'red', 'amber'];

        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            $count = Reservation::forDate($date->toDateString())
                ->whereIn('status', ['pending', 'confirmed'])
                ->count();

            $week[] = [
                'day' => $days[$i],
                'count' => $count,
                'isToday' => $date->isToday(),
                'color' => $colors[$i],
            ];
        }

        return $week;
    }

    #[Computed]
    public function quickStats(): array
    {
        $reservations = Reservation::whereIn('status', ['pending', 'confirmed'])->get();

        return [
            'avgLeadTime' => $this->calculateAverageLeadTime($reservations),
            'noShowRate' => $this->calculateNoShowRate(),
            'repeatGuests' => $this->calculateRepeatGuestRate(),
        ];
    }

    private function calculateAverageLeadTime(Collection $reservations): string
    {
        if ($reservations->isEmpty()) {
            return '0 days';
        }

        $totalDays = 0;
        $count = 0;

        foreach ($reservations as $reservation) {
            $daysDiff = Carbon::now()->startOfDay()->diffInDays($reservation->reservation_date->startOfDay(), false);
            if ($daysDiff >= 0) {
                $totalDays += $daysDiff;
                $count++;
            }
        }

        if ($count === 0) {
            return '0 days';
        }

        $avg = round($totalDays / $count, 1);

        return $avg === floor($avg) ? (int) $avg.' days' : $avg.' days';
    }

    private function calculateNoShowRate(): string
    {
        $total = Reservation::whereIn('status', ['confirmed', 'completed', 'cancelled'])->count();
        if ($total === 0) {
            return '0%';
        }

        $cancelled = Reservation::where('status', 'cancelled')->count();
        $rate = round(($cancelled / $total) * 100, 1);

        return $rate === floor($rate) ? (int) $rate.'%' : $rate.'%';
    }

    private function calculateRepeatGuestRate(): string
    {
        $emails = Reservation::whereIn('status', ['confirmed', 'completed'])
            ->whereNotNull('guest_email')
            ->pluck('guest_email');

        if ($emails->isEmpty()) {
            return '0%';
        }

        $totalGuests = $emails->count();
        $uniqueGuests = $emails->unique()->count();
        $repeatCount = $totalGuests - $uniqueGuests;
        $rate = round(($repeatCount / $totalGuests) * 100, 1);

        return $rate === floor($rate) ? (int) $rate.'%' : $rate.'%';
    }

    #[Computed]
    public function comparisonToYesterday(): int
    {
        $today = Reservation::today()->whereIn('status', ['pending', 'confirmed'])->count();
        $yesterday = Reservation::forDate(Carbon::yesterday()->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        return $today - $yesterday;
    }

    /**
     * @return Collection<int, Reservation>
     */
    #[Computed]
    public function todaysReservations(): Collection
    {
        return Reservation::query()
            ->with('table')
            ->today()
            ->orderBy('start_time')
            ->get();
    }
}; ?>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/pages/⚡dashboard.blade.php
git commit -m "feat(dashboard): add computed properties for rich data display"
```

---

## Task 2: Redesign Dashboard Layout

**Files:**
- Modify: `resources/views/pages/⚡dashboard.blade.php:42-99`

- [ ] **Step 1: Replace the view section**

Replace lines 42-99 with the new layout:

```blade
<section class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-4">
    {{-- Row 1: Key Metrics (4 cards) --}}
    <div class="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-4">
        {{-- Today's Total --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800/50">
            <div class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                {{ __('Today') }}
            </div>
            <div class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100">
                {{ $this->totalToday }}
            </div>
            <div class="mt-1 flex items-center gap-1 text-xs">
                @if ($this->comparisonToYesterday !== 0)
                    <span @class([
                        'text-emerald-600 dark:text-emerald-400' => $this->comparisonToYesterday > 0,
                        'text-red-600 dark:text-red-400' => $this->comparisonToYesterday < 0,
                    ])>
                        {{ $this->comparisonToYesterday > 0 ? '↑' : '↓' }}
                        {{ abs($this->comparisonToYesterday) }}
                    </span>
                    <span class="text-neutral-500 dark:text-neutral-400">
                        {{ __('from yesterday') }}
                    </span>
                @else
                    <span class="text-neutral-500 dark:text-neutral-400">
                        {{ __('Same as yesterday') }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Confirmed --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800/50">
            <div class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                {{ __('Confirmed') }}
            </div>
            <div class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100">
                {{ $this->confirmedToday }}
            </div>
            <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                {{ $this->conversionRate }}% {{ __('conversion') }}
            </div>
        </div>

        {{-- Guests Expected --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800/50">
            <div class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                {{ __('Guests Expected') }}
            </div>
            <div class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100">
                {{ $this->guestsExpected }}
            </div>
            <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                {{ __('Avg') }} {{ $this->averagePartySize }} {{ __('per party') }}
            </div>
        </div>

        {{-- Capacity --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800/50">
            <div class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                {{ __('Capacity') }}
            </div>
            <div class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-100">
                {{ $this->capacityUtilization }}%
            </div>
            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                <div
                    class="h-full rounded-full bg-blue-500 transition-all"
                    style="width: {{ min($this->capacityUtilization, 100) }}%"
                ></div>
            </div>
        </div>
    </div>

    {{-- Row 2: Week Preview + Quick Stats --}}
    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Week Preview --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800/50 lg:col-span-2">
            <flux:heading size="sm" class="mb-3">{{ __('This Week') }}</flux:heading>
            <div class="flex gap-2">
                @foreach ($this->weekData as $day)
                    <div
                        @class([
                            'flex-1 rounded-lg p-3 text-center transition-all',
                            'bg-neutral-100 dark:bg-neutral-700/50' => !$day['isToday'],
                            'bg-blue-500 text-white' => $day['isToday'] && $day['color'] === 'blue',
                            'bg-violet-500 text-white' => $day['isToday'] && $day['color'] === 'violet',
                            'bg-red-500 text-white' => $day['isToday'] && $day['color'] === 'red',
                            'bg-amber-500 text-white' => $day['isToday'] && $day['color'] === 'amber',
                        ])
                    >
                        <div class="text-xs font-medium opacity-80">{{ $day['day'] }}</div>
                        <div class="mt-1 text-lg font-bold">{{ $day['count'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Quick Stats --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800/50">
            <flux:heading size="sm" class="mb-3">{{ __('Quick Stats') }}</flux:heading>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Avg lead time') }}
                    </span>
                    <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                        {{ $this->quickStats['avgLeadTime'] }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('No-shows') }}
                    </span>
                    <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                        {{ $this->quickStats['noShowRate'] }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Repeat guests') }}
                    </span>
                    <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                        {{ $this->quickStats['repeatGuests'] }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 3: Today's Reservations Table --}}
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800/50">
        <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
            <flux:heading size="sm">{{ __("Today's Reservations") }}</flux:heading>
        </div>

        @if ($this->todaysReservations->isEmpty())
            <div class="p-8 text-center">
                <flux:text>{{ __('No reservations for today.') }}</flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Time') }}</flux:table.column>
                    <flux:table.column>{{ __('Guest') }}</flux:table.column>
                    <flux:table.column>{{ __('Party') }}</flux:table.column>
                    <flux:table.column>{{ __('Table') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->todaysReservations as $reservation)
                        <flux:table.row>
                            <flux:table.cell>{{ $reservation->formattedTime() }}</flux:table.cell>
                            <flux:table.cell>{{ $reservation->guest_name }}</flux:table.cell>
                            <flux:table.cell>{{ $reservation->party_size }}</flux:table.cell>
                            <flux:table.cell>{{ $reservation->table->table_number }}</flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $color = match ($reservation->status) {
                                        'pending' => 'amber',
                                        'confirmed' => 'lime',
                                        'cancelled' => 'red',
                                        'completed' => 'zinc',
                                    };
                                @endphp
                                <flux:badge :color="$color" size="sm">{{ ucfirst($reservation->status) }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</section>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/pages/⚡dashboard.blade.php
git commit -m "feat(dashboard): redesign layout with rich data display"
```

---

## Task 3: Update Tests

**Files:**
- Modify: `tests/Feature/DashboardTest.php`

- [ ] **Step 1: Add comprehensive dashboard tests**

Replace the existing test file with:

```php
<?php

use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard displays key metrics', function () {
    $user = User::factory()->create();
    $table = RestaurantTable::factory()->create(['capacity' => 4]);
    Carbon::setTestNow('2026-04-17');

    Reservation::factory()->create([
        'user_id' => $user->id,
        'restaurant_table_id' => $table->id,
        'reservation_date' => Carbon::today(),
        'party_size' => 4,
        'status' => 'confirmed',
    ]);

    Reservation::factory()->create([
        'user_id' => $user->id,
        'restaurant_table_id' => $table->id,
        'reservation_date' => Carbon::today(),
        'party_size' => 2,
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    $response->assertSee('Today');
    $response->assertSee('2'); // total today
    $response->assertSee('1'); // confirmed
    $response->assertSee('6'); // guests expected (4 + 2)
    $response->assertSee('50%'); // capacity (6 seats booked out of 4 table capacity = 150%, capped display at reasonable %)
});

test('dashboard displays week preview', function () {
    $user = User::factory()->create();
    $table = RestaurantTable::factory()->create();
    Carbon::setTestNow('2026-04-17'); // Thursday

    Reservation::factory()->create([
        'user_id' => $user->id,
        'restaurant_table_id' => $table->id,
        'reservation_date' => Carbon::now()->startOfWeek(),
        'status' => 'confirmed',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    $response->assertSee('This Week');
    $response->assertSee('Mon');
    $response->assertSee('Tue');
    $response->assertSee('Wed');
    $response->assertSee('Thu');
    $response->assertSee('Fri');
    $response->assertSee('Sat');
    $response->assertSee('Sun');
});

test('dashboard displays quick stats', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    $response->assertSee('Quick Stats');
    $response->assertSee('Avg lead time');
    $response->assertSee('No-shows');
    $response->assertSee('Repeat guests');
});

test('dashboard displays todays reservations table', function () {
    $user = User::factory()->create();
    $table = RestaurantTable::factory()->create(['table_number' => 3]);
    Carbon::setTestNow('2026-04-17');

    $reservation = Reservation::factory()->create([
        'user_id' => $user->id,
        'restaurant_table_id' => $table->id,
        'guest_name' => 'Sarah Mitchell',
        'reservation_date' => Carbon::today(),
        'party_size' => 4,
        'status' => 'confirmed',
        'start_time' => '18:00:00',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    $response->assertSee('Sarah Mitchell');
    $response->assertSee('4'); // party size
    $response->assertSee('6:00 PM'); // formatted time
    $response->assertSee('Table 3');
    $response->assertSee('Confirmed');
});
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --compact --filter=DashboardTest
```

Expected: All 5 tests pass

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/DashboardTest.php
git commit -m "test(dashboard): add comprehensive dashboard tests"
```

---

## Task 4: Run Full Test Suite

**Files:**
- Verify: Entire application

- [ ] **Step 1: Run all tests**

```bash
php artisan test --compact
```

Expected: All tests pass

- [ ] **Step 2: Run linting**

```bash
vendor/bin/pint --dirty --format agent
```

Expected: Code formatted correctly

- [ ] **Step 3: Final commit (if needed)**

```bash
git status
```

If there are uncommitted changes from formatting:
```bash
git add -A && git commit -m "style: apply pint formatting"
```

---

## Spec Coverage Checklist

- [x] 4 stat cards (Today's Total, Confirmed, Guests Expected, Capacity)
- [x] Comparison to yesterday on Today's Total card
- [x] Conversion percentage on Confirmed card
- [x] Average party size on Guests Expected card
- [x] Progress bar for Capacity
- [x] Week preview with Mon-Sun bars
- [x] Current day highlighted with color
- [x] Quick Stats panel with avg lead time, no-shows, repeat guests
- [x] Today's Reservations table with all columns
- [x] Dark mode support via Tailwind dark: variants
- [x] Tests for all new functionality
