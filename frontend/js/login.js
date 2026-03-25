// ============================================================
//  frontend/js/login.js
//  Handles the login form: sends credentials to the PHP API,
//  processes the JSON response, and redirects or shows errors.
// ============================================================

// ── Config ──────────────────────────────────────────────────
// Base URL of the project – adjust if your folder name differs
const API_BASE = 'http://localhost/Inventory-Management-System/Inventra/backend/api';

// ── DOM references ───────────────────────────────────────────
const loginForm    = document.getElementById('loginForm');
const usernameInput= document.getElementById('username');
const passwordInput= document.getElementById('password');
const loginBtn     = document.getElementById('loginBtn');
const loginSpinner = document.getElementById('loginSpinner');
const errorBanner  = document.getElementById('errorBanner');
const errorText    = document.getElementById('errorText');
const togglePwBtn  = document.getElementById('togglePw');
const eyeOpen      = document.getElementById('eyeOpen');
const eyeClosed    = document.getElementById('eyeClosed');

// ── Password visibility toggle ───────────────────────────────
togglePwBtn.addEventListener('click', () => {
  const isPassword = passwordInput.type === 'password';
  passwordInput.type = isPassword ? 'text' : 'password';
  eyeOpen.style.display   = isPassword ? 'none'  : 'block';
  eyeClosed.style.display = isPassword ? 'block' : 'none';
  togglePwBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
});

// ── Helper: show/hide the error banner ───────────────────────
function showError(message) {
  errorText.textContent = message;
  // Remove and re-add the class so the shake animation replays
  errorBanner.classList.remove('visible');
  void errorBanner.offsetWidth;  // force reflow
  errorBanner.classList.add('visible');
}

function hideError() {
  errorBanner.classList.remove('visible');
}

// ── Helper: toggle loading state on the button ───────────────
function setLoading(isLoading) {
  loginBtn.disabled = isLoading;
  loginSpinner.classList.toggle('visible', isLoading);
}

// ── Form submit ──────────────────────────────────────────────
loginForm.addEventListener('submit', async (event) => {
  // Prevent the default browser form submission (page reload)
  event.preventDefault();

  hideError();

  const username = usernameInput.value.trim();
  const password = passwordInput.value;

  // Basic client-side validation
  if (!username || !password) {
    showError('Please enter both username and password.');
    return;
  }

  setLoading(true);

  try {
    /*
     * fetch() sends an HTTP POST request to the PHP login API.
     *
     * WHY credentials: 'include'?
     *   When your HTML page and your PHP API are on the same
     *   localhost domain, the browser normally doesn't send
     *   cookies with fetch() requests unless you explicitly
     *   ask it to.  credentials: 'include' tells the browser:
     *   "send any existing cookies (like PHPSESSID) with this
     *   request, AND save any new cookies the server sets."
     *   Without this, PHP sessions would never persist between
     *   requests and checkAuth.php would always say Unauthorized.
     */
    const response = await fetch(`${API_BASE}/login.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      credentials: 'include',      // ← required for session cookies
      body: JSON.stringify({ username, password })
    });

    // Parse the JSON response from PHP
    const data = await response.json();

    if (data.status === 'success') {
      // ✅ Login successful – save role to sessionStorage (optional)
      // and redirect to the dashboard
      sessionStorage.setItem('role', data.role);

      // Small visual delay so the user sees the success state
      loginBtn.textContent = '✓ Redirecting…';
      setTimeout(() => {
        window.location.href = 'dashboard.html';
      }, 600);

    } else {
      // ❌ Server returned an error (wrong password, etc.)
      showError(data.message || 'Invalid credentials. Please try again.');
      setLoading(false);
    }

  } catch (err) {
    // Network error (XAMPP not running, wrong URL, etc.)
    console.error('Login error:', err);
    showError('Could not reach the server. Is XAMPP running?');
    setLoading(false);
  }
});

// ── Clear error as user types ────────────────────────────────
usernameInput.addEventListener('input', hideError);
passwordInput.addEventListener('input', hideError);
