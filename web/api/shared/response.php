<?php

/**
 * Response Helpers Module
 * Provides standardized JSON response functions for API endpoints
 */

/**
 * Send successful JSON response
 * @param mixed $data Data to send in response
 * @return void
 */
function sendResponse(mixed $data): void
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send error JSON response
 * @param string $message Error message
 * @param int $code HTTP status code (default: 400)
 * @return void
 */
function sendError(string $message, int $code = 400): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message]);
    exit;
}

/**
 * Send not found error
 * @param string $resource Resource type that wasn't found
 * @return void
 */
function sendNotFound(string $resource = 'Resource'): void
{
    sendError("$resource not found", 404);
}

/**
 * Send bad request error
 * @param string $message Error message
 * @return void
 */
function sendBadRequest(string $message): void
{
    sendError($message, 400);
}

/**
 * Send server error
 * @param string $message Error message
 * @return void
 */
function sendServerError(string $message = 'Internal server error'): void
{
    sendError($message, 500);
}
