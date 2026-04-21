<?php
class NutritionController {
    
    public function getNutritionInfo($params) {
        if (!isset($params['query'])) {
            $this->sendErrorResponse('Missing query parameter. Please provide a food item.', 400);
        }
        
        $query = trim($params['query']);
        $apiKey = $_ENV['USDA_API_KEY'] ?? null;
        
        if (!$apiKey) {
            $this->sendErrorResponse('USDA API key not configured.', 500);
        }
        
        try {
            $searchResult = $this->searchFood($query, $apiKey);
            if (empty($searchResult['foods'])) {
                $this->sendErrorResponse("No food found for '{$query}'.", 404);
            }
            
            $food = $searchResult['foods'][0];
            $nutritionData = $this->getFoodNutrition($food['fdcId'], $apiKey);
            $nutrients = $this->extractNutrients($nutritionData);
            
            $response = [
                'query' => $query,
                'food' => $food['description'],
                'serving_size' => '100g',
                'calories' => $nutrients['calories'] ?? 'N/A',
                'protein_g' => $nutrients['protein'] ?? 'N/A',
                'carbohydrates_g' => $nutrients['carbs'] ?? 'N/A',
                'fat_g' => $nutrients['fat'] ?? 'N/A',
                'source' => 'USDA FoodData Central'
            ];
            
            $this->sendSuccessResponse($response);
        } catch (Exception $e) {
            $this->sendErrorResponse($e->getMessage(), 500);
        }
    }
    
    private function searchFood($query, $apiKey) {
        $url = "https://api.nal.usda.gov/fdc/v1/foods/search?api_key={$apiKey}&query=" . urlencode($query) . "&pageSize=1";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("USDA API error (HTTP {$httpCode})");
        }
        return json_decode($response, true);
    }
    
    private function getFoodNutrition($fdcId, $apiKey) {
        $url = "https://api.nal.usda.gov/fdc/v1/food/{$fdcId}?api_key={$apiKey}";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }
    
    private function extractNutrients($nutritionData) {
        $nutrients = ['calories' => null, 'protein' => null, 'carbs' => null, 'fat' => null];
        if (!isset($nutritionData['foodNutrients'])) return $nutrients;
        
        foreach ($nutritionData['foodNutrients'] as $nutrient) {
            $usdaTag = $nutrient['nutrient']['usdaTag'] ?? '';
            $name = $nutrient['nutrient']['name'] ?? '';
            $value = $nutrient['amount'] ?? null;
            
            if ($usdaTag === 'ENERC_KCAL' || $name === 'Energy') {
                $nutrients['calories'] = round($value, 1);
            } elseif ($usdaTag === 'PROCNT' || stripos($name, 'Protein') !== false) {
                $nutrients['protein'] = round($value, 1);
            } elseif ($usdaTag === 'CHOCDF' || stripos($name, 'Carbohydrate') !== false) {
                $nutrients['carbs'] = round($value, 1);
            } elseif ($usdaTag === 'FAT' || stripos($name, 'Total lipid') !== false || stripos($name, 'Fat') !== false) {
                $nutrients['fat'] = round($value, 1);
            }
        }
        return $nutrients;
    }
    
    private function sendSuccessResponse($data) {
        http_response_code(200);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit();
    }
    
    private function sendErrorResponse($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit();
    }
}
?>