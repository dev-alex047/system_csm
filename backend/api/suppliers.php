<?php
// Suppliers API endpoint
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

$pdo = getPDO();

$action = $_POST['action'] ?? '';

switch ($action) {
        case 'getProveedores':
            requireLogin();

            try {
                $stmt = $pdo->prepare("SELECT id, name FROM suppliers WHERE eliminado = 0 ORDER BY name ASC, id ASC");
                $stmt->execute();

                $json['status'] = 'ok';
                $json['answer'] = 'Proveedores consultados correctamente';
                $json['results'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $json['code'] = 200;
            } catch (PDOException $e) {
                error_log('getProveedores: ' . $e->getMessage());
                $json['status'] = 'error';
                $json['answer'] = 'No fue posible consultar los proveedores';
                $json['code'] = 500;
            }
            break;

        case 'getProveedor':
            requireRole('admin');

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                $json['status'] = 'error';
                $json['answer'] = 'Identificador de proveedor inválido';
                $json['code'] = 400;
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? AND eliminado = 0 LIMIT 1");
                    $stmt->execute([$id]);
                    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);

                    $json['status'] = $proveedor ? 'ok' : 'error';
                    $json['answer'] = $proveedor ? 'Proveedor encontrado' : 'Proveedor no encontrado';
                    $json['results'] = $proveedor ?: null;
                    $json['code'] = $proveedor ? 200 : 404;
                } catch (PDOException $e) {
                    error_log('getProveedor: ' . $e->getMessage());
                    $json['status'] = 'error';
                    $json['answer'] = 'No fue posible consultar el proveedor';
                    $json['code'] = 500;
                }
            }
            break;

        case 'getProductosProveedor':
            requireLogin();

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $pagina = max(1, (int)($_POST['pagina'] ?? 1));
            $limite = max(1, min(96, (int)($_POST['limite'] ?? 24)));

            if (!$id || $id < 1) {
                $json['status'] = 'error';
                $json['answer'] = 'Identificador de proveedor inválido';
                $json['code'] = 400;
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ? AND eliminado = 0");
                    $stmt->execute([$id]);

                    $total = (int)$stmt->fetchColumn();
                    $paginas = max(1, (int)ceil($total / $limite));
                    $pagina = min($pagina, $paginas);
                    $offset = ($pagina - 1) * $limite;

                    $sql = "SELECT id, code, name, public_price, stock
                            FROM products
                            WHERE supplier_id = :id AND eliminado = 0
                            ORDER BY id DESC
                            LIMIT :limite OFFSET :offset";

                    $stmt = $pdo->prepare($sql);
                    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
                    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                    $stmt->execute();

                    $json['status'] = 'ok';
                    $json['answer'] = 'Productos del proveedor consultados correctamente';
                    $json['results'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $json['total'] = $total;
                    $json['pagina'] = $pagina;
                    $json['limite'] = $limite;
                    $json['paginas'] = $paginas;
                    $json['code'] = 200;
                } catch (PDOException $e) {
                    error_log('getProductosProveedor: ' . $e->getMessage());
                    $json['status'] = 'error';
                    $json['answer'] = 'No fue posible consultar los productos del proveedor';
                    $json['code'] = 500;
                }
            }
            break;

        case 'setProveedor':
            requireRole('admin');

            $campos = [
                'name', 'contact_name', 'phone_number', 'mobile_number',
                'email', 'address', 'company', 'rfc', 'legal_name',
                'postal_code', 'bank', 'interbank_key', 'branch',
                'account_number', 'account_holder', 'categories'
            ];

            $idTexto = trim((string)($_POST['id'] ?? ''));
            $editar = $idTexto !== '';
            $id = $editar ? filter_var($idTexto, FILTER_VALIDATE_INT) : null;
            $datos = [];
            $error = '';

            foreach ($campos as $campo) {
                if (!array_key_exists($campo, $_POST)) continue;

                if (!is_string($_POST[$campo])) {
                    $error = 'Datos inválidos en ' . $campo;
                    break;
                }

                $valor = trim($_POST[$campo]);
                $datos[$campo] = $valor === '' ? null : $valor;
            }

            if ($editar && (!$id || $id < 1)) $error = 'Identificador de proveedor inválido';
            if ((!$editar || array_key_exists('name', $datos)) && empty($datos['name'])) $error = 'El nombre del proveedor es obligatorio';
            if (!empty($datos['email']) && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) $error = 'Correo electrónico inválido';
            if ($editar && !$datos) $error = 'No se enviaron campos para actualizar';

            if ($error !== '') {
                $json['status'] = 'error';
                $json['answer'] = $error;
                $json['code'] = 400;
            } else {
                try {
                    if ($editar) {
                        $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE id = ? AND eliminado = 0 LIMIT 1");
                        $stmt->execute([$id]);

                        if (!$stmt->fetchColumn()) {
                            $json['status'] = 'error';
                            $json['answer'] = 'El proveedor no existe o fue eliminado';
                            $json['code'] = 404;
                        } else {
                            $set = [];

                            foreach (array_keys($datos) as $campo) {
                                $set[] = "$campo = :$campo";
                            }

                            $datos['id'] = $id;

                            $sql = "UPDATE suppliers SET " . implode(', ', $set) . ",
                                    updated_at = NOW()
                                    WHERE id = :id AND eliminado = 0";

                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($datos);

                            $json['status'] = 'ok';
                            $json['answer'] = 'Proveedor actualizado correctamente';
                            $json['success'] = true;
                            $json['id'] = $id;
                            $json['code'] = 200;
                        }
                    } else {
                        foreach ($campos as $campo) {
                            if (!array_key_exists($campo, $datos)) $datos[$campo] = null;
                        }

                        $columnas = array_keys($datos);
                        $marcadores = array_map(function ($campo) {
                            return ':' . $campo;
                        }, $columnas);

                        $sql = "INSERT INTO suppliers (" . implode(', ', $columnas) . ",
                                eliminado, created_at, updated_at)
                                VALUES (" . implode(', ', $marcadores) . ", 0, NOW(), NOW())";

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($datos);
                        $id = (int)$pdo->lastInsertId();

                        $json['status'] = 'ok';
                        $json['answer'] = 'Proveedor registrado correctamente';
                        $json['success'] = true;
                        $json['id'] = $id;
                        $json['code'] = 200;
                    }
                } catch (PDOException $e) {
                    error_log('setProveedor: ' . $e->getMessage());

                    $json['status'] = 'error';
                    $json['answer'] = 'No fue posible guardar el proveedor';
                    $json['code'] = 500;
                }
            }
            break;

        case 'eliminarProveedor':
            requireRole('admin');

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                $json['status'] = 'error';
                $json['answer'] = 'Identificador de proveedor inválido';
                $json['code'] = 400;
            } else {
                try {
                    $sql = "UPDATE suppliers
                            SET eliminado = 1, updated_at = NOW()
                            WHERE id = ? AND eliminado = 0";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$id]);

                    $eliminado = $stmt->rowCount() > 0;

                    $json['status'] = $eliminado ? 'ok' : 'error';
                    $json['answer'] = $eliminado
                        ? 'Proveedor eliminado correctamente'
                        : 'El proveedor no existe o ya fue eliminado';
                    $json['success'] = $eliminado;
                    $json['code'] = $eliminado ? 200 : 404;
                } catch (PDOException $e) {
                    error_log('eliminarProveedor: ' . $e->getMessage());

                    $json['status'] = 'error';
                    $json['answer'] = 'No fue posible eliminar el proveedor';
                    $json['code'] = 500;
                }
            }
            break;

        default:
            $json['status'] = 'error';
            $json['answer'] = 'Acción no reconocida';
            $json['code'] = 400;
            break;
    }

print json_encode($json);

?>