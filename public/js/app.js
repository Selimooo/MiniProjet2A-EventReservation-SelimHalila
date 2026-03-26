/**
 * app.js — Main frontend application logic
 * Handles: event listing, event detail, reservation form, auth UI
 */

document.addEventListener('DOMContentLoaded', () => {
    updateNavbar();
    routePage();
});

// ── Routing ────────────────────────────────────────────────────────────────

function routePage() {
    const page = document.body.dataset.page;
    if (page === 'home')      renderEventList();
    if (page === 'event')     renderEventDetail();
    if (page === 'auth')      initAuthPage();
}

// ── Navbar ─────────────────────────────────────────────────────────────────

function updateNavbar() {
    const user = TokenStore.getUser();
    const navAuth = document.getElementById('nav-auth');
    if (!navAuth) return;

    if (user) {
        navAuth.innerHTML = `
            <span class="nav-user">👤 ${escapeHtml(user.username || user.email)}</span>
            <a href="#" onclick="logout(); return false;" class="btn btn-sm btn-outline">Logout</a>
        `;
    } else {
        navAuth.innerHTML = `<a href="/auth" class="btn btn-sm btn-primary">Login / Register</a>`;
    }
}

// ── Event List ─────────────────────────────────────────────────────────────

async function renderEventList() {
    const container = document.getElementById('events-container');
    if (!container) return;

    container.innerHTML = '<div class="loading">Loading events...</div>';

    try {
        const events = await loadEvents();
        if (events.length === 0) {
            container.innerHTML = '<p class="empty">No upcoming events.</p>';
            return;
        }
        container.innerHTML = events.map(renderEventCard).join('');
    } catch (e) {
        container.innerHTML = `<p class="error">Error: ${escapeHtml(e.message)}</p>`;
    }
}

function renderEventCard(event) {
    const date = new Date(event.date).toLocaleDateString('en-GB', {
        weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
    const available = event.availableSeats;
    const badgeClass = available === 0 ? 'badge-full' : available < 10 ? 'badge-low' : 'badge-ok';
    const badgeText = available === 0 ? 'FULL' : `${available} seats`;

    return `
    <div class="event-card">
        ${event.image ? `<img src="${escapeHtml(event.image)}" alt="${escapeHtml(event.title)}" class="event-img">` : ''}
        <div class="event-body">
            <span class="badge ${badgeClass}">${badgeText}</span>
            <h3>${escapeHtml(event.title)}</h3>
            <p class="event-meta">📅 ${date}</p>
            <p class="event-meta">📍 ${escapeHtml(event.location)}</p>
            <p class="event-desc">${escapeHtml(event.description.substring(0, 120))}…</p>
            <a href="/event/${event.id}" class="btn btn-primary">View Details</a>
        </div>
    </div>`;
}

// ── Event Detail ───────────────────────────────────────────────────────────

async function renderEventDetail() {
    const container = document.getElementById('event-detail');
    if (!container) return;

    const eventId = container.dataset.eventId;
    if (!eventId) return;

    try {
        const event = await loadEvent(eventId);
        const date = new Date(event.date).toLocaleDateString('en-GB', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });

        document.getElementById('event-title').textContent = event.title;
        document.getElementById('event-date').textContent = date;
        document.getElementById('event-location').textContent = event.location;
        document.getElementById('event-seats').textContent = `${event.availableSeats} / ${event.seats} seats available`;
        document.getElementById('event-description').textContent = event.description;

        if (event.image) {
            document.getElementById('event-image').src = event.image;
            document.getElementById('event-image').style.display = 'block';
        }

        if (event.availableSeats > 0) {
            setupReservationForm(event);
        } else {
            document.getElementById('reservation-section').innerHTML = '<p class="badge badge-full">This event is fully booked.</p>';
        }
    } catch (e) {
        container.innerHTML = `<p class="error">Error: ${escapeHtml(e.message)}</p>`;
    }
}

function setupReservationForm(event) {
    const form = document.getElementById('reservation-form');
    if (!form) return;

    // Pre-fill from logged-in user
    const user = TokenStore.getUser();
    if (user) {
        const emailField = form.querySelector('[name="email"]');
        if (emailField) emailField.value = user.email;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Booking...';

        const name  = form.querySelector('[name="name"]').value.trim();
        const email = form.querySelector('[name="email"]').value.trim();
        const phone = form.querySelector('[name="phone"]').value.trim();

        try {
            const result = await reserveEvent(event.id, name, email, phone);
            document.getElementById('reservation-section').innerHTML = `
                <div class="alert alert-success">
                    <h3>✅ Reservation Confirmed!</h3>
                    <p>Hello <strong>${escapeHtml(result.reservation.name)}</strong>,</p>
                    <p>Your spot at <strong>${escapeHtml(result.reservation.event)}</strong> is confirmed.</p>
                    <p>Confirmation sent to: ${escapeHtml(result.reservation.email)}</p>
                </div>
            `;
        } catch (err) {
            showFormError(form, err.message);
            btn.disabled = false;
            btn.textContent = 'Confirm Reservation';
        }
    });
}

// ── Auth Page ──────────────────────────────────────────────────────────────

function initAuthPage() {
    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(btn.dataset.tab).classList.add('active');
        });
    });

    // Passkey Register
    const regForm = document.getElementById('register-form');
    if (regForm) {
        regForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = regForm.querySelector('[name="email"]').value.trim();
            const btn   = regForm.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Setting up passkey...';

            try {
                await registerPasskey(email);
                showSuccess(regForm, 'Passkey registered! Redirecting...');
                setTimeout(() => window.location.href = '/', 1500);
            } catch (err) {
                showFormError(regForm, err.message);
                btn.disabled = false;
                btn.textContent = 'Register with Passkey';
            }
        });
    }

    // Passkey Login
    const passkeyBtn = document.getElementById('passkey-login-btn');
    if (passkeyBtn) {
        passkeyBtn.addEventListener('click', async () => {
            passkeyBtn.disabled = true;
            passkeyBtn.textContent = 'Authenticating...';
            try {
                await loginWithPasskey();
                showSuccess(document.getElementById('login-section'), 'Login successful! Redirecting...');
                updateNavbar();
                setTimeout(() => window.location.href = '/', 1500);
            } catch (err) {
                showFormError(document.getElementById('login-section'), err.message);
                passkeyBtn.disabled = false;
                passkeyBtn.textContent = '🔑 Login with Passkey';
            }
        });
    }

    // Password Login
    const pwForm = document.getElementById('password-login-form');
    if (pwForm) {
        pwForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email    = pwForm.querySelector('[name="email"]').value.trim();
            const password = pwForm.querySelector('[name="password"]').value;
            const btn      = pwForm.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Logging in...';

            try {
                await loginWithPassword(email, password);
                showSuccess(pwForm, 'Login successful! Redirecting...');
                updateNavbar();
                setTimeout(() => window.location.href = '/', 1500);
            } catch (err) {
                showFormError(pwForm, err.message);
                btn.disabled = false;
                btn.textContent = 'Login';
            }
        });
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────

function showFormError(container, message) {
    let el = container.querySelector('.form-error');
    if (!el) {
        el = document.createElement('div');
        el.className = 'form-error alert alert-danger';
        container.appendChild(el);
    }
    el.textContent = message;
}

function showSuccess(container, message) {
    let el = container.querySelector('.form-success');
    if (!el) {
        el = document.createElement('div');
        el.className = 'form-success alert alert-success';
        container.appendChild(el);
    }
    el.textContent = message;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
