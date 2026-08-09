<?php

namespace Raikia\SeatTimerboard\Services;

class TimerSyncMarker
{
    private const PREFIX = '[seat-timerboard-sync]';
    private const SUFFIX = '[/seat-timerboard-sync]';

    public function append(?string $notes, array $payload): string
    {
        $cleanNotes = $this->strip($notes);
        $marker = self::PREFIX . json_encode($payload) . self::SUFFIX;

        return trim(implode("\n\n", array_filter([
            $cleanNotes,
            $marker,
        ])));
    }

    public function extract(?string $notes): ?array
    {
        $raw = (string) $notes;
        $pattern = '/' . preg_quote(self::PREFIX, '/') . '(.*?)' . preg_quote(self::SUFFIX, '/') . '/s';

        if (! preg_match($pattern, $raw, $matches)) {
            return null;
        }

        $decoded = json_decode(trim($matches[1]), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function strip(?string $notes): ?string
    {
        $raw = trim((string) $notes);

        if ($raw === '') {
            return null;
        }

        $pattern = '/' . preg_quote(self::PREFIX, '/') . '.*?' . preg_quote(self::SUFFIX, '/') . '/s';
        $clean = trim(preg_replace($pattern, '', $raw) ?? '');

        return $clean === '' ? null : $clean;
    }
}
