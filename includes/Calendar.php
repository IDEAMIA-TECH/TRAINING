<?php
class Calendar {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function getEvents($filters = []) {
        $where = "1=1";
        $params = [];
        
        if (isset($filters['type'])) {
            $where .= " AND type = ?";
            $params[] = $filters['type'];
        }
        
        if (isset($filters['course_id'])) {
            $where .= " AND course_id = ?";
            $params[] = $filters['course_id'];
        }
        
        $query = "
            SELECT 
                id,
                title,
                description,
                start_datetime as start,
                end_datetime as end,
                location,
                type,
                color,
                course_id
            FROM calendar_events 
            WHERE {$where}
            ORDER BY start_datetime ASC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCourses() {
        $query = "
            SELECT id, title 
            FROM courses 
            WHERE status = 'active' 
            ORDER BY title ASC
        ";
        
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function createEvent($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO calendar_events (
                title, description, start_datetime, end_datetime,
                location, type, color, course_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['title'],
            $data['description'],
            $data['start'],
            $data['end'],
            $data['location'] ?? null,
            $data['type'],
            $data['color'],
            $data['course_id'] ?: null
        ]);
    }
    
    public function updateEvent($id, $data) {
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
            UPDATE calendar_events 
            SET " . implode(', ', $updates) . "
            WHERE id = ?
        ");
        
        return $stmt->execute($params);
    }
    
    public function deleteEvent($id) {
        $stmt = $this->conn->prepare("DELETE FROM calendar_events WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function syncWithGoogleCalendar($event_id) {
        // Implementar sincronización con Google Calendar
        // Requiere configuración de Google Calendar API
    }
} 