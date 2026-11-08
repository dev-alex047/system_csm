<?php 
class consultas{
    private $token;
    private $conn;
    private $usuarioOperador = 1; // ID real del operador en la tabla users

    public function __construct() {
        //$this->token = $session;
        $this->connectToDatabase();
    }

    private function connectToDatabase() {
        $conexion = new conexion();
        $this->conn = $conexion->getConnection();

        if (!$this->conn || $this->conn->connect_error) {
            $this->conn = null;
            return false;
        }
        
        $this->conn->set_charset("utf8mb4");

        return true;
    }

 
public function getResumenInicial($data = []) {
    $json = [];
    $inicio = isset($data["fecha_inicio"]) ? trim((string)$data["fecha_inicio"]) : date("Y-m-01");
    $fin = isset($data["fecha_fin"]) ? trim((string)$data["fecha_fin"]) : date("Y-m-d");
    $fechaInicio = DateTime::createFromFormat("!Y-m-d", $inicio);
    $fechaFin = DateTime::createFromFormat("!Y-m-d", $fin);

    if (!$this->conn) {
        $json = ["status"=>"error","answer"=>"No hay conexión con MySQL.","code"=>500];
    } elseif (!$fechaInicio || !$fechaFin || $fechaInicio->format("Y-m-d") !== $inicio || $fechaFin->format("Y-m-d") !== $fin || $inicio > $fin) {
        $json = ["status"=>"warning","answer"=>"Selecciona un rango de fechas válido.","code"=>400];
    } elseif ($fechaInicio->diff($fechaFin)->days > 366) {
        $json = ["status"=>"warning","answer"=>"El periodo máximo permitido es de 366 días.","code"=>400];
    } else {
        try {
            $consultasPeriodo = [
                "ventas" => "SELECT COUNT(*) cantidad, COALESCE(SUM(total_amount),0) importe FROM sales WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)",
                "salidas" => "SELECT COUNT(*) cantidad, COALESCE(SUM(amount),0) importe FROM account_moves WHERE type = 'SALIDA' AND status = 'PAGADO' AND `date` >= ? AND `date` < DATE_ADD(?, INTERVAL 1 DAY)",
                "ultimas_ventas" => "SELECT id, client_name, payment_method, total_amount, created_at FROM sales WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY created_at DESC, id DESC LIMIT 8",
                "movimientos" => "SELECT id, `date`, type, origin, user_reference_name, amount, status FROM account_moves WHERE `date` >= ? AND `date` < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY `date` DESC, id DESC LIMIT 8"
            ];

            $resultados = [];

            foreach ($consultasPeriodo as $clave => $sql) {
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) throw new RuntimeException($this->conn->error);
                $stmt->bind_param("ss", $inicio, $fin);
                if (!$stmt->execute()) throw new RuntimeException($stmt->error);
                $result = $stmt->get_result();

                if ($clave === "ultimas_ventas" || $clave === "movimientos") {
                    $resultados[$clave] = [];
                    while ($fila = $result->fetch_assoc()) $resultados[$clave][] = $fila;
                } else {
                    $resultados[$clave] = $result->fetch_assoc();
                }
                $stmt->close();
            }

            $consultasSaldo = [
                "caja" => "SELECT COUNT(*) cantidad, COALESCE(SUM(current_balance),0) saldo FROM cash_registers WHERE kind = 'CASH' AND is_active = 1",
                "cobrar" => "SELECT COUNT(*) cantidad, COALESCE(SUM(pending_amount),0) saldo FROM receivables WHERE status <> 'CANCELADO' AND pending_amount > 0",
                "pagar" => "SELECT COUNT(*) cantidad, COALESCE(SUM(pending_amount),0) saldo FROM payables WHERE status <> 'CANCELADO' AND pending_amount > 0"
            ];

            foreach ($consultasSaldo as $clave => $sql) {
                $result = $this->conn->query($sql);
                if (!$result) throw new RuntimeException($this->conn->error);
                $resultados[$clave] = $result->fetch_assoc();
                $result->free();
            }

            $json["status"] = "ok";
            $json["answer"] = "Resumen consultado correctamente.";
            $json["results"] = [
                "periodo" => ["inicio"=>$inicio,"fin"=>$fin],
                "ventas" => $resultados["ventas"],
                "salidas" => $resultados["salidas"],
                "caja" => $resultados["caja"],
                "cobrar" => $resultados["cobrar"],
                "pagar" => $resultados["pagar"],
                "ultimas_ventas" => $resultados["ultimas_ventas"],
                "movimientos" => $resultados["movimientos"]
            ];
            $json["code"] = 200;

        } catch (Throwable $e) {
            error_log("getResumenInicial: " . $e->getMessage());
            $json = ["status"=>"error","answer"=>"No fue posible obtener el resumen contable.","code"=>500];
        }
    }

    return $json;
}



private function ejecutarSqlVenta($sql, $tipos = '', $parametros = []) {
    $stmt = $this->conn->prepare($sql);
    if (!$stmt) throw new RuntimeException($this->conn->error, (int)$this->conn->errno);

    if ($tipos !== '') {
        $argumentos = [$tipos];
        foreach ($parametros as $i => &$valor) $argumentos[] = &$valor;
        unset($valor);
        if (!call_user_func_array([$stmt, 'bind_param'], $argumentos)) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException($error);
        }
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $codigo = (int)$stmt->errno;
        $stmt->close();
        throw new RuntimeException($error, $codigo);
    }
    return $stmt;
}

private function centavosVenta($valor) {
    if (!is_string($valor) && !is_numeric($valor)) return null;
    $valor = trim((string)$valor);
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $valor)) return null;
    $partes = explode('.', $valor, 2);
    return (int)$partes[0] * 100 + (int)str_pad(isset($partes[1]) ? $partes[1] : '', 2, '0');
}

private function textoVenta($datos, $campo, $maximo) {
    $valor = isset($datos[$campo]) ? $datos[$campo] : '';
    if (!is_string($valor) && !is_numeric($valor)) throw new InvalidArgumentException('Dato inválido: ' . $campo);
    $valor = trim((string)$valor);
    if (strlen($valor) > $maximo || strpos($valor, "\0") !== false) throw new InvalidArgumentException('El campo ' . $campo . ' excede su longitud permitida.');
    return $valor;
}



public function getCajasVenta() {
    $json = [];

    if (!$this->conn) {
        $json = ['status'=>'error','answer'=>'No existe conexión con MySQL.','code'=>500];
    } else {
        try {
            $stmt = $this->ejecutarSqlVenta("SELECT id, name, current_balance FROM cash_registers WHERE kind = 'CASH' AND is_active = 1 ORDER BY name ASC");
            $cajas = [];
            $result = $stmt->get_result();

            while ($fila = $result->fetch_assoc()) {
                $fila['id'] = (int)$fila['id'];
                $cajas[] = $fila;
            }
            $stmt->close();
            $json = ['status'=>'ok','answer'=>'Cajas consultadas correctamente.','results'=>$cajas,'code'=>200];
        } catch (Throwable $e) {
            error_log('getCajasVenta: ' . $e->getMessage());
            $json = ['status'=>'error','answer'=>'No fue posible consultar las cajas.','code'=>500];
        }
    }

    return $json;
}



public function guardarVentaNota($data) {
    $json = [];
    $transaccion = false;
    $token = '';

    if (!$this->conn) {
        $json = ['status'=>'error','answer'=>'No existe conexión con MySQL.','code'=>500];
    } else {
        try {
            $venta = isset($data['venta']) && is_string($data['venta']) ? json_decode($data['venta'], true) : null;
            if (!is_array($venta)) throw new InvalidArgumentException('No se recibieron datos válidos de la venta.');

            $token = $this->textoVenta($venta, 'solicitud_id', 32);
            if (!preg_match('/^[a-f0-9]{32}$/D', $token)) throw new InvalidArgumentException('Identificador de solicitud inválido.');

            $cliente = $this->textoVenta($venta, 'cliente', 100);
            $vendedor = $this->textoVenta($venta, 'vendedor', 100);
            $direccion = $this->textoVenta($venta, 'direccion', 255);
            $telefono = $this->textoVenta($venta, 'telefono', 50);
            $folioManual = $this->textoVenta($venta, 'folio', 50);
            $observaciones = $this->textoVenta($venta, 'observaciones', 500);
            $forma = $this->textoVenta($venta, 'forma_pago', 30);
            $metodoAbono = $this->textoVenta($venta, 'metodo_abono', 30);
            $referencia = $this->textoVenta($venta, 'referencia', 80);
            $referenciaAbono = $this->textoVenta($venta, 'referencia_abono', 80);
            $cajaId = isset($venta['caja_id']) ? (int)$venta['caja_id'] : 0;

            if ($cliente === '' || $vendedor === '') throw new InvalidArgumentException('Completa el cliente y el vendedor.');
            if ($folioManual !== '' && !preg_match('~^[A-Za-z0-9][A-Za-z0-9._/-]{0,49}$~D', $folioManual)) throw new InvalidArgumentException('El folio solamente permite letras, números, puntos, guiones y diagonales.');
            if (!in_array($forma, ['EFECTIVO','TRANSFERENCIA','CREDITO','PARCIALIDADES'], true)) throw new InvalidArgumentException('Modalidad de pago inválida.');
            if (!isset($venta['items']) || !is_array($venta['items']) || count($venta['items']) < 1 || count($venta['items']) > 100) throw new InvalidArgumentException('Agrega entre 1 y 100 artículos.');

            $items = [];
            $totalCentavos = 0;

            foreach ($venta['items'] as $index => $item) {
                if (!is_array($item) || (isset($item['origen']) && $item['origen'] !== 'MANUAL')) throw new InvalidArgumentException('Origen de artículo no permitido.');

                $nombre = $this->textoVenta($item, 'nombre', 200);
                $codigo = $this->textoVenta($item, 'codigo', 80);
                $cantidad100 = $this->centavosVenta(isset($item['cantidad']) ? $item['cantidad'] : '');
                $precioCentavos = $this->centavosVenta(isset($item['precio']) ? $item['precio'] : '');

                if ($nombre === '' || $cantidad100 === null || $cantidad100 < 1 || $cantidad100 > 10000000 || $precioCentavos === null || $precioCentavos > 100000000) {
                    throw new InvalidArgumentException('Revisa descripción, cantidad o precio del artículo ' . ($index + 1) . '.');
                }

                $importeCentavos = (int)round(($cantidad100 * $precioCentavos) / 100);
                if ($importeCentavos > 9999999999) throw new InvalidArgumentException('El importe de un artículo supera el máximo permitido.');

                $totalCentavos += $importeCentavos;
                if ($totalCentavos > 9999999999) throw new InvalidArgumentException('El total supera $99,999,999.99.');

                $items[] = [
                    'nombre'=>$nombre,
                    'codigo'=>$codigo,
                    'cantidad'=>number_format($cantidad100 / 100, 2, '.', ''),
                    'precio'=>number_format($precioCentavos / 100, 2, '.', ''),
                    'importe'=>number_format($importeCentavos / 100, 2, '.', '')
                ];
            }

            if ($totalCentavos <= 0) throw new InvalidArgumentException('El total de la venta debe ser mayor que cero.');

            $pagadoCentavos = 0;
            $metodoCobro = '';
            $referenciaCobro = '';

            if ($forma === 'EFECTIVO' || $forma === 'TRANSFERENCIA') {
                $pagadoCentavos = $totalCentavos;
                $metodoCobro = $forma;
                $referenciaCobro = $forma === 'TRANSFERENCIA' ? $referencia : '';
            } elseif ($forma === 'PARCIALIDADES') {
                $pagadoCentavos = $this->centavosVenta(isset($venta['abono']) ? $venta['abono'] : '');
                if ($pagadoCentavos === null || $pagadoCentavos <= 0 || $pagadoCentavos >= $totalCentavos) throw new InvalidArgumentException('El abono debe ser mayor que cero y menor que el total.');
                if (!in_array($metodoAbono, ['EFECTIVO','TRANSFERENCIA'], true)) throw new InvalidArgumentException('Método del abono inválido.');
                $metodoCobro = $metodoAbono;
                $referenciaCobro = $metodoAbono === 'TRANSFERENCIA' ? $referenciaAbono : '';
            }

            if ($pagadoCentavos > 0 && $metodoCobro === 'EFECTIVO' && $cajaId <= 0) throw new InvalidArgumentException('Selecciona una caja activa para recibir efectivo.');

            $saldoCentavos = $totalCentavos - $pagadoCentavos;
            $total = number_format($totalCentavos / 100, 2, '.', '');
            $pagado = number_format($pagadoCentavos / 100, 2, '.', '');
            $saldo = number_format($saldoCentavos / 100, 2, '.', '');

            if ($this->usuarioOperador <= 0) throw new InvalidArgumentException('Configura el usuario operador en consultas.php.');

            $stmt = $this->ejecutarSqlVenta('SELECT id FROM users WHERE id = ? LIMIT 1', 'i', [$this->usuarioOperador]);
            $existeUsuario = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$existeUsuario) throw new InvalidArgumentException('El usuario operador configurado no existe en users.');

            $stmt = $this->ejecutarSqlVenta('SELECT id, note_folio FROM sales WHERE request_token = ? LIMIT 1', 's', [$token]);
            $anterior = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($anterior) {
                $json = [
                    'status'=>'ok','answer'=>'Esta nota ya estaba registrada; no se duplicó.',
                    'results'=>['id'=>(int)$anterior['id'],'folio'=>$anterior['note_folio'],'duplicada'=>true],
                    'code'=>200
                ];
            } else {
                $fecha = date('Y-m-d H:i:s');
                $idCajaVenta = $pagadoCentavos > 0 && $metodoCobro === 'EFECTIVO' ? $cajaId : null;
                $folioInicial = $folioManual !== '' ? $folioManual : null;
                $ticketInicial = null;

                if (!$this->conn->begin_transaction()) throw new RuntimeException('No fue posible iniciar la transacción.');
                $transaccion = true;

                if ($idCajaVenta !== null) {
                    $stmt = $this->ejecutarSqlVenta("SELECT id FROM cash_registers WHERE id = ? AND kind = 'CASH' AND is_active = 1 FOR UPDATE", 'i', [$idCajaVenta]);
                    $cajaValida = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$cajaValida) throw new InvalidArgumentException('La caja seleccionada ya no está activa.');
                }

                $stmt = $this->ejecutarSqlVenta(
                    'INSERT INTO sales (user_id,client_name,client_address,client_phone,payment_method,bank_reference,cash_register_id,pending_amount,total_amount,ticket_barcode,notes,created_at,updated_at,sale_kind,note_folio,seller_name,initial_paid,request_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    'isssssisssssssssss',
                    [$this->usuarioOperador,$cliente,$direccion,$telefono,$forma,$referenciaCobro,$idCajaVenta,$saldo,$total,$ticketInicial,$observaciones,$fecha,$fecha,'NOTA',$folioInicial,$vendedor,$pagado,$token]
                );
                $stmt->close();

                $idVenta = (int)$this->conn->insert_id;
                $folio = $folioInicial !== null ? $folioInicial : 'N-' . str_pad((string)$idVenta, 8, '0', STR_PAD_LEFT);

                $stmt = $this->ejecutarSqlVenta('UPDATE sales SET note_folio = ?, ticket_barcode = ? WHERE id = ?', 'ssi', [$folio,$folio,$idVenta]);
                $stmt->close();

                $stmtItem = $this->conn->prepare("INSERT INTO sale_items (sale_id,product_id,quantity,price,total,item_origin,manual_name,manual_code) VALUES (?,NULL,?,?,?,'MANUAL',?,?)");
                if (!$stmtItem) throw new RuntimeException($this->conn->error, (int)$this->conn->errno);

                $cantidad = $precio = $importe = '0.00';
                $nombreArticulo = $codigoArticulo = '';
                $stmtItem->bind_param('isssss', $idVenta, $cantidad, $precio, $importe, $nombreArticulo, $codigoArticulo);

                foreach ($items as $item) {
                    $cantidad = $item['cantidad'];
                    $precio = $item['precio'];
                    $importe = $item['importe'];
                    $nombreArticulo = $item['nombre'];
                    $codigoArticulo = $item['codigo'];

                    if (!$stmtItem->execute()) {
                        $error = new RuntimeException($stmtItem->error, (int)$stmtItem->errno);
                        $stmtItem->close();
                        throw $error;
                    }
                }
                $stmtItem->close();

                if ($saldoCentavos > 0) {
                    $stmt = $this->ejecutarSqlVenta(
                        "INSERT INTO receivables (sale_id,client_id,client_name,user_id,total_amount,paid_amount,pending_amount,status,due_date,created_at,updated_at) VALUES (?,NULL,?,?,?,?,?,'PENDIENTE',NULL,?,?)",
                        'isisssss', [$idVenta,$cliente,$this->usuarioOperador,$total,$pagado,$saldo,$fecha,$fecha]
                    );
                    $stmt->close();
                }

                if ($pagadoCentavos > 0) {
                    $origen = $metodoCobro === 'EFECTIVO' ? 'CAJA' : 'BANCO';
                    $referenciaMovimiento = $referenciaCobro !== '' ? $referenciaCobro : $folio;
                    $notaMovimiento = 'Cobro inicial de venta';

                    $stmt = $this->ejecutarSqlVenta(
                        "INSERT INTO account_moves (`date`,`type`,`origin`,cash_register_id,user_reference_name,reference,amount,user_id,link_type,link_id,ticket_number,status,note,notes,created_at,updated_at) VALUES (?,'VENTA',?,?,?,?,?,?,'VENTA',?,?,'PAGADO',?,?,?,?)",
                        'ssisssiisssss', [$fecha,$origen,$idCajaVenta,$vendedor,$referenciaMovimiento,$pagado,$this->usuarioOperador,$idVenta,$folio,$notaMovimiento,$observaciones,$fecha,$fecha]
                    );
                    $stmt->close();

                    if ($metodoCobro === 'EFECTIVO') {
                        $descripcion = 'Cobro de venta ' . $folio;

                        $stmt = $this->ejecutarSqlVenta(
                            "INSERT INTO cash_flows (cash_register_id,user_id,amount,`type`,description,reference,created_at) VALUES (?,?,?,'IN',?,?,?)",
                            'iissss', [$idCajaVenta,$this->usuarioOperador,$pagado,$descripcion,$folio,$fecha]
                        );
                        $stmt->close();

                        $stmt = $this->ejecutarSqlVenta(
                            "UPDATE cash_registers SET current_balance = current_balance + ?, updated_at = ? WHERE id = ? AND kind = 'CASH' AND is_active = 1",
                            'ssi', [$pagado,$fecha,$idCajaVenta]
                        );
                        if ($stmt->affected_rows !== 1) throw new RuntimeException('No se pudo actualizar la caja.');
                        $stmt->close();
                    }
                }

                if (!$this->conn->commit()) throw new RuntimeException('No fue posible confirmar la transacción.');
                $transaccion = false;

                $json = [
                    'status'=>'ok',
                    'answer'=>'Venta registrada correctamente.',
                    'results'=>[
                        'id'=>$idVenta,'folio'=>$folio,'total'=>$total,
                        'recibido'=>$pagado,'saldo'=>$saldo,
                        'forma_pago'=>$forma,'duplicada'=>false
                    ],
                    'code'=>200
                ];
            }
        } catch (InvalidArgumentException $e) {
            if ($transaccion) $this->conn->rollback();
            $json = ['status'=>'warning','answer'=>$e->getMessage(),'code'=>400];
        } catch (Throwable $e) {
            if ($transaccion) $this->conn->rollback();
            error_log('guardarVentaNota: ' . $e->getMessage());
            $json = ['status'=>'error','answer'=>'No fue posible confirmar el registro de la venta.','code'=>500];

            if ((int)$e->getCode() === 1062) {
                try {
                    $stmt = $this->ejecutarSqlVenta('SELECT id, note_folio FROM sales WHERE request_token = ? LIMIT 1', 's', [$token]);
                    $repetida = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($repetida) {
                        $json = [
                            'status'=>'ok','answer'=>'La venta ya había sido registrada; no se duplicó.',
                            'results'=>['id'=>(int)$repetida['id'],'folio'=>$repetida['note_folio'],'duplicada'=>true],
                            'code'=>200
                        ];
                    } else {
                        $json = ['status'=>'warning','answer'=>'El folio ya está utilizado en otra nota.','code'=>409];
                    }
                } catch (Throwable $otroError) {
                    error_log('Validar venta duplicada: ' . $otroError->getMessage());
                }
            }
        }
    }

    return $json;
}


private function getDatosNotaVentaPdf($idVenta)
{
    $json = array();

    $stmt = $this->conn->prepare("SELECT id, note_folio, client_name, client_address, client_phone, seller_name, payment_method, bank_reference, total_amount, initial_paid, pending_amount, notes, created_at FROM sales WHERE id = ? AND sale_kind = 'NOTA' LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $idVenta);

    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $result = $stmt->get_result();
    $venta = $result->fetch_assoc();
    $stmt->close();

    if (!$venta) {
        return null;
    }

    $stmtItems = $this->conn->prepare("SELECT quantity, price, total, manual_name, manual_code FROM sale_items WHERE sale_id = ? ORDER BY id ASC");
    if (!$stmtItems) {
        return null;
    }

    $stmtItems->bind_param("i", $idVenta);

    if (!$stmtItems->execute()) {
        $stmtItems->close();
        return null;
    }

    $resultItems = $stmtItems->get_result();
    $venta["items"] = array();

    while ($item = $resultItems->fetch_assoc()) {
        $venta["items"][] = $item;
    }

    $stmtItems->close();

    return $venta;
}

public function generarPdfNotaVenta($idVenta)
{
    $json = array();

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No hay conexión con MySQL.";
        $json["code"] = 500;
        return $json;
    }

    if ($idVenta <= 0) {
        $json["status"] = "warning";
        $json["answer"] = "ID de venta inválido.";
        $json["code"] = 400;
        return $json;
    }

    try {
        $venta = $this->getDatosNotaVentaPdf($idVenta);

        if (!$venta) {
            $json["status"] = "warning";
            $json["answer"] = "No se encontró la nota de venta.";
            $json["code"] = 404;
            return $json;
        }

        $pdf = new PDFNotaVenta();
        $archivo = $pdf->generarArchivo($venta);

        $json["status"] = "ok";
        $json["answer"] = "Nota de venta generada correctamente.";
        $json["results"] = array(
            "id" => (int)$venta["id"],
            "folio" => $venta["note_folio"],
            "archivo" => $archivo["ruta_web"],
            "nombre" => $archivo["nombre"]
        );
        $json["code"] = 200;
    } catch (Throwable $e) {
        error_log("generarPdfNotaVenta: " . $e->getMessage());
        $json["status"] = "error";
        $json["answer"] = "No fue posible generar la nota de venta.";
        $json["code"] = 500;
    }

    return $json;
}
    
}
?>