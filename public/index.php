<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/http.php';
sessionId();
securityHeaders();
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Φυσικοθεραπευτήριο Κίνηση — AI Receptionist</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/app.css">
</head>
<body data-tab="chat">
<div class="app">

  <svg width="0" height="0" aria-hidden="true" style="position:absolute"><linearGradient id="rhea-grad" gradientUnits="userSpaceOnUse" x1="5" y1="2" x2="20" y2="21"><stop offset="0%" stop-color="#12a594"/><stop offset="55%" stop-color="#4a8fd0"/><stop offset="100%" stop-color="#7c5cd6"/></linearGradient><symbol id="rhea-mark" viewBox="0 0 24 24"><path d="M12 2.4l1.7 4.5 4.5 1.7-4.5 1.7L12 14.8l-1.7-4.5L5.8 8.6l4.5-1.7z"/><path d="M18.7 14l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7z" opacity=".55"/></symbol></svg>

  <header>
    <div class="brand">
      <div class="logo" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17c3-1 5-3.5 7-7s4-6 9-6"/><circle cx="19" cy="4" r="1.6" fill="#fff" stroke="none"/></svg>
      </div>
      <div class="brand-txt">
        <span class="brand-name">Φυσικοθεραπευτήριο <em>Κίνηση</em></span>
        <span class="brand-tag">DEMO</span>
      </div>
    </div>
    <div class="badge">
      <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M12 2.4l1.7 4.5 4.5 1.7-4.5 1.7L12 14.8l-1.7-4.5L5.8 8.6l4.5-1.7z"/><path d="M18.7 14l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7z" opacity=".55"/></svg>
      AI Receptionist
      <span class="live" aria-hidden="true"></span>
    </div>
  </header>

  <div class="mobile-tabs">
    <button class="tab active" data-tab="chat" type="button">Συνομιλία</button>
    <button class="tab" data-tab="cal" type="button">Πρόγραμμα</button>
  </div>

  <div class="main">

    <section class="chat-panel" aria-label="Συνομιλία με τον ψηφιακό βοηθό">
      <div id="chat-messages">
        <div class="msg msg--bot">
          <div class="avatar" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="url(#rhea-grad)"><use href="#rhea-mark"/></svg>
          </div>
          <div class="bubble">Γεια σας! Είμαι η Ρέα, η ψηφιακή ρεσεψιόν του Φυσικοθεραπευτηρίου Κίνηση. Μπορώ να κλείσω ραντεβού, να ελέγξω διαθεσιμότητα ή να σας ενημερώσω για τις υπηρεσίες μας. Πώς μπορώ να βοηθήσω;</div>
        </div>
        <div id="typing-indicator" class="msg msg--bot" hidden>
          <div class="avatar" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="url(#rhea-grad)"><use href="#rhea-mark"/></svg>
          </div>
          <div class="bubble"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
        </div>
      </div>

      <div class="composer">
        <div class="chips" id="chat-chips">
          <button class="chip" type="button" data-msg="Θέλω να κλείσω ραντεβού για μασάζ">Ραντεβού για μασάζ</button>
          <button class="chip" type="button" data-msg="Τι υπηρεσίες έχετε;">Τι υπηρεσίες έχετε;</button>
          <button class="chip" type="button" data-msg="Τι διαθεσιμότητα έχετε την Τετάρτη;">Διαθεσιμότητα Τετάρτης</button>
        </div>
        <div class="composer-row">
          <textarea id="chat-input" rows="1" placeholder="Γράψτε το μήνυμά σας…" autocomplete="off" maxlength="500"></textarea>
          <button id="chat-send" type="button" aria-label="Αποστολή" disabled>
            <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V5"/><path d="M6 11l6-6 6 6"/></svg>
          </button>
        </div>
      </div>
    </section>

    <section class="calendar-panel" aria-label="Εβδομαδιαίο πρόγραμμα">
      <div class="cal-head">
        <div>
          <div class="cal-title">Πρόγραμμα εβδομάδας</div>
          <div id="cal-range">—</div>
        </div>
        <div class="legend" id="legend"></div>
      </div>
      <div id="calendar-grid"></div>
    </section>

  </div>

  <footer>
    <button id="reset-btn" type="button">
      <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 12a8.5 8.5 0 1 0 2.6-6.1"/><path d="M3 4.5V9h4.5"/></svg>
      Reset Demo
    </button>
    <div class="tagline"><strong>White-label ready</strong> — ίδιος κώδικας, άλλο config: κομμωτήριο, γυμναστήριο, οδοντιατρείο. · <a href="/health.php">health</a></div>
  </footer>
</div>

<script src="/assets/app.js"></script>
</body>
</html>
