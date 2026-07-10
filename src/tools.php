<?php
declare(strict_types=1);

require_once __DIR__ . '/calendar.php';

/**
 * The three tools, as the Messages API expects them. Descriptions are in Greek:
 * they are instructions to the model, and the model works in Greek here.
 */
function toolDefinitions(): array {
    return [
        [
            'name'        => 'check_availability',
            'description' => 'Επιστρέφει τα ελεύθερα ωριαία slots για μια συγκεκριμένη ημέρα. Κάλεσέ το πάντα πριν προτείνεις ή κλείσεις ώρα.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'date' => ['type' => 'string', 'description' => 'Ημερομηνία YYYY-MM-DD, μέσα στην εβδομάδα του ημερολογίου'],
                ],
                'required' => ['date'],
            ],
        ],
        [
            'name'        => 'create_booking',
            'description' => 'Δημιουργεί κράτηση. Κάλεσέ το μόνο όταν έχεις υπηρεσία, ημερομηνία, ώρα, ονοματεπώνυμο και τηλέφωνο, και αφού ο πελάτης επιβεβαιώσει.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'service_id'     => ['type' => 'integer', 'description' => 'Το id της υπηρεσίας από τον κατάλογο'],
                    'date'           => ['type' => 'string',  'description' => 'YYYY-MM-DD'],
                    'time'           => ['type' => 'string',  'description' => 'Ώρα έναρξης HH:00, π.χ. 17:00'],
                    'customer_name'  => ['type' => 'string',  'description' => 'Ονοματεπώνυμο πελάτη'],
                    'customer_phone' => ['type' => 'string',  'description' => 'Ελληνικό κινητό, 10 ψηφία'],
                ],
                'required' => ['service_id', 'date', 'time', 'customer_name', 'customer_phone'],
            ],
        ],
        [
            'name'        => 'cancel_booking',
            'description' => 'Ακυρώνει υπάρχουσα κράτηση. Ταυτοποίηση με το τηλέφωνο της κράτησης (τα ελληνικά ονόματα κλίνονται, το τηλέφωνο όχι)· η ημερομηνία βοηθά αν υπάρχουν πολλές.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'customer_name'  => ['type' => 'string'],
                    'customer_phone' => ['type' => 'string'],
                    'date'           => ['type' => 'string', 'description' => 'Προαιρετικά, YYYY-MM-DD'],
                ],
                'required' => ['customer_phone'],
            ],
        ],
    ];
}

/** Routes a tool_use block to its implementation. Never throws: the model needs an answer either way. */
function executeTool(PDO $pdo, string $sid, string $name, array $input): array {
    try {
        return match ($name) {
            'check_availability' => toolCheckAvailability($pdo, $sid, $input),
            'create_booking'     => toolCreateBooking($pdo, $sid, $input),
            'cancel_booking'     => toolCancelBooking($pdo, $sid, $input),
            default              => ['ok' => false, 'error' => 'unknown_tool'],
        };
    } catch (Throwable $e) {
        error_log('[tool] ' . $name . ': ' . $e->getMessage());
        return ['ok' => false, 'error' => 'internal_error'];
    }
}

function validCalendarDate(string $date): bool {
    return in_array($date, array_column(weekDays(), 'date'), true);
}

function freeSlotsFor(PDO $pdo, string $sid, string $date): array {
    $stmt = $pdo->prepare(
        "SELECT time FROM appointments WHERE session_id = ? AND date = ? AND status = 'confirmed'"
    );
    $stmt->execute([$sid, $date]);
    $taken = array_column($stmt->fetchAll(), 'time');
    return array_values(array_diff(slotHours(), $taken));
}

function toolCheckAvailability(PDO $pdo, string $sid, array $in): array {
    $date = trim((string)($in['date'] ?? ''));
    if (!validCalendarDate($date)) {
        return ['ok' => false, 'error' => 'date_outside_calendar',
                'calendar_days' => array_column(weekDays(), 'date')];
    }
    return ['ok' => true, 'date' => $date, 'free_slots' => freeSlotsFor($pdo, $sid, $date)];
}

function toolCreateBooking(PDO $pdo, string $sid, array $in): array {
    $serviceId = (int)($in['service_id'] ?? 0);
    $date      = trim((string)($in['date'] ?? ''));
    $time      = trim((string)($in['time'] ?? ''));
    $name      = trim((string)($in['customer_name'] ?? ''));
    $phone     = preg_replace('/\D/', '', (string)($in['customer_phone'] ?? ''));

    $stmt = $pdo->prepare('SELECT name FROM services WHERE id = ?');
    $stmt->execute([$serviceId]);
    $serviceName = $stmt->fetchColumn();

    if ($serviceName === false)             return ['ok' => false, 'error' => 'unknown_service'];
    if (!validCalendarDate($date))          return ['ok' => false, 'error' => 'date_outside_calendar',
                                                    'calendar_days' => array_column(weekDays(), 'date')];
    if (!in_array($time, slotHours(), true)) return ['ok' => false, 'error' => 'time_outside_hours',
                                                    'valid_times' => slotHours()];
    if ($name === '' || mb_strlen($name) > 60) return ['ok' => false, 'error' => 'invalid_name'];
    if (strlen($phone) !== 10)              return ['ok' => false, 'error' => 'invalid_phone',
                                                    'hint' => 'Χρειάζεται ελληνικό κινητό 10 ψηφίων'];

    try {
        $pdo->prepare(
            'INSERT INTO appointments (session_id, service_id, date, time, customer_name, customer_phone)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$sid, $serviceId, $date, $time, $name, $phone]);
    } catch (PDOException $e) {
        // 23000 = integrity constraint. The partial unique index is the real
        // gatekeeper here: even a race between two requests cannot double-book.
        if ($e->getCode() === '23000') {
            return ['ok' => false, 'error' => 'slot_taken',
                    'free_slots' => freeSlotsFor($pdo, $sid, $date)];
        }
        throw $e;
    }

    return ['ok' => true,
            'appointment' => ['id' => (int)$pdo->lastInsertId(), 'service' => $serviceName,
                              'date' => $date, 'time' => $time, 'customer_name' => $name]];
}

function toolCancelBooking(PDO $pdo, string $sid, array $in): array {
    $phone = preg_replace('/\D/', '', (string)($in['customer_phone'] ?? ''));
    $date  = trim((string)($in['date'] ?? ''));

    if (strlen($phone) !== 10) {
        return ['ok' => false, 'error' => 'invalid_phone',
                'hint' => 'Χρειάζεται το κινητό 10 ψηφίων με το οποίο έγινε η κράτηση'];
    }

    $sql = "SELECT a.id, a.date, a.time, a.customer_name, s.name AS service
              FROM appointments a JOIN services s ON s.id = a.service_id
             WHERE a.session_id = ? AND a.status = 'confirmed' AND a.customer_phone = ?";
    $params = [$sid, $phone];
    if ($date !== '') { $sql .= ' AND a.date = ?'; $params[] = $date; }

    $stmt = $pdo->prepare($sql . ' ORDER BY a.date, a.time');
    $stmt->execute($params);
    $matches = $stmt->fetchAll();

    if ($matches === [])     return ['ok' => false, 'error' => 'not_found'];
    if (count($matches) > 1) return ['ok' => false, 'error' => 'multiple_matches',
                                     'appointments' => $matches,
                                     'hint' => 'Ζήτα την ημερομηνία για να ξεχωρίσεις'];

    $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?")
        ->execute([$matches[0]['id']]);

    return ['ok' => true, 'cancelled' => $matches[0]];
}
