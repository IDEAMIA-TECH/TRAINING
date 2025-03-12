<?php
require_once 'Analytics.php';

function track_page_view() {
    $analytics = Analytics::getInstance();
    $title = isset($GLOBALS['page_title']) ? $GLOBALS['page_title'] : 'Página sin título';
    $analytics->trackPageView($title);
}

// Registrar el middleware
add_action('after_page_load', 'track_page_view'); 