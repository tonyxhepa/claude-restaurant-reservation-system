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

<style>
    @import url('https://fonts.bunny.net/css?family=lora:400,500,600');
    .res-heading { font-family: 'Lora', Georgia, 'Times New Roman', serif; }
</style>

<section class="flex h-full w-full flex-1 flex-col">
    {{-- Page Header --}}
    <div class="border-b border-zinc-700 bg-zinc-900/60 px-6 py-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-500/80">
                    {{ __('Account') }}
                </p>
                <h1 class="res-heading mt-0.5 text-2xl font-medium text-zinc-100">
                    {{ __('My Reservations') }}
                </h1>
            </div>
            <flux:button
                :href="route('reservations.book')"
                variant="primary"
                size="sm"
                icon="plus"
                wire:navigate
            >
                {{ __('Book a Table') }}
            </flux:button>
        </div>
    </div>

    <div class="flex-1 p-6">
        {{-- Filter Tabs --}}
        <div class="mb-5 flex border-b border-zinc-700">
            @foreach (['upcoming' => __('Upcoming'), 'past' => __('Past'), 'all' => __('All')] as $value => $label)
                <button
                    wire:click="setFilter('{{ $value }}')"
                    class="relative mr-5 pb-3 text-sm transition-colors focus:outline-none
                        {{ $filter === $value
                            ? 'font-semibold text-zinc-100'
                            : 'font-medium text-zinc-500 hover:text-zinc-300' }}"
                >
                    {{ $label }}
                    @if ($filter === $value)
                        <span class="absolute bottom-[-1px] left-0 right-0 h-[2px] rounded-full bg-amber-500"></span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Empty State --}}
        @if ($this->reservations->isEmpty())
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-zinc-800 ring-1 ring-zinc-700">
                    <svg class="h-6 w-6 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-zinc-300">
                    {{ $filter === 'upcoming' ? __('No upcoming reservations') : __('No reservations found') }}
                </p>
                <p class="mt-1 text-xs text-zinc-600">
                    {{ $filter === 'upcoming' ? __('Ready for your next visit?') : __('Nothing to show here.') }}
                </p>
                @if ($filter !== 'past')
                    <div class="mt-5">
                        <flux:button :href="route('reservations.book')" variant="primary" size="sm" wire:navigate>
                            {{ __('Reserve a Table') }}
                        </flux:button>
                    </div>
                @endif
            </div>

        {{-- Reservation Cards --}}
        @else
            <div class="space-y-2">
                @foreach ($this->reservations as $reservation)
                    @php
                        $statusColor = match ($reservation->status) {
                            'pending' => 'amber',
                            'confirmed' => 'lime',
                            'cancelled' => 'red',
                            'completed' => 'zinc',
                        };
                        $dotClass = match ($reservation->status) {
                            'pending' => 'bg-amber-400',
                            'confirmed' => 'bg-lime-400',
                            'cancelled' => 'bg-red-400',
                            'completed' => 'bg-zinc-500',
                        };
                    @endphp

                    <div
                        wire:key="res-{{ $reservation->id }}"
                        class="group flex overflow-hidden rounded-xl border border-zinc-700/80 bg-zinc-900 transition-all duration-150 hover:border-zinc-600 hover:shadow-lg hover:shadow-black/30"
                    >
                        {{-- Date Column --}}
                        <div class="flex w-[4.25rem] flex-shrink-0 flex-col items-center justify-center bg-zinc-800/70 py-4">
                            <span class="text-[9px] font-bold uppercase tracking-[0.15em] text-zinc-400">
                                {{ $reservation->reservation_date->format('M') }}
                            </span>
                            <span class="mt-0.5 text-[1.6rem] font-bold leading-none tabular-nums text-zinc-100">
                                {{ $reservation->reservation_date->format('j') }}
                            </span>
                            <span class="mt-1 text-[9px] font-semibold uppercase tracking-[0.12em] text-zinc-600">
                                {{ $reservation->reservation_date->format('D') }}
                            </span>
                        </div>

                        {{-- Divider --}}
                        <div class="w-px self-stretch bg-zinc-700/50"></div>

                        {{-- Details --}}
                        <div class="flex min-w-0 flex-1 items-center gap-3 px-4 py-3.5 sm:px-5">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span class="text-sm font-semibold text-zinc-100">
                                        {{ $reservation->formattedTime() }}
                                    </span>
                                    <span class="text-zinc-600">&middot;</span>
                                    <span class="text-sm text-zinc-300">
                                        {{ __('Table') }} {{ $reservation->table->table_number }}
                                    </span>
                                    <span class="rounded bg-zinc-800 px-1.5 py-px text-[11px] capitalize text-zinc-400 ring-1 ring-zinc-700">
                                        {{ $reservation->table->section }}
                                    </span>
                                </div>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <span class="text-xs text-zinc-500">
                                        {{ $reservation->party_size }} {{ $reservation->party_size === 1 ? __('guest') : __('guests') }}
                                    </span>
                                    <span class="text-zinc-700">&middot;</span>
                                    <span class="font-mono text-[11px] tracking-widest text-zinc-600">
                                        {{ $reservation->confirmation_code }}
                                    </span>
                                </div>
                            </div>

                            {{-- Status + Action --}}
                            <div class="flex flex-shrink-0 items-center gap-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="h-1.5 w-1.5 flex-shrink-0 rounded-full {{ $dotClass }}"></span>
                                    <span class="hidden text-xs font-medium text-zinc-400 sm:inline">
                                        {{ ucfirst($reservation->status) }}
                                    </span>
                                </div>

                                @if ($reservation->canBeCancelled())
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="cancel({{ $reservation->id }})"
                                        wire:confirm="{{ __('Cancel this reservation?') }}"
                                        class="text-zinc-600 opacity-60 transition-all hover:text-red-400 group-hover:opacity-100"
                                    >
                                        {{ __('Cancel') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
