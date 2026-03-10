"""
Middleware de autenticación — equivalente a TokenMiddleware.php.
Verifica el token JWT (cookie 'sst' o Bearer '11_') en cada request
y adjunta el user_id en request.state para uso posterior.
"""
from typing import Optional

from fastapi import Request
from fastapi.responses import JSONResponse
from starlette.middleware.base import BaseHTTPMiddleware

from app.core.security import verify_session_token

# Rutas que no requieren autenticación
PUBLIC_PATHS = {
    "/",
    "/login",
    "/auth",
    "/health",
    "/docs",
    "/redoc",
    "/openapi.json",
    "/auth/sso/url",
}

# Prefijos públicos (cualquier ruta que empiece así)
PUBLIC_PREFIXES = (
    "/static/",
    "/favicon",
)


def _is_public(path: str) -> bool:
    if path in PUBLIC_PATHS:
        return True
    return any(path.startswith(prefix) for prefix in PUBLIC_PREFIXES)


class TokenMiddleware(BaseHTTPMiddleware):
    """
    Verifica autenticación en cada request.
    Equivalente a TokenMiddleware.php con soporte para cookie 'sst' y Bearer '11_'.
    """

    async def dispatch(self, request: Request, call_next):
        path = request.url.path

        # Rutas públicas: pasar sin verificar
        if _is_public(path):
            return await call_next(request)

        # Intentar extraer token de Bearer header
        auth_header = request.headers.get("Authorization", "")
        bearer_token: Optional[str] = None
        if auth_header.startswith("Bearer 11_"):
            bearer_token = auth_header.split(" ", 1)[1]

        # Intentar extraer cookie de sesión
        sst_cookie = request.cookies.get("sst")

        # Sin ningún token → 401
        if not bearer_token and not sst_cookie:
            return JSONResponse(
                status_code=401,
                content={"error": True, "message": "Sesión no encontrada o expirada"},
            )

        try:
            if sst_cookie:
                token_data = verify_session_token(sst_cookie)
                request.state.user_id = token_data["user_id"]
                request.state.jti = token_data.get("jti")
                request.state.auth_mode = "cookie"
            else:
                # TODO Sprint 3: validar bearer_token contra octopus_users
                # Por ahora, dejar pasar con user_id=None para que el endpoint lo maneje
                request.state.user_id = None
                request.state.auth_mode = "bearer"
                request.state.bearer_token = bearer_token

        except Exception:
            return JSONResponse(
                status_code=401,
                content={"error": True, "message": "Token inválido o expirado"},
            )

        return await call_next(request)
