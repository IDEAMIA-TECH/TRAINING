<?php
class ReportManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function generateReport($type, $parameters = []) {
        switch ($type) {
            case 'course_performance':
                return $this->generateCoursePerformanceReport($parameters);
            case 'user_activity':
                return $this->generateUserActivityReport($parameters);
            case 'enrollment_trends':
                return $this->generateEnrollmentTrendsReport($parameters);
            case 'exam_statistics':
                return $this->generateExamStatisticsReport($parameters);
            default:
                throw new Exception("Tipo de reporte no válido");
        }
    }
    
    private function generateCoursePerformanceReport($params) {
        $courseId = $params['course_id'] ?? null;
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        
        $where = [];
        $values = [];
        
        if ($courseId) {
            $where[] = "c.id = ?";
            $values[] = $courseId;
        }
        
        if ($startDate) {
            $where[] = "cs.last_updated >= ?";
            $values[] = $startDate;
        }
        
        if ($endDate) {
            $where[] = "cs.last_updated <= ?";
            $values[] = $endDate;
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $sql = "
            SELECT 
                c.title as course_name,
                cs.total_students,
                cs.completion_rate,
                cs.average_score,
                ROUND(cs.total_time_spent / 60, 1) as total_hours_spent,
                (
                    SELECT COUNT(*)
                    FROM exam_attempts ea
                    JOIN exams e ON ea.exam_id = e.id
                    WHERE e.course_id = c.id AND ea.status = 'completed'
                ) as total_exam_attempts,
                (
                    SELECT AVG(score)
                    FROM exam_attempts ea
                    JOIN exams e ON ea.exam_id = e.id
                    WHERE e.course_id = c.id AND ea.status = 'completed'
                ) as average_exam_score
            FROM courses c
            JOIN course_statistics cs ON c.id = cs.course_id
            $whereClause
            ORDER BY cs.total_students DESC
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($values);
        
        return [
            'type' => 'course_performance',
            'title' => 'Reporte de Rendimiento de Cursos',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }
    
    private function generateUserActivityReport($params) {
        $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $params['end_date'] ?? date('Y-m-d');
        
        $sql = "
            SELECT 
                DATE(al.created_at) as date,
                COUNT(DISTINCT al.user_id) as active_users,
                COUNT(*) as total_actions,
                action_type,
                entity_type
            FROM activity_logs al
            WHERE al.created_at BETWEEN ? AND ?
            GROUP BY DATE(al.created_at), action_type, entity_type
            ORDER BY date DESC
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        
        return [
            'type' => 'user_activity',
            'title' => 'Reporte de Actividad de Usuarios',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }
    
    private function generateEnrollmentTrendsReport($params) {
        $period = $params['period'] ?? 'monthly';
        $limit = $params['limit'] ?? 12;
        
        $groupBy = $period === 'daily' ? 'DATE(e.created_at)' : 
                  ($period === 'weekly' ? 'YEARWEEK(e.created_at)' : 'DATE_FORMAT(e.created_at, "%Y-%m")');
        
        $sql = "
            SELECT 
                $groupBy as period,
                COUNT(*) as total_enrollments,
                COUNT(DISTINCT e.course_id) as unique_courses,
                COUNT(DISTINCT e.user_id) as unique_users
            FROM enrollments e
            GROUP BY period
            ORDER BY period DESC
            LIMIT ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$limit]);
        
        return [
            'type' => 'enrollment_trends',
            'title' => 'Tendencias de Inscripción',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }
    
    private function generateExamStatisticsReport($params) {
        $courseId = $params['course_id'] ?? null;
        
        $where = [];
        $values = [];
        
        if ($courseId) {
            $where[] = "e.course_id = ?";
            $values[] = $courseId;
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $sql = "
            SELECT 
                e.title as exam_title,
                c.title as course_title,
                COUNT(ea.id) as total_attempts,
                ROUND(AVG(ea.score), 2) as average_score,
                (
                    SELECT COUNT(*)
                    FROM exam_attempts ea2
                    WHERE ea2.exam_id = e.id AND ea2.score >= e.passing_score
                ) as passed_count,
                (
                    SELECT COUNT(*)
                    FROM exam_attempts ea2
                    WHERE ea2.exam_id = e.id AND ea2.score < e.passing_score
                ) as failed_count
            FROM exams e
            JOIN courses c ON e.course_id = c.id
            LEFT JOIN exam_attempts ea ON e.id = ea.exam_id
            $whereClause
            GROUP BY e.id
            ORDER BY total_attempts DESC
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($values);
        
        return [
            'type' => 'exam_statistics',
            'title' => 'Estadísticas de Exámenes',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }
    
    public function updateStatistics() {
        // Actualizar estadísticas de cursos
        $this->updateCourseStatistics();
        
        // Actualizar estadísticas de usuarios
        $this->updateUserStatistics();
    }
    
    private function updateCourseStatistics() {
        $sql = "
            INSERT INTO course_statistics (course_id, total_students, completion_rate, average_score, total_time_spent)
            SELECT 
                c.id,
                COUNT(DISTINCT e.user_id) as total_students,
                (
                    SELECT COUNT(DISTINCT user_id) * 100.0 / NULLIF(COUNT(DISTINCT e2.user_id), 0)
                    FROM course_progress cp
                    JOIN enrollments e2 ON cp.enrollment_id = e2.id
                    WHERE e2.course_id = c.id AND cp.completed = TRUE
                ) as completion_rate,
                (
                    SELECT AVG(score)
                    FROM exam_attempts ea
                    JOIN exams ex ON ea.exam_id = ex.id
                    WHERE ex.course_id = c.id AND ea.status = 'completed'
                ) as average_score,
                COALESCE(
                    (
                        SELECT SUM(TIMESTAMPDIFF(MINUTE, start_time, IFNULL(end_time, NOW())))
                        FROM course_progress cp
                        JOIN enrollments e2 ON cp.enrollment_id = e2.id
                        WHERE e2.course_id = c.id
                    ),
                    0
                ) as total_time_spent
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id
            GROUP BY c.id
            ON DUPLICATE KEY UPDATE
                total_students = VALUES(total_students),
                completion_rate = VALUES(completion_rate),
                average_score = VALUES(average_score),
                total_time_spent = VALUES(total_time_spent)
        ";
        
        $this->conn->query($sql);
    }
    
    private function updateUserStatistics() {
        $sql = "
            INSERT INTO user_statistics (
                user_id, courses_enrolled, courses_completed, 
                total_time_spent, average_score, last_activity
            )
            SELECT 
                u.id,
                COUNT(DISTINCT e.course_id) as courses_enrolled,
                COUNT(DISTINCT CASE WHEN cp.completed = TRUE THEN e.course_id END) as courses_completed,
                COALESCE(
                    SUM(TIMESTAMPDIFF(MINUTE, cp.start_time, IFNULL(cp.end_time, NOW()))),
                    0
                ) as total_time_spent,
                (
                    SELECT AVG(score)
                    FROM exam_attempts ea
                    WHERE ea.user_id = u.id AND ea.status = 'completed'
                ) as average_score,
                (
                    SELECT MAX(created_at)
                    FROM activity_logs
                    WHERE user_id = u.id
                ) as last_activity
            FROM users u
            LEFT JOIN enrollments e ON u.id = e.user_id
            LEFT JOIN course_progress cp ON e.id = cp.enrollment_id
            GROUP BY u.id
            ON DUPLICATE KEY UPDATE
                courses_enrolled = VALUES(courses_enrolled),
                courses_completed = VALUES(courses_completed),
                total_time_spent = VALUES(total_time_spent),
                average_score = VALUES(average_score),
                last_activity = VALUES(last_activity)
        ";
        
        $this->conn->query($sql);
    }
} 