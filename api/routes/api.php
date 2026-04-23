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
        
        // Extract query string for ID parameters
        $query = parse_url($uri, PHP_URL_QUERY);
        parse_str($query, $queryParams);
        
        foreach ($this->routes as $route) {
            // Try exact match first
            if ($route['method'] === $method && $route['path'] === $path) {
                $this->callHandler($route['handler'], $queryParams);
                return;
            }
            
            // Try pattern matching for dynamic routes like /meal-plans/{id}
            $pattern = preg_replace('#\{[^}]+\}#', '([^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';
            if ($route['method'] === $method && preg_match($pattern, $path, $matches)) {
                array_shift($matches); // Remove full match
                $this->callHandler($route['handler'], array_merge($queryParams, ['id' => $matches[0] ?? null]));
                return;
            }
        }
        
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
        exit();
    }
    
    private function callHandler($handler, $queryParams = []) {
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
        $params = array_merge($_GET, $_POST, $queryParams);
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = array_merge($params, $input);
        
        // Map 'id' to 'plan_id' for meal plan routes
        if (isset($data['id']) && in_array($methodName, ['getSavedMealPlan', 'deleteSavedMealPlan'])) {
            $data['plan_id'] = $data['id'];
        }
        
        // ============ AUTHENTICATION MIDDLEWARE ============
        // Routes that require authentication
        $authRequiredRoutes = [
            'addFavorite', 'getFavorites', 'deleteFavorite',
            'getSavedMealPlans', 'getSavedMealPlan', 'deleteSavedMealPlan'
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

// Google OAuth routes
$router->add('GET', '/auth/google', 'GoogleAuthController@redirectToGoogle');
$router->add('GET', '/auth/google/callback', 'GoogleAuthController@handleCallback');

// API routes
$router->add('GET', '/search', 'SearchController@searchRecipes');
$router->add('GET', '/nutrition', 'NutritionController@getNutritionInfo');
$router->add('POST', '/meal-plan', 'MealPlanController@generateMealPlan');
$router->add('GET', '/meal-plans', 'MealPlanController@getSavedMealPlans');
$router->add('GET', '/meal-plans/{id}', 'MealPlanController@getSavedMealPlan');
$router->add('DELETE', '/meal-plans/{id}', 'MealPlanController@deleteSavedMealPlan');

// Favorite recipes routes (require authentication)
$router->add('GET', '/user/favorites', 'FavoritesController@getFavorites');
$router->add('POST', '/user/favorites', 'FavoritesController@addFavorite');
$router->add('DELETE', '/user/favorites', 'FavoritesController@deleteFavorite');
