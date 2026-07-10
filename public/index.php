<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
sessionId();
?>
<!doctype html>
<html lang="el">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Φυσικοθεραπευτήριο Ρέα — κρατήσεις</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Commissioner:wght@300;400;500;600&display=swap">
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <header class="bar">
    <div class="brand">
      <span class="mark" aria-hidden="true">ρ</span>
      <div>
        <h1>Φυσικοθεραπευτήριο Ρέα</h1>
        <p>Λάρισα · Δευτέρα–Παρασκευή, 09:00–20:00</p>
      </div>
    </div>
    <button id="reset" class="ghost">Επαναφορά demo</button>
  </header>

  <main>
    <section class="chat" aria-label="Συνομιλία με τη ρεσεψιόν">
      <div id="messages" class="messages">
        <div class="msg agent">
          <p>Καλησπέρα! Είμαι η ρεσεψιόν του Φυσικοθεραπευτηρίου Ρέα. Πώς μπορώ να
             σας εξυπηρετήσω;</p>
        </div>
      </div>
      <form id="composer" class="composer">
        <input id="input" type="text" placeholder="Γράψτε το μήνυμά σας…" autocomplete="off" maxlength="500">
        <button type="submit">Αποστολή</button>
      </form>
    </section>

    <section class="calendar" aria-label="Ημερολόγιο εβδομάδας">
      <div class="cal-head">
        <h2>Εβδομάδα <span id="week-range"></span></h2>
        <p id="cal-status">Φόρτωση…</p>
      </div>
      <div id="grid" class="grid"></div>
    </section>
  </main>

  <footer class="bar foot">
    <p>White-label: ίδιος κώδικας, διαφορετικό config — κομμωτήριο, γυμναστήριο, οδοντιατρείο.</p>
    <a href="/health.php">health</a>
  </footer>

  <script src="/assets/app.js"></script>
</body>
</html>
