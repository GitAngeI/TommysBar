<?php
// =============================================
//  dashboard.php — Tommy's Bar  (Main view)
// =============================================
require_once __DIR__ . '/../config/db_connect.php';

$db = get_db();

// ── KPI 1: Events this week ───────────────────
$kpi_events = $db->query("
    SELECT COUNT(*) FROM pedidos
    WHERE Fecha_Entrega BETWEEN DATE_FORMAT(NOW(), '%Y-%m-%d') 
                             AND DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-%d'), INTERVAL 6 DAY)
")->fetchColumn();

// ── KPI 2: Projected revenue this month ───────
$kpi_revenue = $db->query("
    SELECT COALESCE(SUM(Total), 0) FROM pedidos
    WHERE MONTH(Fecha_Entrega) = MONTH(CURDATE())
      AND YEAR(Fecha_Entrega)  = YEAR(CURDATE())
")->fetchColumn();

// ── KPI 3: Pending balances (Total - Anticipo) ─
$kpi_pending = $db->query("
    SELECT COALESCE(SUM(Total - Anticipo), 0) FROM pedidos
    WHERE Estado != 'Pagado Total'
")->fetchColumn();

// ── AGENDA: upcoming orders, chronological ────
$agenda = $db->query("
    SELECT
        p.Pedido_Id,
        p.Fecha_Entrega,
        p.Hora_Inicio,
        p.Hora_Fin,
        p.Anticipo,
        p.Total,
        p.Estado,
        CONCAT(c.Nombre_Cliente, ' ', c.PrimerApellido_Cliente) AS Cliente,
        c.Telefono_Cliente,
        d.Ciudad AS Direccion,
        GROUP_CONCAT(pr.Nombre_Snack, ' (x', dp.Cantidad, ')' SEPARATOR ', ') AS Paquetes
    FROM pedidos p
    INNER JOIN clientes c       ON c.Cliente_Id    = p.clientes_Cliente_Id
    INNER JOIN direccion d      ON d.direccion_Id  = p.direccion_Id
    INNER JOIN DETALLE_PEDIDO dp ON dp.pedidos_Pedido_Id = p.Pedido_Id
    INNER JOIN productos pr     ON pr.Producto_Id  = dp.productos_Producto_Id
    WHERE p.Fecha_Entrega >= CURDATE()
    GROUP BY p.Pedido_Id
    ORDER BY p.Fecha_Entrega ASC, p.Hora_Inicio ASC
    LIMIT 50
")->fetchAll();

// ── Products list for the form ─────────────────
$productos = $db->query("SELECT Producto_Id, Nombre_Snack, Precio FROM productos ORDER BY Nombre_Snack")->fetchAll();

// ── Employees list for the form ────────────────
$empleados = $db->query("SELECT Empleado_Id, CONCAT(Nombre_Empleado,' ',PrimerApellido_Empleado) AS Nombre FROM empleados ORDER BY Nombre_Empleado")->fetchAll();

// ── Helper: format fecha relative ─────────────
function fmt_fecha(string $fecha): string {
    $ts    = strtotime($fecha);
    $today = strtotime(date('Y-m-d'));
    $diff  = ($ts - $today) / 86400;
    if ($diff == 0) return 'Hoy';
    if ($diff == 1) return 'Mañana';
    if ($diff < 7)  return date('l', $ts); // weekday
    return date('d M Y', $ts);
}

function fmt_money(float $v): string { return '$' . number_format($v, 2); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tommy's Bar — Gestor de Eventos</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Nunito:wght@400;600;700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
/* ── CSS VARIABLES ───────────────────────── */
:root {
  --naranja:  #F26419;
  --amarillo: #F7B731;
  --cafe:     #3D1C02;
  --cafe-md:  #6B3A1F;
  --cafe-lt:  #A0522D;
  --blanco:   #FFF8F0;
  --gris-lt:  #F9EFE4;
  --sidebar-w: 240px;
  --radius:   12px;
}

/* ── RESET / BASE ────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }
body {
  font-family: 'Nunito', sans-serif;
  background: var(--gris-lt);
  color: var(--cafe);
  min-height: 100vh;
  overflow-x: hidden;
}

/* ── SIDEBAR ─────────────────────────────── */
#sidebar {
  position: fixed; top: 0; left: 0;
  width: var(--sidebar-w); height: 100vh;
  background: var(--cafe);
  display: flex; flex-direction: column;
  z-index: 1000;
  transition: transform .3s;
}
.sidebar-logo {
  padding: 24px 20px 16px;
  border-bottom: 1px solid rgba(255,255,255,.1);
}

/*-----------+-+--+-+-+- */
.sidebar-logo img {
  display: block;
  margin: 0 auto; /* Centra la imagen si es necesario */
  transition: transform 0.3s ease;
}
.sidebar-logo img:hover {
  transform: scale(1.05); /* Efecto visual al pasar el mouse */
}
/*-----------+-+--+-+-+- */
.sidebar-logo .brand-name {
  font-family: 'Bebas Neue', cursive;
  font-size: 1.7rem;
  color: var(--amarillo);
  letter-spacing: 2px;
  line-height: 1;
}
.sidebar-logo .brand-sub {
  font-size: .72rem;
  color: rgba(255,255,255,.55);
  letter-spacing: 1.5px;
  text-transform: uppercase;
}
.sidebar-nav { padding: 16px 12px; flex: 1; }
.sidebar-nav a {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; border-radius: 8px;
  color: rgba(255,255,255,.75);
  font-weight: 600; font-size: .88rem;
  text-decoration: none;
  transition: background .2s, color .2s;
  margin-bottom: 4px;
}
.sidebar-nav a:hover,
.sidebar-nav a.active {
  background: var(--naranja);
  color: #fff;
}
.sidebar-nav a i { font-size: 1.1rem; }

/* ── TOPBAR ──────────────────────────────── */
#topbar {
  position: fixed; top: 0; left: var(--sidebar-w); right: 0;
  height: 64px; z-index: 999;
  background: var(--cafe);
  display: flex; align-items: center;
  padding: 0 28px;
  gap: 16px;
  box-shadow: 0 2px 12px rgba(0,0,0,.18);
}
#topbar .page-title {
  font-family: 'Bebas Neue', cursive;
  font-size: 1.5rem;
  color: var(--blanco);
  letter-spacing: 1.5px;
  flex: 1;
}
.top-search {
  position: relative;
  width: 280px;
}
.top-search input {
  width: 100%; border-radius: 25px;
  border: none; padding: 7px 16px 7px 40px;
  font-family: 'Nunito', sans-serif;
  font-size: .85rem;
  background: rgba(255,255,255,.12);
  color: #fff;
  outline: none;
}
.top-search input::placeholder { color: rgba(255,255,255,.45); }
.top-search i {
  position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
  color: rgba(255,255,255,.5); font-size: .9rem;
}
.top-date {
  font-size: .8rem; color: rgba(255,255,255,.65);
  display: flex; align-items: center; gap: 6px;
}
.top-user {
  display: flex; align-items: center; gap: 8px;
  font-size: .84rem; color: rgba(255,255,255,.9); font-weight: 700;
}
.avatar {
  width: 34px; height: 34px; border-radius: 50%;
  background: var(--naranja);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: .95rem;
}

/* ── MAIN CONTENT ────────────────────────── */
#main {
  margin-left: var(--sidebar-w);
  padding: 80px 28px 40px;
  min-height: 100vh;
}

/* ── KPI CARDS ───────────────────────────── */
.kpi-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; margin-bottom: 28px; }
.kpi-card {
  background: #fff;
  border-radius: var(--radius);
  padding: 20px 22px;
  display: flex; align-items: center; gap: 16px;
  box-shadow: 0 2px 12px rgba(61,28,2,.08);
  border-left: 5px solid transparent;
  transition: transform .2s;
}
.kpi-card:hover { transform: translateY(-3px); }
.kpi-card:nth-child(1) { border-color: var(--naranja); }
.kpi-card:nth-child(2) { border-color: var(--amarillo); }
.kpi-card:nth-child(3) { border-color: #2ECC71; }
.kpi-icon {
  width: 52px; height: 52px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; flex-shrink: 0;
}
.kpi-card:nth-child(1) .kpi-icon { background: #FFF0E6; color: var(--naranja); }
.kpi-card:nth-child(2) .kpi-icon { background: #FFFBE6; color: #D4A017; }
.kpi-card:nth-child(3) .kpi-icon { background: #E6FAF0; color: #2ECC71; }
.kpi-label { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: var(--cafe-lt); }
.kpi-value { font-family: 'Bebas Neue', cursive; font-size: 2.2rem; color: var(--cafe); line-height: 1.1; }
.kpi-sub   { font-size: .72rem; color: var(--cafe-lt); margin-top: 2px; }

/* ── SECTION CARD ────────────────────────── */
.section-card {
  background: #fff;
  border-radius: var(--radius);
  box-shadow: 0 2px 12px rgba(61,28,2,.07);
  overflow: hidden;
  margin-bottom: 28px;
}
.section-header {
  padding: 16px 22px;
  border-bottom: 2px solid var(--gris-lt);
  display: flex; align-items: center; gap: 10px;
}
.section-header h2 {
  font-family: 'Bebas Neue', cursive;
  font-size: 1.3rem; letter-spacing: 1px;
  color: var(--cafe); margin: 0; flex: 1;
}

/* ── AGENDA TABLE ────────────────────────── */
.agenda-table { width: 100%; border-collapse: collapse; }
.agenda-table thead th {
  background: var(--cafe); color: var(--blanco);
  font-size: .78rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .6px; padding: 11px 16px;
  white-space: nowrap;
}
.agenda-table tbody tr {
  border-bottom: 1px solid #F0E8DF;
  transition: background .15s;
}
.agenda-table tbody tr:hover { background: #FFF5EE; }
.agenda-table td { padding: 13px 16px; font-size: .88rem; vertical-align: middle; }
.td-fecha strong { display: block; font-size: .92rem; font-weight: 900; color: var(--cafe); }
.td-fecha small  { color: var(--cafe-lt); font-size: .78rem; }
.td-cliente strong { font-weight: 800; }
.td-cliente small { color: var(--cafe-lt); font-size: .78rem; display: block; }
.td-paquete { max-width: 220px; font-weight: 600; color: var(--cafe-md); }

/* ── STATUS BADGES ───────────────────────── */
.badge-estado {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 10px; border-radius: 20px;
  font-size: .78rem; font-weight: 700;
}
.badge-pagado   { background: #E6FAF0; color: #1E8449; }
.badge-anticipo { background: #FFF9E0; color: #9A6800; }
.badge-pendiente{ background: #FFF0F0; color: #C0392B; }

/* ── BUTTONS ─────────────────────────────── */
.btn-tommy {
  background: var(--naranja); color: #fff;
  border: none; border-radius: 8px;
  font-family: 'Nunito', sans-serif; font-weight: 700;
  padding: 8px 18px; font-size: .85rem;
  cursor: pointer; transition: background .2s, transform .1s;
  display: inline-flex; align-items: center; gap: 6px;
}
.btn-tommy:hover  { background: #D4561A; color: #fff; }
.btn-tommy:active { transform: scale(.97); }
.btn-ticket {
  background: transparent;
  border: 1.5px solid var(--cafe-lt);
  border-radius: 6px; color: var(--cafe);
  font-size: .78rem; font-weight: 700;
  padding: 5px 12px; cursor: pointer;
  transition: all .15s;
}
.btn-ticket:hover { border-color: var(--naranja); color: var(--naranja); }

/* ── NEW EVENT FORM ──────────────────────── */
.form-label-tommy { font-weight: 700; font-size: .84rem; color: var(--cafe-md); margin-bottom: 4px; }
.form-control-tommy {
  border: 1.5px solid #DDD0C5;
  border-radius: 8px;
  font-family: 'Nunito', sans-serif;
  font-size: .9rem;
  padding: 9px 14px;
  transition: border-color .2s;
}
.form-control-tommy:focus {
  border-color: var(--naranja);
  box-shadow: 0 0 0 3px rgba(242,100,25,.12);
  outline: none;
}
.form-section-title {
  font-family: 'Bebas Neue', cursive;
  font-size: 1.1rem; letter-spacing: 1px;
  color: var(--naranja); margin: 20px 0 10px;
  padding-bottom: 4px;
  border-bottom: 2px dashed #F7C9A8;
}

/* ── MODAL OVERRIDES ─────────────────────── */
.modal-header-tommy {
  background: var(--cafe);
  color: #fff;
  border-radius: calc(var(--radius) - 1px) calc(var(--radius) - 1px) 0 0;
}
.modal-header-tommy .modal-title {
  font-family: 'Bebas Neue', cursive;
  font-size: 1.4rem; letter-spacing: 1.5px;
}
.modal-content { border-radius: var(--radius); overflow: hidden; }

/* ── PACKAGES ADMIN TABLE ────────────────── */
.pkg-table { width: 100%; border-collapse: collapse; }
.pkg-table th { background: var(--cafe); color: #fff; padding: 10px 14px; font-size: .8rem; text-transform: uppercase; letter-spacing: .5px; }
.pkg-table td { padding: 10px 14px; border-bottom: 1px solid #EEE5DC; font-size: .88rem; }
.pkg-table tr:hover td { background: #FFF5EE; }

/* ── TOAST ───────────────────────────────── */
.toast-tommy {
  position: fixed; bottom: 28px; right: 28px;
  background: var(--cafe); color: #fff;
  padding: 14px 22px; border-radius: 10px;
  font-weight: 700; font-size: .9rem;
  box-shadow: 0 6px 24px rgba(0,0,0,.25);
  z-index: 9999;
  display: none; align-items: center; gap: 10px;
  transition: opacity .3s;
}
.toast-tommy.show { display: flex; }
.toast-tommy.success { border-left: 4px solid #2ECC71; }
.toast-tommy.error   { border-left: 4px solid #E74C3C; }

/* ── RESPONSIVE ──────────────────────────── */
@media (max-width: 768px) {
  :root { --sidebar-w: 0px; }
  #sidebar { transform: translateX(-240px); }
  #sidebar.open { transform: translateX(0); width: 240px; }
  .kpi-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- ── SIDEBAR ─────────────────────────────────── -->
<nav id="sidebar">
  <!--<div class="sidebar-logo">
    <div style="font-size:1.8rem; margin-bottom:4px;">🍹</div>
    <div class="brand-name">Tommy's Bar</div>
    <div class="brand-sub">Gestión de Eventos</div>
  </div> -->

<div class="sidebar-logo">
  <a href="dashboard.php" style="text-decoration: none;">
    <img src="img/tommys_bar_logo.png" alt="Logo Tommy's Bar" class="img-fluid mb-2" style="max-height: 80px;">
    <div class="brand-name">Tommy's Bar</div>
    <div class="brand-sub">Gestión de Eventos</div>
  </a>
</div>


  <div class="sidebar-nav">
    <a href="dashboard.php" class="active"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
    <a href="#" data-bs-toggle="modal" data-bs-target="#modalNuevoEvento">
      <i class="bi bi-plus-circle-fill"></i> Agendar Nuevo Evento
    </a>
    <a href="#" data-bs-toggle="modal" data-bs-target="#modalPaquetes">
      <i class="bi bi-box-seam-fill"></i> Gestión de Paquetes
    </a>
    <a href="#"><i class="bi bi-people-fill"></i> Directorio de clientes</a>
    <a href="#"><i class="bi bi-graph-up-arrow"></i> Reportes</a>
    <a href="#"><i class="bi bi-gear-fill"></i> Configuración</a>
  </div>
  <div style="padding:16px 20px; border-top:1px solid rgba(255,255,255,.1);">
    <small style="color:rgba(255,255,255,.4); font-size:.72rem;">v1.0 · Tommy's Bar 2026</small>
  </div>
  <a href="logout.php" onclick="return confirm('¿Cerrar sesión?')" style="color:rgba(255,100,100,.7);">
  <i class="bi bi-box-arrow-left"></i> Cerrar Sesión
    </a>
</nav>



<!-- ── TOPBAR ───────────────────────────────────── -->
<header id="topbar">
  <div class="page-title">Gestor de Eventos</div>
  <div class="top-search">
    <i class="bi bi-search"></i>
    <input type="text" id="searchInput" placeholder="Buscar cliente, pedido…">
  </div>
  <div class="top-date"><i class="bi bi-calendar3"></i> Hoy: <?= date('d M Y') ?></div>
  <div class="top-user">
    <div class="avatar"><i class="bi bi-person-fill"></i></div>
    <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>
    <a href="logout.php" onclick="return confirm('¿Cerrar sesión?')">
      <i class="bi bi-box-arrow-right"></i>
  </a>
  </div>
</header>

<!-- ── MAIN ─────────────────────────────────────── -->
<main id="main">

  <!-- KPIs -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-icon"><i class="bi bi-calendar-event-fill"></i></div>
      <div>
        <div class="kpi-label">Eventos esta Semana</div>
        <div class="kpi-value"><?= (int)$kpi_events ?> <span style="font-size:1rem;font-family:'Nunito',sans-serif;font-weight:700;">agendados</span></div>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
      <div>
        <div class="kpi-label">Ingresos Proyectados (Mes)</div>
        <div class="kpi-value"><?= fmt_money((float)$kpi_revenue) ?></div>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon"><i class="bi bi-wallet2"></i></div>
      <div>
        <div class="kpi-label">Saldos Pendientes de Cobro</div>
        <div class="kpi-value"><?= fmt_money((float)$kpi_pending) ?></div>
        <div class="kpi-sub">(Total − Anticipos)</div>
      </div>
    </div>
  </div>

  <!-- Agenda Table -->
  <div class="section-card">
    <div class="section-header">
      <i class="bi bi-calendar2-week-fill" style="color:var(--naranja);font-size:1.2rem;"></i>
      <h2>Agenda de Eventos Próximos</h2>
      <button class="btn-tommy" data-bs-toggle="modal" data-bs-target="#modalNuevoEvento">
        <i class="bi bi-plus-lg"></i> Nuevo Evento
      </button>
    </div>
    <div style="overflow-x:auto;">
      <table class="agenda-table" id="agendaTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Fecha y Hora</th>
            <th>Cliente</th>
            <th>Paquete Contratado</th>
            <th>direccion</th>
            <th>Estado de Pago</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($agenda)): ?>
          <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--cafe-lt);">
            <i class="bi bi-calendar-x" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
            No hay eventos próximos agendados.
          </td></tr>
          <?php else: ?>
          <?php foreach ($agenda as $i => $row):
            $saldo = (float)$row['Total'] - (float)$row['Anticipo'];
            if ($row['Estado'] === 'Pagado Total') {
              $badge = '<span class="badge-estado badge-pagado"><i class="bi bi-check-circle-fill"></i> Pagado Total</span>';
            } elseif ($row['Estado'] === 'Anticipo') {
              $badge = '<span class="badge-estado badge-anticipo"><i class="bi bi-circle-half"></i> Anticipo ('.fmt_money((float)$row['Anticipo']).')</span>';
            } else {
              $badge = '<span class="badge-estado badge-pendiente"><i class="bi bi-exclamation-circle-fill"></i> Faltan '.fmt_money($saldo).'</span>';
            }
          ?>
          <tr>
            <td style="color:var(--cafe-lt);font-size:.8rem;font-weight:700;">#<?= $row['Pedido_Id'] ?></td>
            <td class="td-fecha">
              <strong><?= htmlspecialchars(fmt_fecha($row['Fecha_Entrega'])) ?></strong>
              <small><?= htmlspecialchars(substr($row['Hora_Inicio'],0,5)) ?> – <?= htmlspecialchars(substr($row['Hora_Fin'],0,5)) ?></small>
            </td>
            <td class="td-cliente">
              <strong><?= htmlspecialchars($row['Cliente']) ?></strong>
              <small><i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($row['Telefono_Cliente'] ?? '—') ?></small>
            </td>
            <td class="td-paquete"><?= htmlspecialchars($row['Paquetes']) ?></td>
            <td><?= htmlspecialchars($row['Direccion']) ?></td>
            <td><?= $badge ?></td>
            <td>
              <button class="btn-ticket" onclick="verTicket(<?= $row['Pedido_Id'] ?>)">
                <i class="bi bi-receipt"></i> Ver Ticket
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main><!-- /main -->


<!-- ══════════════════════════════════════════════ -->
<!--  MODAL: NUEVO EVENTO                          -->
<!-- ══════════════════════════════════════════════ -->
<div class="modal fade" id="modalNuevoEvento" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header modal-header-tommy">
        <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2"></i> Agendar Nuevo Evento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:24px;">

        <div class="form-section-title">👤 Datos del Cliente</div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-tommy">Nombre *</label>
            <input type="text" id="f_nombre" class="form-control form-control-tommy" placeholder="Nombre">
          </div>
          <div class="col-md-4">
            <label class="form-label-tommy">Primer Apellido *</label>
            <input type="text" id="f_apellido" class="form-control form-control-tommy" placeholder="Apellido">
          </div>
          <div class="col-md-4">
            <label class="form-label-tommy">Segundo Apellido</label>
            <input type="text" id="f_segundo_apellido" class="form-control form-control-tommy" placeholder="Opcional">
          </div>
          <div class="col-md-4">
            <label class="form-label-tommy">Teléfono *</label>
            <input type="tel" id="f_telefono" class="form-control form-control-tommy">
          </div>
        </div>

        <div class="form-section-title">📦 Paquete y Detalles del Evento</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label-tommy">Paquete *</label>
            <select id="f_producto" class="form-control form-control-tommy" onchange="calcTotal()">
              <option value="">— Seleccionar paquete —</option>
              <?php foreach ($productos as $p): ?>
              <option value="<?= $p['Producto_Id'] ?>" data-precio="<?= $p['Precio'] ?>">
                <?= htmlspecialchars($p['Nombre_Snack']) ?> — $<?= number_format($p['Precio'],2) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label-tommy">Cantidad *</label>
            <input type="number" id="f_cantidad" class="form-control form-control-tommy" value="1" min="1" onchange="calcTotal()">
          </div>
          <div class="col-md-4">
            <label class="form-label-tommy">Carga Adicional</label>
            <input type="number" id="f_carga" class="form-control form-control-tommy" value="0" min="0" step="50" onchange="calcTotal()">
          </div>
          <div class="col-md-3">
            <label class="form-label-tommy">Fecha del Evento *</label>
            <input type="date" id="f_fecha" class="form-control form-control-tommy" min="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label-tommy">Hora Inicio *</label>
            <input type="time" id="f_hora_ini" class="form-control form-control-tommy">
          </div>
          <div class="col-md-3">
            <label class="form-label-tommy">Hora Fin *</label>
            <input type="time" id="f_hora_fin" class="form-control form-control-tommy">
          </div>
          <div class="col-md-3">
            <label class="form-label-tommy">Empleado Asignado</label>
            <select id="f_empleado" class="form-control form-control-tommy">
              <option value="">— Sin asignar —</option>
              <?php foreach ($empleados as $e): ?>
              <option value="<?= $e['Empleado_Id'] ?>"><?= htmlspecialchars($e['Nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-section-title">📍 direccion del Evento</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label-tommy">Calle y Número *</label>
            <input type="text" id="f_calle" class="form-control form-control-tommy" placeholder="Ej. Av. Constitución 1234">
          </div>
          <div class="col-md-3">
            <label class="form-label-tommy">Colonia</label>
            <input type="text" id="f_colonia" class="form-control form-control-tommy" placeholder="Colonia">
          </div>
          <div class="col-md-3">
            <label class="form-label-tommy">Ciudad *</label>
            <input type="text" id="f_ciudad" class="form-control form-control-tommy" placeholder="Monterrey">
          </div>
        </div>

        <div class="form-section-title">💰 Pago</div>
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label-tommy">Anticipo (mín. $500) *</label>
            <input type="number" id="f_anticipo" class="form-control form-control-tommy" value="500" min="500" step="100" onchange="calcTotal()">
          </div>
          <div class="col-md-8">
            <div id="totalBox" style="background:var(--gris-lt);border-radius:10px;padding:14px 18px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
              <div><div style="font-size:.75rem;font-weight:700;color:var(--cafe-lt);text-transform:uppercase;">Subtotal</div>
                   <div id="dispSubtotal" style="font-size:1.3rem;font-weight:900;color:var(--cafe);">$0.00</div></div>
              <div><div style="font-size:.75rem;font-weight:700;color:var(--cafe-lt);text-transform:uppercase;">Anticipo</div>
                   <div id="dispAnticipo" style="font-size:1.3rem;font-weight:900;color:var(--amarillo);">$500.00</div></div>
              <div><div style="font-size:.75rem;font-weight:700;color:var(--cafe-lt);text-transform:uppercase;">Saldo</div>
                   <div id="dispSaldo" style="font-size:1.3rem;font-weight:900;color:var(--naranja);">—</div></div>
            </div>
          </div>
        </div>

      </div><!-- /modal-body -->
      <div class="modal-footer" style="border-top:2px solid var(--gris-lt);">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn-tommy" onclick="submitNuevoEvento()">
          <i class="bi bi-calendar-check-fill"></i> Confirmar Evento
        </button>
      </div>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════ -->
<!--  MODAL: GESTIÓN DE PAQUETES                  -->
<!-- ══════════════════════════════════════════════ -->
<div class="modal fade" id="modalPaquetes" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header modal-header-tommy">
        <h5 class="modal-title"><i class="bi bi-box-seam-fill me-2"></i> Gestión de Paquetes</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:24px;">

        <!-- Add new package row -->
        <div class="row g-2 align-items-end mb-4" style="background:var(--gris-lt);padding:16px;border-radius:10px;">
          <div class="col-md-6">
            <label class="form-label-tommy">Nombre del Paquete</label>
            <input type="text" id="pkg_nombre" class="form-control form-control-tommy" placeholder="Ej. Churros (70 pax)">
          </div>
          <div class="col-md-3">
            <label class="form-label-tommy">Precio ($)</label>
            <input type="number" id="pkg_precio" class="form-control form-control-tommy" placeholder="0.00" min="0" step="50">
          </div>
          <div class="col-md-3">
            <button class="btn-tommy w-100" onclick="pkgCreate()">
              <i class="bi bi-plus-lg"></i> Agregar
            </button>
          </div>
        </div>

        <!-- Packages table -->
        <div style="overflow-x:auto;">
          <table class="pkg-table" id="pkgTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Paquete</th>
                <th>Precio</th>
                <th style="width:140px;">Acciones</th>
              </tr>
            </thead>
            <tbody id="pkgTableBody">
              <?php foreach ($productos as $p): ?>
              <tr id="pkg-row-<?= $p['Producto_Id'] ?>">
                <td style="color:var(--cafe-lt);font-weight:700;">#<?= $p['Producto_Id'] ?></td>
                <td>
                  <span class="pkg-display"><?= htmlspecialchars($p['Nombre_Snack']) ?></span>
                  <input type="text" class="form-control form-control-tommy pkg-edit" style="display:none;" value="<?= htmlspecialchars($p['Nombre_Snack']) ?>">
                </td>
                <td>
                  <span class="pkg-display">$<?= number_format($p['Precio'],2) ?></span>
                  <input type="number" class="form-control form-control-tommy pkg-edit" style="display:none;" value="<?= $p['Precio'] ?>" step="50">
                </td>
                <td>
                  <button class="btn-ticket me-1" onclick="pkgEdit(<?= $p['Producto_Id'] ?>)" title="Editar">
                    <i class="bi bi-pencil-fill"></i>
                  </button>
                  <button class="btn-ticket" onclick="pkgSave(<?= $p['Producto_Id'] ?>)" title="Guardar" style="display:none;" id="pkg-save-<?= $p['Producto_Id'] ?>">
                    <i class="bi bi-check-lg"></i>
                  </button>
                  <button class="btn-ticket" onclick="pkgDelete(<?= $p['Producto_Id'] ?>)" title="Eliminar" style="color:#C0392B;border-color:#C0392B;">
                    <i class="bi bi-trash3-fill"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- ── TOAST NOTIFICATION ───────────────────────── -->
<div id="toastMsg" class="toast-tommy">
  <i class="bi bi-check-circle-fill" id="toastIcon"></i>
  <span id="toastText">Operación exitosa</span>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── TOAST ──────────────────────────────────────────
function showToast(msg, type = 'success') {
  const el = document.getElementById('toastMsg');
  const ic = document.getElementById('toastIcon');
  document.getElementById('toastText').textContent = msg;
  el.className = `toast-tommy show ${type}`;
  ic.className = type === 'success' ? 'bi bi-check-circle-fill' : 'bi bi-x-circle-fill';
  setTimeout(() => { el.className = 'toast-tommy'; }, 4000);
}

// ── SEARCH FILTER ──────────────────────────────────
document.getElementById('searchInput').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#agendaTable tbody tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

// ── TOTAL CALCULATOR ───────────────────────────────
function calcTotal() {
  const sel   = document.getElementById('f_producto');
  const opt   = sel.options[sel.selectedIndex];
  const precio = opt ? parseFloat(opt.dataset.precio || 0) : 0;
  const qty   = parseInt(document.getElementById('f_cantidad').value) || 1;
  const carga = parseFloat(document.getElementById('f_carga').value) || 0;
  const ant   = parseFloat(document.getElementById('f_anticipo').value) || 500;
  const sub   = precio * qty + carga;
  const saldo = sub - ant;

  document.getElementById('dispSubtotal').textContent = '$' + sub.toFixed(2);
  document.getElementById('dispAnticipo').textContent = '$' + ant.toFixed(2);
  document.getElementById('dispSaldo').textContent    = saldo > 0 ? '$' + saldo.toFixed(2) : '✓ Liquidado';
}

// ── SUBMIT NEW EVENT ───────────────────────────────
function submitNuevoEvento() {
  const payload = {
    nombre_cliente:   document.getElementById('f_nombre').value.trim(),
    apellido_cliente: document.getElementById('f_apellido').value.trim(),
    segundo_apellido: document.getElementById('f_segundo_apellido').value.trim(),
    telefono_cliente: document.getElementById('f_telefono').value.trim(),
    producto_id:      document.getElementById('f_producto').value,
    cantidad:         document.getElementById('f_cantidad').value,
    carga_adicional:  document.getElementById('f_carga').value,
    fecha_entrega:    document.getElementById('f_fecha').value,
    hora_inicio:      document.getElementById('f_hora_ini').value,
    hora_fin:         document.getElementById('f_hora_fin').value,
    empleado_id:      document.getElementById('f_empleado').value,
    calle:            document.getElementById('f_calle').value.trim(),
    colonia:          document.getElementById('f_colonia').value.trim(),
    ciudad:           document.getElementById('f_ciudad').value.trim(),
    anticipo:         document.getElementById('f_anticipo').value,
  };

  // Basic front-end validation
  const req = ['nombre_cliente','apellido_cliente','telefono_cliente','producto_id','fecha_entrega','hora_inicio','hora_fin','calle','ciudad'];
  for (const k of req) {
    if (!payload[k]) { showToast(`Campo requerido: ${k.replace(/_/g,' ')}`, 'error'); return; }
  }

  fetch('insert_logic.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      showToast(`✅ Evento #${data.pedido_id} creado correctamente`);
      bootstrap.Modal.getInstance(document.getElementById('modalNuevoEvento')).hide();
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast('Error: ' + data.error, 'error');
    }
  })
  .catch(() => showToast('Error de conexión', 'error'));
}

// ── PACKAGES CRUD ──────────────────────────────────
function pkgCreate() {
  const nombre = document.getElementById('pkg_nombre').value.trim();
  const precio = document.getElementById('pkg_precio').value;
  if (!nombre || !precio) { showToast('Nombre y precio requeridos', 'error'); return; }
  fetch('packages_crud.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ nombre, precio: parseFloat(precio) })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) { showToast('Paquete agregado'); setTimeout(() => location.reload(), 1200); }
    else showToast(d.error, 'error');
  });
}

function pkgEdit(id) {
  const row = document.getElementById(`pkg-row-${id}`);
  row.querySelectorAll('.pkg-display').forEach(el => el.style.display = 'none');
  row.querySelectorAll('.pkg-edit').forEach(el => el.style.display = 'block');
  document.getElementById(`pkg-save-${id}`).style.display = 'inline-block';
}

function pkgSave(id) {
  const row    = document.getElementById(`pkg-row-${id}`);
  const inputs = row.querySelectorAll('.pkg-edit');
  const nombre = inputs[0].value.trim();
  const precio = parseFloat(inputs[1].value);
  if (!nombre || isNaN(precio)) { showToast('Datos inválidos', 'error'); return; }
  fetch('packages_crud.php', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, nombre, precio })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) { showToast('Paquete actualizado'); setTimeout(() => location.reload(), 1200); }
    else showToast(d.error, 'error');
  });
}

function pkgDelete(id) {
  if (!confirm('¿Eliminar este paquete?')) return;
  fetch('packages_crud.php', {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) { document.getElementById(`pkg-row-${id}`)?.remove(); showToast('Paquete eliminado'); }
    else showToast(d.error, 'error');
  });
}

// ── TICKET VIEWER (placeholder) ───────────────────
function verTicket(id) {
  window.open(`ticket.php?id=${id}`, '_blank', 'width=600,height=700');
}
</script>
</body>
</html>
