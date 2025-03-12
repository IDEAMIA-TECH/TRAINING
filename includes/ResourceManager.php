<?php
class ResourceManager {
    private $conn;
    private $uploadPath;
    
    public function __construct($conn, $config = []) {
        $this->conn = $conn;
        $this->uploadPath = $config['upload_path'] ?? 'uploads/resources/';
    }
    
    public function createResource($data, $file = null) {
        $this->conn->beginTransaction();
        
        try {
            $fileId = null;
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $fileId = $this->uploadFile($file);
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO resources (
                    title, description, type,
                    file_id, external_url, category_id,
                    is_premium, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $data['type'],
                $fileId,
                $data['external_url'] ?? null,
                $data['category_id'] ?? null,
                $data['is_premium'] ?? false,
                $data['status'] ?? 'active',
                $data['created_by']
            ]);
            
            $resourceId = $this->conn->lastInsertId();
            $this->conn->commit();
            
            return $resourceId;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function getResources($filters = [], $page = 1, $limit = 20) {
        $sql = "
            SELECT 
                r.*,
                rc.name as category_name,
                f.filename,
                f.mime_type,
                u.name as creator_name
            FROM resources r
            LEFT JOIN resource_categories rc ON r.category_id = rc.id
            LEFT JOIN files f ON r.file_id = f.id
            LEFT JOIN users u ON r.created_by = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['type'])) {
            $sql .= " AND r.type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND r.category_id = ?";
            $params[] = $filters['category'];
        }
        
        if (isset($filters['is_premium'])) {
            $sql .= " AND r.is_premium = ?";
            $params[] = $filters['is_premium'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = ($page - 1) * $limit;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getResource($id) {
        $stmt = $this->conn->prepare("
            SELECT 
                r.*,
                rc.name as category_name,
                f.filename,
                f.mime_type,
                f.filesize,
                u.name as creator_name
            FROM resources r
            LEFT JOIN resource_categories rc ON r.category_id = rc.id
            LEFT JOIN files f ON r.file_id = f.id
            LEFT JOIN users u ON r.created_by = u.id
            WHERE r.id = ?
        ");
        
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function downloadResource($resourceId, $userId) {
        $resource = $this->getResource($resourceId);
        
        if (!$resource || !$resource['file_id']) {
            throw new Exception("Recurso no encontrado");
        }
        
        // Verificar acceso premium si es necesario
        if ($resource['is_premium']) {
            // Implementar verificación de suscripción
        }
        
        // Registrar descarga
        $stmt = $this->conn->prepare("
            INSERT INTO resource_downloads (
                resource_id, user_id, ip_address
            ) VALUES (?, ?, ?)
        ");
        
        $stmt->execute([
            $resourceId,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        // Actualizar contador
        $stmt = $this->conn->prepare("
            UPDATE resources
            SET downloads = downloads + 1
            WHERE id = ?
        ");
        
        $stmt->execute([$resourceId]);
        
        return $resource;
    }
    
    public function updateResource($id, $data, $file = null) {
        $this->conn->beginTransaction();
        
        try {
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $fileId = $this->uploadFile($file);
                $data['file_id'] = $fileId;
            }
            
            $sql = "UPDATE resources SET ";
            $params = [];
            
            foreach ($data as $key => $value) {
                $sql .= "$key = ?, ";
                $params[] = $value;
            }
            
            $sql = rtrim($sql, ", ") . " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    private function uploadFile($file) {
        // Validar archivo
        $allowedTypes = ['application/pdf', 'application/zip', 'video/mp4', 'audio/mpeg'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception("Tipo de archivo no permitido");
        }
        
        // Generar nombre único
        $filename = uniqid() . '_' . $file['name'];
        $filepath = $this->uploadPath . $filename;
        
        // Mover archivo
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception("Error al subir el archivo");
        }
        
        // Registrar en base de datos
        $stmt = $this->conn->prepare("
            INSERT INTO files (
                filename, filepath,
                mime_type, filesize
            ) VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $file['name'],
            $filepath,
            $file['type'],
            $file['size']
        ]);
        
        return $this->conn->lastInsertId();
    }
    
    public function getCategories($parentId = null) {
        $sql = "
            SELECT *
            FROM resource_categories
            WHERE parent_id " . ($parentId ? "= ?" : "IS NULL") . "
            ORDER BY order_index, name
        ";
        
        $stmt = $this->conn->prepare($sql);
        
        if ($parentId) {
            $stmt->execute([$parentId]);
        } else {
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 