'use strict';

/* ============================================================
   ΦΥΣΙΚΟΘΕΡΑΠΕΥΤΗΡΙΟ ΚΙΝΗΣΗ — AI RECEPTIONIST
   Frontend: vanilla JS πάνω στα endpoints του PHP backend.
   Το ημερολόγιο δείχνει την εβδομάδα που ορίζει ο server —
   το frontend δεν υπολογίζει ποτέ ημερομηνίες μόνο του.
   ============================================================ */

const $  = sel => document.querySelector(sel);
const el = (tag, cls) => { const e = document.createElement(tag); if (cls) e.className = cls; return e; };
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmtHour = h => String(h).padStart(2, '0') + ':00';

const GR_MONTHS = ['Ιανουαρίου','Φεβρουαρίου','Μαρτίου','Απριλίου','Μαΐου','Ιουνίου',
                   'Ιουλίου','Αυγούστου','Σεπτεμβρίου','Οκτωβρίου','Νοεμβρίου','Δεκεμβρίου'];

const AVATAR_SVG = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><path d="M18 19a2 2 0 0 1-2 2h-3"/><rect x="2.5" y="13.5" width="4" height="6" rx="1.6"/><rect x="17.5" y="13.5" width="4" height="6" rx="1.6"/></svg>';

/* -------------------- state -------------------- */

// Ό,τι επιστρέφει το GET /api/appointments.php, μεταφρασμένο για το render.
let state = { days: [], hours: [], services: [], appointments: [] };
let seen = new Set();        // ids ήδη στην οθόνη· ό,τι νέο παίρνει το glow
let botBusy = false;
let initialChatHTML = '';

/* -------------------- API -------------------- */

async function fetchJson(url, options) {
  const res  = await fetch(url, options);
  const data = await res.json().catch(() => ({}));
  if (!res.ok) { const e = new Error(data.error || `HTTP ${res.status}`); e.code = data.error; throw e; }
  return data;
}

/** API payload → το σχήμα που περιμένει η renderCalendar (day index + hour int). */
function translate(data) {
  state.days     = data.days;
  state.hours    = data.hours.map(h => parseInt(h, 10));
  state.services = data.services;
  state.appointments = data.appointments.map(a => ({
    id:      a.id,
    day:     data.days.findIndex(d => d.date === a.date),
    hour:    parseInt(a.time, 10),
    name:    a.customer_name,
    service: a.color_key,
    label:   a.service_name,
  })).filter(a => a.day !== -1);
}

async function refreshCalendar() {
  try {
    translate(await fetchJson('/api/appointments.php'));
    renderAll();
  } catch (err) {
    console.error(err);
    $('#cal-range').textContent = 'Το ημερολόγιο δεν φόρτωσε — δοκιμάστε ανανέωση.';
  }
}
window.refreshCalendar = refreshCalendar;

/* -------------------- chat UI -------------------- */

function scrollChat() { const m = $('#chat-messages'); m.scrollTop = m.scrollHeight; }

function addMessageToChat(role, text) {
  const isUser = role === 'user';
  const cls = role === 'error' ? 'msg msg--enter msg--bot msg--error'
            : 'msg msg--enter ' + (isUser ? 'msg--user' : 'msg--bot');
  const wrap = el('div', cls);
  if (!isUser) {
    const av = el('div', 'avatar');
    av.setAttribute('aria-hidden', 'true');
    av.innerHTML = AVATAR_SVG;
    wrap.appendChild(av);
  }
  const bubble = el('div', 'bubble');
  bubble.textContent = text;
  wrap.appendChild(bubble);
  $('#chat-messages').insertBefore(wrap, $('#typing-indicator'));
  scrollChat();
}

function showTyping() { $('#typing-indicator').hidden = false; scrollChat(); }
function hideTyping() { $('#typing-indicator').hidden = true; }

function updateSendState() {
  $('#chat-send').disabled = botBusy || !$('#chat-input').value.trim();
}

function autoGrow(t) { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 120) + 'px'; }

const ERRORS = {
  session_limit:     'Το demo έχει όριο μηνυμάτων ανά επισκέπτη. Πατήστε Reset Demo για νέα συνεδρία.',
  message_too_long:  'Το μήνυμα είναι πολύ μεγάλο.',
  agent_unavailable: 'Η ρεσεψιόν δεν αποκρίνεται αυτή τη στιγμή. Δοκιμάστε ξανά σε λίγο.',
};

async function sendMessage() {
  const input = $('#chat-input');
  const text = input.value.trim();
  if (!text || botBusy) return;

  addMessageToChat('user', text);
  $('#chat-chips').classList.add('is-hidden');
  input.value = '';
  autoGrow(input);

  botBusy = true;
  updateSendState();
  showTyping();

  try {
    const data = await fetchJson('/api/chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text }),
    });
    hideTyping();
    addMessageToChat('assistant', data.reply);
    await refreshCalendar();   // το demo moment: ό,τι έγραψε ο agent, εμφανίζεται
  } catch (err) {
    hideTyping();
    addMessageToChat('error', ERRORS[err.code] ?? 'Κάτι πήγε στραβά. Δοκιμάστε ξανά.');
    console.error(err);
  } finally {
    botBusy = false;
    updateSendState();
    input.focus();
  }
}

/* -------------------- calendar -------------------- */

function rangeText() {
  if (state.days.length < 5) return '—';
  const a = new Date(state.days[0].date), b = new Date(state.days[4].date);
  if (a.getMonth() === b.getMonth())
    return `${a.getDate()}–${b.getDate()} ${GR_MONTHS[b.getMonth()]} ${b.getFullYear()}`;
  return `${a.getDate()} ${GR_MONTHS[a.getMonth()]} – ${b.getDate()} ${GR_MONTHS[b.getMonth()]} ${b.getFullYear()}`;
}

function localISODate(d) {
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

function renderLegend() {
  const legend = $('#legend');
  legend.innerHTML = '';
  for (const s of state.services) {
    const span = el('span');
    span.innerHTML = `<i style="background:var(--c-${esc(s.color_key)})"></i>${esc(s.name)}`;
    legend.appendChild(span);
  }
}

function renderApptCard(a) {
  const isNew = !seen.has(a.id);
  const card = el('div', 'appt appt--' + a.service + (isNew ? ' is-new' : ''));
  card.title = `${a.name} — ${a.label} · ${fmtHour(a.hour)}`;
  card.innerHTML =
    `<span class="appt-name">${esc(a.name)}</span>` +
    `<span class="appt-svc">${esc(a.label)}</span>`;
  return card;
}

function renderCalendar() {
  const grid = $('#calendar-grid');
  const todayISO = localISODate(new Date());
  grid.innerHTML = '';

  grid.appendChild(el('div', 'cal-corner'));
  state.days.forEach(d => {
    const head = el('div', 'cal-dayhead' + (d.date === todayISO ? ' is-today' : ''));
    head.innerHTML = `<span class="dh-name">${esc(d.weekday)}</span><span class="dh-date">${parseInt(d.label, 10)}</span>`;
    grid.appendChild(head);
  });

  for (const hour of state.hours) {
    grid.appendChild(Object.assign(el('div', 'cal-time'), { textContent: fmtHour(hour) }));
    state.days.forEach((d, di) => {
      const cell = el('div', 'cal-cell' + (d.date === todayISO ? ' is-today' : ''));
      const appt = state.appointments.find(a => a.day === di && a.hour === hour);
      if (appt) cell.appendChild(renderApptCard(appt));
      grid.appendChild(cell);
    });
  }

  seen = new Set(state.appointments.map(a => a.id));
}

function renderAll() {
  $('#cal-range').textContent = rangeText();
  renderLegend();
  renderCalendar();
}

/* -------------------- reset / tabs / init -------------------- */

async function resetDemo() {
  const btn = $('#reset-btn');
  btn.disabled = true;
  try {
    seen = new Set();
    translate(await fetchJson('/api/reset.php', { method: 'POST' }));
    renderAll();
    $('#chat-messages').innerHTML = initialChatHTML;
    $('#chat-chips').classList.remove('is-hidden');
    $('#chat-input').value = '';
    autoGrow($('#chat-input'));
    updateSendState();
    setTab('chat');
  } catch (err) {
    addMessageToChat('error', 'Η επαναφορά απέτυχε. Δοκιμάστε ξανά.');
    console.error(err);
  } finally {
    btn.disabled = false;
  }
}

function setTab(tab) {
  document.body.dataset.tab = tab;
  document.querySelectorAll('.tab').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
}

function init() {
  const input = $('#chat-input');
  $('#chat-send').addEventListener('click', sendMessage);
  input.addEventListener('input', () => { autoGrow(input); updateSendState(); });
  input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  });
  $('#reset-btn').addEventListener('click', resetDemo);
  document.querySelectorAll('.chip').forEach(c =>
    c.addEventListener('click', () => { input.value = c.dataset.msg; autoGrow(input); updateSendState(); sendMessage(); })
  );
  document.querySelectorAll('.tab').forEach(t =>
    t.addEventListener('click', () => setTab(t.dataset.tab))
  );

  initialChatHTML = $('#chat-messages').innerHTML;
  updateSendState();
  refreshCalendar();
}

document.addEventListener('DOMContentLoaded', init);

/* Server-side limits, surfaced in the user's language. */
Object.assign(ERRORS, {
  rate_limited:      'Πολλά αιτήματα σε σύντομο διάστημα. Δοκιμάστε ξανά σε λίγα λεπτά.',
  demo_budget_spent: 'Το ημερήσιο όριο του demo εξαντλήθηκε. Δοκιμάστε αύριο.',
});
