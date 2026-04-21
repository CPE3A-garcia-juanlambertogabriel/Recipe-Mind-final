<?php
require_once __DIR__ . '/../models/UserModel.php';

class FavoritesController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new UserModel();
    }
    
    // GET /user/favorites
    public function getFavorites($data) {
        if (!isset($data['user_id'])) {
            $this->sendErrorResponse('User ID required', 401);
        }
        $favorites = $this->userModel->getSavedRecipes($data['user_id']);
        $this->sendSuccessResponse($favorites);
    }
    
    // POST /user/favorites
    public function addFavorite($data) {
        if (!isset($data['user_id'])) {
            $this->sendErrorResponse('User ID required', 401);
        }
        if (!isset($data['recipe_id']) || !isset($data['recipe_title'])) {
            $this->sendErrorResponse('Missing recipe_id or recipe_title', 400);
        }
        
        $result = $this->userModel->saveRecipe(
            $data['user_id'],
            $data['recipe_id'],
            $data['recipe_title'],
            $data['recipe_image'] ?? null,
            $data['source_url'] ?? null
        );
        
        if ($result) {
            $this->sendSuccessResponse(['message' => 'Recipe saved to favorites']);
        } else {
            $this->sendErrorResponse('Failed to save recipe', 500);
        }
    }
    
    // DELETE /user/favorites?recipe_id=123
    public function deleteFavorite($data) {
        if (!isset($data['user_id'])) {
            $this->sendErrorResponse('User ID required', 401);
        }
        if (!isset($data['recipe_id'])) {
            $this->sendErrorResponse('Missing recipe_id', 400);
        }
        
        $result = $this->userModel->deleteSavedRecipe($data['user_id'], $data['recipe_id']);
        if ($result) {
            $this->sendSuccessResponse(['message' => 'Recipe removed from favorites']);
        } else {
            $this->sendErrorResponse('Recipe not found or could not be deleted', 404);
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