<?php

use App\Models\Reservation;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('All Reservations')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $dateFilter = '';

    #[Url]
    public string $sortBy = 'reservation_date';

    #[Url]
    public string $sortDir = 'asc';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFilter(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    #[Computed]
    public function reservations(): LengthAwarePaginator
    {
        return Reservation::query()
            ->with('table', 'user')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('guest_name', 'like', '%'.$this->search.'%')
                    ->orWhere('guest_email', 'like', '%'.$this->search.'%');
            }))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->dateFilter, fn ($q) => $q->whereDate('reservation_date', $this->dateFilter))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(20);
    }

    public function confirm(int $id): void
    {
        Reservation::findOrFail($id)->update(['status' => 'confirmed']);
        Flux::toast(variant: 'success', text: __('Reservation confirmed.'));
        unset($this->reservations);
    }

    public function cancel(int $id): void
    {
        Reservation::findOrFail($id)->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
        Flux::toast(variant: 'success', text: __('Reservation cancelled.'));
        unset($this->reservations);
    }

    public function complete(int $id): void
    {
        Reservation::findOrFail($id)->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        Flux::toast(variant: 'success', text: __('Reservation completed.'));
        unset($this->reservations);
    }
}; ?>

<section class="w-full p-6">
    <flux:heading size="xl" class="mb-6">{{ __('All Reservations') }}</flux:heading>

    <div class="flex flex-wrap gap-3 mb-6">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name or email') }}" icon="magnifying-glass" class="max-w-xs" />
        <flux:select wire:model.live="statusFilter" placeholder="{{ __('All statuses') }}" class="max-w-xs">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
            <flux:select.option value="confirmed">{{ __('Confirmed') }}</flux:select.option>
            <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
            <flux:select.option value="completed">{{ __('Completed') }}</flux:select.option>
        </flux:select>
        <flux:input type="date" wire:model.live="dateFilter" class="max-w-xs" />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'reservation_date'" :direction="$sortDir" wire:click="sort('reservation_date')">{{ __('Date') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'start_time'" :direction="$sortDir" wire:click="sort('start_time')">{{ __('Time') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'guest_name'" :direction="$sortDir" wire:click="sort('guest_name')">{{ __('Guest') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Table') }}</flux:table.column>
            <flux:table.column>{{ __('Party') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->reservations as $reservation)
                <flux:table.row>
                    <flux:table.cell>{{ $reservation->formattedDate() }}</flux:table.cell>
                    <flux:table.cell>{{ $reservation->formattedTime() }}</flux:table.cell>
                    <flux:table.cell>{{ $reservation->guest_name }}</flux:table.cell>
                    <flux:table.cell>{{ $reservation->guest_email }}</flux:table.cell>
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
                    <flux:table.cell>
                        <div class="flex gap-1">
                            @if ($reservation->isPending())
                                <flux:button size="sm" wire:click="confirm({{ $reservation->id }})">{{ __('Confirm') }}</flux:button>
                            @endif
                            @if ($reservation->isConfirmed())
                                <flux:button size="sm" wire:click="complete({{ $reservation->id }})">{{ __('Complete') }}</flux:button>
                            @endif
                            @if ($reservation->canBeCancelled())
                                <flux:button size="sm" variant="danger" wire:click="cancel({{ $reservation->id }})">{{ __('Cancel') }}</flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->reservations->links() }}
    </div>
</section>
