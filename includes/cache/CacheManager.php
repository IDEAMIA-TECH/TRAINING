<?php
class CacheManager {
    private $cache_dir;
    private $enabled;
    private $ttl;

    public function __construct($cache_dir = null, $ttl = 3600) {
        // Si estamos en el instalador, deshabilitar el caché
        if (strpos($_SERVER['PHP_SELF'], 'install.php') !== false) {
            $this->enabled = false;
            return;
        }

        $this->cache_dir = $cache_dir ?? __DIR__ . '/../../cache';
        $this->ttl = $ttl;
        $this->enabled = true;

        if (!file_exists($this->cache_dir)) {
            @mkdir($this->cache_dir, 0755, true);
        }
    }

    public function get($key) {
        if (!$this->enabled) {
            return false;
        }

        $filename = $this->getCacheFilename($key);
        if (!file_exists($filename)) {
            return false;
        }

        $data = file_get_contents($filename);
        $cache = unserialize($data);

        if (!$cache || !isset($cache['expires']) || !isset($cache['data'])) {
            return false;
        }

        if (time() > $cache['expires']) {
            unlink($filename);
            return false;
        }

        return $cache['data'];
    }

    public function set($key, $data, $ttl = null) {
        if (!$this->enabled) {
            return false;
        }

        $cache = [
            'expires' => time() + ($ttl ?? $this->ttl),
            'data' => $data
        ];

        $filename = $this->getCacheFilename($key);
        return file_put_contents($filename, serialize($cache)) !== false;
    }

    public function delete($key) {
        $filename = $this->getCacheFilename($key);
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return true;
    }

    public function clear() {
        $files = glob($this->cache_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }

    private function getCacheFilename($key) {
        return $this->cache_dir . '/' . md5($key) . '.cache';
    }

    public function disable() {
        $this->enabled = false;
    }

    public function enable() {
        $this->enabled = true;
    }
} 