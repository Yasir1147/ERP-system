<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use Illuminate\Support\Carbon;

/**
 * Reads a WhatsApp chat export and pulls out daily attendance messages.
 *
 * The messages were written by hand over months by several people, so the
 * format varies constantly: "1.", "1=", "1 ", dates as 9/5/2026 or
 * 02.06.2016, and status written as (absent), (Absent), (sick), (p).
 *
 * The parser is deliberately forgiving about shape but never guesses about
 * meaning. Anything it does not recognise is returned as a flag on the row
 * so a person decides, rather than being silently dropped or assumed
 * present.
 */
class WhatsAppAttendanceParser
{
    /** Markers that mean the person did not work that day. */
    private const ABSENT_MARKERS = ['absent', 'absence', 'absnt'];

    /** Markers that mean an excused absence rather than an unexcused one. */
    private const LEAVE_MARKERS = ['sick', 'leave', 'off'];

    /** Markers that mean the day was worked for someone else. */
    private const SUPPLY_MARKERS = ['supply'];

    private const HALF_DAY_MARKERS = ['half day', 'half'];

    private const OVERTIME_MARKERS = ['overtime', 'over time'];

    /** Present confirmations that add nothing; kept so they are not flagged. */
    private const PRESENT_MARKERS = ['p', 'present'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $chat): array
    {
        $rows = [];

        foreach ($this->messages($chat) as $message) {
            foreach ($this->parseMessage($message) as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Splits the export into whole messages.
     *
     * A message starts with "[d/m/yy, h:mm:ss AM] Sender: ", and everything
     * up to the next such header belongs to it, including its line breaks.
     *
     * @return array<int, array{sentOn: string, sender: string, body: string}>
     */
    public function messages(string $chat): array
    {
        $chat = str_replace(["\r\n", "\u{202f}", "\u{200e}"], ["\n", ' ', ''], $chat);

        $pattern = '/^\[(\d{1,2}\/\d{1,2}\/\d{2,4}), [^\]]+\]\s*([^:]+):\s?/m';

        $parts = preg_split($pattern, $chat, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if (! $parts) {
            return [];
        }

        $messages = [];

        // preg_split with DELIM_CAPTURE gives: [preamble?], date, sender, body, date, sender, body...
        for ($i = 0; $i < count($parts) - 2; $i++) {
            if (! preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}$/', trim($parts[$i]))) {
                continue;
            }

            $messages[] = [
                'sentOn' => trim($parts[$i]),
                'sender' => trim($parts[$i + 1]),
                'body' => rtrim($parts[$i + 2]),
            ];

            $i += 2;
        }

        return $messages;
    }

    /**
     * @param  array{sentOn: string, sender: string, body: string}  $message
     * @return array<int, array<string, mixed>>
     */
    private function parseMessage(array $message): array
    {
        $lines = preg_split('/\n/', $message['body']) ?: [];

        $date = null;
        $rawDate = null;
        $project = null;
        $rows = [];
        $headingRunning = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $this->isNoise($line)) {
                continue;
            }

            // The date may come before or after the project heading, so it
            // never clears the heading. Blocks are separated by headings.
            if ($parsed = $this->readDate($line)) {
                $date = $parsed['date'];
                $rawDate = $parsed['raw'];

                // A heading before the date is complete. Without this, a
                // sub-heading further down ("Rebellion staff") would be glued
                // onto the project name in some messages and stand alone in
                // others, depending on where the date happened to sit.
                $headingRunning = false;

                continue;
            }

            $name = $this->readNumberedName($line);

            if ($name === null) {
                // Headings sometimes run over two lines ("Sobha opulence" then
                // "Over time"). Joining them keeps both the project and the
                // qualifier, instead of the second line replacing the first.
                $project = $headingRunning && $project !== null
                    ? $project.' '.$this->cleanHeading($line)
                    : $this->cleanHeading($line);

                $headingRunning = true;

                continue;
            }

            $headingRunning = false;

            if ($name['name'] === '') {
                continue;
            }

            $rows[] = [
                'messageDate' => $message['sentOn'],
                'sender' => $message['sender'],
                'rawDate' => $rawDate,
                'date' => $date,
                'project' => $project,
                'sourceName' => $name['name'],
                'status' => $name['status'],
                'attendanceFraction' => $name['fraction'],
                'flags' => $name['flags'],
                'note' => $name['note'],
            ];
        }

        return $rows;
    }

    /**
     * Lines that carry no attendance, such as "Other manpower room".
     */
    private function isNoise(string $line): bool
    {
        $lower = mb_strtolower($line);

        return str_contains($lower, 'other manpower')
            || str_contains($lower, 'this message was deleted')
            || str_contains($lower, 'voice message omitted')
            || str_contains($lower, 'image omitted');
    }

    /**
     * @return array{date: string, raw: string}|null
     */
    private function readDate(string $line): ?array
    {
        // The date is not always at the start: "( Attendance) 18.05 2026" and
        // "24/5/2026 Sunday Off" both appear. Searching the whole line keeps
        // those blocks, whose heading sits on the line above.
        if (! preg_match('#\b(\d{1,2})\s*[/.-]\s*(\d{1,2})\s*[/.\- ]\s*(\d{2,4})\b#', $line, $match)) {
            return null;
        }

        [$raw, $day, $month, $year] = $match;

        $year = (int) $year;

        if ($year < 100) {
            $year += 2000;
        }

        // 02.06.2016 appears where 2026 was meant; the chat itself only
        // covers 2026, so a year far in the past is a typo, not a fact.
        if ($year < 2020) {
            $year += 10;
        }

        try {
            $date = Carbon::createFromDate($year, (int) $month, (int) $day)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        if (! checkdate((int) $month, (int) $day, $year)) {
            return null;
        }

        return ['date' => $date->toDateString(), 'raw' => trim($raw)];
    }

    /**
     * Reads "1.Akhtar", "2= Nadeem", "7 Nickson", "  3. Allan (absent)".
     *
     * @return array{name: string, status: string, fraction: float, flags: list<string>, note: ?string}|null
     */
    private function readNumberedName(string $line): ?array
    {
        // Either a separator ("1.", "2=") or plain whitespace ("7 Nickson")
        // must sit between the number and the name. Requiring one of the two
        // stops a bare number such as a stray "2026" becoming a person.
        if (! preg_match('/^(\d{1,2})\s*(?:[.=:)\-]+\s*|\s+)(.+)$/u', $line, $match)) {
            return null;
        }

        $rest = trim($match[2]);

        if ($rest === '') {
            return null;
        }

        return $this->readMarkers($rest);
    }

    /**
     * Splits "Ali raza (absent)" into a name and what the marker means.
     *
     * @return array{name: string, status: string, fraction: float, flags: list<string>, note: ?string}
     */
    private function readMarkers(string $text): array
    {
        $status = AttendanceRecord::STATUS_PRESENT;
        $fraction = 1.0;
        $flags = [];
        $notes = [];

        $name = preg_replace_callback(
            '/\(([^)]*)\)/u',
            function (array $match) use (&$status, &$fraction, &$flags, &$notes) {
                $marker = trim(mb_strtolower($match[1]));

                if ($marker === '') {
                    return '';
                }

                if ($this->markerIn($marker, self::ABSENT_MARKERS)) {
                    $status = AttendanceRecord::STATUS_ABSENT;
                } elseif ($this->markerIn($marker, self::LEAVE_MARKERS)) {
                    $status = AttendanceRecord::STATUS_LEAVE;
                    $notes[] = trim($match[1]);
                } elseif ($this->markerIn($marker, self::SUPPLY_MARKERS)) {
                    $flags[] = 'supply';
                } elseif ($this->markerIn($marker, self::HALF_DAY_MARKERS)) {
                    $fraction = 0.5;
                } elseif ($this->markerIn($marker, self::OVERTIME_MARKERS)) {
                    $flags[] = 'overtime';
                } elseif ($this->markerIn($marker, self::PRESENT_MARKERS)) {
                    // Nothing to record; the row is already Present.
                } else {
                    // Anything unrecognised is surfaced, never assumed.
                    $flags[] = 'review';
                    $notes[] = trim($match[1]);
                }

                return '';
            },
            $text,
        ) ?? $text;

        // Trailing markers written without brackets, e.g. "Naeem Absent".
        foreach (array_merge(self::ABSENT_MARKERS, self::LEAVE_MARKERS) as $marker) {
            if (preg_match('/\b'.preg_quote($marker, '/').'\b\s*$/iu', $name)) {
                $status = $this->markerIn($marker, self::ABSENT_MARKERS)
                    ? AttendanceRecord::STATUS_ABSENT
                    : AttendanceRecord::STATUS_LEAVE;

                if ($status === AttendanceRecord::STATUS_LEAVE) {
                    $notes[] = $marker;
                }

                $name = preg_replace('/\b'.preg_quote($marker, '/').'\b\s*$/iu', '', $name) ?? $name;
            }
        }

        $name = trim(preg_replace('/[\s.:=\-]+$/u', '', $name) ?? $name);

        return [
            'name' => $name,
            'status' => $status,
            'fraction' => $fraction,
            'flags' => array_values(array_unique($flags)),
            'note' => $notes ? implode('; ', array_unique($notes)) : null,
        ];
    }

    /**
     * Whole-word matching, not substring.
     *
     * "camp ac install" contains the letter p and "mohafiz office" contains
     * off; matching on substrings turned both into a status they never had.
     *
     * @param  list<string>  $markers
     */
    private function markerIn(string $marker, array $markers): bool
    {
        foreach ($markers as $candidate) {
            if (preg_match('/\b'.preg_quote($candidate, '/').'\b/iu', $marker)) {
                return true;
            }
        }

        return false;
    }

    private function cleanHeading(string $line): string
    {
        return trim(preg_replace('/\s+/', ' ', $line) ?? $line);
    }
}
