<?php
class Reports {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function getTotalRevenue($start_date, $end_date) {
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(payment_amount), 0) as total
            FROM enrollments
            WHERE payment_status = 'completed'
            AND created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$start_date, $end_date]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    public function getNewEnrollments($start_date, $end_date) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as total
            FROM enrollments
            WHERE created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$start_date, $end_date]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    public function getActiveCourses($start_date, $end_date) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT course_id) as total
            FROM enrollments
            WHERE created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$start_date, $end_date]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    public function getConversionRate($start_date, $end_date) {
        // Total de visitas a páginas de cursos
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as visits
            FROM page_views
            WHERE page_type = 'course'
            AND created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$start_date, $end_date]);
        $visits = $stmt->fetch(PDO::FETCH_ASSOC)['visits'];
        
        // Total de inscripciones completadas
        $enrollments = $this->getNewEnrollments($start_date, $end_date);
        
        return $visits > 0 ? ($enrollments / $visits) * 100 : 0;
    }
    
    public function getRecentTransactions($limit = 10) {
        $stmt = $this->conn->prepare("
            SELECT 
                e.created_at,
                u.name as user_name,
                c.title as course_title,
                e.payment_amount as amount,
                e.payment_method,
                e.payment_status as status
            FROM enrollments e
            JOIN users u ON e.user_id = u.id
            JOIN courses c ON e.course_id = c.id
            ORDER BY e.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getRevenueByMonth($start_date, $end_date) {
        $stmt = $this->conn->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(payment_amount) as total
            FROM enrollments
            WHERE payment_status = 'completed'
            AND created_at BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->execute([$start_date, $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getEnrollmentsByCourse($start_date, $end_date) {
        $stmt = $this->conn->prepare("
            SELECT 
                c.title,
                COUNT(*) as total
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.created_at BETWEEN ? AND ?
            GROUP BY c.id
            ORDER BY total DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPaymentMethods($start_date, $end_date) {
        $stmt = $this->conn->prepare("
            SELECT 
                payment_method,
                COUNT(*) as total
            FROM enrollments
            WHERE payment_status = 'completed'
            AND created_at BETWEEN ? AND ?
            GROUP BY payment_method
        ");
        $stmt->execute([$start_date, $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 