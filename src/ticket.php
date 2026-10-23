<?php
// =============================================
//  ticket.php — Tommy's Bar
//  Printable ticket/receipt for a single order
// =============================================
require_once __DIR__ . '/../config/db_connect.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); die('Pedido no especificado.'); }

$db = get_db();

$pedido = $db->prepare("
    SELECT p.*,
           CONCAT(c.Nombre_Cliente,' ',c.PrimerApellido_Cliente) AS Cliente,
           c.Telefono_Cliente,
           CONCAT(d.Calle,', ',d.Colonia,', ',d.Ciudad) AS Direccion_Full,
           CONCAT(e.Nombre_Empleado,' ',e.PrimerApellido_Empleado) AS Empleado
    FROM pedidos p
    INNER JOIN clientes c ON c.Cliente_Id   = p.clientes_Cliente_Id
    INNER JOIN direccion d ON d.Direccion_Id = p.direccion_Direccion_Id
    LEFT  JOIN empleados e ON e.Empleado_Id  = p.empleados_Empleado_Id
    WHERE p.Pedido_Id = :id
");
$pedido->execute([':id' => $id]);
$p = $pedido->fetch();
if (!$p) { http_response_code(404); die('Pedido no encontrado.'); }

$detalles = $db->prepare("
    SELECT pr.Nombre_Snack, pr.Precio, dp.Cantidad,
           (pr.Precio * dp.Cantidad) AS Subtotal
    FROM DETALLE_PEDIDO dp
    INNER JOIN PRODUCTOS pr ON pr.Producto_Id = dp.PRODUCTOS_Producto_Id
    WHERE dp.pedidos_Pedido_Id = :id
");
$detalles->execute([':id' => $id]);
$items = $detalles->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ticket #<?= $id ?> — Tommy's Bar</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Nunito:wght@400;700;900&display=swap" rel="stylesheet">
<style>
  body { font-family:'Nunito',sans-serif; background:#f5f0e8; margin:0; padding:20px; }
  .ticket { max-width:520px; margin:auto; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.12); }
  .ticket-header { background:#3D1C02; color:#fff; padding:22px 28px; text-align:center; }
  .ticket-header .logo { font-family:'Bebas Neue',cursive; font-size:2.2rem; color:#F7B731; letter-spacing:3px; }
  .ticket-header .sub { font-size:.8rem; opacity:.6; letter-spacing:2px; text-transform:uppercase; }
  .ticket-body { padding:24px 28px; }
  .row-info { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #EEE; font-size:.9rem; }
  .row-info .lbl { color:#A0522D; font-weight:700; }
  .row-info .val { font-weight:800; text-align:right; }
  h3 { font-family:'Bebas Neue',cursive; font-size:1.1rem; letter-spacing:1px; color:#F26419; margin:20px 0 8px; }
  table { width:100%; border-collapse:collapse; font-size:.88rem; }
  th { background:#F9EFE4; color:#3D1C02; padding:8px 10px; text-align:left; font-size:.78rem; text-transform:uppercase; letter-spacing:.5px; }
  td { padding:8px 10px; border-bottom:1px solid #F0E8DF; }
  .totals { background:#F9EFE4; border-radius:8px; padding:14px 16px; margin-top:16px; }
  .total-row { display:flex; justify-content:space-between; padding:3px 0; font-size:.9rem; }
  .total-row.grand { font-family:'Bebas Neue',cursive; font-size:1.4rem; color:#F26419; padding-top:8px; border-top:2px solid #DDD0C5; margin-top:6px; }
  .status-badge { display:inline-block; padding:5px 14px; border-radius:20px; font-weight:800; font-size:.85rem; margin-top:12px; }
  .pagado   { background:#E6FAF0; color:#1E8449; }
  .anticipo { background:#FFF9E0; color:#9A6800; }
  .pendiente{ background:#FFF0F0; color:#C0392B; }
  .ticket-footer { text-align:center; padding:14px; background:#F9EFE4; font-size:.78rem; color:#A0522D; }
  @media print { body{background:#fff;padding:0;} .ticket{box-shadow:none;} }
</style>
</head>
<body>
<div class="ticket">
  <div class="ticket-header">
    <div class="logo">🍹 Tommy's Bar</div>
    <div class="sub">Comprobante de Evento</div>
    <div style="font-size:1.6rem;font-weight:900;margin-top:6px;">Ticket #<?= $id ?></div>
  </div>

  <div class="ticket-body">
    <h3>📋 Información General</h3>
    <div class="row-info"><span class="lbl">Cliente</span><span class="val"><?= htmlspecialchars($p['Cliente']) ?></span></div>
    <div class="row-info"><span class="lbl">Teléfono</span><span class="val"><?= htmlspecialchars($p['Telefono_Cliente'] ?? '—') ?></span></div>
    <div class="row-info"><span class="lbl">Fecha del Evento</span><span class="val"><?= date('d/m/Y', strtotime($p['Fecha_Entrega'])) ?></span></div>
    <div class="row-info"><span class="lbl">Horario</span><span class="val"><?= substr($p['Hora_Inicio'],0,5) ?> – <?= substr($p['Hora_Fin'],0,5) ?></span></div>
    <div class="row-info"><span class="lbl">direccion</span><span class="val" style="max-width:60%;text-align:right;"><?= htmlspecialchars($p['Direccion_Full']) ?></span></div>
    <?php if ($p['Empleado']): ?>
    <div class="row-info"><span class="lbl">Empleado</span><span class="val"><?= htmlspecialchars($p['Empleado']) ?></span></div>
    <?php endif; ?>
    <div class="row-info"><span class="lbl">Confirmado</span><span class="val"><?= date('d/m/Y', strtotime($p['Fecha_Confirmacion'])) ?></span></div>

    <h3>📦 Paquetes Contratados</h3>
    <table>
      <thead><tr><th>Paquete</th><th style="text-align:right;">Precio</th><th style="text-align:center;">Cant.</th><th style="text-align:right;">Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it): ?>
        <tr>
          <td><?= htmlspecialchars($it['Nombre_Snack']) ?></td>
          <td style="text-align:right;">$<?= number_format($it['Precio'],2) ?></td>
          <td style="text-align:center;"><?= $it['Cantidad'] ?></td>
          <td style="text-align:right;">$<?= number_format($it['Subtotal'],2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="totals">
      <?php if ($p['Carga_Adicional'] > 0): ?>
      <div class="total-row"><span>Carga adicional</span><span>$<?= number_format($p['Carga_Adicional'],2) ?></span></div>
      <?php endif; ?>
      <div class="total-row"><span>Anticipo recibido</span><span style="color:#1E8449;">- $<?= number_format($p['Anticipo'],2) ?></span></div>
      <div class="total-row grand"><span>TOTAL</span><span>$<?= number_format($p['Total'],2) ?></span></div>
      <div class="total-row" style="font-weight:900;color:#C0392B;">
        <span>Saldo pendiente</span>
        <span><?= $p['Total']-$p['Anticipo'] > 0 ? '$'.number_format($p['Total']-$p['Anticipo'],2) : '✓ Liquidado' ?></span>
      </div>
    </div>

    <div style="text-align:center;">
      <?php
      $cls = match($p['Estado']) { 'Pagado Total' => 'pagado', 'Anticipo' => 'anticipo', default => 'pendiente' };
      ?>
      <span class="status-badge <?= $cls ?>"><?= htmlspecialchars($p['Estado']) ?></span>
    </div>
  </div>

  <div class="ticket-footer">
    <button onclick="window.print()" style="background:#F26419;color:#fff;border:none;padding:8px 20px;border-radius:6px;font-weight:700;cursor:pointer;margin-bottom:8px;font-family:'Nunito',sans-serif;">
      🖨️ Imprimir Ticket
    </button><br>
    Tommy's Bar · Monterrey, NL · ¡Gracias por su preferencia!
  </div>
</div>
</body>
</html>
