/**
 * auth.js — WebAuthn (Passkeys) + JWT authentication client
 * Covers: register, login with passkey, login with password, token refresh
 */

// ── Base64url helpers ──────────────────────────────────────────────────────

function bufferToBase64Url(buffer) {
    const bytes = Array.from(new Uint8Array(buffer));
    const binary = bytes.map(b => String.fromCharCode(b)).join('');
    return btoa(binary)
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');
}

function base64UrlToBuffer(base64url) {
    let base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const padding = '='.repeat((4 - base64.length % 4) % 4);
    base64 += padding;
    const binary = atob(base64);
    const bytes = Uint8Array.from(binary, c => c.charCodeAt(0));
    return bytes.buffer;
}

// ── Token storage ──────────────────────────────────────────────────────────

const TokenStore = {
    save(token, refreshToken) {
        localStorage.setItem('jwt_token', token);
        localStorage.setItem('refresh_token', refreshToken);
    },
    getToken()   { return localStorage.getItem('jwt_token'); },
    getRefresh() { return localStorage.getItem('refresh_token'); },
    clear() {
        localStorage.removeItem('jwt_token');
        localStorage.removeItem('refresh_token');
        localStorage.removeItem('current_user');
    },
    saveUser(user) { localStorage.setItem('current_user', JSON.stringify(user)); },
    getUser()      {
        const u = localStorage.getItem('current_user');
        return u ? JSON.parse(u) : null;
    },
    isLoggedIn()   { return !!this.getToken(); }
};

// ── Authenticated fetch with auto-refresh ─────────────────────────────────

async function authFetch(url, options = {}) {
    const token = TokenStore.getToken();
    const headers = {
        'Content-Type': 'application/json',
        ...(options.headers || {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {})
    };

    let res = await fetch(url, { ...options, headers });

    // Auto-refresh on 401
    if (res.status === 401) {
        const refreshed = await refreshToken();
        if (refreshed) {
            headers.Authorization = `Bearer ${TokenStore.getToken()}`;
            res = await fetch(url, { ...options, headers });
        } else {
            TokenStore.clear();
            window.location.href = '/';
        }
    }
    return res;
}

// ── Token refresh ──────────────────────────────────────────────────────────

async function refreshToken() {
    const refresh = TokenStore.getRefresh();
    if (!refresh) return false;

    const res = await fetch('/api/token/refresh', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: refresh })
    });

    if (!res.ok) { TokenStore.clear(); return false; }

    const data = await res.json();
    TokenStore.save(data.token, data.refresh_token || refresh);
    return true;
}

// ── Passkey registration ───────────────────────────────────────────────────

async function registerPasskey(email) {
    if (!window.PublicKeyCredential) {
        throw new Error('Passkeys are not supported in this browser. Please use Chrome, Firefox or Safari.');
    }

    // 1. Get options from server
    const optRes = await fetch('/api/auth/register/options', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
    });
    if (!optRes.ok) {
        const err = await optRes.json();
        throw new Error(err.error || 'Failed to get registration options');
    }
    const options = await optRes.json();

    // 2. Create credential via browser API
    const credential = await navigator.credentials.create({
        publicKey: {
            ...options,
            challenge: base64UrlToBuffer(options.challenge),
            user: {
                ...options.user,
                id: base64UrlToBuffer(options.user.id)
            },
            excludeCredentials: (options.excludeCredentials || []).map(c => ({
                ...c,
                id: base64UrlToBuffer(c.id)
            }))
        }
    });

    // 3. Send response to server
    const verifyRes = await fetch('/api/auth/register/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            email,
            credential: {
                id: credential.id,
                rawId: bufferToBase64Url(credential.rawId),
                response: {
                    clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                    attestationObject: bufferToBase64Url(credential.response.attestationObject)
                },
                type: credential.type,
                clientExtensionResults: credential.getClientExtensionResults()
            }
        })
    });

    const result = await verifyRes.json();
    if (!verifyRes.ok) throw new Error(result.error || 'Registration failed');

    if (result.token) {
        TokenStore.save(result.token, result.refresh_token);
        TokenStore.saveUser(result.user);
    }
    return result;
}

// ── Passkey login ──────────────────────────────────────────────────────────

async function loginWithPasskey() {
    if (!window.PublicKeyCredential) {
        throw new Error('Passkeys are not supported in this browser.');
    }

    // 1. Get login options
    const optRes = await fetch('/api/auth/login/options', { method: 'POST' });
    if (!optRes.ok) {
        const err = await optRes.json();
        throw new Error(err.error || 'Failed to get login options');
    }
    const options = await optRes.json();

    // 2. Request authentication from browser
    const assertion = await navigator.credentials.get({
        publicKey: {
            ...options,
            challenge: base64UrlToBuffer(options.challenge),
            allowCredentials: (options.allowCredentials || []).map(c => ({
                ...c,
                id: base64UrlToBuffer(c.id)
            }))
        }
    });

    // 3. Verify with server
    const verifyRes = await fetch('/api/auth/login/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            credential: {
                id: assertion.id,
                rawId: bufferToBase64Url(assertion.rawId),
                response: {
                    clientDataJSON: bufferToBase64Url(assertion.response.clientDataJSON),
                    authenticatorData: bufferToBase64Url(assertion.response.authenticatorData),
                    signature: bufferToBase64Url(assertion.response.signature),
                    userHandle: assertion.response.userHandle
                        ? bufferToBase64Url(assertion.response.userHandle)
                        : null
                },
                type: assertion.type,
                clientExtensionResults: assertion.getClientExtensionResults()
            }
        })
    });

    const result = await verifyRes.json();
    if (!verifyRes.ok) throw new Error(result.error || 'Login failed');

    if (result.token) {
        TokenStore.save(result.token, result.refresh_token);
        TokenStore.saveUser(result.user);
    }
    return result;
}

// ── Password login ─────────────────────────────────────────────────────────

async function loginWithPassword(email, password) {
    const res = await fetch('/api/auth/login/password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
    });

    const result = await res.json();
    if (!res.ok) throw new Error(result.error || 'Login failed');

    if (result.token) {
        TokenStore.save(result.token, result.refresh_token);
        TokenStore.saveUser(result.user);
    }
    return result;
}

// ── Logout ─────────────────────────────────────────────────────────────────

function logout() {
    TokenStore.clear();
    window.location.href = '/';
}

// ── Reserve an event ───────────────────────────────────────────────────────

async function reserveEvent(eventId, name, email, phone) {
    const res = await authFetch(`/api/events/${eventId}/reserve`, {
        method: 'POST',
        body: JSON.stringify({ name, email, phone })
    });

    const result = await res.json();
    if (!res.ok) throw new Error(result.error || 'Reservation failed');
    return result;
}

// ── Load events ────────────────────────────────────────────────────────────

async function loadEvents() {
    const res = await fetch('/api/events');
    if (!res.ok) throw new Error('Failed to load events');
    return await res.json();
}

async function loadEvent(id) {
    const res = await fetch(`/api/events/${id}`);
    if (!res.ok) throw new Error('Event not found');
    return await res.json();
}

// Export for module use (optional)
if (typeof module !== 'undefined') {
    module.exports = { registerPasskey, loginWithPasskey, loginWithPassword, logout, authFetch, TokenStore, loadEvents, loadEvent, reserveEvent };
}
