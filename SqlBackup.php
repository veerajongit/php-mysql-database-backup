<?php

class SqlBackup {
    function __construct(
        string $dbHost = 'localhost',
        string $dbName = 'testDB',
        string $dbUser = 'root',
        string $dbPassword = 'root',
        string $backupDir = __DIR__ . '/backup'
    ) {
        $this->dbHost     = $dbHost;
        $this->dbName     = $dbName;
        $this->dbUser     = $dbUser;
        $this->dbPassword = $dbPassword;
        $this->backupDir  = $backupDir;
    }

    function get_file_size_unit($file_size) {
        switch (true) {
        case ($file_size / 1024 < 1):
            return intval($file_size) . " Bytes";
            break;
        case ($file_size / 1024 >= 1 && $file_size / (1024 * 1024) < 1):
            return intval($file_size / 1024) . " KB";
            break;
        default:
            return intval($file_size / (1024 * 1024)) . " MB";
        }
    }

    function run() {
        $fileName = 'mysqlbackup--' . date('d-m-Y') . '@' . date('h.i.s') . '.sql';

        if (function_exists('max_execution_time')) {
            if (ini_get('max_execution_time') > 0) {
                set_time_limit(0);
            }
        }

        // Check if directory is already created and has the proper permissions
        if (!file_exists($this->backupDir)) {
            mkdir($this->backupDir, 0700);
        }

        if (!is_writable($this->backupDir)) {
            chmod($this->backupDir, 0700);
        }

        // Create an ".htaccess" file , it will restrict direct accss to the backup-directory .
        $content = 'Allow from all';
        $file    = new SplFileObject($this->backupDir . '/.htaccess', "w");
        $file->fwrite($content);

        $mysqli = new mysqli($this->dbHost, $this->dbUser, $this->dbPassword, $this->dbName);
        if (mysqli_connect_errno()) {
            printf("Connect failed: %s", mysqli_connect_error());
            exit();
        }
        // Introduction information
        $return = '';
        $return .= "--\n";
        $return .= "-- A Mysql Backup System \n";
        $return .= "--\n";
        $return .= '-- Export created: ' . date("Y/m/d") . ' on ' . date("h:i") . "\n\n\n";
        $return = "--\n";
        $return .= "-- Database : " . $this->dbName . "\n";
        $return .= "--\n";
        $return .= "-- --------------------------------------------------\n";
        $return .= "-- ---------------------------------------------------\n";
        $return .= 'SET AUTOCOMMIT = 0 ;' . "\n";
        $return .= 'SET FOREIGN_KEY_CHECKS=0 ;' . "\n";
        $tables = array();
        // Exploring what tables this database has
        $result = $mysqli->query('SHOW TABLES');
        // Cycle through "$result" and put content into an array
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        // Cycle through each  table
        foreach ($tables as $table) {
            // Get content of each table
            $result = $mysqli->query('SELECT * FROM ' . $table);
            // Get number of fields (columns) of each table
            $num_fields = $mysqli->field_count;
            // Add table information
            $return .= "--\n";
            $return .= '-- Tabel structure for table `' . $table . '`' . "\n";
            $return .= "--\n";
            $return .= 'DROP TABLE  IF EXISTS `' . $table . '`;' . "\n";
            // Get the table-shema
            $shema = $mysqli->query('SHOW CREATE TABLE ' . $table);
            // Extract table shema
            $tableshema = $shema->fetch_row();
            // Append table-shema into code
            $return .= $tableshema[1] . ";" . "\n\n";
            // Cycle through each table-row
            while ($rowdata = $result->fetch_row()) {
                // Prepare code that will insert data into table
                $return .= 'INSERT INTO `' . $table . '`  VALUES ( ';
                // Extract data of each row
                for ($i = 0; $i < $num_fields; $i++) {
                    $return .= '"' . $mysqli->real_escape_string($rowdata[$i]) . "\",";
                }
                // Let's remove the last comma
                $return = substr("$return", 0, -1);
                $return .= ");" . "\n";
            }
            $return .= "\n\n";
        }
        // Close the connection
        $mysqli->close();
        $return .= 'SET FOREIGN_KEY_CHECKS = 1 ; ' . "\n";
        $return .= 'COMMIT ; ' . "\n";
        $return .= 'SET AUTOCOMMIT = 1 ; ' . "\n";
        //$file = file_put_contents($fileName , $return) ;
        $zip     = new ZipArchive();
        $resOpen = $zip->open($this->backupDir . '/' . $fileName . ".zip", ZIPARCHIVE::CREATE);
        if ($resOpen) {
            $zip->addFromString($fileName, "$return");
        }
        $zip->close();

        $this->fileSize = $this->get_file_size_unit(filesize($this->backupDir . "/" . $fileName . '.zip'));
        return true;
    }
}