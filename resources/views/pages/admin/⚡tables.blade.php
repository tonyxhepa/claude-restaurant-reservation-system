<?php

use App\Models\RestaurantTable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
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
    }

    public function toggleActive(int $id): void
    {
        $table = RestaurantTable::findOrFail($id);
        $table->update(['is_active' => ! $table->is_active]);
        unset($this->tables);
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Tables') }}</flux:heading>
            <flux:subheading>{{ __('Manage restaurant tables') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('New Table') }}</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Table #') }}</flux:table.column>
            <flux:table.column>{{ __('Section') }}</flux:table.column>
            <flux:table.column>{{ __('Capacity') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Reservations') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->tables as $table)
                <flux:table.row>
                    <flux:table.cell class="font-medium">{{ $table->table_number }}</flux:table.cell>
                    <flux:table.cell class="capitalize">{{ $table->section }}</flux:table.cell>
                    <flux:table.cell>{{ $table->capacity }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($table->is_active)
                            <flux:badge color="lime" size="sm">{{ __('Active') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $table->reservations_count }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
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

    <flux:modal name="table-form" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit Table') : __('New Table') }}</flux:heading>
            </div>

            <flux:input wire:model="table_number" :label="__('Table number')" required />
            <flux:input type="number" wire:model="capacity" :label="__('Capacity')" min="1" required />

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
    </flux:modal>
</section>
