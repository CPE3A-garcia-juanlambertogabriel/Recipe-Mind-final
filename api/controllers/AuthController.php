<?php
require_once __DIR__ . '/../config/database.php';

class AuthController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function register($data) {
        $username = $data['username'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        
        if (!$username || !$email || !$password) {
            $this->sendErrorResponse('Username, email, and password are required', 400);
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendErrorResponse('Invalid email format', 400);
        }
        
        if (strlen($password) < 6) {
            $this->sendErrorResponse('Password must be at least 6 characters', 400);
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $this->db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $passwordHash]);
            
            $userId = $this->db->lastInsertId();
            $token = $this->generateToken($userId);
            
            $this->sendSuccessResponse([
                'message' => 'User registered successfully',
                'user_id' => $userId,
                'token' => $token
            ]);
            
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $this->sendErrorResponse('Username or email already exists', 409);
            } else {
                $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
            }
        }
    }
    
    public function login($data) {
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        
        if (!$email || !$password) {
            $this->sendErrorResponse('Email and password are required', 400);
        }
        
        try {
            $stmt = $this->db->prepare("SELECT id, username, email, password_hash FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password_hash'])) {
                $this->sendErrorResponse('Invalid email or password', 401);
            }
            
            $token = $this->generateToken($user['id']);
            
            $this->sendSuccessResponse([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email']
                ],
                'token' => $token
            ]);
            
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    
    public function verifyToken($token) {
        try {
            $decoded = base64_decode($token);
            $parts = explode(':', $decoded);
            
            if (count($parts) !== 2) {
                return null;
            }
            
            $userId = $parts[0];
            $timestamp = $parts[1];
            
            // Token expires after 7 days
            if (time() - $timestamp > 604800) {
                return null;
            }
            
            $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            return $user ? $user['id'] : null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function generateToken($userId) {
        $timestamp = time();
        return base64_encode($userId . ':' . $timestamp);
    }
    
    private function sendSuccessResponse($data) {
        http_response_code(200);
        echo json_encode($data);
        exit();
    }
    
    private function sendErrorResponse($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit();
    }
}