"""
Dependencias FastAPI — equivalente a TokenMiddleware.php.
Se inyectan con Depends() en cada ruta que requiere autenticación.
"""
from typing import Optional

from fastapi import Cookie, Depends, HTTPException, Request

from app.core.security import verify_session_token


async def get_current_user(
    request: Request,
    sst: Optional[str] = Cookie(default=None),
) -> dict:
    """
    Dependency de autenticación. Soporta dos modos:

    1. Cookie 'sst' (sesión SSO/login web) — modo principal
    2. Bearer token con prefijo '11_' (acceso API externo)

    Equivalente a TokenMiddleware::process() en PHP.
    Retorna dict con {user_id, jti}.
    """
    # Modo 1: Bearer token con prefijo "11_"
    auth_header = request.headers.get("Authorization", "")
    if auth_header.startswith("Bearer 11_"):
        bearer_token = auth_header.split(" ", 1)[1]
        # TODO Sprint 3: validar bearer_token contra octopus_users.tokens
        # Por ahora devolvemos error hasta que se implemente el módulo de usuarios
        raise HTTPException(
            status_code=501,
            detail="Autenticación por Bearer token pendiente de implementar (Sprint 3)",
        )

    # Modo 2: Cookie de sesión JWT
    if not sst:
        raise HTTPException(status_code=401, detail="Sesión no encontrada o expirada")

    return verify_session_token(sst)


# Alias para uso más limpio en los routers
CurrentUser = Depends(get_current_user)
