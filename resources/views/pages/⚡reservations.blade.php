<?php

use App\Models\Reservation;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('My Reservations')] class extends Component {
    #[Url]
    public string $filter = 'upcoming';

    /**
     * @return Collection<int, Reservation>
     */
    #[Computed]
    public function reservations(): Collection
    {
        $query = Reservation::query()
            ->with('table')
            ->forUser(auth()->id())
            ->orderBy('reservation_date')
            ->orderBy('start_time');

        return match ($this->filter) {
            'past' => $query->where('reservation_date', '<', Carbon::today())->get(),
            'all' => $query->get(),
            default => $query->where('reservation_date', '>=', Carbon::today())->get(),
        };
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function cancel(int $id): void
    {
        $reservation = Reservation::where('user_id', auth()->id())->findOrFail($id);

        if (! $reservation->canBeCancelled()) {
            Flux::toast(variant: 'danger', text: __('This reservation cannot be cancelled.'));

            return;
        }

        $reservation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        unset($this->reservations);

        Flux::toast(variant: 'success', text: __('Reservation cancelled.'));
    }
}; ?>

<section class="w-full p-6">
    <flux:heading size="xl" class="mb-6">{{ __('My Reservations') }}</flux:heading>

    <div class="flex gap-2 mb-6">
        <flux:button size="sm" :variant="$filter === 'upcoming' ? 'primary' : 'ghost'" wire:click="setFilter('upcoming')">{{ __('Upcoming') }}</flux:button>
        <flux:button size="sm" :variant="$filter === 'past' ? 'primary' : 'ghost'" wire:click="setFilter('past')">{{ __('Past') }}</flux:button>
        <flux:button size="sm" :variant="$filter === 'all' ? 'primary' : 'ghost'" wire:click="setFilter('all')">{{ __('All') }}</flux:button>
    </div>

    @if ($this->reservations->isEmpty())
        <flux:text>{{ __('No reservations found.') }}</flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Time') }}</flux:table.column>
                <flux:table.column>{{ __('Table') }}</flux:table.column>
                <flux:table.column>{{ __('Party') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->reservations as $reservation)
                    <flux:table.row>
                        <flux:table.cell class="font-mono">{{ $reservation->confirmation_code }}</flux:table.cell>
                        <flux:table.cell>{{ $reservation->formattedDate() }}</flux:table.cell>
                        <flux:table.cell>{{ $reservation->formattedTime() }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $reservation->table->table_number }}
                            <span class="text-xs text-zinc-500">({{ ucfirst($reservation->table->section) }})</span>
                        </flux:table.cell>
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
                        <flux:table.cell>
                            @if ($reservation->canBeCancelled())
                                <flux:button size="sm" variant="danger" wire:click="cancel({{ $reservation->id }})" wire:confirm="{{ __('Cancel this reservation?') }}">
                                    {{ __('Cancel') }}
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
