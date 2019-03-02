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
            die("ERROR: Database \"" . $db_name."\" already exists." . PHP_EOL);

        $this->createDatabase($con, $db_name);
        $con->select_db($db_name);

        // Use the db name for the username and pw - devs can update this manually if they need more security.
        $this->createWebappUser($con, $db_name, $db_name, $db_name);

        $this->createSchemaVariablesTable($con);

        $con->close();
    }

    public function upgradeDb() {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // make mysqli throw exceptions
        require_once("dbconf.php");
        $con = new mysqli($host, $username, $password , $db_name);

        // Check connection
        if($con->connect_error){
            die("ERROR: Could not connect. " . mysqli_connect_error() . PHP_EOL);
        }

        // UPGRADE STEPS
        // !!!!ATTENTION!!!! Don't forget to update the EXPECTED_DATABASE_VERSION in ./www/header.php
        // todo: start transaction
        $this->v_0_0_to_v_1_0($con);
        //$this->v_1_0_to_v_1_1($con);
        // Add steps here...
        // todo: end transaction
        // !!!!ATTENTION!!!! Don't forget to update the EXPECTED_DATABASE_VERSION in ./www/header.php

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
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row["count"] > 0;
    }

    private function createDatabase(mysqli $con, $db_name) {
        if ($con->query("CREATE DATABASE " . mysqli_real_escape_string($con, $db_name)) === TRUE) {
            echo "Database \"" . $db_name . "\" successfully created" . PHP_EOL;
        }
        else {
            echo "Error: " . $con->error . PHP_EOL;
        }
    }

    private function createWebappUser(mysqli $con, $db_name, $user_name, $user_pw) {
        // Create a user with the same name as the db
        if ($con->query("CREATE USER '" . mysqli_real_escape_string($con, $user_name) . "'@'localhost'
        IDENTIFIED BY '" . mysqli_real_escape_string($con, $user_pw) . "'") === TRUE
        && $con->query("GRANT SELECT,INSERT,UPDATE ON "
                . mysqli_real_escape_string($con, $db_name) . ".* TO " . mysqli_real_escape_string($con, $db_name)) === TRUE) {
            echo "Database \"" . $db_name . "\" successfully created" . PHP_EOL;
        }
        else {
            echo "Error: " . $con->error . PHP_EOL;
        }
    }

    private function createSchemaVariablesTable(mysqli $con) {
        if ($con->query(file_get_contents("./v_0_0/schema_variables.sql")) === TRUE
        && $con->query("INSERT INTO `schema_variables` (`key`, `value`) values ('database_version', '0.0');") === TRUE) {
            echo "Table \"schema_variables\" successfully created" . PHP_EOL;
        }
        else {
            echo "Error: " . $con->error . PHP_EOL;
        }
    }

    private function getDatabaseVersion(mysqli $con) {
        $result = $con->query("SELECT `value` FROM `schema_variables` WHERE `key` = 'database_version';");

        if($result->num_rows == 0)
            die("ERROR: 'database_version' schema variable returned no result.");

        if($result->num_rows > 1)
            die("ERROR: 'database_version' schema variable returned more than 1 result.");
        return $result->fetch_assoc()["value"];
    }

    private function setDatabaseVersion(mysqli $con, $version) {
        if (!($stmt = $con->prepare("UPDATE `schema_variables` SET `value` = ? WHERE `key` = 'database_version';"))) {
            echo "Prepare failed: (" . $con->errno . ") " . $con->error;
        }

        if (!$stmt->bind_param("s", $version)) {
            echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
        }

        if (!$stmt->execute()) {
            echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        }
    }

    // UPGRADE STEPS

    private function v_0_0_to_v_1_0(mysqli $con) {
        if(getDatabaseVersion($con) == "0.0") {
            $con->multi_query(file_get_contents("./v_1_0/bootstrap.sql"));
            setDatabaseVersion($con, "1.0");
        }
    }

    private function v_1_0_to_v_1_1(mysqli $con) {
        if(getDatabaseVersion($con) == "1.0") {
            // run next upgrade...
            setDatabaseVersion($con, "0.1.1");
        }
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