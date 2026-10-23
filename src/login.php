<?php
// =============================================
//  login.php — Tommy's Bar
//  Auth logic + UI. Redirects to dashboard on success.
// =============================================
require_once __DIR__ . '/../config/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in → go directly to dashboard
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error       = '';
$logged_out  = isset($_GET['logged_out']);
$redirect_to = htmlspecialchars($_GET['redirect'] ?? 'dashboard.php', ENT_QUOTES);

// ── POST: Process login ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Basic CSRF: verify token
    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = 'Solicitud inválida. Recarga la página e intenta de nuevo.';
    } else {
        $usuario    = trim($_POST['usuario']    ?? '');
        $contrasena = trim($_POST['contrasena'] ?? '');

        if ($usuario === '' || $contrasena === '') {
            $error = 'Por favor ingresa usuario y contraseña.';
        } else {
            $db   = get_db();
            $stmt = $db->prepare("
                SELECT Administrador_Id, Nombre_Administrador, Contrasena_Administrador
                FROM   administrador
                WHERE  Usuario_Administrador = :usuario
                LIMIT  1
            ");
            $stmt->execute([':usuario' => $usuario]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($contrasena, $admin['Contrasena_Administrador'])) {
                // ── Auth success ──────────────────
                session_regenerate_id(true); // prevent session fixation

                $_SESSION['admin_id']           = $admin['Administrador_Id'];
                $_SESSION['admin_user']         = $usuario;
                $_SESSION['admin_name']         = $admin['Nombre_Administrador'];
                $_SESSION['_last_regenerated']  = time();

                // Redirect to originally requested page or dashboard
                $safe_redirect = ltrim(parse_url($_GET['redirect'] ?? 'dashboard.php', PHP_URL_PATH), '/');
                $allowed_pages = ['dashboard.php', 'ticket.php']; // whitelist
                $target = in_array($safe_redirect, $allowed_pages) ? $safe_redirect : 'dashboard.php';

                header('Location: ' . $target);
                exit;
            } else {
                // Intentional vague message (don't reveal which field is wrong)
                $error = 'Usuario o contraseña incorrectos.';
                // Optional: add login attempt throttling here (store in session/DB)
            }
        }
    }
}

// ── Generate CSRF token for the form ─────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tommy's Bar — Acceso Administrador</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Serif+Display:ital@0;1&family=Nunito:wght@400;600;700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
/* ── CSS VARIABLES ───────────────────────────── */
:root {
  --naranja:   #F26419;
  --naranja-d: #C44E0E;
  --amarillo:  #F7B731;
  --cafe:      #2E1503;
  --cafe-md:   #6B3A1F;
  --cafe-lt:   #A0613A;
  --blanco:    #FFF8F0;
  --gris-lt:   #F5EDE3;
}

/* ── RESET ───────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── LAYOUT ──────────────────────────────────── */
body {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
  font-family: 'Nunito', sans-serif;
  background: var(--cafe);
  overflow: hidden;
}

/* ── LEFT PANEL (brand) ──────────────────────── */
.panel-brand {
  position: relative;
  background: var(--cafe);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 60px 48px;
  overflow: hidden;
}

/* Animated bokeh circles */
.bokeh {
  position: absolute;
  border-radius: 50%;
  opacity: .12;
  animation: float linear infinite;
}
.bokeh:nth-child(1){ width:280px;height:280px;background:var(--naranja);top:-60px;left:-80px;animation-duration:18s; }
.bokeh:nth-child(2){ width:180px;height:180px;background:var(--amarillo);bottom:60px;left:40px;animation-duration:14s;animation-delay:-5s; }
.bokeh:nth-child(3){ width:120px;height:120px;background:var(--naranja);top:45%;right:-20px;animation-duration:20s;animation-delay:-9s; }

@keyframes float {
  0%,100% { transform: translateY(0) scale(1); }
  50%      { transform: translateY(-30px) scale(1.05); }
}

/* Diagonal stripe overlay */
.panel-brand::before {
  content: '';
  position: absolute; inset: 0;
  background: repeating-linear-gradient(
    -55deg,
    transparent,
    transparent 40px,
    rgba(255,255,255,.02) 40px,
    rgba(255,255,255,.02) 41px
  );
  pointer-events: none;
}

.brand-content { position: relative; z-index: 1; text-align: center; }

.brand-cocktail {
  font-size: 5.5rem;
  display: block;
  margin-bottom: 12px;
  filter: drop-shadow(0 8px 24px rgba(242,100,25,.5));
  animation: sway 4s ease-in-out infinite;
}
@keyframes sway {
  0%,100% { transform: rotate(-4deg); }
  50%      { transform: rotate(4deg); }
}

.brand-name {
  font-family: 'Bebas Neue', cursive;
  font-size: 4.5rem;
  letter-spacing: 6px;
  color: var(--amarillo);
  line-height: 1;
  text-shadow: 0 4px 20px rgba(247,183,49,.3);
}
.brand-tagline {
  font-family: 'DM Serif Display', serif;
  font-style: italic;
  font-size: 1.05rem;
  color: rgba(255,255,255,.55);
  margin-top: 10px;
  letter-spacing: .5px;
}

.brand-divider {
  width: 60px; height: 3px;
  background: linear-gradient(90deg, var(--naranja), var(--amarillo));
  border-radius: 2px;
  margin: 24px auto;
}

.brand-badges {
  display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;
  margin-top: 8px;
}
.badge-pill {
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.12);
  color: rgba(255,255,255,.7);
  font-size: .75rem; font-weight: 700;
  padding: 5px 14px; border-radius: 20px;
  letter-spacing: .5px;
  text-transform: uppercase;
}

/* ── RIGHT PANEL (form) ──────────────────────── */
.panel-form {
  background: var(--blanco);
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 64px 56px;
  position: relative;
  overflow: hidden;
}

/* Top accent bar */
.panel-form::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 5px;
  background: linear-gradient(90deg, var(--naranja), var(--amarillo), var(--naranja));
  background-size: 200% 100%;
  animation: shimmer 3s linear infinite;
}
@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Decorative corner shape */
.panel-form::after {
  content: '';
  position: absolute;
  bottom: -60px; right: -60px;
  width: 200px; height: 200px;
  background: var(--gris-lt);
  border-radius: 50%;
  pointer-events: none;
}

.form-wrap {
  position: relative; z-index: 1;
  max-width: 380px;
  width: 100%;
  margin: 0 auto;
}

.form-eyebrow {
  font-size: .75rem; font-weight: 900;
  text-transform: uppercase; letter-spacing: 2px;
  color: var(--naranja);
  margin-bottom: 8px;
}
.form-heading {
  font-family: 'Bebas Neue', cursive;
  font-size: 2.8rem; letter-spacing: 2px;
  color: var(--cafe);
  line-height: 1; margin-bottom: 6px;
}
.form-sub {
  font-size: .88rem; color: var(--cafe-lt);
  margin-bottom: 36px;
}

/* ── ALERTS ──────────────────────────────────── */
.alert-tommy {
  border-radius: 10px;
  padding: 13px 16px;
  font-size: .87rem; font-weight: 700;
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 24px;
  animation: slideDown .3s ease;
}
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.alert-error   { background: #FFF0F0; color: #C0392B; border-left: 4px solid #E74C3C; }
.alert-success { background: #E6FAF0; color: #1A7040; border-left: 4px solid #2ECC71; }

/* ── FORM FIELDS ─────────────────────────────── */
.field-group { margin-bottom: 20px; }
.field-label {
  display: block;
  font-size: .8rem; font-weight: 900;
  text-transform: uppercase; letter-spacing: .8px;
  color: var(--cafe-md);
  margin-bottom: 7px;
}
.field-wrap { position: relative; }
.field-icon {
  position: absolute; left: 14px; top: 50%;
  transform: translateY(-50%);
  color: var(--cafe-lt); font-size: 1rem;
  pointer-events: none;
  transition: color .2s;
}
.field-input {
  width: 100%;
  padding: 13px 14px 13px 42px;
  border: 2px solid #DDD0C5;
  border-radius: 10px;
  font-family: 'Nunito', sans-serif;
  font-size: .95rem;
  color: var(--cafe);
  background: #fff;
  outline: none;
  transition: border-color .2s, box-shadow .2s;
}
.field-input:focus {
  border-color: var(--naranja);
  box-shadow: 0 0 0 4px rgba(242,100,25,.1);
}
.field-input:focus ~ .field-icon { color: var(--naranja); }

/* Eye toggle for password */
.field-eye {
  position: absolute; right: 14px; top: 50%;
  transform: translateY(-50%);
  background: none; border: none;
  color: var(--cafe-lt); font-size: 1rem;
  cursor: pointer; padding: 0;
  line-height: 1;
  transition: color .2s;
}
.field-eye:hover { color: var(--naranja); }

/* ── SUBMIT BUTTON ───────────────────────────── */
.btn-login {
  width: 100%;
  padding: 14px;
  background: var(--cafe);
  color: var(--blanco);
  border: none; border-radius: 10px;
  font-family: 'Bebas Neue', cursive;
  font-size: 1.2rem; letter-spacing: 2px;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 10px;
  transition: background .2s, transform .1s, box-shadow .2s;
  box-shadow: 0 4px 16px rgba(46,21,3,.2);
  margin-top: 8px;
}
.btn-login:hover {
  background: var(--naranja);
  box-shadow: 0 6px 24px rgba(242,100,25,.35);
}
.btn-login:active { transform: scale(.98); }
.btn-login i { font-size: 1.1rem; }

/* Loading state */
.btn-login.loading { pointer-events: none; opacity: .8; }
.btn-login.loading::after {
  content: '';
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .7s linear infinite;
  margin-left: 8px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── FOOTER ──────────────────────────────────── */
.form-footer {
  margin-top: 32px; text-align: center;
  font-size: .78rem; color: var(--cafe-lt);
}
.form-footer a { color: var(--naranja); font-weight: 700; text-decoration: none; }
.form-footer a:hover { text-decoration: underline; }

/* ── RESPONSIVE ──────────────────────────────── */
@media (max-width: 768px) {
  body { grid-template-columns: 1fr; overflow: auto; }
  .panel-brand { display: none; }
  .panel-form  { padding: 48px 28px; min-height: 100vh; }
}
</style>
</head>
<body>

<!-- ── LEFT: Brand Panel ───────────────────────── -->
<div class="panel-brand">
  <!-- Bokeh decorations -->
  <div class="bokeh"></div>
  <div class="bokeh"></div>
  <div class="bokeh"></div>

  <div class="brand-content">
    <img src="img/tommys_bar_logo.png" alt="Logo Tommy's Bar" class="img-fluid mb-2" style="max-height: 180px;">
    <div class="brand-name">Tommy's<br>Bar</div>
    <div class="brand-tagline">Eventos que se recuerdan</div>
    <div class="brand-divider"></div>
    <div class="brand-badges">
      <span class="badge-pill"><i class="bi bi-calendar-check"></i> Gestión de Eventos</span>
      <span class="badge-pill"><i class="bi bi-shield-lock"></i> Área Privada</span>
    </div>
  </div>
</div>

<!-- ── RIGHT: Form Panel ───────────────────────── -->
<div class="panel-form">
  <div class="form-wrap">

    <div class="form-eyebrow">Sistema de Administración</div>
    <div class="form-heading">Bienvenido<br>de regreso</div>
    <div class="form-sub">Accede con tus credenciales de administrador.</div>

    <!-- Alerts -->
    <?php if ($error): ?>
    <div class="alert-tommy alert-error">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php elseif ($logged_out): ?>
    <div class="alert-tommy alert-success">
      <i class="bi bi-check-circle-fill"></i>
      Sesión cerrada correctamente. ¡Hasta pronto!
    </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" action="login.php<?= $redirect_to !== 'dashboard.php' ? '?redirect=' . urlencode($redirect_to) : '' ?>" id="loginForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

      <!-- Usuario -->
      <div class="field-group">
        <label class="field-label" for="usuario">Usuario</label>
        <div class="field-wrap">
          <i class="bi bi-person-fill field-icon" id="icon-user"></i>
          <input
            type="text"
            id="usuario"
            name="usuario"
            class="field-input"
            placeholder="usuario_admin"
            value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
            autocomplete="username"
            required
          >
        </div>
      </div>

      <!-- Contraseña -->
      <div class="field-group">
        <label class="field-label" for="contrasena">Contraseña</label>
        <div class="field-wrap">
          <i class="bi bi-lock-fill field-icon" id="icon-pass"></i>
          <input
            type="password"
            id="contrasena"
            name="contrasena"
            class="field-input"
            placeholder="••••••••••"
            autocomplete="current-password"
            required
          >
          <button type="button" class="field-eye" id="togglePass" title="Mostrar/ocultar contraseña">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <!-- Submit -->
      <button type="submit" class="btn-login" id="submitBtn">
        <i class="bi bi-box-arrow-in-right"></i>
        Ingresar al Sistema
      </button>
    </form>

    <div class="form-footer">
      Tommy's Bar &copy; <?= date('Y') ?> &mdash;
      <a href="mailto:admin@tommysbar.mx">Soporte</a>
    </div>

  </div>
</div>

<script>
// ── Password visibility toggle ─────────────────
const toggleBtn  = document.getElementById('togglePass');
const passInput  = document.getElementById('contrasena');
const eyeIcon    = document.getElementById('eyeIcon');

toggleBtn.addEventListener('click', () => {
  const isHidden = passInput.type === 'password';
  passInput.type = isHidden ? 'text' : 'password';
  eyeIcon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
});

// ── Icon highlight on field focus ─────────────
document.getElementById('usuario').addEventListener('focus',   () => document.getElementById('icon-user').style.color = 'var(--naranja)');
document.getElementById('usuario').addEventListener('blur',    () => document.getElementById('icon-user').style.color = '');
document.getElementById('contrasena').addEventListener('focus',() => document.getElementById('icon-pass').style.color = 'var(--naranja)');
document.getElementById('contrasena').addEventListener('blur', () => document.getElementById('icon-pass').style.color = '');

// ── Loading state on submit ────────────────────
document.getElementById('loginForm').addEventListener('submit', function (e) {
  const u = document.getElementById('usuario').value.trim();
  const p = document.getElementById('contrasena').value.trim();
  if (!u || !p) return; // let HTML5 handle blanks
  const btn = document.getElementById('submitBtn');
  btn.classList.add('loading');
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Verificando…';
});
</script>

</body>
</html>
