<?php
class QueryOptimizer {
    private $db;
    private $cache;
    private $logger;

    public function __construct($db, $cache, $logger) {
        $this->db = $db;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function getActiveCourses($page = 1, $limit = 10, $filters = []) {
        $cache_key = "courses_p{$page}_l{$limit}_" . md5(serialize($filters));
        $result = $this->cache->get($cache_key);

        if ($result !== false) {
            return $result;
        }

        $where = ["status = 'active'"];
        $params = [];

        if (!empty($filters['category'])) {
            $where[] = "category_id = ?";
            $params[] = $filters['category'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "start_date >= ?";
            $params[] = $filters['date_from'];
        }

        $offset = ($page - 1) * $limit;
        $where_clause = implode(' AND ', $where);

        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                cat.name as category_name,
                (SELECT COUNT(*) FROM course_registrations cr WHERE cr.course_id = c.id AND cr.status != 'cancelled') as registered_students,
                (SELECT image_url FROM course_images ci WHERE ci.course_id = c.id AND ci.is_main = 1 LIMIT 1) as main_image
            FROM courses c
            LEFT JOIN categories cat ON c.category_id = cat.id
            WHERE {$where_clause}
            ORDER BY c.start_date ASC
            LIMIT ? OFFSET ?
        ");

        array_push($params, $limit, $offset);
        $stmt->execute($params);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->cache->set($cache_key, $courses, 300); // 5 minutos de caché
        return $courses;
    }

    public function getUserDashboardData($user_id) {
        $cache_key = "user_dashboard_{$user_id}";
        $result = $this->cache->get($cache_key);

        if ($result !== false) {
            return $result;
        }

        // Obtener inscripciones activas
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                cr.status as registration_status,
                p.status as payment_status,
                cat.name as category_name
            FROM course_registrations cr
            JOIN courses c ON cr.course_id = c.id
            LEFT JOIN payments p ON cr.payment_id = p.id
            LEFT JOIN categories cat ON c.category_id = cat.id
            WHERE cr.user_id = ?
            AND cr.status != 'cancelled'
            AND c.start_date >= CURDATE()
            ORDER BY c.start_date ASC
        ");
        $stmt->execute([$user_id]);
        $active_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener historial de pagos
        $stmt = $this->db->prepare("
            SELECT 
                p.*,
                c.title as course_title
            FROM payments p
            JOIN courses c ON p.course_id = c.id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [
            'active_courses' => $active_courses,
            'recent_payments' => $recent_payments
        ];

        $this->cache->set($cache_key, $data, 600); // 10 minutos de caché
        return $data;
    }

    public function getCourseDetails($course_id) {
        $cache_key = "course_details_{$course_id}";
        $result = $this->cache->get($cache_key);

        if ($result !== false) {
            return $result;
        }

        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                cat.name as category_name,
                (
                    SELECT COUNT(*) 
                    FROM course_registrations cr 
                    WHERE cr.course_id = c.id 
                    AND cr.status != 'cancelled'
                ) as registered_students,
                (
                    SELECT GROUP_CONCAT(image_url)
                    FROM course_images ci
                    WHERE ci.course_id = c.id
                ) as images
            FROM courses c
            LEFT JOIN categories cat ON c.category_id = cat.id
            WHERE c.id = ?
        ");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($course) {
            $course['images'] = $course['images'] ? explode(',', $course['images']) : [];
            $this->cache->set($cache_key, $course, 300); // 5 minutos de caché
        }

        return $course;
    }

    public function invalidateCourseCache($course_id) {
        $this->cache->delete("course_details_{$course_id}");
        $this->cache->delete("courses_p1_l10_"); // Limpiar caché de lista principal
    }

    public function invalidateUserCache($user_id) {
        $this->cache->delete("user_dashboard_{$user_id}");
    }
} 