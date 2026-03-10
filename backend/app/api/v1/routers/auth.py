"""
Router de autenticación — equivalente a los endpoints /auth, /api/islogged,
/api/logout, /api/refreshToken y gestión de tokens en index.php.
"""
import secrets
from datetime import datetime, timezone
from typing import Optional

import httpx
from fastapi import APIRouter, Depends, HTTPException, Query, Request
from fastapi.responses import JSONResponse, RedirectResponse

from app.core.config import get_settings
from app.core.dependencies import get_current_user
from app.core.security import (
    clear_session_cookie,
    create_session_token,
    set_session_cookie,
    verify_session_token,
)
from app.schemas.auth import LoginRequest, TokenResponse
from app.schemas.common import APIResponse

router = APIRouter(tags=["Autenticación"])
settings = get_settings()

# URL base de Microsoft para OAuth2
MS_OAUTH_BASE = f"https://login.microsoftonline.com/{settings.AZURE_TENANT_ID}/oauth2/v2.0"
MS_GRAPH_ME = "https://graph.microsoft.com/v1.0/me"


# ---------------------------------------------------------------
# GET /auth/sso/url — Devuelve la URL de autorización de Azure AD
# ---------------------------------------------------------------
@router.get("/auth/sso/url", summary="URL de autorización Azure AD")
async def get_sso_url(request: Request):
    """
    Genera la URL de autorización de Azure AD para iniciar el flujo SSO.
    El frontend redirige al usuario a esta URL.
    Equivalente al enlace SSO en login.phtml.
    """
    state = secrets.token_urlsafe(32)
    params = {
        "client_id":     settings.AZURE_CLIENT_ID.get_secret_value(),
        "response_type": "code",
        "redirect_uri":  settings.AZURE_REDIRECT_URI,
        "scope":         "openid profile email User.Read",
        "state":         state,
        "response_mode": "query",
    }
    query_string = "&".join(f"{k}={v}" for k, v in params.items())
    authorization_url = f"{MS_OAUTH_BASE}/authorize?{query_string}"
    return {"authorization_url": authorization_url, "state": state}


# ---------------------------------------------------------------
# GET /auth — Callback OAuth2 de Azure AD
# Equivalente al handler GET /auth en index.php
# ---------------------------------------------------------------
@router.get("/auth", summary="Callback OAuth2 Azure AD")
async def azure_ad_callback(
    request: Request,
    code: Optional[str] = Query(default=None),
    state: Optional[str] = Query(default=None),
    error: Optional[str] = Query(default=None),
    error_description: Optional[str] = Query(default=None),
):
    """
    Recibe el código de autorización de Azure AD, lo intercambia por un
    access_token de Microsoft Graph, obtiene el perfil del usuario y
    genera el JWT de sesión propio (cookie 'sst').
    """
    # Error devuelto por Azure AD
    if error:
        redirect_url = f"/login?error={error_description or error}"
        return RedirectResponse(url=redirect_url)

    if not code:
        return RedirectResponse(url="/login?error=Código de autorización no recibido")

    # Intercambiar code por token de Microsoft
    async with httpx.AsyncClient() as client:
        token_response = await client.post(
            f"{MS_OAUTH_BASE}/token",
            data={
                "client_id":     settings.AZURE_CLIENT_ID.get_secret_value(),
                "client_secret": settings.AZURE_CLIENT_SECRET.get_secret_value(),
                "code":          code,
                "redirect_uri":  settings.AZURE_REDIRECT_URI,
                "grant_type":    "authorization_code",
                "scope":         "openid profile email User.Read",
            },
        )

        if token_response.status_code != 200:
            return RedirectResponse(url="/login?error=Error al autenticar con Microsoft")

        ms_tokens = token_response.json()
        ms_access_token = ms_tokens.get("access_token")

        # Obtener perfil del usuario de Microsoft Graph
        profile_response = await client.get(
            MS_GRAPH_ME,
            headers={"Authorization": f"Bearer {ms_access_token}"},
        )

        if profile_response.status_code != 200:
            return RedirectResponse(url="/login?error=No se pudo obtener el perfil de Microsoft")

        ms_profile = profile_response.json()

    # TODO Sprint 3: buscar/crear el usuario en octopus_users por email (ms_profile["mail"])
    # Por ahora usamos un user_id simulado para la estructura
    user_email = ms_profile.get("mail") or ms_profile.get("userPrincipalName", "")
    simulated_user_id = hash(user_email) % 100000  # Placeholder hasta Sprint 3

    # Generar JWT de sesión propio
    user_agent = request.headers.get("user-agent", "")
    hostname = str(request.base_url)
    token, jti, _ = create_session_token(simulated_user_id, user_agent, hostname)

    # Redirigir al frontend con la cookie establecida
    response = RedirectResponse(url="/app")
    set_session_cookie(response, token)
    return response


# ---------------------------------------------------------------
# POST /api/login — Login local con email + password
# ---------------------------------------------------------------
@router.post("/api/login", response_model=TokenResponse, summary="Login local")
async def login_local(credentials: LoginRequest, request: Request):
    """
    Autenticación con credenciales locales (email + contraseña).
    Equivalente al handler POST /auth en index.php.
    """
    # TODO Sprint 3: validar credentials.email y credentials.password
    # contra octopus_users via Usuarios::authUser()
    # Por ahora retornamos error hasta que se migre el módulo de usuarios
    raise HTTPException(
        status_code=501,
        detail="Login local pendiente de implementar en Sprint 3 (módulo Usuarios)",
    )


# ---------------------------------------------------------------
# GET /api/islogged — Verifica sesión activa
# ---------------------------------------------------------------
@router.get("/api/islogged", response_model=APIResponse, summary="Verificar sesión")
async def is_logged(current_user: dict = Depends(get_current_user)):
    """
    Verifica si el usuario tiene una sesión activa válida.
    El frontend llama a este endpoint al cargar para saber si está autenticado.
    """
    return APIResponse(
        error=False,
        message="Sesión activa",
        data={"user_id": current_user["user_id"]},
    )


# ---------------------------------------------------------------
# GET /api/refreshToken — Renueva el token JWT
# ---------------------------------------------------------------
@router.get("/api/refreshToken", summary="Refrescar token")
async def refresh_token(request: Request):
    """
    Refresca el token JWT de sesión si aún es válido.
    Equivalente a refreshToken() en functions.php.
    Genera un nuevo token y actualiza la cookie.
    """
    sst = request.cookies.get("sst")
    if not sst:
        raise HTTPException(status_code=401, detail="Sin sesión activa")

    # Verificar el token actual
    token_data = verify_session_token(sst)
    user_id = token_data["user_id"]

    # Generar nuevo token
    user_agent = request.headers.get("user-agent", "")
    hostname = str(request.base_url)
    new_token, _, _ = create_session_token(user_id, user_agent, hostname)

    response = JSONResponse(content={"error": False, "message": "Token renovado"})
    set_session_cookie(response, new_token)
    return response


# ---------------------------------------------------------------
# GET /api/logout — Cerrar sesión
# ---------------------------------------------------------------
@router.get("/api/logout", response_model=APIResponse, summary="Cerrar sesión")
async def logout(request: Request):
    """
    Invalida la sesión del usuario y elimina la cookie.
    Equivalente al handler GET /api/logout en index.php.
    """
    response = JSONResponse(content={"error": False, "message": "Sesión cerrada correctamente"})
    clear_session_cookie(response)
    return response


# ---------------------------------------------------------------
# POST /api/createToken — Crear token Bearer API
# ---------------------------------------------------------------
@router.post("/api/createToken", response_model=APIResponse, summary="Crear token Bearer API")
async def create_bearer_token(
    request: Request,
    current_user: dict = Depends(get_current_user),
):
    """
    Crea un token Bearer con prefijo '11_' para acceso programático a la API.
    Equivalente a POST /api/createToken en index.php.
    TODO Sprint 3: implementar persistencia en octopus_users.
    """
    token_value = f"11_{secrets.token_urlsafe(32)}"
    # TODO Sprint 3: guardar token en octopus_users con user_id, fecha_creacion, expiración
    return APIResponse(
        error=False,
        message="Token creado (pendiente persistencia Sprint 3)",
        data={"token": token_value},
    )


# ---------------------------------------------------------------
# POST /api/deleteToken — Eliminar token Bearer API
# ---------------------------------------------------------------
@router.post("/api/deleteToken", response_model=APIResponse, summary="Eliminar token Bearer API")
async def delete_bearer_token(
    request: Request,
    current_user: dict = Depends(get_current_user),
):
    """
    Elimina un token Bearer de la base de datos.
    TODO Sprint 3: implementar con octopus_users.
    """
    raise HTTPException(
        status_code=501,
        detail="Pendiente de implementar en Sprint 3 (módulo Usuarios)",
    )


# ---------------------------------------------------------------
# GET /api/getTokensUser — Listar tokens Bearer del usuario
# ---------------------------------------------------------------
@router.get("/api/getTokensUser", response_model=APIResponse, summary="Listar tokens del usuario")
async def get_tokens_user(current_user: dict = Depends(get_current_user)):
    """
    Lista todos los tokens Bearer del usuario autenticado.
    TODO Sprint 3: implementar con octopus_users.
    """
    raise HTTPException(
        status_code=501,
        detail="Pendiente de implementar en Sprint 3 (módulo Usuarios)",
    )
