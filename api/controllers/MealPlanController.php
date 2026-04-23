<?php
require_once __DIR__ . '/../config/database.php';

// Load environment variables from .env file
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    foreach (explode("\n", $envContent) as $line) {
        $line = trim($line);
        if ($line && strpos($line, '#') !== 0 && strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!getenv($key)) {
                putenv("$key=$value");
            }
        }
    }
}

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

        $prompt = $this->buildPrompt($ingredients, $diet, $skill, $restrictions);

        try {
            $response = $this->callGeminiAI($prompt);
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

    private function callGeminiAI($prompt) {
        // Google Gemini API
        $apiKey = getenv('GEMINI_API_KEY') ?: 'YOUR_GEMINI_API_KEY';

        if ($apiKey === 'YOUR_GEMINI_API_KEY') {
            throw new Exception('Gemini API key not configured. Set GEMINI_API_KEY environment variable or add to .env file');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

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
                'maxOutputTokens' => 2048
            ]
        ];

        $ch = curl_init($url);
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

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? 'Unknown error';
            throw new Exception('Gemini API failed with HTTP ' . $httpCode . ': ' . $errorMsg);
        }

        return json_decode($response, true);
    }

    private function parseGeminiResponse($response) {
        // Gemini returns text in candidates[0].content.parts[0].text
        $rawText = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty($rawText)) {
            throw new Exception('Empty response from Gemini AI');
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

    public function getSavedMealPlans($data) {
        if (!isset($data['user_id'])) {
            $this->sendErrorResponse('User ID required', 401);
        }

        try {
            $stmt = $this->db->prepare("SELECT id, user_id, plan_data, ingredients, diet, skill_level, restrictions, created_at FROM meal_plans WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$data['user_id']]);
            $plans = $stmt->fetchAll();

            // Parse JSON data for each plan
            foreach ($plans as &$plan) {
                $plan['plan_data'] = json_decode($plan['plan_data'], true);
            }

            $this->sendSuccessResponse($plans);
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    
    public function getSavedMealPlan($data) {
        if (!isset($data['user_id'])) {
            $this->sendErrorResponse('User ID required', 401);
        }
        if (!isset($data['plan_id'])) {
            $this->sendErrorResponse('Plan ID required', 400);
        }
        
        try {
            $stmt = $this->db->prepare("SELECT id, user_id, plan_data, ingredients, diet, skill_level, restrictions, created_at FROM meal_plans WHERE id = ? AND user_id = ?");
            $stmt->execute([$data['plan_id'], $data['user_id']]);
            $plan = $stmt->fetch();
            
            if (!$plan) {
                $this->sendErrorResponse('Plan not found', 404);
            }
            
            // Parse JSON data
            $plan['plan_data'] = json_decode($plan['plan_data'], true);
            
            $this->sendSuccessResponse($plan);
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    
    public function deleteSavedMealPlan($data) {
        if (!isset($data['user_id'])) {
            $this->sendErrorResponse('User ID required', 401);
        }
        if (!isset($data['plan_id'])) {
            $this->sendErrorResponse('Plan ID required', 400);
        }
        
        try {
            $stmt = $this->db->prepare("DELETE FROM meal_plans WHERE id = ? AND user_id = ?");
            $stmt->execute([$data['plan_id'], $data['user_id']]);
            
            if ($stmt->rowCount() === 0) {
                $this->sendErrorResponse('Plan not found', 404);
            }
            
            $this->sendSuccessResponse(['message' => 'Meal plan deleted']);
        } catch (PDOException $e) {
            $this->sendErrorResponse('Database error: ' . $e->getMessage(), 500);
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
