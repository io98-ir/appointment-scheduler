<?php

/**
 * The double-booking race (ADR-004, roadmap T2.2): 30 parallel POST /holds
 * for one slot through the real web server. Usage:
 *
 *   php tests/Concurrency/race.php <base url> <seed json> <staff|any>
 *
 * "staff" asks for the first staff member: exactly one request may win.
 * "any" lets the business choose between the two: exactly two may win,
 * each with a different staff member. The others must get 409 slot_taken.
 * Exits 1 on any other outcome.
 */

declare(strict_types=1);

// A closure, so the script defines no globals.
exit((static function (array $args): int {
    $requests = 30;

    [, $base, $seedJson, $mode] = $args + [null, null, null, null];
    $seed = json_decode((string) $seedJson, true, 512, JSON_THROW_ON_ERROR);
    $route = is_array($seed) ? ($seed['route'] ?? null) : null;
    $nonce = is_array($seed) ? ($seed['nonce'] ?? null) : null;
    $staff = is_array($seed) ? ($seed['staff'] ?? null) : null;
    if (
        !is_array($seed) || !is_string($route) || !is_string($nonce) || !is_array($staff)
        || !in_array($mode, ['staff', 'any'], true)
    ) {
        fwrite(STDERR, "Usage: race.php <base url> <seed json> <staff|any>\n");
        return 2;
    }

    $body = [
        'variant' => $seed['variant'] ?? null,
        'location' => $seed['location'] ?? null,
        'start' => $seed['start'] ?? null,
    ];
    if ('staff' === $mode) {
        $body['staff'] = $staff[0] ?? null;
    }
    $url = rtrim((string) $base, '/') . '/?rest_route=' . rawurlencode($route);

    $multi = curl_multi_init();
    $handles = [];
    for ($i = 0; $i < $requests; ++$i) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-WP-Nonce: ' . $nonce],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        curl_multi_add_handle($multi, $handle);
        $handles[] = $handle;
    }
    do {
        $status = curl_multi_exec($multi, $running);
        if ($running > 0) {
            curl_multi_select($multi);
        }
    } while ($running > 0 && CURLM_OK === $status);

    $won = [];
    $outcomes = [];
    foreach ($handles as $handle) {
        $code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $response = json_decode((string) curl_multi_getcontent($handle), true);
        $errorCode = is_array($response) && is_string($response['code'] ?? null) ? $response['code'] : '';
        $outcomes[] = trim($code . ' ' . $errorCode);
        $staffId = is_array($response) ? ($response['staff_id'] ?? null) : null;
        if (201 === $code && is_int($staffId)) {
            $won[] = $staffId;
        }
        curl_multi_remove_handle($multi, $handle);
    }
    curl_multi_close($multi);

    $counts = array_count_values($outcomes);
    ksort($counts);
    echo "{$mode}: " . json_encode($counts) . ' won by staff ' . json_encode($won) . "\n";

    $expected = 'staff' === $mode ? 1 : 2;
    $lost = $counts['409 slot_taken'] ?? 0;
    if (count($won) !== $expected || $lost !== $requests - $expected || count(array_unique($won)) !== $expected) {
        fwrite(STDERR, "Expected {$expected} winner(s), each a different staff member, and the rest 409 slot_taken.\n");
        return 1;
    }

    return 0;
})($argv));
