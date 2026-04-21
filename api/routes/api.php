<?php
class Router {
    private $routes = [];
    
    public function add($method, $path, $handler) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }
    
    public function dispatch($method, $uri) {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = preg_replace('#^/recipe-mind-final/api#', '', $path);
        $path = str_replace('/api', '', $path);
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                $this->callHandler($route['handler']);
                return;
            }
        }
        
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
        exit();
    }
    
    private function callHandler($handler) {
        list($controllerName, $methodName) = explode('@', $handler);
        $controllerFile = __DIR__ . '/../controllers/' . $controllerName . '.php';
        
        if (!file_exists($controllerFile)) {
            http_response_code(500);
            echo json_encode(['error' => 'Controller not found: ' . $controllerName]);
            exit();
        }
        
        require_once $controllerFile;
        $controller = new $controllerName();
        
        // Get request data
        $params = array_merge($_GET, $_POST);
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = array_merge($params, $input);
        
        // ============ AUTHENTICATION MIDDLEWARE ============
        // Routes that require authentication
        $authRequiredRoutes = [
            'addFavorite', 'getFavorites', 'deleteFavorite',
            'saveRecipe', 'getSavedRecipes', 'deleteSavedRecipe',
            'getSavedMealPlans'
        ];
        
        // Get Authorization header
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $authHeader);
        
        // Check if this route requires authentication
        if (in_array($methodName, $authRequiredRoutes)) {
            if (empty($token)) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized - Please login first']);
                exit();
            }
            
            // Verify the token
            require_once __DIR__ . '/../controllers/AuthController.php';
            $authController = new AuthController();
            $userId = $authController->verifyToken($token);
            
            if (!$userId) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized - Invalid or expired token']);
                exit();
            }
            
            // Attach user_id to the data
            $data['user_id'] = $userId;
        }
        // ==================================================
        
        // Call the controller method
        call_user_func([$controller, $methodName], $data);
    }
}

// ============ ROUTE DEFINITIONS ============
$router = new Router();

// Auth routes
$router->add('POST', '/auth/register', 'AuthController@register');
$router->add('POST', '/auth/login', 'AuthController@login');

// API routes
$router->add('GET', '/search', 'SearchController@searchRecipes');
$router->add('GET', '/nutrition', 'NutritionController@getNutritionInfo');
$router->add('POST', '/meal-plan', 'MealPlanController@generateMealPlan');
$router->add('GET', '/meal-plans', 'MealPlanController@getSavedMealPlans');

// Favorite recipes routes (require authentication)
$router->add('GET', '/user/favorites', 'FavoritesController@getFavorites');
$router->add('POST', '/user/favorites', 'FavoritesController@addFavorite');
$router->add('DELETE', '/user/favorites', 'FavoritesController@deleteFavorite');