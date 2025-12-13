<?php

/**
 * Simple File-Based Cache for NAS Deployment
 *
 * Perfect for Synology NAS with weekly data updates:
 * - No dependencies (no Redis/Memcached needed)
 * - No memory overhead (file-based, not in-memory)
 * - Auto-expires after 7 days (safety net)
 * - Manual invalidation on data upload (primary mechanism)
 *
 * Usage:
 *   $cache = new SimpleCache();
 *   $data = $cache->get('my_key');
 *   if ($data === null) {
 *       $data = expensiveOperation();
 *       $cache->set('my_key', $data);
 *   }
 *
 * Clear all caches after data upload:
 *   $cache->clear();
 */
class SimpleCache
{
    private string $cacheDir;
    private int $ttl;

    /**
     * Initialize cache
     *
     * @param string $cacheDir Directory to store cache files (default: /tmp/dashboard_cache/)
     * @param int $ttl Time-to-live in seconds (default: 7 days)
     */
    public function __construct($cacheDir = '/tmp/dashboard_cache/', $ttl = 604800)
    {
        $this->cacheDir = rtrim($cacheDir, '/') . '/';
        $this->ttl = $ttl;

        // Create cache directory if it doesn't exist
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Get cached data by key
     *
     * @param string $key Cache key
     * @return mixed|null Cached data or null if not found/expired
     */
    public function get($key)
    {
        $file = $this->getCacheFile($key);

        // Check if cache file exists and is not expired
        if (file_exists($file)) {
            $age = time() - filemtime($file);

            if ($age < $this->ttl) {
                $contents = file_get_contents($file);
                return json_decode($contents, true);
            } else {
                // Expired - delete the file
                @unlink($file);
            }
        }

        return null;
    }

    /**
     * Store data in cache
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache (will be JSON encoded)
     * @return bool Success status
     */
    public function set($key, $data)
    {
        $file = $this->getCacheFile($key);
        $json = json_encode($data);

        if ($json === false) {
            error_log("SimpleCache: Failed to encode data for key: $key");
            return false;
        }

        $result = file_put_contents($file, $json);

        if ($result === false) {
            error_log("SimpleCache: Failed to write cache file: $file");
            return false;
        }

        return true;
    }

    /**
     * Clear all cache files
     * Call this after uploading new data
     *
     * @return int Number of cache files deleted
     */
    public function clear()
    {
        $files = glob($this->cacheDir . '*.cache');
        $count = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Delete a specific cache key
     *
     * @param string $key Cache key to delete
     * @return bool Success status
     */
    public function delete($key)
    {
        $file = $this->getCacheFile($key);

        if (file_exists($file)) {
            return @unlink($file);
        }

        return true; // Already deleted
    }

    /**
     * Get cache statistics
     *
     * @return array<string, int|float> Cache stats (file_count, total_size_bytes, oldest_file_age)
     */
    public function getStats(): array
    {
        $files = glob($this->cacheDir . '*.cache');
        $totalSize = 0;
        $oldestAge = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
                $age = time() - filemtime($file);
                $oldestAge = max($oldestAge, $age);
            }
        }

        return [
            'file_count' => count($files),
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'oldest_file_age_seconds' => $oldestAge,
            'oldest_file_age_hours' => round($oldestAge / 3600, 1)
        ];
    }

    /**
     * Get cache file path for a key
     *
     * @param string $key Cache key
     * @return string Full path to cache file
     */
    private function getCacheFile($key)
    {
        return $this->cacheDir . md5($key) . '.cache';
    }
}
