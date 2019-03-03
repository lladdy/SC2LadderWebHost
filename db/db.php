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

        echo "Running database upgrade..." . PHP_EOL;

        try {
            // UPGRADE STEPS
            // todo: implement upgrade step info messages
            // !!!!ATTENTION!!!! Don't forget to update the EXPECTED_DATABASE_VERSION in /www/header.php
            $this->v_0_0_to_v_1_0($con);
            //$this->v_1_0_to_v_1_1($con);
            // Add new steps here...
            // !!!!ATTENTION!!!! Don't forget to update the EXPECTED_DATABASE_VERSION in /www/header.php

            echo "Database upgrade finished." . PHP_EOL;
        } catch (Exception $e) {
            echo "ERROR: Database upgrade failed! " . $e->getMessage() . PHP_EOL;
            echo "The database is now in an unpredictable state!" . PHP_EOL;
        }

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
        echo "Creating \"" . $user_name . "\" user..." . PHP_EOL;
        // Create a user with the same name as the db
        $con->query("CREATE USER '" . mysqli_real_escape_string($con, $user_name) . "' IDENTIFIED BY '" . mysqli_real_escape_string($con, $user_pw) . "'");

        echo "Applying user permissions..." . PHP_EOL;
        $con->query("GRANT SELECT,INSERT,UPDATE ON " . mysqli_real_escape_string($con, $db_name) . ".* TO '" . mysqli_real_escape_string($con, $user_name) . "'");
    }

    private function createSchemaVariablesTable(mysqli $con) {
        echo "Creating \"schema_variables\" table..." . PHP_EOL;
        $con->query(file_get_contents("./v_0_0/schema_variables.sql"));
        echo "Setting database version..." . PHP_EOL;
        $con->query("INSERT INTO `schema_variables` (`schema_version`) values ('0.0')");
    }

    // If you update this, update getDatabaseVersion in /www/header.php as well
    private function getDatabaseVersion(mysqli $con) {
        $result = $con->query("SELECT `schema_version` FROM `schema_variables`");

        if($result->num_rows == 0)
            die("ERROR: schema_variables returned no result.");

        if($result->num_rows > 1)
            die("ERROR: schema_variables returned more than 1 row.");

        $value = $result->fetch_assoc();
        return $value["schema_version"];
    }

    public function setDatabaseVersion(mysqli $con, $version) {
        if (!($stmt = $con->prepare("UPDATE `schema_variables` SET `schema_version` = ?"))) {
            echo "Prepare failed: (" . $con->errno . ") " . $con->error;
        }

        if (!$stmt->bind_param("s", $version)) {
            echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
        }

        if (!$stmt->execute()) {
            echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        }

        if ($stmt->affected_rows !== 1) {
            echo "ERROR: Unexpected number of affected rows: " . $stmt->affected_rows;
        }

        $stmt->close();
    }

    // UPGRADE STEPS

    private function v_0_0_to_v_1_0(mysqli $con) {
        if($this->getDatabaseVersion($con) == "0.0") {
            $con->multi_query(file_get_contents("./v_1_0/bootstrap.sql"));
            while($con->next_result()); // Important! flush results from previous multiquery, else setDatabaseVersion won't work.
            $this->setDatabaseVersion($con, "1.0");
        }
    }

    private function v_1_0_to_v_1_1(mysqli $con) {
        if($this->getDatabaseVersion($con) == "1.0") {
            // run new upgrade here...
            $this->setDatabaseVersion($con, "1.1");
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