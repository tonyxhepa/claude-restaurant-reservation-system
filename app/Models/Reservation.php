<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'restaurant_table_id',
    'guest_name',
    'guest_email',
    'guest_phone',
    'party_size',
    'reservation_date',
    'start_time',
    'status',
    'special_notes',
    'confirmation_code',
    'cancelled_at',
    'completed_at',
])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected function setStartTimeAttribute(string $value): void
    {
        $this->attributes['start_time'] = CarbonImmutable::parse($value)->format('H:i:s');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    public function scopeForDate(Builder $query, string $date): void
    {
        $query->whereDate('reservation_date', $date);
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    public function scopeToday(Builder $query): void
    {
        $query->whereDate('reservation_date', Carbon::today());
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereDate('reservation_date', '>=', Carbon::today());
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', ['pending', 'confirmed']);
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    public function scopeForGuest(Builder $query, string $email): void
    {
        $query->where('guest_email', $email);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'], true);
    }

    public function endTime(): string
    {
        return CarbonImmutable::parse($this->start_time)->addMinutes(90)->format('H:i');
    }

    public function formattedDate(): string
    {
        return $this->reservation_date->format('M j, Y');
    }

    public function formattedTime(): string
    {
        return CarbonImmutable::parse($this->start_time)->format('g:i A');
    }

    public static function generateConfirmationCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (self::where('confirmation_code', $code)->exists());

        return $code;
    }

    public static function isTableAvailable(
        int $tableId,
        string $date,
        string $startTime,
        int $bufferMinutes = 90,
        ?int $excludeId = null,
    ): bool {
        $start = CarbonImmutable::parse($startTime);
        $windowStart = $start->subMinutes($bufferMinutes)->format('H:i:s');
        $windowEnd = $start->addMinutes($bufferMinutes)->format('H:i:s');

        $query = self::query()
            ->where('restaurant_table_id', $tableId)
            ->whereDate('reservation_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('start_time', '>=', $windowStart)
            ->where('start_time', '<', $windowEnd);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return ! $query->exists();
    }

    /**
     * @return Collection<int, RestaurantTable>
     */
    public static function availableTablesForSlot(string $date, string $startTime, int $partySize): Collection
    {
        $start = CarbonImmutable::parse($startTime);
        $windowStart = $start->subMinutes(90)->format('H:i:s');
        $windowEnd = $start->addMinutes(90)->format('H:i:s');

        $busyTableIds = self::query()
            ->whereDate('reservation_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('start_time', '>=', $windowStart)
            ->where('start_time', '<', $windowEnd)
            ->pluck('restaurant_table_id')
            ->unique()
            ->all();

        return RestaurantTable::query()
            ->active()
            ->minCapacity($partySize)
            ->whereNotIn('id', $busyTableIds)
            ->orderBy('capacity')
            ->get();
    }

    /**
     * @return BaseCollection<int, array{time: string, label: string, available_tables: int}>
     */
    public static function availableSlotsForDate(string $date, int $partySize): BaseCollection
    {
        $slots = collect();
        $current = CarbonImmutable::parse($date.' 11:00:00');
        $end = CarbonImmutable::parse($date.' 21:30:00');

        while ($current->lessThanOrEqualTo($end)) {
            $time = $current->format('H:i');
            $available = self::availableTablesForSlot($date, $time, $partySize);

            $slots->push([
                'time' => $time,
                'label' => $current->format('g:i A'),
                'available_tables' => $available->count(),
            ]);

            $current = $current->addMinutes(30);
        }

        return $slots;
    }
}
