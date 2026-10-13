<?php

class conexion {
    private $conn;

    public function __construct() {
        $servername = "localhost";
        $username = "admin_fjpq";
        $password = "fjpq050402SCP";
        $dbname = "inventario";

        $this->conn = new mysqli($servername, $username, $password, $dbname);

        if ($this->conn->connect_error) {
            $json = []; 
            $json["status"] = "error";
            $json["answer"] =  $this->conn->connect_error;
            $json["code"] = 501;
            print(json_encode($json));
            exit();
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>