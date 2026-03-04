<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require_once '../includes/operationsDB.php';
require_once '../includes/errorResponder.php';

class PermissionsMiddleware
{
    protected $basePath;
    protected $errorResponder;

    public function __construct($basePath)
    {
        $this->basePath = $basePath;
        $this->errorResponder = new ErrorResponder();
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $requestedPath = $request->getUri()->getPath();
        $userId = $request->getAttribute('userID');
        $response = null;

        if (!$userId) {
            $response = $handler->handle($request);
        } else {
            $userRoles = $this->getUserRoles($userId);

            if (empty($userRoles)) {
                $response = $this->createAccessDeniedResponse($requestedPath);
            } else {
                $relativePath = $requestedPath;
                if (!empty($this->basePath) && strpos($requestedPath, $this->basePath) === 0) {
                    $relativePath = substr($requestedPath, strlen($this->basePath));
                }

                $accessiblePaths = $this->getPathsForRoles($userRoles);

                if (!$this->isPathAccessibleForRoles($relativePath, $accessiblePaths)) {
                    $response = $this->createAccessDeniedResponse($requestedPath);
                } else {
                    $response = $handler->handle($request);
                }
            }
        }

        return $response;
    }

    private function createAccessDeniedResponse(string $path): Response
    {
        return $this->errorResponder->createErrorResponse($path, 'No tienes permiso para acceder al recurso "' . $path . '"', 403);
    }


    private function getPathsForRoles($roles = [])
    {
        $db = new Usuarios('octopus_users');
        return $db->getPathsByRole(array_column($roles, 'role_id'));
    }

    private function getUserRoles($token)
    {
        $db = new Usuarios('octopus_users');
        return $db->getRolesByUser($token["data"]);
    }

    private function isPathAccessibleForRoles(string $path, array $accessiblePaths): bool
    {
        foreach ($accessiblePaths as $accessiblePath) {
            if (fnmatch($accessiblePath['route'], $path)) {
                return true;
            }
        }

        return false;
    }
}
