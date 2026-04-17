<?php

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Collection;
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
