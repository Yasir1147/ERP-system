<?php

use App\Services\Attendance\WhatsAppAttendanceParser;

function parseChat(string $chat): array
{
    return app(WhatsAppAttendanceParser::class)->parse($chat);
}

it('reads a plain duty message', function () {
    $rows = parseChat(<<<'CHAT'
    [5/16/26, 10:47:56 AM] +971 54 360 0402: 13/05/2026
    Shoba  wave opulence

    1=Ansar Abbas
    2=Asghar Ali
    3=Godwin
    CHAT);

    expect($rows)->toHaveCount(3);
    expect($rows[0]['date'])->toBe('2026-05-13');
    expect($rows[0]['project'])->toBe('Shoba wave opulence');
    expect($rows[0]['sourceName'])->toBe('Ansar Abbas');
    expect($rows[0]['status'])->toBe('present');
    expect($rows[2]['sourceName'])->toBe('Godwin');
});

it('accepts the different numbering styles used in the chat', function () {
    $rows = parseChat(<<<'CHAT'
    [6/2/26, 1:00:00 PM] Ansar Abbas: 02/06/2026
    Sobha opulence

    1.Akhtar Babar
    2= Nadeem
    7 Nickson
        3. Allan
    CHAT);

    expect(array_column($rows, 'sourceName'))
        ->toBe(['Akhtar Babar', 'Nadeem', 'Nickson', 'Allan']);
});

it('reads absent markers in every spelling used', function () {
    $rows = parseChat(<<<'CHAT'
    [6/4/26, 9:43:05 AM] +971 54 360 0402: 04/06/2026
    shoba opulence
    1= Asghar
    2= Ali raza (absent)
    3= Mayo (ABSENT )
    4= Akhtar..(Absence)
    5= Naeem Absent
    CHAT);

    expect(array_column($rows, 'status'))
        ->toBe(['present', 'absent', 'absent', 'absent', 'absent']);
    expect($rows[4]['sourceName'])->toBe('Naeem');
});

it('treats sick as leave and keeps the reason', function () {
    $rows = parseChat(<<<'CHAT'
    [7/14/26, 8:04:52 AM] +971 55 618 1031: 14/07/2026
    Spider sites

    1. Godwin
    4. Suleiman, sick
    CHAT);

    expect($rows[1]['status'])->toBe('leave');
    expect($rows[1]['note'])->toContain('sick');
});

it('flags supply days instead of assuming they were worked here', function () {
    $rows = parseChat(<<<'CHAT'
    [6/6/26, 10:22:38 AM] +971 54 360 0402: 06/06/2026
    shoba opulence
    1= Asghar
    3= henry  (supply )
    CHAT);

    expect($rows[0]['flags'])->toBe([]);
    expect($rows[1]['flags'])->toContain('supply');
});

it('reads a half day', function () {
    $rows = parseChat(<<<'CHAT'
    [6/6/26, 4:47:18 PM] Ansar Abbas: 06.06.2026
    Sobha

    3. Isaac. ( Half day)
    CHAT);

    expect($rows[0]['attendanceFraction'])->toBe(0.5);
    expect($rows[0]['status'])->toBe('present');
});

it('flags a marker it does not understand rather than guessing', function () {
    $rows = parseChat(<<<'CHAT'
    [8/10/26, 11:04:49 AM] Ansar Abbas: 10/08/2026
    Vida residence

    8. Allan ( camp ac install)
    9. Stephin...( fingerprint)
    CHAT);

    expect($rows[0]['flags'])->toContain('review');
    expect($rows[0]['note'])->toContain('camp ac install');
    expect($rows[1]['flags'])->toContain('review');
});

it('splits one message that covers several projects', function () {
    $rows = parseChat(<<<'CHAT'
    [7/29/26, 7:56:25 AM] Ansar Abbas: Al Habtoor
    27/7/2026

    1. Majeed
    2. Nickson

    Sobha opulence

    1. Asghar
    2. Henry

    Vida residence

    1. Ansar Abbas
    CHAT);

    expect($rows)->toHaveCount(5);
    expect($rows[0]['project'])->toBe('Al Habtoor');
    expect($rows[2]['project'])->toBe('Sobha opulence');
    expect($rows[4]['project'])->toBe('Vida residence');
    expect(array_unique(array_column($rows, 'date')))->toBe(['2026-07-27']);
});

it('corrects the 2016 year typo the chat contains', function () {
    $rows = parseChat(<<<'CHAT'
    [6/2/26, 11:56:10 AM] Ansar Abbas: Al Nisr plaza Sharjah
    02.06.2016

    1. Mayo
    CHAT);

    expect($rows[0]['date'])->toBe('2026-06-02');
});

it('ignores lines that are not attendance', function () {
    $rows = parseChat(<<<'CHAT'
    [5/16/26, 10:46:35 AM] +971 54 360 0402: 12/05/2026
    Stor work

    1=Ansar Abbas

    Other manpower  room
    [5/22/26, 9:37:02 AM] +971 52 724 9032: <voice message omitted>
    [5/22/26, 8:57:28 AM] +971 52 724 9032: Where is the attendance of 21
    CHAT);

    expect($rows)->toHaveCount(1);
    expect($rows[0]['sourceName'])->toBe('Ansar Abbas');
});

it('keeps the sender and message date for tracing a row back', function () {
    $rows = parseChat(<<<'CHAT'
    [8/15/26, 9:06:04 AM] Ansar Abbas: 15/08/2026
    Al Habtoor Tower

    1. Ansar Abbas
    CHAT);

    expect($rows[0]['sender'])->toBe('Ansar Abbas');
    expect($rows[0]['messageDate'])->toBe('8/15/26');
    expect($rows[0]['rawDate'])->toBe('15/08/2026');
});

it('does not invent rows for a message with no names', function () {
    $rows = parseChat(<<<'CHAT'
    [5/31/26, 12:26:37 PM] +971 54 360 0402: 24/5/2026
    Sunday  Off
    [7/18/26, 10:53:47 AM] +971 52 724 9032: Hm sbko software se dor rkhen
    CHAT);

    expect($rows)->toBe([]);
});

it('joins a heading that runs over two lines', function () {
    $rows = parseChat(<<<'CHAT'
    [8/2/26, 9:49:16 AM] Ansar Abbas: 02/08/2026
    Sobha opulence
    Over time

    1. Ansar Abbas
    2. Asghar
    CHAT);

    expect($rows)->toHaveCount(2);
    expect($rows[0]['project'])->toBe('Sobha opulence Over time');
});

it('finds a date sitting inside a heading line', function () {
    $rows = parseChat(<<<'CHAT'
    [5/20/26, 10:57:28 AM] Ansar Abbas: Site: Jewel of the Creek
     ( Attendance) 18.05 2026

    1.Ansar Abbas
    2.Younas
    CHAT);

    expect($rows)->toHaveCount(2);
    expect($rows[0]['date'])->toBe('2026-05-18');
    expect($rows[0]['project'])->toBe('Site: Jewel of the Creek');
});
