<?php
require_once __DIR__ . '/../config/database.php';

class SearchController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function searchRecipes($params) {
        if (!isset($params['ingredients'])) {
            $this->sendErrorResponse('Missing ingredients parameter', 400);
        }
        
        $ingredients = $params['ingredients'];
        $number = $params['number'] ?? 12;
        $apiKey = $_ENV['SPOONACULAR_API_KEY'] ?? null;
        
        if (!$apiKey) {
            $this->sendErrorResponse('Spoonacular API key not configured', 500);
        }
        
        try {
            $url = 'https://api.spoonacular.com/recipes/findByIngredients?' . http_build_query([
                'ingredients' => $ingredients,
                'number' => $number,
                'ranking' => 1,
                'ignorePantry' => 'true',
                'apiKey' => $apiKey
            ]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                $this->sendErrorResponse('cURL error: ' . $curlError, 500);
            }
            
            if ($httpCode !== 200) {
                $this->sendErrorResponse('Spoonacular API error', $httpCode, json_decode($response, true));
            }
            
            // Save to search history if user is logged in
            $userId = $params['user_id'] ?? null;
            if ($userId) {
                $data = json_decode($response, true);
                $this->saveSearchHistory($userId, $ingredients, count($data));
            }
            
            $this->sendSuccessResponse(json_decode($response, true));
            
        } catch (Exception $e) {
            $this->sendErrorResponse('Internal server error: ' . $e->getMessage(), 500);
        }
    }
    
    private function saveSearchHistory($userId, $ingredients, $resultsCount) {
        try {
            $stmt = $this->db->prepare("INSERT INTO search_history (user_id, ingredients, results_count) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $ingredients, $resultsCount]);
        } catch (PDOException $e) {
            // Log error but don't fail the request
            error_log('Failed to save search history: ' . $e->getMessage());
        }
    }
    
    private function sendSuccessResponse($data) {
        http_response_code(200);
        echo json_encode($data);
        exit();
    }
    
    private function sendErrorResponse($message, $code = 400, $details = null) {
        http_response_code($code);
        $response = ['error' => $message];
        if ($details) {
            $response['details'] = $details;
        }
        echo json_encode($response);
        exit();
    }
}