<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class City extends Model
{
    protected $fillable = [
        'name',
        'timezone',
        'is_available',
        'load_percentage',
        'levelion_city_id',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'load_percentage' => 'decimal:2',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Объединяет записи с одинаковым названием; оставляет одну — с levelion_city_id, иначе с меньшим id.
     */
    public static function dedupeByName(): int
    {
        /** @var Collection<string, Collection<int, self>> $groups */
        $groups = static::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (self $city) => mb_strtolower(trim($city->name)));

        $removed = 0;

        foreach ($groups as $cities) {
            if ($cities->count() <= 1) {
                continue;
            }

            $keeper = static::pickKeeper($cities);

            foreach ($cities->where('id', '!=', $keeper->id) as $duplicate) {
                DB::transaction(function () use ($keeper, $duplicate): void {
                    static::reassignReferences($duplicate->id, $keeper->id);

                    $updates = [];

                    if ($keeper->levelion_city_id === null && $duplicate->levelion_city_id !== null) {
                        $levelionCityId = $duplicate->levelion_city_id;
                        DB::table('cities')
                            ->where('id', $duplicate->id)
                            ->update(['levelion_city_id' => null]);
                        $updates['levelion_city_id'] = $levelionCityId;
                    }

                    if (($keeper->timezone ?? '') === '' && ($duplicate->timezone ?? '') !== '') {
                        $updates['timezone'] = $duplicate->timezone;
                    }

                    if ($keeper->load_percentage <= 0 && (float) $duplicate->load_percentage > 0) {
                        $updates['load_percentage'] = $duplicate->load_percentage;
                    }

                    if (! $keeper->is_available && $duplicate->is_available) {
                        $updates['is_available'] = true;
                    }

                    if ($updates !== []) {
                        $keeper->update($updates);
                        $keeper->refresh();
                    }

                    $duplicate->delete();
                });

                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @param  Collection<int, self>  $cities
     */
    private static function pickKeeper(Collection $cities): self
    {
        $withLevelion = $cities->filter(fn (self $city) => $city->levelion_city_id !== null);

        if ($withLevelion->isNotEmpty()) {
            return $withLevelion->sortBy('id')->first();
        }

        return $cities->sortBy('id')->first();
    }

    private static function reassignReferences(int $fromId, int $toId): void
    {
        if ($fromId === $toId) {
            return;
        }

        DB::table('orders')->where('city_id', $fromId)->update(['city_id' => $toId]);
        DB::table('reviews')->where('city_id', $fromId)->update(['city_id' => $toId]);
        DB::table('withdrawal_request_items')->where('city_id', $fromId)->update(['city_id' => $toId]);

        $pivotRows = DB::table('user_allowed_cities')->where('city_id', $fromId)->get();

        foreach ($pivotRows as $row) {
            $alreadyAllowed = DB::table('user_allowed_cities')
                ->where('user_id', $row->user_id)
                ->where('city_id', $toId)
                ->exists();

            if ($alreadyAllowed) {
                DB::table('user_allowed_cities')->where('id', $row->id)->delete();
            } else {
                DB::table('user_allowed_cities')->where('id', $row->id)->update(['city_id' => $toId]);
            }
        }
    }
}
