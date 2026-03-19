<?php
require_once __DIR__ . '/includes/auth.php';
initSession();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Arellano University Document Request Management System — Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy: #0a1628;
    --navy-mid: #112240;
    --navy-light: #1d3461;
    --gold: #c9a84c;
    --gold-light: #e8c96d;
    --gold-pale: #f5e6b8;
    --crimson: #8b1a1a;
    --white: #f8f6f0;
    --gray: #8a95a3;
    --success: #2d9e6b;
    --danger: #c0392b;
    --warning: #d4a017;
  }

  html, body { height: 100%; font-family: 'DM Sans', sans-serif; }

  body {
    background: var(--navy);
    display: flex;
    min-height: 100vh;
    overflow: hidden;
  }

  /* ─── Left Panel ─── */
  .hero-panel {
    flex: 1.1;
    background: linear-gradient(145deg, var(--navy) 0%, var(--navy-mid) 40%, var(--navy-light) 100%);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
    padding: 60px 70px;
    position: relative;
    overflow: hidden;
  }

  .hero-panel::before {
    content: '';
    position: absolute;
    top: -120px; right: -120px;
    width: 500px; height: 500px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(201,168,76,0.12) 0%, transparent 70%);
    pointer-events: none;
  }

  .hero-panel::after {
    content: '';
    position: absolute;
    bottom: -80px; left: -80px;
    width: 350px; height: 350px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(139,26,26,0.15) 0%, transparent 70%);
    pointer-events: none;
  }

  .geo-lines {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background-image:
      repeating-linear-gradient(0deg, transparent, transparent 59px, rgba(201,168,76,0.04) 60px),
      repeating-linear-gradient(90deg, transparent, transparent 59px, rgba(201,168,76,0.04) 60px);
    pointer-events: none;
  }

  .brand {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 56px;
    position: relative;
    z-index: 1;
  }

  .brand-logo {
    width: 70px; height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--gold), var(--crimson));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Playfair Display', serif;
    font-size: 28px; font-weight: 900;
    color: #fff;
    box-shadow: 0 8px 32px rgba(201,168,76,0.3);
    flex-shrink: 0;
    overflow: hidden;
  }

  .brand-logo img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

  .brand-text { display: flex; flex-direction: column; }
  .brand-text .uni { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700; color: var(--gold); letter-spacing: 0.5px; }
  .brand-text .subtitle { font-size: 11px; font-weight: 500; color: var(--gray); text-transform: uppercase; letter-spacing: 2px; margin-top: 2px; }

  .hero-headline {
    position: relative; z-index: 1;
    font-family: 'Playfair Display', serif;
    font-size: clamp(36px, 4vw, 58px);
    font-weight: 900;
    color: var(--white);
    line-height: 1.1;
    margin-bottom: 24px;
  }

  .hero-headline span {
    display: block;
    background: linear-gradient(90deg, var(--gold), var(--gold-light));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .hero-desc {
    position: relative; z-index: 1;
    color: var(--gray);
    font-size: 15px;
    line-height: 1.8;
    max-width: 420px;
    margin-bottom: 48px;
  }

  .feature-pills {
    position: relative; z-index: 1;
    display: flex; flex-wrap: wrap; gap: 10px;
  }

  .pill {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 16px;
    border: 1px solid rgba(201,168,76,0.25);
    border-radius: 100px;
    font-size: 12px; font-weight: 500;
    color: var(--gold-pale);
    background: rgba(201,168,76,0.07);
  }

  .pill svg { width: 14px; height: 14px; fill: var(--gold); }

  /* ─── Right Panel ─── */
  .login-panel {
    flex: 0 0 480px;
    background: var(--white);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 60px 50px;
    position: relative;
    overflow-y: auto;
  }

  .form-header { margin-bottom: 40px; }
  .form-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 32px; font-weight: 900;
    color: var(--navy); margin-bottom: 6px;
  }
  .form-header p { color: var(--gray); font-size: 14px; }

  .tabs {
    display: flex;
    background: rgba(10,22,40,0.06);
    border-radius: 10px;
    padding: 4px;
    margin-bottom: 32px;
    gap: 4px;
  }

  .tab-btn {
    flex: 1;
    padding: 9px;
    border: none;
    border-radius: 8px;
    background: transparent;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px; font-weight: 600;
    color: var(--gray);
    cursor: pointer;
    transition: all 0.2s;
  }
  .tab-btn.active {
    background: var(--navy);
    color: var(--gold);
    box-shadow: 0 2px 8px rgba(10,22,40,0.2);
  }

  .tab-pane { display: none; }
  .tab-pane.active { display: block; }

  .form-group { margin-bottom: 20px; }
  .form-group label {
    display: block;
    font-size: 12px; font-weight: 600;
    color: var(--navy);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 7px;
  }

  .form-control {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid #ddd5c5;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    color: var(--navy);
    background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
  }

  .form-control:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(201,168,76,0.15);
  }

  .form-control.error { border-color: var(--danger); }

  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

  select.form-control { cursor: pointer; }

  .btn-primary {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, var(--navy), var(--navy-light));
    border: none;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px; font-weight: 600;
    color: var(--gold);
    cursor: pointer;
    transition: all 0.25s;
    letter-spacing: 0.5px;
    margin-top: 6px;
  }

  .btn-primary:hover {
    background: linear-gradient(135deg, var(--navy-light), var(--gold));
    color: var(--navy);
    box-shadow: 0 6px 24px rgba(10,22,40,0.25);
    transform: translateY(-1px);
  }

  .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

  .alert {
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 16px;
    display: none;
  }
  .alert.show { display: block; }
  .alert-error { background: #fdecea; border: 1px solid #f5c6c6; color: var(--danger); }
  .alert-success { background: #e8f8f2; border: 1px solid #a8dfc7; color: var(--success); }

  .field-error { font-size: 11px; color: var(--danger); margin-top: 4px; display: none; }
  .field-error.show { display: block; }

  .divider { text-align: center; color: var(--gray); font-size: 12px; margin: 16px 0; position: relative; }
  .divider::before, .divider::after {
    content: '';
    position: absolute;
    top: 50%; width: calc(50% - 20px);
    height: 1px; background: #ddd5c5;
  }
  .divider::before { left: 0; }
  .divider::after { right: 0; }

  .toggle-link {
    text-align: center; font-size: 13px; color: var(--gray); margin-top: 20px;
  }
  .toggle-link a { color: var(--navy); font-weight: 600; text-decoration: none; }
  .toggle-link a:hover { color: var(--gold); }

  .spinner {
    display: inline-block;
    width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: var(--gold);
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    vertical-align: middle;
    margin-right: 8px;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* Responsive */
  @media (max-width: 900px) {
    body { flex-direction: column; overflow: auto; }
    .hero-panel { flex: none; padding: 40px 30px 30px; min-height: auto; }
    .login-panel { flex: none; padding: 40px 30px; }
    .form-row { grid-template-columns: 1fr; }
  }

  /* Register scroll */
  #registerPane { max-height: 80vh; overflow-y: auto; padding-right: 4px; }
  #registerPane::-webkit-scrollbar { width: 4px; }
  #registerPane::-webkit-scrollbar-thumb { background: #ddd5c5; border-radius: 4px; }
</style>
</head>
<body>

<!-- ─── Hero Panel ─── -->
<div class="hero-panel">
  <div class="geo-lines"></div>

  <div class="brand">
    <div class="brand-logo">
      <img src="assets/au-logo.png" alt="AU Logo" onerror="this.style.display='none'; this.parentElement.textContent='AU';">
    </div>
    <div class="brand-text">
      <span class="uni">Arellano University</span>
      <span class="subtitle">Manila, Philippines</span>
    </div>
  </div>

  <h1 class="hero-headline">
    Arellano University<br><span>Document Request Management System</span>
  </h1>

  <p class="hero-desc">
A centralized, role-based web system automating official document requests and processing for students, departments, and registrar personnel.
  </p>

  <div class="feature-pills">
    <div class="pill">
      <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5z"/></svg>
      Secure RBAC
    </div>
    <div class="pill">
      <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/></svg>
      Hybrid Verification
    </div>
    <div class="pill">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
      Document Requests
    </div>
    <div class="pill">
      <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
      Activity Logs
    </div>
  </div>
</div>

<!-- ─── Login Panel ─── -->
<div class="login-panel">
  <div class="form-header">
    <h2>Welcome Back</h2>
    <p>Sign in to your account or register as a new student</p>
  </div>

  <div class="tabs">
    <button class="tab-btn active" onclick="switchTab('login')">Sign In</button>
    <button class="tab-btn" onclick="switchTab('register')">Student Register</button>
  </div>

  <!-- ─── Login Form ─── -->
  <div class="tab-pane active" id="loginPane">
    <div id="loginAlert" class="alert"></div>

    <form id="loginForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

      <div class="form-group">
        <label>Email Address</label>
        <input type="email" class="form-control" id="loginEmail" name="email" placeholder="your@email.com" required autocomplete="email">
        <span class="field-error" id="emailErr">Please enter a valid email.</span>
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" class="form-control" id="loginPassword" name="password" placeholder="••••••••" required autocomplete="current-password">
        <span class="field-error" id="passErr">Password is required.</span>
      </div>

      <button type="submit" class="btn-primary" id="loginBtn">Sign In</button>
    </form>
  </div>

  <!-- ─── Register Form ─── -->
  <div class="tab-pane" id="registerPane">
    <div id="regAlert" class="alert"></div>

    <form id="registerForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

      <div class="form-row">
        <div class="form-group">
          <label>First Name *</label>
          <input type="text" class="form-control" name="first_name" placeholder="Juan" required>
        </div>
        <div class="form-group">
          <label>Last Name *</label>
          <input type="text" class="form-control" name="last_name" placeholder="Dela Cruz" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Middle Name</label>
          <input type="text" class="form-control" name="middle_name" placeholder="Optional">
        </div>
        <div class="form-group">
          <label>Student Number *</label>
          <input type="text" class="form-control" name="student_number" placeholder="2021-00001" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Course *</label>
          <select class="form-control" name="course" required>
            <option value="">— Select —</option>
            <option>BS Computer Science</option>
            <option>BS Information Technology</option>
            <option>BS Nursing</option>
            <option>BS Accountancy</option>
            <option>BS Business Administration</option>
            <option>BS Engineering</option>
            <option>AB Communication</option>
            <option>BS Education</option>
            <option>BS Psychology</option>
            <option>BS Criminology</option>
          </select>
        </div>
        <div class="form-group">
          <label>Year Level *</label>
          <select class="form-control" name="year_level" required>
            <option value="">— Select —</option>
            <option value="1">1st Year</option>
            <option value="2">2nd Year</option>
            <option value="3">3rd Year</option>
            <option value="4">4th Year</option>
            <option value="5">5th Year</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Section</label>
          <input type="text" class="form-control" name="section" placeholder="e.g. BSCS-4A">
        </div>
        <div class="form-group">
          <label>Contact Number</label>
          <input type="text" class="form-control" name="contact_number" placeholder="09xxxxxxxxx">
        </div>
      </div>

      <div class="form-group">
        <label>Username *</label>
        <input type="text" class="form-control" name="username" placeholder="juandelacruz" required autocomplete="username">
      </div>

      <div class="form-group">
        <label>Email Address *</label>
        <input type="email" class="form-control" name="email" placeholder="juan@email.com" required autocomplete="email">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Password *</label>
          <input type="password" class="form-control" name="password" id="regPass" placeholder="Min. 8 characters" required autocomplete="new-password">
        </div>
        <div class="form-group">
          <label>Confirm Password *</label>
          <input type="password" class="form-control" name="confirm_password" placeholder="Repeat password" required autocomplete="new-password">
        </div>
      </div>

      <div class="form-group">
        <label>Home Address</label>
        <input type="text" class="form-control" name="address" placeholder="City / Province">
      </div>

      <button type="submit" class="btn-primary" id="regBtn">Create Account</button>
    </form>
  </div>

  <div class="toggle-link">
    Need help? Contact <a href="mailto:registrar@arellano.edu.ph">registrar@arellano.edu.ph</a>
  </div>
</div>

<script>
  // Tab switching
  function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach((b, i) => {
      b.classList.toggle('active', (i === 0) === (tab === 'login'));
    });
    document.getElementById('loginPane').classList.toggle('active', tab === 'login');
    document.getElementById('registerPane').classList.toggle('active', tab === 'register');
  }

  function showAlert(id, msg, type) {
    const el = document.getElementById(id);
    el.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error') + ' show';
    el.textContent = msg;
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  // ─── Login Form ───
  document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;
    let valid = true;

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      document.getElementById('emailErr').classList.add('show');
      document.getElementById('loginEmail').classList.add('error');
      valid = false;
    } else {
      document.getElementById('emailErr').classList.remove('show');
      document.getElementById('loginEmail').classList.remove('error');
    }
    if (!password) {
      document.getElementById('passErr').classList.add('show');
      document.getElementById('loginPassword').classList.add('error');
      valid = false;
    } else {
      document.getElementById('passErr').classList.remove('show');
      document.getElementById('loginPassword').classList.remove('error');
    }
    if (!valid) return;

    const btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Signing in...';

    const fd = new FormData(this);
    fd.append('action', 'login');

    try {
      const res = await fetch('ajax/auth.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        showAlert('loginAlert', 'Login successful! Redirecting...', 'success');
        setTimeout(() => window.location.href = data.redirect, 800);
      } else {
        showAlert('loginAlert', data.message || 'Login failed.', 'error');
        btn.disabled = false;
        btn.textContent = 'Sign In';
      }
    } catch {
      showAlert('loginAlert', 'Network error. Please try again.', 'error');
      btn.disabled = false;
      btn.textContent = 'Sign In';
    }
  });

  // ─── Register Form ───
  document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const required = form.querySelectorAll('[required]');
    let valid = true;

    required.forEach(input => {
      if (!input.value.trim()) {
        input.classList.add('error');
        valid = false;
      } else {
        input.classList.remove('error');
      }
    });

    const pass = form.querySelector('[name=password]').value;
    const confirm = form.querySelector('[name=confirm_password]').value;
    if (pass !== confirm) {
      showAlert('regAlert', 'Passwords do not match.', 'error');
      return;
    }
    if (pass.length < 8) {
      showAlert('regAlert', 'Password must be at least 8 characters.', 'error');
      return;
    }
    if (!valid) {
      showAlert('regAlert', 'Please fill all required fields.', 'error');
      return;
    }

    const btn = document.getElementById('regBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Registering...';

    const fd = new FormData(form);
    fd.append('action', 'register');

    try {
      const res = await fetch('ajax/auth.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        showAlert('regAlert', data.message, 'success');
        form.reset();
        setTimeout(() => switchTab('login'), 2500);
      } else {
        showAlert('regAlert', data.message || 'Registration failed.', 'error');
      }
    } catch {
      showAlert('regAlert', 'Network error. Please try again.', 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Create Account';
    }
  });
</script>
</body>
</html>