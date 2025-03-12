<?php
class ZoomManager {
    private $apiKey;
    private $apiSecret;
    private $baseUrl = 'https://api.zoom.us/v2';
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->apiKey = ZOOM_API_KEY;
        $this->apiSecret = ZOOM_API_SECRET;
    }
    
    private function generateJWT() {
        $payload = [
            'iss' => $this->apiKey,
            'exp' => time() + 3600
        ];
        
        return \Firebase\JWT\JWT::encode($payload, $this->apiSecret, 'HS256');
    }
    
    private function request($method, $endpoint, $data = null) {
        $ch = curl_init();
        
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Authorization: Bearer ' . $this->generateJWT(),
            'Content-Type: application/json'
        ];
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Error en la petición a Zoom: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode >= 400) {
            $error = json_decode($response, true);
            throw new Exception('Error de Zoom: ' . ($error['message'] ?? 'Error desconocido'));
        }
        
        return json_decode($response, true);
    }
    
    public function createMeeting($data) {
        $meetingData = [
            'topic' => $data['title'],
            'type' => 2, // Reunión programada
            'start_time' => date('Y-m-d\TH:i:s', strtotime($data['start_time'])),
            'duration' => $data['duration'],
            'timezone' => 'America/Mexico_City',
            'settings' => [
                'host_video' => true,
                'participant_video' => true,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'waiting_room' => true,
                'auto_recording' => 'cloud'
            ]
        ];
        
        $response = $this->request('POST', '/users/me/meetings', $meetingData);
        
        // Guardar en la base de datos
        $stmt = $this->conn->prepare("
            INSERT INTO virtual_classes (
                course_id, title, description, start_time, duration,
                zoom_meeting_id, zoom_join_url, zoom_start_url
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['course_id'],
            $data['title'],
            $data['description'],
            $data['start_time'],
            $data['duration'],
            $response['id'],
            $response['join_url'],
            $response['start_url']
        ]);
        
        return $this->conn->lastInsertId();
    }
    
    public function updateMeeting($classId, $data) {
        $stmt = $this->conn->prepare("
            SELECT zoom_meeting_id FROM virtual_classes WHERE id = ?
        ");
        $stmt->execute([$classId]);
        $meetingId = $stmt->fetchColumn();
        
        $meetingData = [
            'topic' => $data['title'],
            'start_time' => date('Y-m-d\TH:i:s', strtotime($data['start_time'])),
            'duration' => $data['duration']
        ];
        
        $this->request('PATCH', "/meetings/{$meetingId}", $meetingData);
        
        $stmt = $this->conn->prepare("
            UPDATE virtual_classes
            SET title = ?, description = ?, start_time = ?, duration = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['title'],
            $data['description'],
            $data['start_time'],
            $data['duration'],
            $classId
        ]);
    }
    
    public function deleteMeeting($classId) {
        $stmt = $this->conn->prepare("
            SELECT zoom_meeting_id FROM virtual_classes WHERE id = ?
        ");
        $stmt->execute([$classId]);
        $meetingId = $stmt->fetchColumn();
        
        $this->request('DELETE', "/meetings/{$meetingId}");
        
        $stmt = $this->conn->prepare("DELETE FROM virtual_classes WHERE id = ?");
        return $stmt->execute([$classId]);
    }
    
    public function getMeetingDetails($classId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM virtual_classes WHERE id = ?
        ");
        $stmt->execute([$classId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function recordAttendance($classId, $userId, $action) {
        if ($action === 'join') {
            $stmt = $this->conn->prepare("
                INSERT INTO class_attendance (class_id, user_id, join_time)
                VALUES (?, ?, NOW())
            ");
            return $stmt->execute([$classId, $userId]);
        } else {
            $stmt = $this->conn->prepare("
                UPDATE class_attendance
                SET leave_time = NOW()
                WHERE class_id = ? AND user_id = ? AND leave_time IS NULL
            ");
            return $stmt->execute([$classId, $userId]);
        }
    }
} 