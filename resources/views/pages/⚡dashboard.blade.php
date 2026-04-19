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

    public string $statusFilter = 'all';

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
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
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderBy('start_time')
            ->get();
    }
}; ?>

<style>
    @import url('https://fonts.bunny.net/css?family=cormorant-garamond:500,600,700|manrope:400,500,600,700');

    .dash { font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif; }
    .dash-heading { font-family: 'Cormorant Garamond', Georgia, serif; letter-spacing: 0.01em; }
</style>

<section class="dash w-full p-4 sm:p-6">
    {{-- Page header --}}
    <div class="mb-5 flex items-end justify-between">
        <div>
            <p class="font-mono text-[9px] uppercase tracking-[0.18em] text-zinc-500">{{ __('Staff · Lumière Dining Room') }}</p>
            <h1 class="dash-heading mt-2 text-3xl font-semibold text-zinc-50">{{ __("Tonight's service") }}</h1>
        </div>
        <div class="flex gap-2">
            <button class="inline-flex items-center gap-1.5 rounded-[8px] border border-white/[0.13] bg-white/[0.05] px-3.5 py-2 text-xs font-medium text-zinc-400 transition-colors hover:text-zinc-200">
                {{ __('Export') }}
            </button>
            <button class="inline-flex items-center gap-1.5 rounded-[8px] border border-white/[0.18] bg-zinc-50 px-3.5 py-2 text-xs font-medium text-zinc-900 transition-opacity hover:opacity-88">
                {{ __('+ Add walk-in') }}
            </button>
        </div>
    </div>

    {{-- Row 1: Stat cards --}}
    <div class="mb-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
        {{-- Today --}}
        <div class="rounded-[14px] border border-white/[0.13] bg-[oklch(0.168_0.010_68)] px-5 py-[18px]">
            <p class="font-mono text-[9px] uppercase tracking-[0.12em] text-zinc-500">{{ __('Today') }}</p>
            <p class="mt-2 text-[2.5rem] font-black leading-none tracking-[-0.03em] text-zinc-50">{{ $this->totalToday }}</p>
            <div class="mt-1.5 flex items-center gap-1 text-xs text-zinc-500">
                @if ($this->comparisonToYesterday > 0)
                    <span class="text-lime-400">▲ {{ $this->comparisonToYesterday }}</span>
                @elseif ($this->comparisonToYesterday < 0)
                    <span class="text-red-400">▼ {{ abs($this->comparisonToYesterday) }}</span>
                @endif
                {{ __('from yesterday') }}
            </div>
        </div>

        {{-- Confirmed --}}
        <div class="rounded-[14px] border border-white/[0.13] bg-[oklch(0.168_0.010_68)] px-5 py-[18px]">
            <p class="font-mono text-[9px] uppercase tracking-[0.12em] text-zinc-500">{{ __('Confirmed') }}</p>
            <p class="mt-2 text-[2.5rem] font-black leading-none tracking-[-0.03em] text-zinc-50">{{ $this->confirmedToday }}</p>
            <p class="mt-1.5 text-xs text-zinc-500">{{ $this->conversionRate }}% {{ __('conversion') }}</p>
        </div>

        {{-- Guests Expected --}}
        <div class="rounded-[14px] border border-white/[0.13] bg-[oklch(0.168_0.010_68)] px-5 py-[18px]">
            <p class="font-mono text-[9px] uppercase tracking-[0.12em] text-zinc-500">{{ __('Guests Expected') }}</p>
            <p class="mt-2 text-[2.5rem] font-black leading-none tracking-[-0.03em] text-zinc-50">{{ $this->guestsExpected }}</p>
            <p class="mt-1.5 text-xs text-zinc-500">{{ __('Avg') }} {{ $this->averagePartySize }} {{ __('per party') }}</p>
        </div>

        {{-- Capacity --}}
        <div class="rounded-[14px] border border-white/[0.13] bg-[oklch(0.168_0.010_68)] px-5 py-[18px]">
            <p class="font-mono text-[9px] uppercase tracking-[0.12em] text-zinc-500">{{ __('Capacity') }}</p>
            <p class="mt-2 text-[2.5rem] font-black leading-none tracking-[-0.03em] text-zinc-50">{{ $this->capacityUtilization }}%</p>
            <div
                class="mt-2.5 h-[4px] w-full overflow-hidden rounded-full bg-zinc-700/60"
                role="progressbar"
                aria-valuenow="{{ $this->capacityUtilization }}"
                aria-valuemin="0"
                aria-valuemax="100"
            >
                <div class="h-full rounded-full bg-blue-500 transition-all" style="width: {{ min($this->capacityUtilization, 100) }}%"></div>
            </div>
            <p class="mt-1.5 text-xs text-zinc-500">{{ __('of total seats · dinner service') }}</p>
        </div>
    </div>

    {{-- Row 2: This Week + Quick Stats --}}
    <div class="mb-3 grid gap-3 xl:grid-cols-[1fr_17rem]">
        {{-- This Week --}}
        <div class="rounded-[14px] border border-white/[0.13] bg-[oklch(0.168_0.010_68)] px-5 py-[18px]">
            <div class="mb-3.5 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-zinc-100">{{ __('This Week') }}</h3>
                <p class="font-mono text-[10px] tracking-[0.08em] text-zinc-500">{{ __('Mon – Sun') }}</p>
            </div>
            <div class="grid grid-cols-7 gap-2">
                @foreach ($this->weekData as $day)
                    <div @class([
                        'rounded-[10px] border p-2.5 text-center',
                        'border-amber-500/40 bg-amber-500/25' => $day['isToday'],
                        'border-white/[0.12] bg-white/[0.06]' => ! $day['isToday'],
                    ])>
                        <p @class([
                            'text-[10px] font-medium uppercase tracking-[0.06em]',
                            'text-amber-400' => $day['isToday'],
                            'text-zinc-500' => ! $day['isToday'],
                        ])>{{ $day['day'] }}</p>
                        <p @class([
                            'mt-1.5 text-xl font-bold tracking-[-0.02em]',
                            'text-amber-400' => $day['isToday'],
                            'text-zinc-400' => ! $day['isToday'],
                        ])>{{ $day['count'] }}</p>
                        @if ($day['isToday'])
                            <p class="mt-1 font-mono text-[8px] tracking-[0.06em] text-amber-400">{{ __('TODAY') }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Quick Stats --}}
        <div class="rounded-[14px] border border-white/[0.13] bg-[oklch(0.168_0.010_68)] px-5 py-[18px]">
            <h3 class="mb-4 text-sm font-semibold text-zinc-100">{{ __('Quick Stats') }}</h3>
            <div>
                <div class="flex items-center justify-between border-b border-white/[0.1] py-2.5 text-sm">
                    <span class="text-zinc-400">{{ __('Avg lead time') }}</span>
                    <span class="font-mono text-xs font-medium text-zinc-300">{{ $this->quickStats['avgLeadTime'] }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-white/[0.1] py-2.5 text-sm">
                    <span class="text-zinc-400">{{ __('No-shows (30d)') }}</span>
                    <span class="font-mono text-xs font-medium text-zinc-300">{{ $this->quickStats['noShowRate'] }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-white/[0.1] py-2.5 text-sm">
                    <span class="text-zinc-400">{{ __('Repeat guests') }}</span>
                    <span class="font-mono text-xs font-medium text-zinc-300">{{ $this->quickStats['repeatGuests'] }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-white/[0.1] py-2.5 text-sm">
                    <span class="text-zinc-400">{{ __('Avg party size') }}</span>
                    <span class="font-mono text-xs font-medium text-zinc-300">{{ $this->averagePartySize }}</span>
                </div>
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <span class="text-zinc-400">{{ __('Walk-ins today') }}</span>
                    <span class="font-mono text-xs font-medium text-zinc-300">0</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 3: Today's Reservations --}}
    <div class="overflow-hidden rounded-[14px] border border-white/[0.13] bg-[oklch(0.168_0.010_68)]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.1] px-5 py-4">
            <h3 class="text-sm font-semibold text-zinc-100">{{ __("Today's Reservations") }}</h3>
            <div class="flex gap-1.5">
                @foreach (['all' => __('All'), 'pending' => __('Pending'), 'confirmed' => __('Confirmed')] as $val => $label)
                    <button
                        wire:click="setStatusFilter('{{ $val }}')"
                        @class([
                            'rounded-[7px] border px-3 py-1.5 text-[11px] font-medium transition-colors',
                            'border-white/[0.18] bg-zinc-50 text-zinc-900' => $statusFilter === $val,
                            'border-white/[0.1] bg-white/[0.04] text-zinc-500 hover:text-zinc-300' => $statusFilter !== $val,
                        ])
                    >{{ $label }}</button>
                @endforeach
            </div>
        </div>

        @if ($this->todaysReservations->isEmpty())
            <div class="px-5 py-12 text-center">
                <p class="text-sm text-zinc-500">{{ __('No reservations match this filter.') }}</p>
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
                        @php
                            $color = match ($reservation->status) {
                                'pending' => 'amber',
                                'confirmed' => 'lime',
                                'cancelled' => 'red',
                                'completed' => 'zinc',
                            };
                        @endphp
                        <flux:table.row wire:key="dash-res-{{ $reservation->id }}">
                            <flux:table.cell class="font-mono text-zinc-300">{{ $reservation->formattedTime() }}</flux:table.cell>
                            <flux:table.cell class="font-medium text-zinc-100">{{ $reservation->guest_name }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-zinc-400">{{ $reservation->party_size }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-zinc-400">{{ $reservation->table->table_number }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$color" size="sm">{{ ucfirst($reservation->status) }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif

        <div class="flex flex-wrap gap-6 border-t border-white/[0.1] px-5 py-3 font-mono text-[11px] text-zinc-500">
            <span>{{ __('Total reservations:') }} <strong class="text-zinc-300">{{ $this->totalToday }}</strong></span>
            <span>{{ __('Confirmed:') }} <strong class="text-lime-400">{{ $this->confirmedToday }}</strong></span>
            <span>{{ __('Pending:') }} <strong class="text-amber-400">{{ $this->pendingToday }}</strong></span>
            <span>{{ __('Guests tonight:') }} <strong class="text-zinc-300">{{ $this->guestsExpected }}</strong></span>
        </div>
    </div>
</section>
