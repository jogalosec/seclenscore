"""
Middleware de permisos RBAC — equivalente a PermissionsMiddleware.php.
Verifica que el usuario autenticado tiene permiso para acceder a la ruta solicitada.

CORRECCIÓN respecto al PHP original:
  El PHP tenía: if (!$userId) { pasar sin verificar }  ← BUG de seguridad
  Aquí:         if (!user_id) { denegar con 401 }      ← comportamiento correcto
"""
import fnmatch
from typing import Optional

from fastapi import Request
from fastapi.responses import JSONResponse
from starlette.middleware.base import BaseHTTPMiddleware

# Rutas exentas del control de permisos (ya validadas por TokenMiddleware)
PERMISSION_EXEMPT_PATHS = {
    "/",
    "/login",
    "/auth",
    "/health",
    "/docs",
    "/redoc",
    "/openapi.json",
    "/auth/sso/url",
    "/api/islogged",
    "/api/logout",
    "/api/refreshToken",
}

PERMISSION_EXEMPT_PREFIXES = (
    "/static/",
    "/favicon",
)


def _is_exempt(path: str) -> bool:
    if path in PERMISSION_EXEMPT_PATHS:
        return True
    return any(path.startswith(prefix) for prefix in PERMISSION_EXEMPT_PREFIXES)


class PermissionsMiddleware(BaseHTTPMiddleware):
    """
    Verifica permisos RBAC usando wildcard matching (fnmatch).
    Equivalente a PermissionsMiddleware.php.

    Los roles y rutas permitidas se cargan desde octopus_users.
    TODO Sprint 3: implementar carga real desde BD cuando se migre el módulo de usuarios.
    """

    async def dispatch(self, request: Request, call_next):
        path = request.url.path

        # Rutas exentas: pasar directamente
        if _is_exempt(path):
            return await call_next(request)

        # CORRECCIÓN DEL BUG PHP: si no hay user_id, denegar — nunca pasar
        user_id: Optional[int] = getattr(request.state, "user_id", None)
        if not user_id:
            return JSONResponse(
                status_code=401,
                content={"error": True, "message": "No autenticado"},
            )

        # TODO Sprint 3: cargar roles y rutas permitidas desde octopus_users
        # Equivalente a:
        #   $roles = $db->getRolesByUser($userId)
        #   $paths = $db->getPathsByRole($roles)
        #   foreach($paths as $p) { if (fnmatch($p, $path)) { $allowed = true; } }
        #
        # Por ahora, durante la migración, permitir acceso a todos los usuarios autenticados
        # Esto se reemplazará en Sprint 3 con la lógica RBAC real.

        allowed = await _check_permissions_stub(user_id, path)
        if not allowed:
            return JSONResponse(
                status_code=403,
                content={"error": True, "message": "No tienes permisos para acceder a este recurso"},
            )

        return await call_next(request)


async def _check_permissions_stub(user_id: int, path: str) -> bool:
    """
    Stub de verificación de permisos.
    TODO Sprint 3: reemplazar con consulta real a octopus_users.

    Lógica real del PHP:
        $roles = Usuarios::getRolesByUser($userId)  → lista de role_ids
        $paths = Usuarios::getPathsByRole($roles)   → lista de rutas con wildcards
        return any(fnmatch(p, path) for p in paths)
    """
    # Durante Sprint 1 y 2: permitir todo a usuarios autenticados
    return True


def check_path_permission(allowed_paths: list[str], request_path: str) -> bool:
    """
    Verifica si una ruta está permitida usando wildcard matching.
    Equivalente al fnmatch() de PermissionsMiddleware.php.

    Ejemplo: allowed_path="/api/getActivos*" permite "/api/getActivos?id=1"
    """
    return any(fnmatch.fnmatch(request_path, pattern) for pattern in allowed_paths)
