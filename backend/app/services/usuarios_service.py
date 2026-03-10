"""
Lógica de negocio — Módulo Usuarios.
Equivalente a los handlers de /api/newUser, /api/editUser, etc. en index.php.
"""
import secrets
import string

from passlib.context import CryptContext
from sqlalchemy.ext.asyncio import AsyncSession

from app.db import octopus_users as db_users
from app.schemas.common import APIResponse

_pwd_ctx = CryptContext(schemes=["bcrypt"], deprecated="auto", bcrypt__rounds=11)


def _random_password(length: int = 12) -> str:
    """Genera una contraseña aleatoria segura (equivalente a randomPassword() de PHP)."""
    alphabet = string.ascii_letters + string.digits + "!@#$%^&*"
    return "".join(secrets.choice(alphabet) for _ in range(length))


def hash_password(plain: str) -> str:
    return _pwd_ctx.hash(plain)


def verify_password(plain: str, hashed: str) -> bool:
    return _pwd_ctx.verify(plain, hashed)


# ---------------------------------------------------------------
# Usuarios
# ---------------------------------------------------------------

async def get_info_user(db: AsyncSession, user_id: int) -> dict:
    """Devuelve información del usuario autenticado."""
    user = await db_users.get_user(db, user_id)
    if not user:
        return {"error": True, "message": "Usuario no encontrado"}
    return {"error": False, "usuario": user}


async def crear_usuario(db: AsyncSession, email: str, roles: list) -> APIResponse:
    """
    Crea un nuevo usuario con contraseña aleatoria.
    En producción se envía por email (stub — Sprint 5 integra PHPMailer equivalente).
    """
    auth_pass = _random_password()
    password_hash = hash_password(auth_pass)

    result = await db_users.new_user(db, email=email, password_hash=password_hash, roles=roles)

    if result["error"]:
        return APIResponse(error=True, message=result.get("message", "Error al crear usuario"))

    # TODO Sprint 5: enviar email con credenciales usando aiosmtplib
    # await send_welcome_email(email, auth_pass)

    return APIResponse(
        error=False,
        message="Usuario creado correctamente. Se le enviará un email con sus credenciales.",
        data={"user_id": result.get("user_id"), "temp_password": auth_pass},
    )


async def editar_usuario(db: AsyncSession, user_id: int, roles: list) -> APIResponse:
    await db_users.edit_user(db, user_id=user_id, roles=roles)
    return APIResponse(error=False, message="Usuario editado correctamente.")


async def eliminar_usuario(db: AsyncSession, user_id: int) -> APIResponse:
    await db_users.del_user(db, user_id)
    return APIResponse(error=False, message="Usuario borrado correctamente.")


# ---------------------------------------------------------------
# Tokens Bearer 11_
# ---------------------------------------------------------------

async def crear_token(db: AsyncSession, user_id: int, name: str, expired_ts: int) -> APIResponse:
    """
    Genera un Bearer token con prefijo 11_ y lo almacena hasheado.
    Equivalente a createTokensUser() de PHP pero sin la clase TokenBearerEncryptor (AES).
    El token en texto plano se devuelve UNA SOLA VEZ al cliente.
    """
    import base64
    random_bytes = secrets.token_bytes(26)
    raw_token = base64.urlsafe_b64encode(random_bytes).decode().rstrip("=")
    plain_token = f"11_{raw_token}"

    # Almacenar hash SHA-256 para verificación posterior
    import hashlib
    token_hash = hashlib.sha256(plain_token.encode()).hexdigest()

    await db_users.create_token_user(
        db, user_id=user_id, name=name, expired_ts=expired_ts, token_hash=token_hash
    )
    return APIResponse(
        error=False,
        message="Token creado. Guárdalo: no se mostrará de nuevo.",
        data={"token": plain_token},
    )


async def eliminar_token(db: AsyncSession, user_id: int, token_id: int) -> APIResponse:
    await db_users.delete_token_user(db, user_id=user_id, token_id=token_id)
    return APIResponse(error=False, message="Token eliminado correctamente.")
