<?php
require_once("dbconf.php");

class SchemaUpgrader
{
    public function runUpgrade() {
        $dataSource = new mysqli($host, $username, $password , $db_name);

        // Check connection
        if($dataSource->connect_error){
            die("ERROR: Could not connect. " . mysqli_connect_error());
        }

        $this->v_1_0($dataSource);
        $this->v_1_1($dataSource);
    }

    private function v_1_0($con) {
        mysqli_multi_query($con, file_get_contents("./v_1_0/bootstrap.sql"));
    }

    private function v_1_1($con) {
        // run next upgrade
    }
}
}