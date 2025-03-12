<?php
class Analytics {
    private static $instance = null;
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function trackPageView($title, $path = null) {
        $data = [
            'page_title' => $title,
            'page_path' => $path ?? $_SERVER['REQUEST_URI']
        ];
        
        $this->sendEvent('page_view', $data);
    }
    
    public function trackEnrollment($course_id, $course_title, $amount) {
        $data = [
            'currency' => 'MXN',
            'value' => $amount,
            'items' => [[
                'item_id' => $course_id,
                'item_name' => $course_title
            ]]
        ];
        
        $this->sendEvent('purchase', $data);
    }
    
    public function trackLogin($method = 'email') {
        $data = [
            'method' => $method
        ];
        
        $this->sendEvent('login', $data);
    }
    
    public function trackRegistration($method = 'email') {
        $data = [
            'method' => $method
        ];
        
        $this->sendEvent('sign_up', $data);
    }
    
    public function trackCourseView($course_id, $course_title) {
        $data = [
            'items' => [[
                'item_id' => $course_id,
                'item_name' => $course_title
            ]]
        ];
        
        $this->sendEvent('view_item', $data);
    }
    
    private function sendEvent($event_name, $event_params = []) {
        // Verificar si estamos en modo debug
        if (GA_DEBUG_MODE) {
            error_log("GA Event: " . $event_name);
            error_log("GA Params: " . json_encode($event_params));
        }
        
        // Preparar datos para el evento
        $data = [
            'client_id' => $this->getClientId(),
            'events' => [[
                'name' => $event_name,
                'params' => $event_params
            ]]
        ];
        
        // Enviar evento a Google Analytics
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://www.google-analytics.com/' . 
                          (GA_DEBUG_MODE ? 'debug/' : '') . 
                          'mp/collect?measurement_id=' . GA_TRACKING_ID . 
                          '&api_secret=' . GA_API_SECRET,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if (GA_DEBUG_MODE && $response) {
            error_log("GA Response: " . $response);
        }
    }
    
    private function getClientId() {
        if (!isset($_COOKIE['_ga'])) {
            $client_id = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            setcookie('_ga', $client_id, time() + (2 * 365 * 24 * 60 * 60), '/');
            return $client_id;
        }
        
        return $_COOKIE['_ga'];
    }
} 