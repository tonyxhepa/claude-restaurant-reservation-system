<?php

namespace App\Models;

use Database\Factories\RestaurantTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['table_number', 'capacity', 'section', 'is_active', 'notes'])]
class RestaurantTable extends Model
{
    /** @use HasFactory<RestaurantTableFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * @param  Builder<RestaurantTable>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<RestaurantTable>  $query
     */
    public function scopeSection(Builder $query, string $section): void
    {
        $query->where('section', $section);
    }

    /**
     * @param  Builder<RestaurantTable>  $query
     */
    public function scopeMinCapacity(Builder $query, int $capacity): void
    {
        $query->where('capacity', '>=', $capacity);
    }

    /**
     * @return array<int, string>
     */
    public static function sections(): array
    {
        return ['indoor', 'outdoor', 'bar', 'patio'];
    }
}
