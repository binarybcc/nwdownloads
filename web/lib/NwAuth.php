<?php

namespace CirculationDashboard;

/**
 * Newzware Authentication Client
 *
 * Handles all communication with the Newzware authentication API:
 * - Password-based login
 * - Hash-based verification (cookie auto-login)
 * - Hash renewal (expired hash -> fresh hash)
 */

class NwAuth
{
    /**
     * Authenticate with username and password
     *
     * @param string $loginId  Newzware login ID
     * @param string $password User password
     * @return array ['success' => bool, 'user' => array|null, 'error' => string|null]
     */
    public function loginWithPassword($loginId, $password)
    {
        $params = [
            'site'     => NW_SITE_ID,
            'login_id' => $loginId,
            'password' => $password,
        ];

        return $this->authenticate($params);
    }

    /**
     * Authenticate with login ID and hash (cookie-based auto-login)
     *
     * @param string $loginId Newzware login ID
     * @param string $hash    Hash code from cookie
     * @return array ['success' => bool, 'user' => array|null, 'error' => string|null]
     */
    public function verifyHash($loginId, $hash)
    {
        $params = [
            'site'     => NW_SITE_ID,
            'login_id' => $loginId,
            'hash'     => $hash,
        ];

        return $this->authenticate($params);
    }

    /**
     * Renew an expired hash using the old hash
     *
     * Newzware hashes expire at midnight. This endpoint issues a fresh hash
     * as long as the user's subscription is still valid.
     *
     * @param string $loginId Newzware login ID
     * @param string $oldHash Expired hash code
     * @return string|null    New hash code, or null if renewal failed
     */
    public function renewHash($loginId, $oldHash)
    {
        $params = [
            'site'     => NW_SITE_ID,
            'login_id' => $loginId,
            'oH'       => $oldHash,
        ];

        $url = NW_HASH_URL . '?' . http_build_query($params);
        $response = $this->curlRequest($url);

        if (!$response) {
            return null;
        }

        try {
            $xml = new SimpleXMLElement($response);
            $exitCode = (string) ($xml->{'exit-code'}['code'] ?? '');

            if ($exitCode === '0') {
                return (string) $xml->hash['value'];
            }
        } catch (Exception $e) {
            error_log('NwAuth: Hash renewal XML parse error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Attempt auto-login using Newzware cookies
     *
     * @return array ['success' => bool, 'user' => array|null, 'error' => string|null]
     */
    public function tryAutoLogin()
    {
        $loginId = $_COOKIE[NW_COOKIE_LOGIN] ?? null;
        $hash    = $_COOKIE[NW_COOKIE_HASH] ?? null;

        if (!$loginId || !$hash) {
            return ['success' => false, 'user' => null, 'error' => 'No Newzware cookies found'];
        }

        $loginId = urldecode($loginId);

        // Step 1: Try the hash directly
        $result = $this->verifyHash($loginId, $hash);
        if ($result['success']) {
            return $result;
        }

        // Step 2: Hash may be expired — try renewal
        $newHash = $this->renewHash($loginId, $hash);
        if ($newHash) {
            $result = $this->verifyHash($loginId, $newHash);
            if ($result['success']) {
                $this->updateHashCookie($newHash);
                return $result;
            }
        }

        return ['success' => false, 'user' => null, 'error' => 'Hash verification failed'];
    }

    /**
     * Create a PHP session for an authenticated user
     *
     * @param array $user User data from authenticate()
     */
    public function createSession($user)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['logged_in']     = true;
        $_SESSION['user']          = $user['login'];
        $_SESSION['user_type']     = $user['usertype'];
        $_SESSION['login_time']    = time();
        $_SESSION['last_activity'] = time();

        session_regenerate_id(true);
    }

    /**
     * Set Newzware cookies on the parent domain after a password login
     *
     * @param array $user User data (must contain 'hash' and 'login')
     */
    public function setNewzwareCookies($user)
    {
        if (empty($user['hash']) || empty($user['login'])) {
            return;
        }

        $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $midnight = strtotime('tomorrow');
        $domain   = $this->getParentDomain();

        $cookieOptions = [
            'expires'  => $midnight,
            'path'     => '/',
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly'  => true,
            'samesite' => 'Lax',
        ];

        setcookie(NW_COOKIE_HASH, $user['hash'], $cookieOptions);
        setcookie(NW_COOKIE_LOGIN, $user['login'], $cookieOptions);
    }

    // ─────────────────────────────────────────────────
    // Private methods
    // ─────────────────────────────────────────────────

    private function authenticate($params)
    {
        $url = NW_AUTH_URL . '?' . http_build_query($params);
        $response = $this->curlRequest($url);

        if (!$response) {
            return [
                'success' => false,
                'user'    => null,
                'error'   => 'Unable to connect to authentication server.',
            ];
        }

        try {
            $xml = new SimpleXMLElement($response);

            $isAuthenticated = (string) $xml->authenticated === 'Yes';
            $userType        = (string) $xml->usertype;

            if ($isAuthenticated && in_array($userType, NW_ALLOWED_USER_TYPES, true)) {
                return [
                    'success' => true,
                    'user'    => [
                        'login'      => (string) $xml->login,
                        'usertype'   => $userType,
                        'first_name' => (string) $xml->{'first-name'},
                        'last_name'  => (string) $xml->{'last-name'},
                        'email'      => (string) $xml->email,
                        'account'    => (string) $xml->accountnum,
                        'hash'       => (string) $xml->hash,
                        'auto_login' => isset($params['hash']),
                    ],
                    'error' => null,
                ];
            }

            $exitCode = (string) $xml->{'exit-code'};

            if ($isAuthenticated && !in_array($userType, NW_ALLOWED_USER_TYPES, true)) {
                $error = 'Access denied. Your account type is not authorized for this application.';
            } elseif ($exitCode === '2') {
                $error = 'No active subscription found.';
            } elseif ($exitCode === '200') {
                $error = 'Password reset required. Please reset your password in Newzware first.';
            } else {
                $error = 'Invalid credentials or unauthorized account.';
            }

            return ['success' => false, 'user' => null, 'error' => $error];
        } catch (Exception $e) {
            error_log('NwAuth: XML parse error: ' . $e->getMessage());
            return [
                'success' => false,
                'user'    => null,
                'error'   => 'System error processing authentication response.',
            ];
        }
    }

    private function curlRequest($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            if ($curlError) {
                error_log('NwAuth: cURL error: ' . $curlError);
            }
            return false;
        }

        return $response;
    }

    private function updateHashCookie($newHash)
    {
        $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $domain   = COOKIE_DOMAIN;
        $midnight = strtotime('tomorrow');

        setcookie(NW_COOKIE_HASH, $newHash, [
            'expires'  => $midnight,
            'path'     => '/',
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
    }

    private function getParentDomain()
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $host = strtolower(explode(':', $host)[0]);
        $parts = explode('.', $host);

        if (count($parts) <= 2) {
            return '.' . $host;
        }

        return '.' . implode('.', array_slice($parts, -2));
    }
}
