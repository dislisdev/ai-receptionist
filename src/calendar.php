<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** The five columns of the grid, with Greek names and dd/mm labels. */
function weekDays(): array {
    $start = demoWeekStart();
    $days  = [];
    foreach (range(0, 4) as $i) {
        $day    = $start->modify("+$i day");
        $days[] = [
            'date'    => $day->format('Y-m-d'),
            'weekday' => GREEK_DAYS[$i],
            'label'   => $day->format('d/m'),
        ];
    }
    return $days;
}

/** Everything the calendar needs, in one round trip. */
function weekPayload(PDO $pdo, string $sid): array {
    $days = weekDays();

    $stmt = $pdo->prepare(
        "SELECT a.id, a.date, a.time, a.customer_name,
                s.name AS service_name, s.color_key
           FROM appointments a
           JOIN services s ON s.id = a.service_id
          WHERE a.session_id = ?
            AND a.status = 'confirmed'
            AND a.date BETWEEN ? AND ?
       ORDER BY a.date, a.time"
    );
    $stmt->execute([$sid, $days[0]['date'], $days[4]['date']]);

    return [
        'days'         => $days,
        'hours'        => slotHours(),
        'services'     => $pdo->query('SELECT id, name, duration_min, price_eur, color_key FROM services ORDER BY id')->fetchAll(),
        'appointments' => $stmt->fetchAll(),
    ];
}
