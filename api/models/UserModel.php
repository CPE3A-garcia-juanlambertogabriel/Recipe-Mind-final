<?php
require_once __DIR__ . '/../config/database.php';

class UserModel {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function findById($id) {
        try {
            $stmt = $this->db->prepare("SELECT id, username, email, created_at FROM users WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('UserModel findById error: ' . $e->getMessage());
            return null;
        }
    }
    
    public function findByEmail($email) {
        try {
            $stmt = $this->db->prepare("SELECT id, username, email, password_hash FROM users WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('UserModel findByEmail error: ' . $e->getMessage());
            return null;
        }
    }
    
    public function getSavedRecipes($userId) {
        $stmt = $this->db->prepare("SELECT * FROM saved_recipes WHERE user_id = ? ORDER BY saved_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function saveRecipe($userId, $recipeId, $title, $image, $sourceUrl) {
        $stmt = $this->db->prepare("INSERT INTO saved_recipes (user_id, recipe_id, recipe_title, recipe_image, source_url) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE saved_at = CURRENT_TIMESTAMP");
        return $stmt->execute([$userId, $recipeId, $title, $image, $sourceUrl]);
    }

    public function deleteSavedRecipe($userId, $recipeId) {
        $stmt = $this->db->prepare("DELETE FROM saved_recipes WHERE user_id = ? AND recipe_id = ?");
        $stmt->execute([$userId, $recipeId]);
        return $stmt->rowCount() > 0;
    }
}