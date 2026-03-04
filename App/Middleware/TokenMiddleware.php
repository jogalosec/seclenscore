<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once '../includes/token.php';
require_once '../includes/errorResponder.php';
require_once '../includes/operationsDB.php';

class TokenMiddleware
{
    protected $basePath;
    protected $errorResponder;

    const SESSION_EXPIRED_MESSAGE = "Sesión expirada o no encontrada";

    public function __construct($basePath)
    {
        $this->basePath = $basePath;
        $this->errorResponder = new ErrorResponder();
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $path = $request->getUri()->getPath();
        $basePath = $this->basePath;

        if ($this->isPathAllowed($basePath . $path)) {
            return $handler->handle($request);
        }

        $result = $this->authenticate($request, $path);

        if ($result instanceof Response) {
            return $result;
        }

        return $handler->handle($result);
    }


    private function isPathAllowed(string $fullPath): bool
    {
        $allowedPaths = [
            $this->basePath . '/login',
            $this->basePath . '/auth',
            $this->basePath . '/',
        ];

        return in_array($fullPath, $allowedPaths);
    }

    /**
     * Intenta autenticar al usuario usando diferentes métodos
     * @return Request|Response Devuelve la solicitud modificada si la autenticación es exitosa o una respuesta de error
     */
    private function authenticate(Request $request, string $path)
    {
        $privateKey = $this->getPrivateKey();

        $bearerResult = $this->authenticateWithBearer($request, $path);
        if ($bearerResult !== null) {
            return $bearerResult;
        }

        return $this->authenticateWithCookie($request, $path, $privateKey);
    }

    private function getPrivateKey(): string
    {
        $tokenConfig = new TokenConfig();
        $privateKey = $tokenConfig->getPrivateKey();
        $privateKeyDetails = openssl_pkey_get_details($privateKey);
        return $privateKeyDetails['key'];
    }

    /**
     * Intenta autenticar usando el token Bearer en el encabezado Authorization
     * @return Request|Response|null Devuelve la solicitud modificada, una respuesta de error, o null si no hay token Bearer
     */
    private function authenticateWithBearer(Request $request, string $path)
    {
        $bearer = $request->getHeader('Authorization');
        $result = null;

        if (!isset($bearer[0]) || strpos($bearer[0], "Bearer 11_") !== 0) {
            return null;
        }

        try {
            $bearer = explode(" ", $bearer[0]);

            if ($bearer[0] === "Bearer") {
                $db_user = new Usuarios('octopus_users');
                $user = $db_user->getUserByToken($bearer[1]);
                $time = date("Y-m-d H:i:s");
                $expired = $user[0]["expired"] ?? null;

                if (isset($user[0]) && $time < $expired) {
                    $result = $request->withAttribute('userID', ["data" => $user[0]["id"]]);
                } else {
                    $result = $this->errorResponder->createErrorResponse($path, self::SESSION_EXPIRED_MESSAGE, 401);
                }
            } else {
                $result = $this->errorResponder->createErrorResponse($path, self::SESSION_EXPIRED_MESSAGE, 401);
            }
        } catch (Exception $e) {
            error_log($e);
            $result = $this->errorResponder->createErrorResponse($path, "Error inesperado código: " . $e->getCode(), 401);
        }

        return $result;
    }

    /**
     * Intenta autenticar usando el token en la cookie
     * @return Request|Response Devuelve la solicitud modificada o una respuesta de error
     */
    private function authenticateWithCookie(Request $request, string $path, string $privateKey)
    {
        $token = $request->getCookieParams()['sst'] ?? null;

        if (!$token) {
            return $this->errorResponder->createErrorResponse($path, self::SESSION_EXPIRED_MESSAGE, 401);
        }

        try {
            $payload = JWT::decode($token, new Key($privateKey, 'RS256'));
            $refreshedToken = refreshToken([$payload]);
            return $request->withAttribute('userID', $refreshedToken);
        } catch (Exception $e) {
            error_log($e);
            return $this->errorResponder->createErrorResponse($path, "Error inesperado código: " . $e->getCode(), 401);
        }
    }
}
