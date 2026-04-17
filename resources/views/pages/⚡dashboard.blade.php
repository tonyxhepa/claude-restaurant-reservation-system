<?php

use App\Models\Reservation;
use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
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
        return Reservation::query()
            ->whereDate('reservation_date', Carbon::today())
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
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = $startOfWeek->copy()->endOfWeek();

        $counts = Reservation::query()
            ->whereBetween('reservation_date', [$startOfWeek, $endOfWeek])
            ->whereIn('status', ['pending', 'confirmed'])
            ->selectRaw('DATE(reservation_date) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $colors = ['neutral', 'neutral', 'neutral', 'blue', 'violet', 'red', 'amber'];
        $week = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            $dateKey = $date->toDateString();

            $week[] = [
                'day' => $days[$i],
                'count' => $counts->get($dateKey, 0),
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
        return '0%';
    }

    private function calculateRepeatGuestRate(): string
    {
        $totalGuests = Reservation::whereIn('status', ['confirmed', 'completed'])
            ->whereNotNull('guest_email')
            ->count();

        if ($totalGuests === 0) {
            return '0%';
        }

        $uniqueGuests = Reservation::whereIn('status', ['confirmed', 'completed'])
            ->whereNotNull('guest_email')
            ->distinct('guest_email')
            ->count('guest_email');

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
            <div
                class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700"
                role="progressbar"
                aria-label="{{ __('Capacity utilization') }}"
                aria-valuenow="{{ $this->capacityUtilization }}"
                aria-valuemin="0"
                aria-valuemax="100"
            >
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
                            'bg-blue-500 text-white' => $day['isToday'] && $day['color'] === 'neutral',
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
