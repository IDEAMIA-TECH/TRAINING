<?php
class CourseMaterial {
    private $conn;
    private $upload_dir = 'uploads/materials/';
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function uploadMaterial($course_id, $data, $file) {
        try {
            // Validar archivo
            $this->validateFile($file);
            
            // Crear directorio si no existe
            $upload_path = $this->upload_dir . $course_id . '/';
            if (!file_exists($upload_path)) {
                mkdir($upload_path, 0777, true);
            }
            
            // Generar nombre único
            $filename = uniqid() . '_' . $file['name'];
            $file_path = $upload_path . $filename;
            
            // Mover archivo
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception("Error al subir el archivo");
            }
            
            // Guardar en base de datos
            $stmt = $this->conn->prepare("
                INSERT INTO course_materials (
                    course_id, title, description, file_url,
                    file_type, file_size, order_index, is_public
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $course_id,
                $data['title'],
                $data['description'],
                $file_path,
                $this->getFileType($file['type']),
                $file['size'],
                $data['order_index'] ?? 0,
                $data['is_public'] ?? false
            ]);
            
            return $this->conn->lastInsertId();
        } catch (Exception $e) {
            // Eliminar archivo si existe
            if (isset($file_path) && file_exists($file_path)) {
                unlink($file_path);
            }
            throw $e;
        }
    }
    
    public function getMaterials($course_id, $module_id = null) {
        $query = "
            SELECT m.*, 
                   COALESCE(mm.order_index, m.order_index) as display_order
            FROM course_materials m
            LEFT JOIN module_materials mm ON m.id = mm.material_id
            WHERE m.course_id = ?
        ";
        
        $params = [$course_id];
        
        if ($module_id) {
            $query .= " AND mm.module_id = ?";
            $params[] = $module_id;
        }
        
        $query .= " ORDER BY display_order ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateMaterial($id, $data) {
        $updates = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $updates[] = "{$key} = ?";
                $params[] = $value;
            }
        }
        
        $params[] = $id;
        
        $stmt = $this->conn->prepare("
            UPDATE course_materials 
            SET " . implode(', ', $updates) . "
            WHERE id = ?
        ");
        
        return $stmt->execute($params);
    }
    
    public function deleteMaterial($id) {
        // Obtener información del material
        $stmt = $this->conn->prepare("SELECT file_url FROM course_materials WHERE id = ?");
        $stmt->execute([$id]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($material && file_exists($material['file_url'])) {
            unlink($material['file_url']);
        }
        
        $stmt = $this->conn->prepare("DELETE FROM course_materials WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    private function validateFile($file) {
        $max_size = 50 * 1024 * 1024; // 50MB
        $allowed_types = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'video/mp4',
            'audio/mpeg',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if ($file['size'] > $max_size) {
            throw new Exception("El archivo excede el tamaño máximo permitido (50MB)");
        }
        
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception("Tipo de archivo no permitido");
        }
    }
    
    private function getFileType($mime_type) {
        $types = [
            'application/pdf' => 'document',
            'application/msword' => 'document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
            'image/jpeg' => 'image',
            'image/png' => 'image',
            'video/mp4' => 'video',
            'audio/mpeg' => 'audio'
        ];
        
        return $types[$mime_type] ?? 'other';
    }
} 