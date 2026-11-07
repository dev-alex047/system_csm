<?php 
class consultas{
    private $token;
    private $conn;
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

    public function getResumenInicial() {
        $json = [];
        $tablas = [];
        $nombres = ["sales", "sale_items", "cash_registers", "cash_flows", "receivables", "payables", "account_moves", "bank_accounts"];

        if (!$this->conn) {
            $json["status"] = "error";
            $json["answer"] = "No existe conexión con la base de datos.";
            $json["code"] = 500;
        } else {
            foreach ($nombres as $nombre) {
                $tablas[$nombre] = ["nombre" => $nombre, "existe" => false, "motor" => null, "total" => 0, "columnas" => []];
            }

            $sql = "SELECT t.TABLE_NAME, t.ENGINE, c.COLUMN_NAME, c.COLUMN_TYPE
                    FROM INFORMATION_SCHEMA.TABLES t
                    INNER JOIN INFORMATION_SCHEMA.COLUMNS c
                    ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
                    WHERE t.TABLE_SCHEMA = DATABASE()
                    AND t.TABLE_NAME IN ('sales','sale_items','cash_registers','cash_flows','receivables','payables','account_moves','bank_accounts')
                    ORDER BY t.TABLE_NAME, c.ORDINAL_POSITION";

            $result = $this->conn->query($sql);

            if (!$result) {
                error_log("getResumenInicial: " . $this->conn->error);
                $json["status"] = "error";
                $json["answer"] = "No fue posible consultar la estructura de las tablas.";
                $json["code"] = 500;
            } else {
                while ($row = $result->fetch_assoc()) {
                    $nombre = $row["TABLE_NAME"];
                    $tablas[$nombre]["existe"] = true;
                    $tablas[$nombre]["motor"] = $row["ENGINE"];
                    $tablas[$nombre]["columnas"][] = [
                        "nombre" => $row["COLUMN_NAME"],
                        "tipo" => $row["COLUMN_TYPE"]
                    ];
                }
                $result->free();

                $valido = true;

                foreach ($tablas as $nombre => &$tabla) {
                    if (!$tabla["existe"]) continue;

                    $consulta = $this->conn->query("SELECT COUNT(*) AS total FROM `" . $nombre . "`");

                    if (!$consulta) {
                        error_log("getResumenInicial COUNT " . $nombre . ": " . $this->conn->error);
                        $valido = false;
                        break;
                    }

                    $fila = $consulta->fetch_assoc();
                    $tabla["total"] = (int)$fila["total"];
                    $consulta->free();
                }
                unset($tabla);

                if ($valido) {
                    $json["status"] = "ok";
                    $json["answer"] = "Información consultada correctamente.";
                    $json["results"] = array_values($tablas);
                    $json["code"] = 200;
                } else {
                    $json["status"] = "error";
                    $json["answer"] = "No fue posible consultar los registros de las tablas.";
                    $json["code"] = 500;
                }
            }
        }

        return $json;
    }
}
?>