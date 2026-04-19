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
    @import url('https://fonts.bunny.net/css?family=cormorant-garamond:500,600,700|manrope:400,500,600,700');

    .res-page { font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif; }
    .res-page-heading { font-family: 'Cormorant Garamond', Georgia, serif; letter-spacing: 0.01em; }
</style>

<section class="res-page flex h-full w-full flex-1 flex-col p-4 sm:p-6">
    {{-- Page Header --}}
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="font-mono text-[9px] uppercase tracking-[0.18em] text-zinc-500">{{ __('Account') }}</p>
            <h1 class="res-page-heading mt-2 text-3xl font-semibold text-zinc-50">{{ __('My Reservations') }}</h1>
        </div>
        <flux:button :href="route('reservations.book')" variant="primary" icon="plus" wire:navigate>
            {{ __('New reservation') }}
        </flux:button>
    </div>

    {{-- Filter chips --}}
    <div class="mb-6 flex gap-2">
        @foreach (['upcoming' => __('Upcoming'), 'past' => __('Past'), 'all' => __('All')] as $value => $label)
            <button
                wire:click="setFilter('{{ $value }}')"
                @class([
                    'rounded-[8px] border px-3.5 py-1.5 text-[12px] font-medium transition-colors',
                    'border-white/[0.18] bg-zinc-50 text-zinc-900' => $filter === $value,
                    'border-white/[0.1] bg-white/[0.04] text-zinc-500 hover:text-zinc-300' => $filter !== $value,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    {{-- Section label --}}
    @if (! $this->reservations->isEmpty())
        <p class="mb-3 font-mono text-[11px] uppercase tracking-[0.14em] text-zinc-500">
            @if ($filter === 'upcoming')
                {{ __('Upcoming') }} · {{ $this->reservations->count() }}
            @elseif ($filter === 'past')
                {{ __('Past') }} · {{ $this->reservations->count() }}
            @else
                {{ __('All') }} · {{ $this->reservations->count() }}
            @endif
        </p>
    @endif

    {{-- Empty State --}}
    @if ($this->reservations->isEmpty())
        <div class="flex flex-1 flex-col items-center justify-center py-20 text-center">
            <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-full border border-white/[0.1] bg-white/[0.04]">
                <svg class="h-6 w-6 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5" />
                </svg>
            </div>
            <p class="text-sm font-medium text-zinc-300">
                {{ $filter === 'upcoming' ? __('No upcoming reservations') : __('No reservations found') }}
            </p>
            <p class="mt-1.5 text-xs text-zinc-500">
                {{ $filter === 'upcoming' ? __('Ready for your next visit?') : __('Nothing to show here.') }}
            </p>
            @if ($filter !== 'past')
                <div class="mt-6">
                    <flux:button :href="route('reservations.book')" variant="primary" wire:navigate>
                        {{ __('Reserve a Table') }}
                    </flux:button>
                </div>
            @endif
        </div>

    {{-- Reservation rows --}}
    @else
        <div class="space-y-2.5">
            @foreach ($this->reservations as $reservation)
                @php
                    $pillClass = match ($reservation->status) {
                        'confirmed' => 'bg-[oklch(0.25_0.04_155)] text-[oklch(0.84_0.06_155)]',
                        'pending'   => 'bg-amber-400/15 text-amber-300',
                        'cancelled' => 'bg-red-400/12 text-red-300',
                        default     => 'bg-white/[0.07] text-zinc-400',
                    };
                @endphp

                <div
                    wire:key="res-{{ $reservation->id }}"
                    class="grid items-center gap-5 rounded-[18px] border border-white/[0.13] bg-[oklch(0.195_0.010_68)] px-6 py-5 transition-colors hover:border-white/[0.2] sm:grid-cols-[1fr_auto_auto]"
                >
                    {{-- Details --}}
                    <div>
                        <p class="text-base font-semibold text-zinc-50">{{ __('Lumière') }}</p>
                        <p class="mt-1 font-mono text-[12px] text-zinc-500">
                            {{ $reservation->formattedDate() }} · {{ $reservation->formattedTime() }} · {{ trans_choice(':count guest|:count guests', $reservation->party_size, ['count' => $reservation->party_size]) }} · {{ __('Table') }} {{ $reservation->table->table_number }}
                        </p>
                        <p class="mt-0.5 font-mono text-[11px] uppercase tracking-[0.18em] text-zinc-600">{{ $reservation->confirmation_code }}</p>
                    </div>

                    {{-- Status pill --}}
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ $pillClass }}">
                        {{ ucfirst($reservation->status) }}
                    </span>

                    {{-- Actions --}}
                    <div class="flex gap-2">
                        @if ($reservation->canBeCancelled())
                            <flux:button
                                size="sm"
                                variant="ghost"
                                wire:click="cancel({{ $reservation->id }})"
                                wire:confirm="{{ __('Cancel this reservation?') }}"
                                class="!text-zinc-500 hover:!text-red-400"
                            >
                                {{ __('Cancel') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
