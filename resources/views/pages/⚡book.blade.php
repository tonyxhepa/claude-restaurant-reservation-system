<?php

use App\Models\Reservation;
use App\Models\RestaurantTable;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Book a Table')] class extends Component {
    public int $step = 1;

    #[Validate('required|date|after_or_equal:today')]
    public string $reservation_date = '';

    #[Validate('required|integer|min:1|max:20')]
    public int $party_size = 2;

    public string $selected_time = '';

    #[Validate('required|string|max:255')]
    public string $guest_name = '';

    #[Validate('required|email|max:255')]
    public string $guest_email = '';

    #[Validate('nullable|string|max:30')]
    public ?string $guest_phone = null;

    #[Validate('nullable|string|max:1000')]
    public ?string $special_notes = null;

    public ?string $confirmation_code = null;

    public ?string $confirmed_table_number = null;

    public function mount(): void
    {
        $this->reservation_date = Carbon::today()->toDateString();

        if ($user = auth()->user()) {
            $this->guest_name = $user->name;
            $this->guest_email = $user->email;
        }
    }

    public function searchSlots(): void
    {
        $this->validate([
            'reservation_date' => 'required|date|after_or_equal:today',
            'party_size' => 'required|integer|min:1|max:20',
        ]);

        $this->step = 2;
    }

    /**
     * @return Collection<int, array{time: string, label: string, available_tables: int}>
     */
    #[Computed]
    public function availableSlots(): Collection
    {
        if ($this->step < 2) {
            return collect();
        }

        return Reservation::availableSlotsForDate($this->reservation_date, $this->party_size);
    }

    public function selectSlot(string $time): void
    {
        $available = Reservation::availableTablesForSlot($this->reservation_date, $time, $this->party_size);

        if ($available->isEmpty()) {
            Flux::toast(variant: 'danger', text: __('That slot is no longer available.'));

            return;
        }

        $this->selected_time = $time;
        $this->step = 3;
    }

    public function back(): void
    {
        if ($this->step > 1) {
            if ($this->step === 3) {
                $this->selected_time = '';
            }

            $this->step--;
            $this->resetValidation();
        }
    }

    public function confirmBooking(): void
    {
        $this->validate([
            'reservation_date' => 'required|date|after_or_equal:today',
            'party_size' => 'required|integer|min:1|max:20',
            'selected_time' => 'required',
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'required|email|max:255',
            'guest_phone' => 'nullable|string|max:30',
            'special_notes' => 'nullable|string|max:1000',
        ]);

        $available = Reservation::availableTablesForSlot(
            $this->reservation_date,
            $this->selected_time,
            $this->party_size,
        );

        if ($available->isEmpty()) {
            Flux::toast(variant: 'danger', text: __('Sorry, that slot was just taken. Please pick another.'));
            $this->step = 2;

            return;
        }

        /** @var RestaurantTable $table */
        $table = $available->first();

        $reservation = Reservation::create([
            'user_id' => auth()->id(),
            'restaurant_table_id' => $table->id,
            'guest_name' => $this->guest_name,
            'guest_email' => $this->guest_email,
            'guest_phone' => $this->guest_phone,
            'party_size' => $this->party_size,
            'reservation_date' => $this->reservation_date,
            'start_time' => $this->selected_time,
            'status' => 'confirmed',
            'special_notes' => $this->special_notes,
            'confirmation_code' => Reservation::generateConfirmationCode(),
        ]);

        $this->confirmation_code = $reservation->confirmation_code;
        $this->confirmed_table_number = $table->table_number;
        $this->step = 4;
    }

    public function restart(): void
    {
        $this->reset(['step', 'selected_time', 'special_notes', 'confirmation_code', 'confirmed_table_number']);
        $this->step = 1;
        $this->reservation_date = Carbon::today()->toDateString();
    }

    /**
     * @return Collection<int, array{number: int, title: string, detail: string}>
     */
    #[Computed]
    public function steps(): Collection
    {
        return collect([
            ['number' => 1, 'title' => __('Choose evening'), 'detail' => __('Date and party')],
            ['number' => 2, 'title' => __('Select time'), 'detail' => __('Live availability')],
            ['number' => 3, 'title' => __('Guest details'), 'detail' => __('Reservation profile')],
            ['number' => 4, 'title' => __('Confirmation'), 'detail' => __('Table secured')],
        ]);
    }

    #[Computed]
    public function reservationDateLabel(): string
    {
        return Carbon::parse($this->reservation_date)->format('M j, Y');
    }

    #[Computed]
    public function selectedTimeLabel(): string
    {
        if ($this->selected_time === '') {
            return '';
        }

        return Carbon::parse($this->selected_time)->format('g:i A');
    }
}; ?>

<section class="w-full px-4 py-6 sm:px-6 lg:px-8 lg:py-10">
    <div class="relative mx-auto flex w-full max-w-5xl flex-col gap-8 overflow-hidden rounded-[2rem] border border-white/10 bg-[#090807] px-5 py-6 text-stone-100 shadow-[0_32px_90px_rgba(0,0,0,0.55)] sm:px-8 sm:py-8 lg:px-10 lg:py-10">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute inset-x-0 top-0 h-48 bg-gradient-to-b from-amber-300/12 via-amber-200/4 to-transparent"></div>
            <div class="absolute -right-24 top-12 h-56 w-56 rounded-full bg-amber-200/10 blur-3xl"></div>
            <div class="absolute -left-20 bottom-10 h-64 w-64 rounded-full bg-red-950/35 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,245,214,0.08),_transparent_34%),linear-gradient(135deg,rgba(255,255,255,0.03),transparent_45%,rgba(255,237,189,0.03))]"></div>
        </div>

        <div class="relative space-y-8">
            <div class="space-y-6 rounded-[1.75rem] border border-white/10 bg-white/[0.03] p-6 shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] backdrop-blur sm:p-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl space-y-4">
                        <div class="inline-flex items-center rounded-full border border-amber-200/20 bg-amber-100/8 px-3 py-1 text-[0.68rem] font-medium uppercase tracking-[0.32em] text-amber-100/80">
                            {{ __('Private Dining & Reservations') }}
                        </div>

                        <div class="space-y-3">
                            <h1 class="max-w-xl text-4xl font-semibold tracking-[-0.04em] text-white sm:text-5xl">
                                {{ __('Reserve your table for an evening worth dressing up for.') }}
                            </h1>
                            <p class="max-w-2xl text-sm leading-7 text-stone-300 sm:text-base">
                                {{ __('A refined booking flow for a dark, intimate room. Choose your date, review live availability, and leave the rest to our dining team.') }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 text-left sm:min-w-[24rem]">
                        <div class="rounded-2xl border border-white/10 bg-black/30 px-4 py-4">
                            <p class="text-[0.65rem] uppercase tracking-[0.3em] text-stone-500">{{ __('Experience') }}</p>
                            <p class="mt-3 text-sm font-medium text-stone-100">{{ __('Dark luxury') }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-black/30 px-4 py-4">
                            <p class="text-[0.65rem] uppercase tracking-[0.3em] text-stone-500">{{ __('Service') }}</p>
                            <p class="mt-3 text-sm font-medium text-stone-100">{{ __('Live table holds') }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-black/30 px-4 py-4">
                            <p class="text-[0.65rem] uppercase tracking-[0.3em] text-stone-500">{{ __('Reservation') }}</p>
                            <p class="mt-3 text-sm font-medium text-stone-100">{{ __('Instant confirmation') }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 border-t border-white/10 pt-5 text-sm text-stone-300 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/8 bg-white/[0.025] px-4 py-4">
                        <p class="font-medium text-stone-100">{{ __('Thoughtful pacing') }}</p>
                        <p class="mt-2 leading-6 text-stone-400">{{ __('Each step keeps the booking concise and considered, with no unnecessary friction.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/8 bg-white/[0.025] px-4 py-4">
                        <p class="font-medium text-stone-100">{{ __('Real-time availability') }}</p>
                        <p class="mt-2 leading-6 text-stone-400">{{ __('Slots reflect current table availability so guests can commit with confidence.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/8 bg-white/[0.025] px-4 py-4">
                        <p class="font-medium text-stone-100">{{ __('A polished finish') }}</p>
                        <p class="mt-2 leading-6 text-stone-400">{{ __('The confirmation step feels like a reservation receipt, not a generic success message.') }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
                <aside class="rounded-[1.5rem] border border-white/10 bg-[#0d0b0a] p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]">
                    <div class="mb-5 flex items-center justify-between">
                        <div>
                            <p class="font-mono text-[0.67rem] uppercase tracking-[0.28em] text-stone-500">{{ __('Reservation progress') }}</p>
                            <p class="mt-2 text-sm text-stone-400">{{ __('Step :step of 4', ['step' => $step]) }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-full border border-amber-200/20 bg-amber-100/8 text-lg font-semibold text-amber-100">
                            {{ $step }}
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach ($this->steps as $stepItem)
                            @php
                                $isActive = $step === $stepItem['number'];
                                $isComplete = $step > $stepItem['number'];
                            @endphp

                            <div @class([
                                'rounded-2xl border px-4 py-4 transition-all',
                                'border-amber-200/25 bg-amber-100/8 shadow-[0_0_0_1px_rgba(255,237,189,0.06)]' => $isActive,
                                'border-emerald-300/20 bg-emerald-200/[0.07]' => $isComplete,
                                'border-white/8 bg-white/[0.02]' => ! $isActive && ! $isComplete,
                            ])>
                                <div class="flex items-start gap-3">
                                    <div @class([
                                        'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border text-xs font-semibold',
                                        'border-amber-200/30 bg-amber-100/10 text-amber-50' => $isActive,
                                        'border-emerald-300/30 bg-emerald-200/10 text-emerald-100' => $isComplete,
                                        'border-white/12 bg-transparent text-stone-400' => ! $isActive && ! $isComplete,
                                    ])>
                                        {{ $stepItem['number'] }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-stone-100">{{ $stepItem['title'] }}</p>
                                        <p class="mt-1 text-xs leading-5 text-stone-400">{{ $stepItem['detail'] }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </aside>

                <div class="rounded-[1.75rem] border border-white/10 bg-[linear-gradient(180deg,rgba(255,255,255,0.05),rgba(255,255,255,0.025))] p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] backdrop-blur sm:p-7">
                    @if ($step === 1)
                        <div class="space-y-6">
                            <div class="space-y-2">
                                <p class="font-mono text-[0.67rem] uppercase tracking-[0.28em] text-stone-500">{{ __('Step 1') }}</p>
                                <flux:heading size="xl" class="!text-[1.85rem] text-white">{{ __('Begin your reservation') }}</flux:heading>
                                <p class="max-w-xl text-sm leading-7 text-stone-400">
                                    {{ __('Choose the evening and the size of your party. We will surface the most suitable tables available for your booking.') }}
                                </p>
                            </div>

                            <form wire:submit="searchSlots" class="grid gap-4 md:grid-cols-2">
                                <flux:input type="date" wire:model="reservation_date" :label="__('Date')" min="{{ now()->toDateString() }}" required />
                                <flux:input type="number" wire:model="party_size" :label="__('Party size')" min="1" max="20" required />

                                <div class="md:col-span-2 flex flex-col gap-3 border-t border-white/10 pt-5 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="max-w-sm text-sm text-stone-500">{{ __('Your details are only requested after a time is selected.') }}</p>
                                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto">
                                        {{ __('Find available times') }}
                                    </flux:button>
                                </div>
                            </form>
                        </div>
                    @elseif ($step === 2)
                        <div class="space-y-6">
                            <div class="space-y-2">
                                <div class="flex items-start justify-between gap-4">
                                    <p class="font-mono text-[0.67rem] uppercase tracking-[0.28em] text-stone-500">{{ __('Step 2') }}</p>
                                    <div class="rounded-[10px] border border-white/[0.12] bg-white/[0.06] px-3 py-1.5 text-xs text-stone-400">
                                        {{ __('Party of :size · :date', ['size' => $party_size, 'date' => $this->reservationDateLabel]) }}
                                    </div>
                                </div>
                                <flux:heading size="xl" class="!text-[1.85rem] text-white">{{ __('Choose a seating time') }}</flux:heading>
                                <p class="max-w-xl text-sm leading-7 text-stone-400">
                                    {{ __('Availability is updated in real time for a party of :size on :date.', ['size' => $party_size, 'date' => $this->reservationDateLabel]) }}
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @forelse ($this->availableSlots as $slot)
                                    <button
                                        type="button"
                                        wire:click="selectSlot('{{ $slot['time'] }}')"
                                        @disabled($slot['available_tables'] === 0)
                                        @class([
                                            'group rounded-[14px] border px-4 py-4 text-left transition duration-200',
                                            'border-white/[0.12] bg-white/[0.06] hover:border-white/[0.22] hover:bg-white/[0.09]' => $slot['available_tables'] > 0,
                                            'cursor-not-allowed border-white/[0.06] bg-white/[0.02] opacity-40' => $slot['available_tables'] === 0,
                                        ])
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-[18px] font-bold leading-tight tracking-[-0.01em] text-white">{{ $slot['label'] }}</p>
                                                <p class="mt-2 font-mono text-[9px] uppercase tracking-[0.12em] text-stone-500">{{ __('Table service') }}</p>
                                            </div>
                                            <div class="shrink-0 text-right font-mono text-[10px] leading-snug text-stone-500">
                                                {{ $slot['available_tables'] }}<br />{{ __('left') }}
                                            </div>
                                        </div>
                                        <p class="mt-4 text-[13px] leading-[1.6] text-stone-400">
                                            @if ($slot['available_tables'] === 0)
                                                {{ __('Fully committed for this seating.') }}
                                            @elseif ($slot['available_tables'] === 1)
                                                {{ __('Last suitable table available for your party.') }}
                                            @else
                                                {{ __('A strong option for a comfortable arrival and unhurried dinner pacing.') }}
                                            @endif
                                        </p>
                                    </button>
                                @empty
                                    <div class="sm:col-span-2 xl:col-span-3 rounded-[14px] border border-dashed border-white/[0.12] bg-white/[0.02] px-5 py-10 text-center">
                                        <p class="text-lg font-medium text-white">{{ __('No seatings available for this date.') }}</p>
                                        <p class="mx-auto mt-3 max-w-xl text-sm leading-7 text-stone-400">
                                            {{ __('Try another date or adjust the size of your party and we will check again.') }}
                                        </p>
                                    </div>
                                @endforelse
                            </div>

                            <div class="flex justify-start border-t border-white/10 pt-5">
                                <flux:button type="button" variant="ghost" wire:click="back">{{ __('Back') }}</flux:button>
                            </div>
                        </div>
                    @elseif ($step === 3)
                        <div class="space-y-5">
                            <div class="space-y-2">
                                <p class="font-mono text-[0.67rem] uppercase tracking-[0.28em] text-stone-500">{{ __('Step 3') }}</p>
                                <flux:heading size="xl" class="!text-[1.85rem] text-white">{{ __('Complete your reservation') }}</flux:heading>
                                <p class="max-w-xl text-sm leading-7 text-stone-400">
                                    {{ __('You are reserving for :date at :time, party of :size. Share your guest details and any notes the dining room should be aware of.', [
                                        'date' => $this->reservationDateLabel,
                                        'time' => $this->selectedTimeLabel,
                                        'size' => $party_size,
                                    ]) }}
                                </p>
                            </div>

                            <div class="rounded-[10px] border border-white/[0.12] bg-white/[0.06] px-4 py-3 text-sm text-stone-400">
                                {{ __('Selected seating · :date at :time · party of :size', [
                                    'date' => $this->reservationDateLabel,
                                    'time' => $this->selectedTimeLabel,
                                    'size' => $party_size,
                                ]) }}
                            </div>

                            <form wire:submit="confirmBooking" class="grid gap-4 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <flux:input wire:model="guest_name" :label="__('Full name')" required />
                                </div>
                                <flux:input type="email" wire:model="guest_email" :label="__('Email')" required />
                                <flux:input wire:model="guest_phone" :label="__('Phone')" />
                                <div class="md:col-span-2">
                                    <flux:textarea wire:model="special_notes" :label="__('Special notes')" rows="4" />
                                </div>

                                <div class="md:col-span-2 flex flex-col gap-3 border-t border-white/10 pt-5 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="max-w-sm text-sm text-stone-500">
                                        {{ __('Dietary notes, celebrations, or timing preferences can be shared here for the team.') }}
                                    </p>
                                    <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                                        <flux:button variant="ghost" type="button" wire:click="back">{{ __('Back') }}</flux:button>
                                        <flux:button variant="primary" type="submit">{{ __('Confirm booking') }}</flux:button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    @elseif ($step === 4)
                        <div class="space-y-5">
                            <div class="rounded-[1.75rem] border border-emerald-200/15 bg-[linear-gradient(180deg,rgba(16,185,129,0.12),rgba(3,7,18,0.35))] p-6 shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] sm:p-8">
                                <div class="space-y-5">
                                    <div class="inline-flex items-center rounded-full border border-emerald-200/20 bg-emerald-200/10 px-3 py-1 text-[0.68rem] font-medium uppercase tracking-[0.32em] text-emerald-50">
                                        {{ __('Reservation confirmed') }}
                                    </div>

                                    <div class="space-y-3">
                                        <flux:heading size="xl" class="text-white">{{ __('Your table is secured.') }}</flux:heading>
                                        <p class="max-w-2xl text-sm leading-7 text-stone-200/90">
                                            {{ __('We look forward to hosting you. Keep your confirmation code close and present it on arrival if needed.') }}
                                        </p>
                                    </div>

                                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_16rem]">
                                        <div class="rounded-[1.35rem] border border-white/10 bg-black/30 p-5">
                                            <p class="text-[0.68rem] uppercase tracking-[0.32em] text-stone-500">{{ __('Reservation code') }}</p>
                                            <div class="mt-4 text-3xl font-mono font-bold tracking-[0.35em] text-white sm:text-4xl">{{ $confirmation_code }}</div>
                                        </div>

                                        <div class="rounded-[1.35rem] border border-white/10 bg-black/30 p-5 text-sm leading-7 text-stone-300">
                                            <p class="font-medium text-white">{{ __('Evening details') }}</p>
                                            <p class="mt-3">{{ __('Table :table', ['table' => $confirmed_table_number]) }}</p>
                                            <p>{{ $this->reservationDateLabel }}</p>
                                            <p>{{ $this->selectedTimeLabel }}</p>
                                            <p>{{ __('Party of :size', ['size' => $party_size]) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-start">
                                <flux:button wire:click="restart">{{ __('Make another booking') }}</flux:button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
