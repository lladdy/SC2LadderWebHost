<?php

class SchemaManager
{
    public function bootstrapDb() {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // make mysqli throw exceptions
        require_once("dbconf.php");
        $con = new mysqli($host, $username, $password);
        // check connection
        if($con->connect_error)
            die("ERROR: Could not connect. " . mysqli_connect_error());

        if($this->databaseAlreadyExists($con, $db_name))
            die('ERROR: Database "' . $db_name.'" already exists.' . PHP_EOL);

        $this->createDatabase($con, $db_name);
        $con->select_db($db_name);

        $this->createSchemaVariablesTable($con);

        $con->close();
    }

    public function upgradeDb() {
        require_once("dbconf.php");
        $con = new mysqli($host, $username, $password , $db_name);

        // Check connection
        if($con->connect_error){
            die("ERROR: Could not connect. " . mysqli_connect_error() . PHP_EOL);
        }

        $this->v_1_0($con);
        $this->v_1_1($con);

        $con->close();
    }

    private function databaseAlreadyExists(mysqli $con, $db_name) {
        if (!($stmt = $con->prepare("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?"))) {
            echo "Prepare failed: (" . $con->errno . ") " . $con->error;
        }

        if (!$stmt->bind_param("s", $db_name)) {
            echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
        }

        if (!$stmt->execute()) {
            echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        }

        $result = $stmt->get_result();
        $row = mysqli_fetch_assoc($result);
        $stmt->close();
        return $row["count"] > 0;
    }

    private function createDatabase(mysqli $con, $db_name) {
        if ($con->query("CREATE DATABASE " . mysqli_real_escape_string($con, $db_name)) === TRUE) {
            echo 'Database "' . $db_name . '" successfully created' . PHP_EOL;
        }
        else {
            echo 'Error: '. $con->error . PHP_EOL;
        }
    }

    private function createSchemaVariablesTable(mysqli $con) {
        if ($con->query(file_get_contents("./v_0_0/schema_variables.sql")) === TRUE) {
            echo 'Table "schema_variables" successfully created' . PHP_EOL;
        }
        else {
            echo 'Error: '. $con->error . PHP_EOL;
        }
    }

    private function v_1_0(mysqli $con) {
        $con->multi_query(file_get_contents("./v_1_0/bootstrap.sql"));
    }

    private function v_1_1(mysqli $con) {
        // run next upgrade
    }
}

function printUsage() {
    echo "Usage: php " . pathinfo(__FILE__, PATHINFO_BASENAME) . "action" . PHP_EOL;
    echo "actions:" . PHP_EOL;
    echo "   bootstrapdb" . PHP_EOL;
    echo "       Create and bootstrap a new database" . PHP_EOL;
    echo "   upgradedb" . PHP_EOL;
    echo "       Upgrade an existing database" . PHP_EOL;
}


if($argc==2) {
    $schemaManager = new SchemaManager();
    switch ($argv[1]) {
        case "bootstrapdb":
            $schemaManager->bootstrapDb();
            break;
        case "upgradedb":
            $schemaManager->upgradeDb();
            break;
    }
} else {
    printUsage();
}