"""
Tests unitarios para la capa de seguridad JWT.
Ejecutar: pytest tests/unit/test_security.py -v
"""
import pytest
from unittest.mock import patch, mock_open
from fastapi import HTTPException


# ---------------------------------------------------------------
# Helpers de test — claves RSA de test (no usar en producción)
# ---------------------------------------------------------------

TEST_PRIVATE_KEY_PEM = """-----BEGIN RSA PRIVATE KEY-----
MIIEowIBAAKCAQEA2a2rwplBQLzHPZe5RJr9vSMnTBXKxnWBBqjhvmf+iEMTVxOg
...test key placeholder - replace with real key in CI...
-----END RSA PRIVATE KEY-----"""

TEST_AES_KEY_HEX = "0" * 64   # 32 bytes en hex (placeholder)
TEST_AES_IV_HEX  = "0" * 32   # 16 bytes en hex (placeholder)
TEST_PASSPHRASE  = "0" * 64   # hex placeholder


@pytest.fixture
def mock_settings(monkeypatch):
    """Mockea la configuración para tests sin necesitar .env real."""
    from app.core import config
    monkeypatch.setattr(
        "app.core.security.settings",
        type("MockSettings", (), {
            "jwt_passphrase_str": TEST_PASSPHRASE,
            "jwt_aes_key_str":    TEST_AES_KEY_HEX,
            "jwt_aes_iv_str":     TEST_AES_IV_HEX,
            "JWT_ALGORITHM":      "RS256",
            "JWT_ISSUER":         "https://test.seclenscore.com",
            "JWT_AUDIENCE":       "11CertToolTest",
            "is_production":      False,
        })()
    )


class TestAESEncryption:
    """Tests del cifrado AES-256-CBC compatible con PHP."""

    def test_encrypt_decrypt_roundtrip(self, mock_settings):
        """Cifrar y descifrar debe devolver el texto original."""
        from app.core.security import aes_encrypt, aes_decrypt

        original = '{"user_id": 42, "jti": "abc123"}'
        encrypted = aes_encrypt(original)
        decrypted = aes_decrypt(encrypted)

        assert decrypted == original

    def test_encrypt_produces_base64(self, mock_settings):
        """El resultado del cifrado debe ser un string base64 válido."""
        import base64
        from app.core.security import aes_encrypt

        encrypted = aes_encrypt("test data")
        # Verificar que es base64 válido (no lanza excepción)
        decoded = base64.b64decode(encrypted)
        assert len(decoded) > 0

    def test_encrypt_different_inputs_different_output(self, mock_settings):
        """Entradas distintas deben producir cifrados distintos."""
        from app.core.security import aes_encrypt

        enc1 = aes_encrypt("texto uno")
        enc2 = aes_encrypt("texto dos")
        assert enc1 != enc2

    def test_decrypt_invalid_input_raises(self, mock_settings):
        """Descifrar datos corruptos debe lanzar una excepción."""
        from app.core.security import aes_decrypt

        with pytest.raises(Exception):
            aes_decrypt("esto no es base64 válido!!!")


class TestJWTSessionToken:
    """Tests del sistema JWT de sesión (doble cifrado AES + RS256)."""

    @pytest.fixture
    def mock_keys(self, tmp_path, mock_settings):
        """Crea claves RSA temporales para tests."""
        from cryptography.hazmat.primitives.asymmetric import rsa
        from cryptography.hazmat.primitives import serialization
        from cryptography.hazmat.backends import default_backend

        # Generar par de claves RSA de test
        private_key = rsa.generate_private_key(
            public_exponent=65537,
            key_size=2048,
            backend=default_backend(),
        )
        key_file = tmp_path / "test_privatekey.pem"
        key_file.write_bytes(
            private_key.private_bytes(
                serialization.Encoding.PEM,
                serialization.PrivateFormat.TraditionalOpenSSL,
                serialization.NoEncryption(),  # Sin passphrase para test
            )
        )
        return str(key_file)

    def test_invalid_token_raises_401(self, mock_settings):
        """Un token JWT modificado debe lanzar HTTPException 401."""
        from app.core.security import verify_session_token

        with pytest.raises(HTTPException) as exc_info:
            verify_session_token("token.invalido.completamente")
        assert exc_info.value.status_code == 401

    def test_empty_token_raises_401(self, mock_settings):
        """Un token vacío debe lanzar HTTPException 401."""
        from app.core.security import verify_session_token

        with pytest.raises(HTTPException) as exc_info:
            verify_session_token("")
        assert exc_info.value.status_code == 401


class TestSessionCookie:
    """Tests del manejo de cookies de sesión."""

    def test_set_cookie_uses_correct_name(self):
        """La cookie de sesión debe llamarse 'sst'."""
        from fastapi.responses import JSONResponse
        from app.core.security import set_session_cookie

        response = JSONResponse(content={})
        set_session_cookie(response, "test_token_value")
        # Verificar que la cookie 'sst' está en las cabeceras
        set_cookie_header = response.headers.get("set-cookie", "")
        assert "sst=" in set_cookie_header

    def test_clear_cookie_deletes_sst(self):
        """Limpiar la cookie debe eliminar 'sst'."""
        from fastapi.responses import JSONResponse
        from app.core.security import clear_session_cookie

        response = JSONResponse(content={})
        clear_session_cookie(response)
        set_cookie_header = response.headers.get("set-cookie", "")
        assert "sst=" in set_cookie_header
