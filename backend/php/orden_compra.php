<?php 

class orden_compra {
    public function __construct() {
        $this->var = $var;
    }

    public function getOrdenComra($id){
        $json = [];

        $query = "SELECT * FROM ordenes_compra WHERE myuser_id = ? AND id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $this->myuser_id, $id);

        if($stmt->execute()){
            $results = $stmt->get_result();
            $ordenes = [];

            while($row = $stmt->fetch_assoc()){
                $ordenes[] = $row;
            }

            $json["status"] = "ok";
            $json["answer"] = "Ordenes de compra obtenidos correctamente";
            $json["results"] = $ordenes;
            $json["code"] = 200;
        }else{
            $json["status"] = "error";
            $json["answer"] = "No se pudieron obtener las ordenes";
            $json["code"] = 500;
        }

        $stmt->close();

        return $json;
    }

    public function setOrdenComra($item_id, $tipo_item, $total, $fecha_alta){
        $json = [];

        $query = "INSERT INTO ordenes_compra (myuser_id, item_id, tipo_item, total, fecha_alta) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('siiis', $this->mypos, $item_id, $tipo_item, $total, $fecha_alta);

        if($stmt->execute()){
            $json["status"] = "ok";
            $json["answer"] = "Orden de compra generada correctamente";
            $json["id"] = $this->conn->insert_id;
            $json["code"] = 200;
        }else{
            $json["status"] = "error";
            $json["answer"] = "No se pudo guardar la orden de compra";
            $json["code"] = 500;
        }

        $stmt->close();

        return $json;
    }

    public function updateOrdenCompra($id, $argumentos){
        $json = [];

        $query = "UPDATE ordenes_compra SET item_id = ? WHERE myuser_id = ? AND id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('isi', $argumentos, $this->myuser_id, $id);

        if($stmt->execute()){
            if($stmt->affected_row > 0){
                $json["status"] = "ok";
                $json["answer"] = "Orden de compra actualizado correctamente";
                $json["code"] = 200;
            }else{
                $json["status"] = "error";
                $json["answer"] = "No se pudo actualizar la orden de compra";
                $json["code"] = 500;
            }
        }

        $stmt->close();

        return $json;
    }

    public function deleteOrdenCompra($id){
        $json = [];

        $query = "UPDATE ordenes_compra SET eliminado = 1 WHERE myuser_id = ? AND id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('si', $this->myuser_id, $id);

        if($stmt->execute()){
            if($stmt->affected_row > 0){
                $json["status"] = "ok";
                $json["answer"] = "Orden de compra eliminada correctamente";
                $json["code"] = 200;
            }else{
                $json["status"] = "error";
                $json["answer"] = "No se pudo eliminar la orden de compra";
                $json["code"] = 500;
            }
        }

        $stmt->close();

        return $json;
    }
}