<?php
// =============================================
//  insert_logic.php — Tommy's Bar
//  Handles POST for new event (multi-table TX)
// =============================================
require_once __DIR__ . '/../config/db_connect.php';

/**
 * Creates a new event inside a single SQL transaction.
 * Inserts: DIRECCIÓN → clientes (if new) → pedidos → DETALLE_PEDIDO
 *
 * @return array ['success'=>bool, 'pedido_id'=>int|null, 'error'=>string|null]
 */
function insert_new_event(array $data): array {
    $db = get_db();

    // ── Validate required fields ──────────────
    $required = [
        'nombre_cliente','apellido_cliente','telefono_cliente',
        'fecha_entrega','hora_inicio','hora_fin',
        'calle','ciudad','colonia',
        'producto_id','cantidad','anticipo'
    ];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'error' => "Campo requerido faltante: $field"];
        }
    }

    $anticipo = (float) $data['anticipo'];
    if ($anticipo < 500) {
        return ['success' => false, 'error' => 'El anticipo mínimo es $500.'];
    }

    try {
        $db->beginTransaction();

        // ── 1. INSERT DIRECCIÓN ──────────────────
        $stmt = $db->prepare("
            INSERT INTO DIRECCIÓN (Calle, Colonia, Ciudad, Numero_Postal)
            VALUES (:calle, :colonia, :ciudad, :cp)
        ");
        $stmt->execute([
            ':calle'   => trim($data['calle']),
            ':colonia' => trim($data['colonia'] ?? ''),
            ':ciudad'  => trim($data['ciudad']),
            ':cp'      => trim($data['cp'] ?? ''),
        ]);
        $direccion_id = (int) $db->lastInsertId();

        // ── 2. INSERT or REUSE CLIENTE ───────────
        // Check by phone to avoid duplicates
        $cliente_id = null;
        if (!empty($data['telefono_cliente'])) {
            $chk = $db->prepare("
                SELECT Cliente_Id FROM clientes WHERE Telefono_Cliente = :tel LIMIT 1
            ");
            $chk->execute([':tel' => trim($data['telefono_cliente'])]);
            $row = $chk->fetch();
            if ($row) $cliente_id = (int) $row['Cliente_Id'];
        }

        if (!$cliente_id) {
            $stmt2 = $db->prepare("
                INSERT INTO clientes (Nombre_Cliente, PrimerApellido_Cliente, SegundoApellido_Cliente, Telefono_Cliente)
                VALUES (:nombre, :ap1, :ap2, :tel)
            ");
            $stmt2->execute([
                ':nombre' => trim($data['nombre_cliente']),
                ':ap1'    => trim($data['apellido_cliente']),
                ':ap2'    => trim($data['segundo_apellido'] ?? ''),
                ':tel'    => trim($data['telefono_cliente']),
            ]);
            $cliente_id = (int) $db->lastInsertId();
        }

        // ── 3. Compute total from product price × qty ──
        $prod_stmt = $db->prepare("SELECT Precio FROM PRODUCTOS WHERE Producto_Id = :id");
        $prod_stmt->execute([':id' => (int) $data['producto_id']]);
        $producto = $prod_stmt->fetch();
        if (!$producto) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Producto no encontrado.'];
        }

        $cantidad        = (int) $data['cantidad'];
        $carga_adicional = (float) ($data['carga_adicional'] ?? 0);
        $total           = ($producto['Precio'] * $cantidad) + $carga_adicional;
        $saldo_pendiente = $total - $anticipo;

        // Determine payment state
        if ($saldo_pendiente <= 0) {
            $estado = 'Pagado Total';
        } elseif ($anticipo >= 500) {
            $estado = 'Anticipo';
        } else {
            $estado = 'Pendiente';
        }

        // ── 4. INSERT PEDIDO ─────────────────────
        $stmt3 = $db->prepare("
            INSERT INTO pedidos (
                Fecha_Confirmacion, Fecha_Entrega, Hora_Inicio, Hora_Fin,
                Carga_Adicional, Anticipo, Total, Estado,
                CLIENTES_Cliente_Id, EMPLEADOS_Empleado_Id, direccion_Id
            ) VALUES (
                CURDATE(), :fecha_entrega, :hora_inicio, :hora_fin,
                :carga, :anticipo, :total, :estado,
                :cliente_id, :empleado_id, :direccion_id
            )
        ");
        $stmt3->execute([
            ':fecha_entrega' => $data['fecha_entrega'],
            ':hora_inicio'   => $data['hora_inicio'],
            ':hora_fin'      => $data['hora_fin'],
            ':carga'         => $carga_adicional,
            ':anticipo'      => $anticipo,
            ':total'         => $total,
            ':estado'        => $estado,
            ':cliente_id'    => $cliente_id,
            ':empleado_id'   => !empty($data['empleado_id']) ? (int)$data['empleado_id'] : null,
            ':direccion_id'  => $direccion_id,
        ]);
        $pedido_id = (int) $db->lastInsertId();

        // ── 5. INSERT DETALLE_PEDIDO (M:N) ───────
        $stmt4 = $db->prepare("
            INSERT INTO DETALLE_PEDIDO (pedidos_Pedido_Id, PRODUCTOS_Producto_Id, Cantidad)
            VALUES (:pedido_id, :producto_id, :cantidad)
        ");
        $stmt4->execute([
            ':pedido_id'   => $pedido_id,
            ':producto_id' => (int) $data['producto_id'],
            ':cantidad'    => $cantidad,
        ]);

        // Support multiple extra products (optional)
        if (!empty($data['extra_productos']) && is_array($data['extra_productos'])) {
            foreach ($data['extra_productos'] as $extra) {
                if (empty($extra['producto_id'])) continue;
                $stmt4->execute([
                    ':pedido_id'   => $pedido_id,
                    ':producto_id' => (int) $extra['producto_id'],
                    ':cantidad'    => (int) ($extra['cantidad'] ?? 1),
                ]);
            }
        }

        $db->commit();
        return ['success' => true, 'pedido_id' => $pedido_id];

    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        return ['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()];
    }
}


// ── Handle AJAX / direct POST ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Accept JSON body or form data
    $input = json_decode(file_get_contents('php://input'), true);
    $data  = $input ?? $_POST;

    $result = insert_new_event($data);
    echo json_encode($result);
    exit;
}
