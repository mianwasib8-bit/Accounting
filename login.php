<?php
/**
 * Neon purple split login (reference design)
 */
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

use App\Core\Auth;

if (Auth::check()) {
    redirect('/index.php');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } elseif (Auth::attempt($username, $password)) {
        $success = true;
    } else {
        $error = 'Invalid credentials. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login · <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html, body { height: 100%; margin: 0; }
    body {
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
      background: #07060f;
      color: #fff;
      min-height: 100%;
      display: grid;
      place-items: center;
      padding: 20px;
      -webkit-font-smoothing: antialiased;
    }

    .login-stage {
      position: relative;
      width: 100%;
      max-width: 860px;
    }

    /* Outer neon glow frame */
    .login-frame {
      position: relative;
      border-radius: 10px;
      padding: 2px;
      background: linear-gradient(135deg, #a855f7, #7c3aed 40%, #6d28d9 100%);
      box-shadow:
        0 0 0 1px rgba(168, 85, 247, .35),
        0 0 40px rgba(124, 58, 237, .45),
        0 0 80px rgba(109, 40, 217, .25),
        0 30px 80px rgba(0, 0, 0, .55);
      animation: frameIn .5s ease both;
    }

    .login-card {
      display: grid;
      grid-template-columns: 1fr 1fr;
      min-height: 420px;
      border-radius: 8px;
      overflow: hidden;
      background: #0a0814;
    }

    /* Left form panel */
    .login-left {
      background: #0c0a16;
      padding: 48px 42px 36px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      position: relative;
      z-index: 1;
    }

    .login-left h1 {
      margin: 0 0 36px;
      text-align: center;
      font-size: 34px;
      font-weight: 800;
      letter-spacing: -.02em;
      color: #fff;
      text-shadow: 0 0 24px rgba(168, 85, 247, .35);
    }

    .field {
      margin-bottom: 28px;
    }
    .field label {
      display: block;
      font-size: 13px;
      font-weight: 500;
      color: #d4d4d8;
      margin-bottom: 8px;
    }

    .input-line {
      display: flex;
      align-items: center;
      gap: 10px;
      border-bottom: 1.5px solid rgba(255, 255, 255, .55);
      padding-bottom: 8px;
      transition: border-color .2s, box-shadow .2s;
    }
    .input-line:focus-within {
      border-bottom-color: #c084fc;
      box-shadow: 0 1px 0 0 #c084fc;
    }
    .input-line input {
      flex: 1;
      border: 0;
      outline: none;
      background: transparent;
      color: #fff;
      font-size: 15px;
      font-family: inherit;
      padding: 4px 0;
    }
    .input-line input::placeholder {
      color: rgba(255, 255, 255, .35);
    }
    .input-line .ico {
      width: 18px;
      height: 18px;
      color: rgba(255, 255, 255, .7);
      flex-shrink: 0;
    }
    .input-line .toggle-eye {
      border: 0;
      background: transparent;
      color: rgba(255, 255, 255, .55);
      cursor: pointer;
      padding: 0;
      display: grid;
      place-items: center;
    }
    .input-line .toggle-eye:hover { color: #e9d5ff; }

    .btn-login {
      margin-top: 10px;
      width: 100%;
      border: 0;
      border-radius: 999px;
      padding: 13px 18px;
      font-size: 15px;
      font-weight: 700;
      font-family: inherit;
      color: #fff;
      cursor: pointer;
      background: linear-gradient(90deg, #9333ea, #7c3aed 50%, #6d28d9);
      box-shadow: 0 8px 28px rgba(124, 58, 237, .45);
      transition: transform .12s, filter .15s, box-shadow .15s;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .btn-login:hover:not(:disabled) {
      filter: brightness(1.08);
      box-shadow: 0 10px 32px rgba(168, 85, 247, .55);
    }
    .btn-login:active:not(:disabled) { transform: scale(.98); }
    .btn-login:disabled { opacity: .7; cursor: wait; }

    .spinner {
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .7s linear infinite;
      display: none;
    }
    .btn-login.loading .spinner { display: inline-block; }
    .btn-login.loading .btn-text { opacity: .85; }

    .login-foot {
      margin-top: 22px;
      text-align: center;
      font-size: 12.5px;
      color: rgba(255, 255, 255, .55);
    }
    .login-foot strong { color: #e9d5ff; font-weight: 600; }

    .alert {
      margin: 0 0 18px;
      padding: 10px 12px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 500;
      border: 1px solid;
    }
    .alert-error {
      background: rgba(244, 63, 94, .12);
      color: #fda4af;
      border-color: rgba(244, 63, 94, .3);
    }
    .alert-success {
      background: rgba(16, 185, 129, .12);
      color: #6ee7b7;
      border-color: rgba(16, 185, 129, .3);
    }

    /* Right diagonal panel */
    .login-right {
      position: relative;
      background: linear-gradient(145deg, #7c3aed 0%, #6d28d9 45%, #5b21b6 100%);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 36px;
      clip-path: polygon(18% 0, 100% 0, 100% 100%, 0 100%);
      overflow: hidden;
    }
    .login-right::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(circle at 70% 30%, rgba(255,255,255,.18), transparent 45%),
        radial-gradient(circle at 30% 80%, rgba(0,0,0,.12), transparent 40%);
      pointer-events: none;
    }
    .welcome {
      position: relative;
      z-index: 1;
      text-align: center;
      max-width: 260px;
      margin-left: 24px;
    }
    .welcome h2 {
      margin: 0;
      font-size: clamp(1.6rem, 3.2vw, 2.15rem);
      font-weight: 800;
      line-height: 1.15;
      letter-spacing: .02em;
      text-transform: uppercase;
      text-shadow: 0 2px 20px rgba(0,0,0,.2);
    }
    .welcome p {
      margin: 14px 0 0;
      font-size: 13.5px;
      line-height: 1.55;
      color: rgba(255, 255, 255, .88);
      font-weight: 400;
    }

    @keyframes frameIn {
      from { opacity: 0; transform: translateY(12px) scale(.98); }
      to   { opacity: 1; transform: none; }
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Mobile: stack panels */
    @media (max-width: 760px) {
      .login-card {
        grid-template-columns: 1fr;
        min-height: auto;
      }
      .login-right {
        clip-path: none;
        min-height: 180px;
        order: -1;
        padding: 32px 24px;
      }
      .welcome { margin-left: 0; }
      .login-left { padding: 32px 24px 28px; }
      .login-left h1 { font-size: 28px; margin-bottom: 28px; }
    }
  </style>
</head>
<body>
  <div class="login-stage">
    <div class="login-frame">
      <div class="login-card" id="login-card">
        <!-- Left: form -->
        <div class="login-left">
          <h1>Login</h1>

          <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
          <?php endif; ?>
          <?php if ($success): ?>
            <div class="alert alert-success">Login successful. Redirecting…</div>
          <?php endif; ?>

          <form method="POST" action="<?= e(url('/login.php')) ?>" id="login-form" autocomplete="on" <?= $success ? 'style="display:none"' : '' ?>>
            <div class="field">
              <label for="username">Username</label>
              <div class="input-line">
                <input type="text" id="username" name="username" required autofocus
                       value="<?= e($_POST['username'] ?? 'admin') ?>"
                       placeholder="Enter username" />
                <svg class="ico" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21a8 8 0 1116 0"/>
                </svg>
              </div>
            </div>

            <div class="field">
              <label for="password">Password</label>
              <div class="input-line">
                <input type="password" id="password" name="password" required placeholder="Enter password" />
                <button type="button" class="toggle-eye" id="toggle-pass" aria-label="Show password" title="Show / hide">
                  <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                </button>
                <svg class="ico" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
              </div>
            </div>

            <button type="submit" class="btn-login" id="btn-login">
              <span class="spinner"></span>
              <span class="btn-text">Login</span>
            </button>
          </form>

          <p class="login-foot">Demo · <strong>admin</strong> / <strong>admin123</strong></p>
        </div>

        <!-- Right: welcome -->
        <div class="login-right">
          <div class="welcome">
            <h2>Welcome<br/>Back!</h2>
            <p><?= e(APP_NAME) ?> — manage accounts, vouchers and ledgers in one place.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
(function () {
  var form = document.getElementById('login-form');
  var btn = document.getElementById('btn-login');
  var toggle = document.getElementById('toggle-pass');
  var pass = document.getElementById('password');
  var user = document.getElementById('username');

  if (toggle && pass) {
    toggle.addEventListener('click', function () {
      pass.type = pass.type === 'password' ? 'text' : 'password';
    });
  }
  if (form && btn) {
    form.addEventListener('submit', function () {
      btn.classList.add('loading');
      btn.disabled = true;
    });
  }

  // Enter on username → password; Enter on password → submit
  if (user && pass) {
    user.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.keyCode === 13) {
        e.preventDefault();
        pass.focus();
        if (typeof pass.select === 'function') pass.select();
      }
    });
  }
  <?php if ($success): ?>
  setTimeout(function () {
    var c = document.getElementById('login-card');
    c.style.transition = 'opacity .35s, transform .35s';
    c.style.opacity = '0';
    c.style.transform = 'translateY(-10px) scale(.98)';
    setTimeout(function () {
      window.location.href = <?= json_encode(url('/index.php')) ?>;
    }, 340);
  }, 400);
  <?php endif; ?>
})();
</script>

</body>
</html>
