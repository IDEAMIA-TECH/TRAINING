<?php
class FileManager {
    private $conn;
    private $uploadPath;
    private $allowedTypes;
    private $maxFileSize;
    
    public function __construct($conn, $config = []) {
        $this->conn = $conn;
        $this->uploadPath = $config['upload_path'] ?? 'uploads/';
        $this->allowedTypes = $config['allowed_types'] ?? ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $this->maxFileSize = $config['max_size'] ?? 5242880; // 5MB por defecto
    }
    
    public function uploadFile($file, $userId, $entityType = null, $entityId = null) {
        // Validar archivo
        $this->validateFile($file);
        
        // Generar nombre único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $relativePath = date('Y/m/') . $filename;
        $fullPath = $this->uploadPath . $relativePath;
        
        // Crear directorio si no existe
        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }
        
        // Mover archivo
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new Exception("Error al subir el archivo");
        }
        
        // Registrar en base de datos
        $stmt = $this->conn->prepare("
            INSERT INTO files (
                user_id, filename, original_name,
                mime_type, size, path,
                entity_type, entity_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $filename,
            $file['name'],
            $file['type'],
            $file['size'],
            $relativePath,
            $entityType,
            $entityId
        ]);
        
        $fileId = $this->conn->lastInsertId();
        
        // Procesar imagen si es necesario
        if (strpos($file['type'], 'image/') === 0) {
            $this->processImage($fileId, $fullPath);
        }
        
        return $fileId;
    }
    
    public function getFile($fileId) {
        $stmt = $this->conn->prepare("
            SELECT f.*, i.*
            FROM files f
            LEFT JOIN images i ON f.id = i.file_id
            WHERE f.id = ?
        ");
        
        $stmt->execute([$fileId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function deleteFile($fileId, $userId) {
        $file = $this->getFile($fileId);
        
        if (!$file || ($file['user_id'] != $userId && !has_permission('manage_files'))) {
            throw new Exception("No tienes permiso para eliminar este archivo");
        }
        
        // Eliminar archivo físico
        @unlink($this->uploadPath . $file['path']);
        
        // Eliminar miniaturas si es imagen
        if (isset($file['thumbnail_path'])) {
            @unlink($this->uploadPath . $file['thumbnail_path']);
            @unlink($this->uploadPath . $file['medium_path']);
            @unlink($this->uploadPath . $file['large_path']);
        }
        
        // Eliminar registros
        $this->conn->beginTransaction();
        
        try {
            // Eliminar metadatos
            $stmt = $this->conn->prepare("
                DELETE FROM file_metadata WHERE file_id = ?
            ");
            $stmt->execute([$fileId]);
            
            // Eliminar imagen
            $stmt = $this->conn->prepare("
                DELETE FROM images WHERE file_id = ?
            ");
            $stmt->execute([$fileId]);
            
            // Eliminar archivo
            $stmt = $this->conn->prepare("
                DELETE FROM files WHERE id = ?
            ");
            $stmt->execute([$fileId]);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    private function validateFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir el archivo: " . $file['error']);
        }
        
        if (!in_array($file['type'], $this->allowedTypes)) {
            throw new Exception("Tipo de archivo no permitido");
        }
        
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception("El archivo excede el tamaño máximo permitido");
        }
    }
    
    private function processImage($fileId, $path) {
        list($width, $height) = getimagesize($path);
        
        // Crear miniaturas
        $sizes = [
            'thumbnail' => [150, 150],
            'medium' => [300, 300],
            'large' => [800, 800]
        ];
        
        $paths = [];
        foreach ($sizes as $size => $dimensions) {
            $paths[$size] = $this->createThumbnail($path, $dimensions[0], $dimensions[1]);
        }
        
        // Registrar imagen
        $stmt = $this->conn->prepare("
            INSERT INTO images (
                file_id, width, height,
                thumbnail_path, medium_path, large_path
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $fileId,
            $width,
            $height,
            $paths['thumbnail'],
            $paths['medium'],
            $paths['large']
        ]);
    }
    
    private function createThumbnail($source, $maxWidth, $maxHeight) {
        $info = getimagesize($source);
        $type = $info[2];
        
        if ($type === IMAGETYPE_JPEG) {
            $image = imagecreatefromjpeg($source);
        } elseif ($type === IMAGETYPE_PNG) {
            $image = imagecreatefrompng($source);
        } elseif ($type === IMAGETYPE_GIF) {
            $image = imagecreatefromgif($source);
        } else {
            throw new Exception("Tipo de imagen no soportado");
        }
        
        $width = $info[0];
        $height = $info[1];
        
        $ratio = min($maxWidth/$width, $maxHeight/$height);
        $newWidth = round($width * $ratio);
        $newHeight = round($height * $ratio);
        
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preservar transparencia
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagecolortransparent($thumb, imagecolorallocate($thumb, 0, 0, 0));
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }
        
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        $path = dirname($source) . '/' . pathinfo($source, PATHINFO_FILENAME) . "_{$maxWidth}x{$maxHeight}." . pathinfo($source, PATHINFO_EXTENSION);
        
        if ($type === IMAGETYPE_JPEG) {
            imagejpeg($thumb, $path, 90);
        } elseif ($type === IMAGETYPE_PNG) {
            imagepng($thumb, $path, 9);
        } elseif ($type === IMAGETYPE_GIF) {
            imagegif($thumb, $path);
        }
        
        imagedestroy($thumb);
        imagedestroy($image);
        
        return str_replace($this->uploadPath, '', $path);
    }
} 