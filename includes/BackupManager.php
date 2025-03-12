<?php
class BackupManager {
    private $conn;
    private $backupPath;
    private $excludePaths;
    
    public function __construct($conn, $config = []) {
        $this->conn = $conn;
        $this->backupPath = $config['backup_path'] ?? 'backups/';
        $this->excludePaths = $config['exclude_paths'] ?? ['backups/', 'tmp/', 'cache/'];
    }
    
    public function createBackup($type = 'full', $userId = null) {
        $startTime = date('Y-m-d H:i:s');
        $filename = $this->generateFilename($type);
        
        try {
            switch ($type) {
                case 'database':
                    $size = $this->backupDatabase($filename);
                    break;
                case 'files':
                    $size = $this->backupFiles($filename);
                    break;
                case 'full':
                    $size = $this->backupFull($filename);
                    break;
                default:
                    throw new Exception("Tipo de backup no válido");
            }
            
            // Registrar backup exitoso
            $this->logBackup([
                'filename' => $filename,
                'type' => $type,
                'size' => $size,
                'status' => 'success',
                'started_at' => $startTime,
                'completed_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId
            ]);
            
            return $filename;
            
        } catch (Exception $e) {
            // Registrar backup fallido
            $this->logBackup([
                'filename' => $filename,
                'type' => $type,
                'size' => 0,
                'status' => 'failed',
                'started_at' => $startTime,
                'error_message' => $e->getMessage(),
                'created_by' => $userId
            ]);
            
            throw $e;
        }
    }
    
    public function runScheduledBackups() {
        $stmt = $this->conn->prepare("
            SELECT * FROM backup_schedules
            WHERE is_active = TRUE
            AND (next_run IS NULL OR next_run <= NOW())
        ");
        
        $stmt->execute();
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($schedules as $schedule) {
            try {
                $this->createBackup($schedule['type']);
                
                // Actualizar próxima ejecución
                $nextRun = $this->calculateNextRun($schedule['frequency']);
                
                $stmt = $this->conn->prepare("
                    UPDATE backup_schedules
                    SET last_run = NOW(),
                        next_run = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([$nextRun, $schedule['id']]);
                
                // Limpiar backups antiguos
                $this->cleanOldBackups($schedule['type'], $schedule['retention_days']);
                
            } catch (Exception $e) {
                // Log error pero continuar con el siguiente
                error_log("Error en backup programado: " . $e->getMessage());
            }
        }
    }
    
    private function backupDatabase($filename) {
        $output = [];
        $returnVar = 0;
        
        // Configuración de la base de datos
        $dbConfig = [
            'host' => DB_HOST,
            'user' => DB_USER,
            'pass' => DB_PASS,
            'name' => DB_NAME
        ];
        
        // Comando mysqldump
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s %s > %s',
            escapeshellarg($dbConfig['host']),
            escapeshellarg($dbConfig['user']),
            escapeshellarg($dbConfig['pass']),
            escapeshellarg($dbConfig['name']),
            escapeshellarg($this->backupPath . $filename)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Error al crear backup de la base de datos");
        }
        
        return filesize($this->backupPath . $filename);
    }
    
    private function backupFiles($filename) {
        $excludePattern = '';
        foreach ($this->excludePaths as $path) {
            $excludePattern .= " --exclude='$path'";
        }
        
        $command = sprintf(
            'tar -czf %s %s .',
            escapeshellarg($this->backupPath . $filename),
            $excludePattern
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Error al crear backup de archivos");
        }
        
        return filesize($this->backupPath . $filename);
    }
    
    private function backupFull($filename) {
        // Crear backup de la base de datos
        $dbFilename = 'db_' . $filename;
        $this->backupDatabase($dbFilename);
        
        // Crear backup de archivos incluyendo el backup de la base de datos
        $size = $this->backupFiles($filename);
        
        // Eliminar el archivo temporal de la base de datos
        unlink($this->backupPath . $dbFilename);
        
        return $size;
    }
    
    private function generateFilename($type) {
        return sprintf(
            '%s_%s_%s.tar.gz',
            $type,
            date('Y-m-d'),
            uniqid()
        );
    }
    
    private function calculateNextRun($frequency) {
        $date = new DateTime();
        
        switch ($frequency) {
            case 'daily':
                $date->modify('+1 day');
                break;
            case 'weekly':
                $date->modify('+1 week');
                break;
            case 'monthly':
                $date->modify('+1 month');
                break;
        }
        
        return $date->format('Y-m-d H:i:s');
    }
    
    private function cleanOldBackups($type, $days) {
        $stmt = $this->conn->prepare("
            SELECT filename
            FROM backup_logs
            WHERE type = ?
            AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND status = 'success'
        ");
        
        $stmt->execute([$type, $days]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $file = $this->backupPath . $row['filename'];
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // Eliminar registros
        $stmt = $this->conn->prepare("
            DELETE FROM backup_logs
            WHERE type = ?
            AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        $stmt->execute([$type, $days]);
    }
    
    private function logBackup($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO backup_logs (
                filename, type, size, status,
                started_at, completed_at, error_message,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['filename'],
            $data['type'],
            $data['size'],
            $data['status'],
            $data['started_at'],
            $data['completed_at'] ?? null,
            $data['error_message'] ?? null,
            $data['created_by']
        ]);
    }
} 