<?php

use App\Models\Reservation;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * @return Builder<Reservation>
     */
    protected function reservationsQuery(): Builder
    {
        return Reservation::query()
            ->with('table', 'user')
            ->when($this->search, fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('guest_name', 'like', '%'.$this->search.'%')
                    ->orWhere('guest_email', 'like', '%'.$this->search.'%');
            }))
            ->when($this->statusFilter, fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->dateFilter, fn (Builder $query) => $query->whereDate('reservation_date', $this->dateFilter));
    }

    #[Computed]
    public function reservations(): LengthAwarePaginator
    {
        return $this->reservationsQuery()
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(20);
    }

    /**
     * @return array<int, array{label: string, value: string, hint: string, accent: string}>
     */
    #[Computed]
    public function reservationSummary(): array
    {
        $today = Reservation::query()->today();

        return [
            [
                'label' => __('Today'),
                'value' => (string) (clone $today)->count(),
                'hint' => __('Bookings scheduled'),
                'accent' => 'amber',
            ],
            [
                'label' => __('Confirmed'),
                'value' => (string) (clone $today)->where('status', 'confirmed')->count(),
                'hint' => __('Ready for service'),
                'accent' => 'emerald',
            ],
            [
                'label' => __('Pending'),
                'value' => (string) Reservation::query()->where('status', 'pending')->count(),
                'hint' => __('Awaiting approval'),
                'accent' => 'copper',
            ],
            [
                'label' => __('Guests'),
                'value' => (string) ((clone $today)->active()->sum('party_size')),
                'hint' => __('Expected covers today'),
                'accent' => 'stone',
            ],
        ];
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

<style>
    @import url('https://fonts.bunny.net/css?family=cormorant-garamond:500,600,700|manrope:400,500,600,700');

    .admin-console {
        --console-bg: linear-gradient(180deg, rgb(15 23 42 / 0.98) 0%, rgb(24 24 27 / 0.96) 100%);
        --console-panel: rgb(24 24 27 / 0.78);
        --console-panel-strong: rgb(39 39 42 / 0.86);
        --console-border: rgb(245 158 11 / 0.18);
        --console-highlight: rgb(251 191 36 / 0.88);
        --console-muted: rgb(161 161 170);
        --console-text: rgb(244 244 245);
        --console-heading: 'Cormorant Garamond', Georgia, serif;
        --console-body: 'Manrope', ui-sans-serif, system-ui, sans-serif;
        font-family: var(--console-body);
    }

    .admin-console-heading {
        font-family: var(--console-heading);
        letter-spacing: 0.01em;
    }
</style>

<section class="admin-console w-full p-4 sm:p-6">
    <div class="overflow-hidden rounded-[2rem] border border-amber-400/15 bg-[radial-gradient(circle_at_top,_rgba(251,191,36,0.18),_transparent_34%),linear-gradient(135deg,rgba(255,255,255,0.02),rgba(255,255,255,0))] shadow-[0_24px_80px_rgba(0,0,0,0.45)] ring-1 ring-white/5">
        <div class="border-b border-white/10 bg-[var(--console-bg)] px-5 py-6 sm:px-8 sm:py-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-2xl">
                    <p class="text-[0.7rem] font-semibold uppercase tracking-[0.38em] text-amber-300/75">{{ __('Service Desk') }}</p>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <flux:heading size="xl" class="admin-console-heading !text-4xl !font-semibold !text-zinc-50 sm:!text-5xl">
                                {{ __('Reservations Overview') }}
                            </flux:heading>
                            <flux:text class="mt-3 max-w-xl text-sm leading-6 !text-zinc-300/80 sm:text-base">
                                {{ __("A polished maitre d' console for tracking tonight's book, confirming arrivals, and keeping service flowing with confidence.") }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 rounded-[1.5rem] border border-amber-300/15 bg-white/5 p-3 sm:grid-cols-2 xl:min-w-[22rem]">
                    <div class="rounded-[1.2rem] border border-white/10 bg-black/15 p-4">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.3em] text-zinc-500">{{ __('Open Filters') }}</p>
                        <p class="mt-2 text-sm font-medium text-zinc-100">{{ __('Search, refine, and sort the service ledger in real time.') }}</p>
                    </div>
                    <div class="rounded-[1.2rem] border border-amber-300/15 bg-amber-400/10 p-4">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.3em] text-amber-200/80">{{ __('Live status') }}</p>
                        <p class="mt-2 text-sm font-medium text-zinc-100">{{ __('Actions remain unchanged so the team can move fast without relearning the page.') }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($this->reservationSummary as $summary)
                    @php
                        $accentClasses = match ($summary['accent']) {
                            'amber' => 'from-amber-300/20 via-amber-200/8 to-transparent border-amber-300/25 text-amber-100',
                            'emerald' => 'from-emerald-300/18 via-emerald-200/8 to-transparent border-emerald-300/20 text-emerald-100',
                            'copper' => 'from-orange-300/16 via-amber-200/8 to-transparent border-orange-300/20 text-orange-100',
                            default => 'from-zinc-200/10 via-zinc-200/5 to-transparent border-white/10 text-zinc-100',
                        };
                    @endphp
                    <div class="rounded-[1.5rem] border bg-gradient-to-br p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.04)] {{ $accentClasses }}">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.3em] text-zinc-400">{{ $summary['label'] }}</p>
                        <div class="mt-4 flex items-end justify-between gap-4">
                            <span class="text-3xl font-semibold tracking-tight text-white">{{ $summary['value'] }}</span>
                            <span class="rounded-full border border-white/10 bg-black/10 px-3 py-1 text-[0.68rem] uppercase tracking-[0.24em] text-zinc-400">
                                {{ $summary['hint'] }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-zinc-950/95 px-5 py-5 sm:px-8">
            <div class="rounded-[1.5rem] border border-white/10 bg-white/[0.03] p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.3em] text-amber-200/65">{{ __('Service Ledger') }}</p>
                        <h2 class="admin-console-heading mt-2 text-3xl font-semibold text-zinc-50">{{ __('All Reservations') }}</h2>
                        <p class="mt-2 text-sm text-zinc-400">{{ __('Filter the dining room book by guest, date, and status without losing the premium operational feel.') }}</p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3 lg:min-w-[46rem]">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ __('Search guest or email') }}"
                            icon="magnifying-glass"
                            class="min-w-0"
                        />
                        <flux:select wire:model.live="statusFilter" placeholder="{{ __('All statuses') }}" class="min-w-0">
                            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                            <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
                            <flux:select.option value="confirmed">{{ __('Confirmed') }}</flux:select.option>
                            <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
                            <flux:select.option value="completed">{{ __('Completed') }}</flux:select.option>
                        </flux:select>
                        <flux:input type="date" wire:model.live="dateFilter" class="min-w-0" />
                    </div>
                </div>

                <div class="mt-5 overflow-hidden rounded-[1.4rem] border border-white/10 bg-zinc-950/60">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column sortable :sorted="$sortBy === 'reservation_date'" :direction="$sortDir" wire:click="sort('reservation_date')">{{ __('Date') }}</flux:table.column>
                            <flux:table.column sortable :sorted="$sortBy === 'start_time'" :direction="$sortDir" wire:click="sort('start_time')">{{ __('Time') }}</flux:table.column>
                            <flux:table.column sortable :sorted="$sortBy === 'guest_name'" :direction="$sortDir" wire:click="sort('guest_name')">{{ __('Guest') }}</flux:table.column>
                            <flux:table.column>{{ __('Table') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            <flux:table.column>{{ __('Action') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->reservations as $reservation)
                                @php
                                    $color = match ($reservation->status) {
                                        'pending' => 'amber',
                                        'confirmed' => 'lime',
                                        'cancelled' => 'red',
                                        'completed' => 'zinc',
                                    };
                                @endphp

                                <flux:table.row wire:key="reservation-{{ $reservation->id }}">
                                    <flux:table.cell>
                                        <div class="space-y-1">
                                            <p class="font-medium text-zinc-100">{{ $reservation->formattedDate() }}</p>
                                            <p class="text-xs uppercase tracking-[0.24em] text-zinc-500">{{ $reservation->reservation_date->format('l') }}</p>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="space-y-1">
                                            <p class="font-medium text-zinc-100">{{ $reservation->formattedTime() }}</p>
                                            <p class="text-xs text-zinc-500">{{ __('90 min service window') }}</p>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="space-y-2">
                                            <div>
                                                <p class="font-medium text-zinc-100">{{ $reservation->guest_name }}</p>
                                                <p class="text-sm text-zinc-400">{{ $reservation->guest_email }}</p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                                                <span class="rounded-full border border-white/10 bg-white/[0.03] px-2.5 py-1">
                                                    {{ trans_choice(':count guest|:count guests', $reservation->party_size, ['count' => $reservation->party_size]) }}
                                                </span>
                                                <span class="font-mono uppercase tracking-[0.24em]">{{ $reservation->confirmation_code }}</span>
                                            </div>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="space-y-1">
                                            <p class="font-medium text-zinc-100">{{ __('Table') }} {{ $reservation->table->table_number }}</p>
                                            <p class="text-xs uppercase tracking-[0.24em] text-zinc-500">{{ ucfirst($reservation->table->section) }}</p>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$color" size="sm">{{ ucfirst($reservation->status) }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex flex-wrap gap-2">
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
                </div>

                <div class="mt-4">
                    {{ $this->reservations->links() }}
                </div>
            </div>
        </div>
    </div>
</section>
