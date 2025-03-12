<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    redirect('/login.php');
}

class DatabaseBackup {
    private $db;
    private $backup_dir;
    private $filename;

    public function __construct($db) {
        $this->db = $db;
        $this->backup_dir = __DIR__ . '/../../backups';
        $this->filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';

        if (!file_exists($this->backup_dir)) {
            mkdir($this->backup_dir, 0755, true);
        }
    }

    public function generate() {
        try {
            $tables = $this->getTables();
            $output = $this->getHeader();

            foreach ($tables as $table) {
                $output .= $this->backupTable($table);
            }

            $backup_file = $this->backup_dir . '/' . $this->filename;
            file_put_contents($backup_file, $output);

            // Registrar el backup en la base de datos
            $stmt = $this->db->prepare("
                INSERT INTO database_backups (
                    filename, 
                    size, 
                    created_by
                ) VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $this->filename,
                filesize($backup_file),
                $_SESSION['user_id']
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error en backup: " . $e->getMessage());
            return false;
        }
    }

    private function getTables() {
        $stmt = $this->db->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getHeader() {
        return "-- Backup generado el " . date('Y-m-d H:i:s') . "\n" .
               "-- Host: " . DB_HOST . "\n" .
               "-- Base de datos: " . DB_NAME . "\n\n" .
               "SET FOREIGN_KEY_CHECKS=0;\n\n";
    }

    private function backupTable($table) {
        $output = "-- Estructura de la tabla `$table`\n";
        
        // Obtener estructura
        $stmt = $this->db->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $output .= $row['Create Table'] . ";\n\n";

        // Obtener datos
        $stmt = $this->db->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $output .= "-- Datos de la tabla `$table`\n";
            $columns = array_keys($rows[0]);
            
            foreach ($rows as $row) {
                $values = array_map(function($value) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return $this->db->quote($value);
                }, $row);

                $output .= "INSERT INTO `$table` (`" . 
                          implode('`, `', $columns) . 
                          "`) VALUES (" . 
                          implode(', ', $values) . 
                          ");\n";
            }
        }

        $output .= "\n";
        return $output;
    }

    public function getFilename() {
        return $this->filename;
    }
}

// Procesar la solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $backup = new DatabaseBackup($db);
        
        if ($backup->generate()) {
            $response = [
                'success' => true,
                'message' => 'Backup generado exitosamente',
                'filename' => $backup->getFilename()
            ];
        } else {
            throw new Exception('Error al generar el backup');
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} 