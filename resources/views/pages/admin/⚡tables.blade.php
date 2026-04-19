<?php

use App\Models\RestaurantTable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Tables')] class extends Component {
    public ?int $editingId = null;

    #[Validate('required|string|max:50')]
    public string $table_number = '';

    #[Validate('required|integer|min:1|max:50')]
    public int $capacity = 2;

    #[Validate('required|string|in:indoor,outdoor,bar,patio')]
    public string $section = 'indoor';

    #[Validate('boolean')]
    public bool $is_active = true;

    #[Validate('nullable|string|max:1000')]
    public ?string $notes = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    /**
     * @return Collection<int, RestaurantTable>
     */
    #[Computed]
    public function tables(): Collection
    {
        return RestaurantTable::query()
            ->withCount('reservations')
            ->orderBy('section')
            ->orderBy('table_number')
            ->get();
    }

    /**
     * @return array<int, array{label: string, value: string, hint: string, accent: string}>
     */
    #[Computed]
    public function tableSummary(): array
    {
        $tables = $this->tables;

        return [
            [
                'label' => __('Active Tables'),
                'value' => (string) $tables->where('is_active', true)->count(),
                'hint' => __('Ready for seating'),
                'accent' => 'amber',
            ],
            [
                'label' => __('Inactive'),
                'value' => (string) $tables->where('is_active', false)->count(),
                'hint' => __('Off the floor'),
                'accent' => 'stone',
            ],
            [
                'label' => __('Capacity'),
                'value' => (string) $tables->sum('capacity'),
                'hint' => __('Total available seats'),
                'accent' => 'emerald',
            ],
            [
                'label' => __('Reservations'),
                'value' => (string) $tables->sum('reservations_count'),
                'hint' => __('Lifetime bookings tracked'),
                'accent' => 'copper',
            ],
        ];
    }

    /**
     * @return array<int, array{section: string, tables: int, seats: string}>
     */
    #[Computed]
    public function sectionSummary(): array
    {
        return collect(RestaurantTable::sections())
            ->map(function (string $section): array {
                $tables = $this->tables->where('section', $section);

                return [
                    'section' => ucfirst($section),
                    'tables' => $tables->count(),
                    'seats' => Number::format($tables->sum('capacity')),
                ];
            })
            ->all();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'table_number', 'capacity', 'section', 'is_active', 'notes']);
        $this->capacity = 2;
        $this->section = 'indoor';
        $this->is_active = true;
        $this->resetValidation();
        Flux::modal('table-form')->show();
    }

    public function openEdit(int $id): void
    {
        $table = RestaurantTable::findOrFail($id);
        $this->editingId = $table->id;
        $this->table_number = $table->table_number;
        $this->capacity = $table->capacity;
        $this->section = $table->section;
        $this->is_active = $table->is_active;
        $this->notes = $table->notes;
        $this->resetValidation();
        Flux::modal('table-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'table_number' => 'required|string|max:50|unique:restaurant_tables,table_number'.($this->editingId ? ','.$this->editingId : ''),
            'capacity' => 'required|integer|min:1|max:50',
            'section' => 'required|string|in:indoor,outdoor,bar,patio',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($this->editingId) {
            RestaurantTable::findOrFail($this->editingId)->update($validated);
            Flux::toast(variant: 'success', text: __('Table updated.'));
        } else {
            RestaurantTable::create($validated);
            Flux::toast(variant: 'success', text: __('Table created.'));
        }

        Flux::modal('table-form')->close();
        unset($this->tables);
        unset($this->tableSummary, $this->sectionSummary);
    }

    public function toggleActive(int $id): void
    {
        $table = RestaurantTable::findOrFail($id);
        $table->update(['is_active' => ! $table->is_active]);
        unset($this->tables);
        unset($this->tableSummary, $this->sectionSummary);
    }
}; ?>

<style>
    @import url('https://fonts.bunny.net/css?family=cormorant-garamond:500,600,700|manrope:400,500,600,700');

    .admin-console {
        --console-bg: linear-gradient(180deg, rgb(15 23 42 / 0.98) 0%, rgb(24 24 27 / 0.96) 100%);
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
                    <p class="text-[0.7rem] font-semibold uppercase tracking-[0.38em] text-amber-300/75">{{ __('Dining Room') }}</p>
                    <flux:heading size="xl" class="admin-console-heading mt-3 !text-4xl !font-semibold !text-zinc-50 sm:!text-5xl">
                        {{ __('Floor Overview') }}
                    </flux:heading>
                    <flux:text class="mt-3 max-w-xl text-sm leading-6 !text-zinc-300/80 sm:text-base">
                        {{ __('Keep every section of the room composed, seatable, and beautifully organized from a single luxury hospitality control surface.') }}
                    </flux:text>
                </div>

                <div class="grid gap-3 rounded-[1.5rem] border border-amber-300/15 bg-white/5 p-3 sm:grid-cols-2 xl:min-w-[24rem]">
                    @foreach ($this->sectionSummary as $section)
                        <div class="rounded-[1.2rem] border border-white/10 bg-black/15 p-4">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.3em] text-zinc-500">{{ $section['section'] }}</p>
                            <div class="mt-3 flex items-end justify-between gap-3">
                                <div>
                                    <p class="text-2xl font-semibold text-zinc-50">{{ $section['tables'] }}</p>
                                    <p class="text-xs text-zinc-400">{{ trans_choice(':count table|:count tables', $section['tables'], ['count' => $section['tables']]) }}</p>
                                </div>
                                <span class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-[0.68rem] uppercase tracking-[0.24em] text-zinc-400">
                                    {{ $section['seats'] }} {{ __('seats') }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="-mx-5 sm:-mx-8 mt-8 grid grid-cols-2 border-t border-white/10 md:grid-cols-4">
                @foreach ($this->tableSummary as $i => $summary)
                    @php
                        $isLast = $i === count($this->tableSummary) - 1;
                        $isHighlighted = $summary['accent'] === 'amber';
                    @endphp
                    <div class="{{ $isLast ? '' : 'border-r border-white/10' }} px-6 py-5 sm:px-8">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.28em] text-zinc-500">{{ $summary['label'] }}</p>
                        <p class="mt-2 text-[2rem] font-bold leading-none tracking-tight {{ $isHighlighted ? 'text-amber-300' : 'text-zinc-50' }}">{{ $summary['value'] }}</p>
                        <p class="mt-1.5 text-[0.68rem] uppercase tracking-[0.2em] {{ $isHighlighted ? 'text-amber-400/70' : 'text-zinc-500' }}">{{ $summary['hint'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-zinc-950/95 px-5 py-5 sm:px-8">
            <div class="rounded-[1.5rem] border border-white/10 bg-white/[0.03] p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.3em] text-amber-200/65">{{ __('Table Registry') }}</p>
                        <h2 class="admin-console-heading mt-2 text-3xl font-semibold text-zinc-50">{{ __('Tables') }}</h2>
                        <p class="mt-2 text-sm text-zinc-400">{{ __('Edit floor inventory, review section balance, and keep table metadata crisp for service.') }}</p>
                    </div>

                    <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('New Table') }}</flux:button>
                </div>

                <div class="mt-5 overflow-hidden rounded-[1.4rem] border border-white/10 bg-zinc-950/60">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Table #') }}</flux:table.column>
                            <flux:table.column>{{ __('Section') }}</flux:table.column>
                            <flux:table.column>{{ __('Capacity') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            <flux:table.column>{{ __('Reservations') }}</flux:table.column>
                            <flux:table.column>{{ __('Action') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->tables as $table)
                                <flux:table.row wire:key="table-{{ $table->id }}">
                                    <flux:table.cell>
                                        <div class="space-y-1">
                                            <p class="font-medium text-zinc-100">{{ $table->table_number }}</p>
                                            <p class="text-xs uppercase tracking-[0.24em] text-zinc-500">{{ __('Service ready') }}</p>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <span class="inline-flex rounded-full border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs uppercase tracking-[0.24em] text-zinc-300">
                                            {{ ucfirst($table->section) }}
                                        </span>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="space-y-1">
                                            <p class="font-medium text-zinc-100">{{ $table->capacity }}</p>
                                            <p class="text-xs text-zinc-500">{{ trans_choice(':count seat|:count seats', $table->capacity, ['count' => $table->capacity]) }}</p>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($table->is_active)
                                            <flux:badge color="lime" size="sm">{{ __('Active') }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <p class="font-medium text-zinc-100">{{ $table->reservations_count }}</p>
                                        <div class="mt-1.5 flex items-center gap-2">
                                            <div class="h-[3px] w-24 overflow-hidden rounded-full bg-white/10">
                                                <div class="h-full rounded-full bg-lime-400/70" style="width: {{ min(100, $table->reservations_count * 12) }}%"></div>
                                            </div>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex flex-wrap gap-2">
                                            <flux:button size="sm" wire:click="openEdit({{ $table->id }})">{{ __('Edit') }}</flux:button>
                                            <flux:button size="sm" variant="subtle" wire:click="toggleActive({{ $table->id }})">
                                                {{ $table->is_active ? __('Deactivate') : __('Activate') }}
                                            </flux:button>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>
        </div>
    </div>

    <flux:modal name="table-form" class="md:w-[32rem]">
        <div class="rounded-[1.75rem] border border-amber-300/15 bg-[linear-gradient(180deg,rgba(15,23,42,0.98),rgba(24,24,27,0.98))] p-1 shadow-[0_30px_80px_rgba(0,0,0,0.55)]">
            <form wire:submit="save" class="space-y-6 rounded-[1.45rem] border border-white/10 bg-black/15 p-6">
                <div>
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.3em] text-amber-200/65">{{ $editingId ? __('Refine Table') : __('Add Table') }}</p>
                    <flux:heading size="lg" class="admin-console-heading mt-2 !text-3xl !font-semibold !text-zinc-50">
                        {{ $editingId ? __('Edit Table') : __('New Table') }}
                    </flux:heading>
                    <flux:text class="mt-2 !text-zinc-400">
                        {{ __('Maintain floor accuracy with the same polished control surface used across the admin suite.') }}
                    </flux:text>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="table_number" :label="__('Table number')" required />
                    <flux:input type="number" wire:model="capacity" :label="__('Capacity')" min="1" required />
                </div>

                <flux:select wire:model="section" :label="__('Section')" required>
                    @foreach (\App\Models\RestaurantTable::sections() as $section)
                        <flux:select.option value="{{ $section }}">{{ ucfirst($section) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:switch wire:model.live="is_active" :label="__('Active')" />

                <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</section>
