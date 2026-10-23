<?php
// =============================================
//  packages_crud.php — Tommy's Bar
//  Admin CRUD for PRODUCTOS (Packages)
// =============================================
require_once __DIR__ . '/../config/db_connect.php';

header('Content-Type: application/json');

$db     = get_db();
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// Route by method
switch ($method) {

    // ── LIST ALL ──────────────────────────────
    case 'GET':
        $stmt = $db->query("SELECT Producto_Id, Nombre_Snack, Precio FROM PRODUCTOS ORDER BY Nombre_Snack");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    // ── CREATE ────────────────────────────────
    case 'POST':
        if (empty($input['nombre']) || !isset($input['precio'])) {
            echo json_encode(['success' => false, 'error' => 'nombre y precio requeridos.']);
            break;
        }
        $stmt = $db->prepare("INSERT INTO PRODUCTOS (Nombre_Snack, Precio) VALUES (:nombre, :precio)");
        $stmt->execute([':nombre' => trim($input['nombre']), ':precio' => (float) $input['precio']]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    // ── UPDATE ────────────────────────────────
    case 'PUT':
        if (empty($input['id']) || empty($input['nombre']) || !isset($input['precio'])) {
            echo json_encode(['success' => false, 'error' => 'id, nombre y precio requeridos.']);
            break;
        }
        $stmt = $db->prepare("UPDATE PRODUCTOS SET Nombre_Snack = :nombre, Precio = :precio WHERE Producto_Id = :id");
        $stmt->execute([
            ':nombre' => trim($input['nombre']),
            ':precio' => (float) $input['precio'],
            ':id'     => (int)   $input['id'],
        ]);
        echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
        break;

    // ── DELETE ────────────────────────────────
    case 'DELETE':
        $id = (int) ($input['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'id requerido.']); break; }
        // Guard: don't delete if used in orders
        $chk = $db->prepare("SELECT COUNT(*) FROM DETALLE_PEDIDO WHERE PRODUCTOS_Producto_Id = :id");
        $chk->execute([':id' => $id]);
        if ($chk->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'No se puede eliminar: el paquete tiene pedidos asociados.']);
            break;
        }
        $stmt = $db->prepare("DELETE FROM PRODUCTOS WHERE Producto_Id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
}
