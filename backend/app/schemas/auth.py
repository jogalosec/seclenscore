"""Schemas Pydantic para el módulo de autenticación."""
from typing import Optional

from pydantic import BaseModel, EmailStr


class LoginRequest(BaseModel):
    """Credenciales para login local."""
    email: EmailStr
    password: str
    recaptcha_token: Optional[str] = None
    recaptcha_v2: bool = False


class TokenResponse(BaseModel):
    """Respuesta tras autenticación exitosa."""
    error: bool = False
    access_token: str
    token_type: str = "bearer"
    user: dict


class SSOCallbackRequest(BaseModel):
    """Datos recibidos en el callback de Azure AD."""
    code: str
    state: str


class RefreshTokenResponse(BaseModel):
    error: bool = False
    message: str = "Token renovado"


class UserInfo(BaseModel):
    """Información básica del usuario autenticado."""
    id: int
    email: str
    nombre: Optional[str] = None
    rol: Optional[int] = None
