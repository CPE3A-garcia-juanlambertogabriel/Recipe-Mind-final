<?php
require_once __DIR__ . '/../config/database.php';

class MealPlanController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function generateMealPlan($data) {
        if (!isset($data['ingredients'])) {
            $this->sendErrorResponse('Missing ingredients', 400);
        }
        
        $ingredients = $data['ingredients'];
        $diet = $data['diet'] ?? 'balanced';
        $skill = $data['skill'] ?? 'beginner';
        $restrictions = $data['restrictions'] ?? '';
        $apiKey = $_ENV['GEMINI_API_KEY'] ?? null;
        
        if (!$apiKey) {
            $this->sendErrorResponse('Gemini API key not configured. Get one from https://makersuite.google.com/app/apikey', 500);
        }
        
        $prompt = $this->buildPrompt($ingredients, $diet, $skill, $restrictions);
        
        try {
            $response = $this->callGeminiAPI($apiKey, $prompt);
            $planData = $this->parseGeminiResponse($response);
            
            $userId = $data['user_id'] ?? null;
            if ($userId) {
                $this->saveMealPlan($userId, $planData, $ingredients, $diet, $skill, $restrictions);
            }
            
            $this->sendSuccessResponse($planData);
            
        } catch (Exception $e) {
            $this->sendErrorResponse($e->getMessage(), 500);
        }
    }
    
    private function callGeminiAPI($apiKey, $prompt) {
        // Use the available models from your list (in order of preference)
        $models = [
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-flash-latest',
            'gemini-pro-latest'
        ];
        
        $lastError = null;
        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;
            
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 2048,
                ]
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 60
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                error_log("Gemini: Successfully using model $model");
                return json_decode($response, true);
            }
            
            $lastError = "Model $model failed with HTTP $httpCode";
        }
        
        throw new Exception('No Gemini models available. Last error: ' . $lastError);
    }
    
    private function parseGeminiResponse($response) {
        $rawText = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        if (empty($rawText)) {
            throw new Exception('Empty response from Gemini API');
        }
        
        // Clean markdown code blocks
        $cleaned = preg_replace('/```json\s*|\s*```/', '', $rawText);
        $cleaned = trim($cleaned);
        
        // Extract JSON object if there's extra text
        if (preg_match('/\{[\s\S]*\}/', $cleaned, $matches)) {
            $cleaned = $matches[0];
        }
        
        $planData = json_decode($cleaned, true);
        
        if (!$planData) {
            error_log('Failed to parse JSON. Raw response: ' . $rawText);
            throw new Exception('Failed to parse meal plan JSON. Raw response: ' . substr($rawText, 0, 200));
        }
        
        if (!isset($planData['days']) || !is_array($planData['days']) || count($planData['days']) !== 7) {
            throw new Exception('Invalid meal plan structure: missing or invalid days array');
        }
        
        return $planData;
    }
    
    public function getSavedMealPlans($params) {
        if (!isset($params['user_id'])) {
            $this->sendErrorResponse('User ID required', 401);
        }
        
        $userId = $params['user_id'];
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM meal_plans WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$userId]);
            $plans = $stmt->fetchAll();
            
            $this->sendSuccessResponse($plans);
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    
    private function buildPrompt($ingredients, $diet, $skill, $restrictions) {
        return "You are a professional nutritionist and chef. Create a detailed 7-day meal plan.

User's available ingredients: $ingredients
Diet preference: $diet
Cooking skill level: $skill
Restrictions/allergies: " . ($restrictions ?: 'None') . "

Generate a 7-day meal plan using primarily the available ingredients. You can suggest additional common pantry items as needed.

Respond ONLY with a valid JSON object in this exact format — no extra text, no markdown, no explanations:

{
  \"days\": [
    {
      \"breakfast\": \"Recipe name here\",
      \"lunch\": \"Recipe name here\",
      \"dinner\": \"Recipe name here\"
    },
    {
      \"breakfast\": \"Recipe name here\",
      \"lunch\": \"Recipe name here\",
      \"dinner\": \"Recipe name here\"
    },
    {
      \"breakfast\": \"Recipe name here\",
      \"lunch\": \"Recipe name here\",
      \"dinner\": \"Recipe name here\"
    },
    {
      \"breakfast\": \"Recipe name here\",
      \"lunch\": \"Recipe name here\",
      \"dinner\": \"Recipe name here\"
    },
    {
      \"breakfast\": \"Recipe name here\",
      \"lunch\": \"Recipe name here\",
      \"dinner\": \"Recipe name here\"
    },
    {
      \"breakfast\": \"Recipe name here\",
      \"lunch\": \"Recipe name here\",
      \"dinner\": \"Recipe name here\"
    },
    {
      \"breakfast\": \"Recipe name here\",
      \"lunch\": \"Recipe name here\",
      \"dinner\": \"Recipe name here\"
    }
  ],
  \"tips\": \"2-3 helpful tips about this meal plan, ingredient prep, or nutritional advice. Keep it friendly and practical.\"
}

Make meal names specific and appealing. Respect the diet preference and any restrictions strictly.

Return ONLY the JSON object, nothing else.";
    }
    
    private function saveMealPlan($userId, $planData, $ingredients, $diet, $skill, $restrictions) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO meal_plans (user_id, plan_data, ingredients, diet, skill_level, restrictions) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                json_encode($planData),
                $ingredients,
                $diet,
                $skill,
                $restrictions
            ]);
        } catch (PDOException $e) {
            error_log('Failed to save meal plan: ' . $e->getMessage());
        }
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
?>