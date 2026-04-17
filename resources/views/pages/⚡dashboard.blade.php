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

<section class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-4">
    <div class="grid auto-rows-min gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-6">
            <flux:subheading>{{ __('Today') }}</flux:subheading>
            <div class="text-3xl font-bold">{{ $this->totalToday }}</div>
            <flux:text size="sm">{{ __('Total reservations') }}</flux:text>
        </div>
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-6">
            <flux:subheading>{{ __('Confirmed') }}</flux:subheading>
            <div class="text-3xl font-bold">{{ $this->confirmedToday }}</div>
            <flux:text size="sm">{{ __('Confirmed today') }}</flux:text>
        </div>
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-6">
            <flux:subheading>{{ __('Pending') }}</flux:subheading>
            <div class="text-3xl font-bold">{{ $this->pendingToday }}</div>
            <flux:text size="sm">{{ __('Awaiting confirmation') }}</flux:text>
        </div>
    </div>

    <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-6">
        <flux:heading size="lg" class="mb-4">{{ __("Today's Reservations") }}</flux:heading>

        @if ($this->todaysReservations->isEmpty())
            <flux:text>{{ __('No reservations for today.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Time') }}</flux:table.column>
                    <flux:table.column>{{ __('Guest') }}</flux:table.column>
                    <flux:table.column>{{ __('Table') }}</flux:table.column>
                    <flux:table.column>{{ __('Party') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->todaysReservations as $reservation)
                        <flux:table.row>
                            <flux:table.cell>{{ $reservation->formattedTime() }}</flux:table.cell>
                            <flux:table.cell>{{ $reservation->guest_name }}</flux:table.cell>
                            <flux:table.cell>{{ $reservation->table->table_number }}</flux:table.cell>
                            <flux:table.cell>{{ $reservation->party_size }}</flux:table.cell>
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
