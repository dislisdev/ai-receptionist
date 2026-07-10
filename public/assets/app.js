'use strict';

const grid    = document.getElementById('grid');
const range   = document.getElementById('week-range');
const status  = document.getElementById('cal-status');
const resetBtn = document.getElementById('reset');

/** Appointment ids already on screen. Anything new gets the landing animation. */
let seen = new Set();

async function fetchJson(url, options) {
  const res = await fetch(url, options);
  if (!res.ok) throw new Error(`${url} -> HTTP ${res.status}`);
  return res.json();
}

/** Rebuilds the whole grid. Cheap at this size, and impossible to leave stale. */
function render(data) {
  const byslot = new Map();
  for (const a of data.appointments) byslot.set(`${a.date} ${a.time}`, a);

  const cells = [];
  cells.push('<div class="ghead"></div>');
  for (const d of data.days) {
    cells.push(`<div class="ghead"><strong>${d.weekday}</strong><span>${d.label}</span></div>`);
  }

  for (const hour of data.hours) {
    cells.push(`<div class="gtime">${hour}</div>`);
    for (const d of data.days) {
      const appt = byslot.get(`${d.date} ${hour}`);
      if (!appt) {
        cells.push('<div class="gcell gslot"></div>');
        continue;
      }
      const isNew = !seen.has(appt.id);
      cells.push(
        `<div class="gcell gslot"><div class="card${isNew ? ' is-new' : ''}" data-id="${appt.id}">` +
        `<strong>${escapeHtml(appt.customer_name)}</strong>` +
        `<span>${escapeHtml(appt.service_name)}</span>` +
        `</div></div>`
      );
    }
  }

  grid.innerHTML = cells.join('');
  seen = new Set(data.appointments.map(a => a.id));

  const first = data.days[0], last = data.days[4];
  range.textContent = `${first.label} – ${last.label}`;

  const n = data.appointments.length;
  status.textContent = n === 0
    ? 'Καμία κράτηση αυτή την εβδομάδα'
    : `${n} ${n === 1 ? 'κράτηση' : 'κρατήσεις'}`;
}

function escapeHtml(s) {
  return s.replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}

/** Called after every agent turn in step 3. Exposed on window for that reason. */
async function refresh() {
  try {
    render(await fetchJson('/api/appointments.php'));
  } catch (err) {
    status.textContent = 'Το ημερολόγιο δεν φόρτωσε. Δοκιμάστε ανανέωση.';
    console.error(err);
  }
}
window.refreshCalendar = refresh;

resetBtn.addEventListener('click', async () => {
  resetBtn.disabled = true;
  try {
    seen = new Set();
    render(await fetchJson('/api/reset.php', { method: 'POST' }));
  } catch (err) {
    status.textContent = 'Η επαναφορά απέτυχε.';
    console.error(err);
  } finally {
    resetBtn.disabled = false;
  }
});

refresh();

const messages = document.getElementById('messages');
const composer = document.getElementById('composer');
const input    = document.getElementById('input');
const sendBtn  = composer.querySelector('button');

function bubble(role, text) {
  const el = document.createElement('div');
  el.className = `msg ${role}`;
  el.innerHTML = `<p>${escapeHtml(text)}</p>`;
  messages.append(el);
  messages.scrollTop = messages.scrollHeight;
  return el;
}

function typingIndicator() {
  const el = document.createElement('div');
  el.className = 'msg agent typing';
  el.innerHTML = '<i></i><i></i><i></i>';
  messages.append(el);
  messages.scrollTop = messages.scrollHeight;
  return el;
}

const ERRORS = {
  session_limit:     'Το demo έχει όριο μηνυμάτων ανά επισκέπτη. Πατήστε «Επαναφορά demo».',
  message_too_long:  'Το μήνυμα είναι πολύ μεγάλο.',
  agent_unavailable: 'Η ρεσεψιόν δεν αποκρίνεται αυτή τη στιγμή. Δοκιμάστε ξανά.',
};

composer.addEventListener('submit', async (event) => {
  event.preventDefault();
  const text = input.value.trim();
  if (!text) return;

  bubble('user', text);
  input.value = '';
  input.disabled = sendBtn.disabled = true;
  const typing = typingIndicator();

  try {
    const res  = await fetch('/api/chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text }),
    });
    const data = await res.json();
    typing.remove();

    if (!res.ok) {
      bubble('error', ERRORS[data.error] ?? 'Κάτι πήγε στραβά. Δοκιμάστε ξανά.');
    } else {
      bubble('agent', data.reply);
      await refresh();
    }
  } catch (err) {
    typing.remove();
    bubble('error', 'Δεν υπάρχει σύνδεση. Ελέγξτε το δίκτυο.');
    console.error(err);
  } finally {
    input.disabled = sendBtn.disabled = false;
    input.focus();
  }
});
