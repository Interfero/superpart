<?php

namespace App\Support;

class MeetingTime
{
    /** Округление времени HH:MM вверх до ближайших 5 минут. */
    public static function roundUpToFiveMinutes(string $time): string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) {
            return $time;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        $minutes = (int) ceil($minutes / 5) * 5;

        if ($minutes >= 60) {
            $minutes = 0;
            $hours = ($hours + 1) % 24;
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
