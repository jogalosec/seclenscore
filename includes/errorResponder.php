<?php

use Psr\Http\Message\ResponseInterface as Response;

class ErrorResponder
{
    public function createErrorResponse(string $requestedPath, string $message, int $statusCode): Response
    {
        $response = new \Slim\Psr7\Response();
        if (preg_match('/^\/api\//', $requestedPath)) {
            // Respuesta JSON para rutas que comienzan con /api/
            $response->getBody()->write(json_encode([
                'error' => true,
                'message' => $message
            ]));
            return $response->withStatus($statusCode)
                ->withHeader('Content-Type', 'application/json');
        } else {
            // Respuesta HTML para otras rutas
            ob_start();
            require_once '../templates/error.phtml';
            $html = ob_get_clean();

            $response->getBody()->write($html);

            $response = new \Slim\Psr7\Response();
            $response->getBody()->write($html);
            return $response
                ->withHeader('Content-Type', 'text/html')
                ->withStatus(200);
        }
    }
}
