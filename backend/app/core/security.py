"""
Sistema JWT completo para SecLensCore — equivalente a token.php.
Compatible con los tokens generados por el sistema PHP durante la coexistencia.

El sistema usa doble cifrado:
  1. El payload se cifra con AES-256-CBC (openssl_encrypt en PHP)
  2. El resultado se firma con RS256 usando la clave privada RSA

Cookie de sesión: "sst" con flags HttpOnly, Secure, SameSite=Strict
"""
import base64
import json
import secrets
from datetime import datetime, timedelta, timezone

from cryptography.hazmat.backends import default_backend
from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from fastapi import HTTPException
from fastapi.responses import JSONResponse
from jose import JWTError, jwt

from app.core.config import get_settings

settings = get_settings()


# ---------------------------------------------------------------
# Carga de claves RSA
# ---------------------------------------------------------------

def load_private_key_pem() -> str:
    """
    Carga la clave privada RSA desde el archivo PEM con passphrase hex.
    Equivalente a TokenConfig.getPrivateKey() en token.php.
    """
    passphrase = bytes.fromhex(settings.jwt_passphrase_str)
    with open(settings.JWT_PRIVATE_KEY_PATH, "rb") as f:
        private_key = serialization.load_pem_private_key(
            f.read(),
            password=passphrase,
            backend=default_backend(),
        )
    return private_key.private_bytes(
        serialization.Encoding.PEM,
        serialization.PrivateFormat.TraditionalOpenSSL,
        serialization.NoEncryption(),
    ).decode()


def load_public_key_pem() -> str:
    """Extrae la clave pública desde la privada para verificar tokens JWT."""
    passphrase = bytes.fromhex(settings.jwt_passphrase_str)
    with open(settings.JWT_PRIVATE_KEY_PATH, "rb") as f:
        private_key = serialization.load_pem_private_key(
            f.read(),
            password=passphrase,
            backend=default_backend(),
        )
    return private_key.public_key().public_bytes(
        serialization.Encoding.PEM,
        serialization.PublicFormat.SubjectPublicKeyInfo,
    ).decode()


# ---------------------------------------------------------------
# Cifrado AES-256-CBC compatible con PHP openssl_encrypt
# ---------------------------------------------------------------

def _pkcs7_pad(data: bytes, block_size: int = 16) -> bytes:
    """PKCS7 padding — igual que openssl usa por defecto en PHP."""
    pad_len = block_size - (len(data) % block_size)
    return data + bytes([pad_len] * pad_len)


def _pkcs7_unpad(data: bytes) -> bytes:
    """Elimina el PKCS7 padding."""
    pad_len = data[-1]
    return data[:-pad_len]


def aes_encrypt(plaintext: str) -> str:
    """
    Cifra una cadena con AES-256-CBC.
    Compatible con: openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv) en PHP.
    """
    key = bytes.fromhex(settings.jwt_aes_key_str)
    iv  = bytes.fromhex(settings.jwt_aes_iv_str)
    data = _pkcs7_pad(plaintext.encode("utf-8"))
    cipher = Cipher(algorithms.AES(key), modes.CBC(iv), backend=default_backend())
    encryptor = cipher.encryptor()
    ciphertext = encryptor.update(data) + encryptor.finalize()
    return base64.b64encode(ciphertext).decode()


def aes_decrypt(ciphertext_b64: str) -> str:
    """
    Descifra AES-256-CBC.
    Compatible con: openssl_decrypt() en PHP.
    """
    key = bytes.fromhex(settings.jwt_aes_key_str)
    iv  = bytes.fromhex(settings.jwt_aes_iv_str)
    ciphertext = base64.b64decode(ciphertext_b64)
    cipher = Cipher(algorithms.AES(key), modes.CBC(iv), backend=default_backend())
    decryptor = cipher.decryptor()
    padded = decryptor.update(ciphertext) + decryptor.finalize()
    return _pkcs7_unpad(padded).decode("utf-8")


# ---------------------------------------------------------------
# Generación y verificación de tokens JWT de sesión
# ---------------------------------------------------------------

def create_session_token(
    user_id: int,
    user_agent: str,
    hostname: str,
) -> tuple[str, str, dict]:
    """
    Crea el token JWT de sesión.
    Equivalente al bloque del callback /auth en index.php.

    El payload interno se cifra con AES y luego se firma con RS256.
    Retorna: (token_jwt, jti, payload_interno)
    """
    now = datetime.now(timezone.utc)
    jti = secrets.token_hex(16)

    # Payload interno del JWT (el que se cifra con AES)
    inner_payload = {
        "iss": settings.JWT_ISSUER,
        "aud": user_agent + hostname,
        "iat": int(now.timestamp()),
        "exp": int((now + timedelta(hours=1)).timestamp()),
        "jti": jti,
        "data": user_id,
    }

    # Cifrar el payload interno con AES-256-CBC
    encrypted = aes_encrypt(
        json.dumps({"data": json.dumps(inner_payload), "jti": jti})
    )

    # Firmar con RS256 — la posición "0" es el mismo formato que el PHP
    token = jwt.encode(
        {0: encrypted},
        load_private_key_pem(),
        algorithm=settings.JWT_ALGORITHM,
    )
    return token, jti, inner_payload


def verify_session_token(token: str) -> dict:
    """
    Verifica y descifra el token JWT de sesión.
    Equivalente a JWT::decode + TokenEncryptor.decrypt() en TokenMiddleware.php.
    Retorna: {"user_id": int, "jti": str}
    """
    try:
        public_key = load_public_key_pem()
        # jose permite opciones; desactivamos audiencia para mantener compat PHP
        payload = jwt.decode(
            token,
            public_key,
            algorithms=[settings.JWT_ALGORITHM],
            options={"verify_aud": False},
        )
        # El dato cifrado está en la clave "0" (igual que en el PHP)
        encrypted_data = payload.get("0") or payload.get(0)
        if not encrypted_data:
            raise HTTPException(status_code=401, detail="Token malformado")

        decrypted = json.loads(aes_decrypt(encrypted_data))
        inner = json.loads(decrypted.get("data", "{}"))
        return {
            "user_id": inner.get("data"),
            "jti": decrypted.get("jti"),
        }
    except JWTError as exc:
        raise HTTPException(status_code=401, detail=f"Token inválido: {exc}") from exc


def set_session_cookie(response: JSONResponse, token: str, domain: str = "") -> None:
    """
    Establece la cookie de sesión segura 'sst'.
    Equivalente a setcookie('sst', $token, $options) en PHP.
    """
    response.set_cookie(
        key="sst",
        value=token,
        max_age=3600,
        path="/",
        domain=domain or None,
        secure=settings.is_production,  # Solo Secure en producción
        httponly=True,
        samesite="strict",
    )


def clear_session_cookie(response: JSONResponse) -> None:
    """Elimina la cookie de sesión (logout)."""
    response.delete_cookie(
        key="sst",
        path="/",
        httponly=True,
        samesite="strict",
    )
