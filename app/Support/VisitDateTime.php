<?php

namespace App\Support;

use App\Models\City;
use Illuminate\Support\Carbon;

final class VisitDateTime
{
    public static function cityTimezone(City $city): string
    {
        $tz = trim((string) $city->timezone);

        if ($tz !== '') {
            try {
                new \DateTimeZone($tz);

                return $tz;
            } catch (\Throwable) {
                //
            }
        }

        return (string) config('app.timezone', 'UTC');
    }

    public static function cityNow(City $city): Carbon
    {
        return Carbon::now(self::cityTimezone($city));
    }

    public static function parseVisit(City $city, string $dateDmy, string $timeHm): Carbon
    {
        return Carbon::createFromFormat(
            'd.m.Y H:i',
            $dateDmy.' '.MeetingTime::roundUpToFiveMinutes($timeHm),
            self::cityTimezone($city)
        );
    }

    public static function formatInCity(City $city, ?Carbon $moment): ?string
    {
        if ($moment === null) {
            return null;
        }

        return $moment
            ->copy()
            ->timezone(self::cityTimezone($city))
            ->format('d.m.Y, H:i');
    }
}
