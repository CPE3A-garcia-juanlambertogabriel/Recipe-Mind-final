<?php
header('Content-Type: application/json');

// Load environment variables
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// CORS handling
require_once __DIR__ . '/middleware/CorsMiddleware.php';
CorsMiddleware::handle();

// Route handling
require_once __DIR__ . '/routes/api.php';

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);