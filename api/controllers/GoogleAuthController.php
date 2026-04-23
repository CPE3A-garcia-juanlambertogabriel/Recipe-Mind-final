<?php
require_once __DIR__ . '/../config/database.php';

class GoogleAuthController {
    private $db;
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
        $this->redirectUri = $_ENV['GOOGLE_REDIRECT_URI'] ?? '';
    }

    // GET /auth/google - Redirect to Google OAuth
    public function redirectToGoogle() {
        if (empty($this->clientId)) {
            http_response_code(500);
            echo json_encode(['error' => 'Google OAuth not configured. Please set GOOGLE_CLIENT_ID in .env']);
            exit();
        }

        $state = bin2hex(random_bytes(16));
        setcookie('google_oauth_state', $state, time() + 300, '/');

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'select_account'
        ];

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        header('Location: ' . $url);
        exit();
    }

    // GET /auth/google/callback - Handle OAuth callback
    public function handleCallback($data) {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            http_response_code(500);
            echo json_encode(['error' => 'Google OAuth not configured']);
            exit();
        }

        // Verify state
        $state = $_GET['state'] ?? '';
        $savedState = $_COOKIE['google_oauth_state'] ?? '';
        if ($state !== $savedState) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid state parameter']);
            exit();
        }
        setcookie('google_oauth_state', '', time() - 3600, '/');

        $code = $_GET['code'] ?? null;
        if (!$code) {
            http_response_code(400);
            echo json_encode(['error' => 'No authorization code received']);
            exit();
        }

        // Exchange code for tokens
        $tokenResponse = $this->exchangeCodeForToken($code);
        if (!$tokenResponse) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get access token from Google']);
            exit();
        }

        // Get user info from Google
        $userInfo = $this->getUserInfo($tokenResponse['access_token']);
        if (!$userInfo) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get user info from Google']);
            exit();
        }

        // Find or create user in database
        $user = $this->findOrCreateUser($userInfo);

        // Generate our own token
        $token = $this->generateToken($user['id']);

        // Redirect to frontend login page with token (login.html will store it and redirect to dashboard)
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost/recipe-mind-final';
        $redirectUrl = $frontendUrl . '/login.html?token=' . urlencode($token) .
                       '&user_id=' . urlencode($user['id']) .
                       '&email=' . urlencode($user['email']) .
                       '&username=' . urlencode($user['username']);

        header('Location: ' . $redirectUrl);
        exit();
    }

    private function exchangeCodeForToken($code) {
        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri
        ];

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    private function getUserInfo($accessToken) {
        $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    private function findOrCreateUser($userInfo) {
        $email = $userInfo['email'];
        $name = $userInfo['name'] ?? explode('@', $email)[0];

        try {
            // Check if user exists
            $stmt = $this->db->prepare("SELECT id, username, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                return $user;
            }

            // Create new user
            $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $passwordHash]);

            $userId = $this->db->lastInsertId();

            return [
                'id' => $userId,
                'username' => $name,
                'email' => $email
            ];

        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                // Duplicate entry - user was created between check and insert
                $stmt = $this->db->prepare("SELECT id, username, email FROM users WHERE email = ?");
                $stmt->execute([$email]);
                return $stmt->fetch();
            }
            throw $e;
        }
    }

    private function generateToken($userId) {
        $timestamp = time();
        return base64_encode($userId . ':' . $timestamp);
    }
}
