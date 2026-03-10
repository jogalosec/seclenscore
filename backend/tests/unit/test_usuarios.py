"""
Tests unitarios para el módulo Usuarios / Roles / Endpoints / Tokens.
Ejecutar: pytest tests/unit/test_usuarios.py -v
"""
import pytest
from unittest.mock import AsyncMock, MagicMock, patch


# ---------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------

@pytest.fixture
def mock_db():
    db = MagicMock()
    db.execute = AsyncMock()
    db.commit  = AsyncMock()
    return db


@pytest.fixture
def usuario_sample():
    return {"id": 1, "email": "alice@example.com", "roles": [{"id": 2, "name": "Analyst"}]}


# ---------------------------------------------------------------
# Tests de Schemas
# ---------------------------------------------------------------

class TestUsuarioSchemas:

    def test_user_create_email_valido(self):
        from app.schemas.usuario import UserCreate
        u = UserCreate(email="user@test.com", rol=[1, 2])
        assert u.email == "user@test.com"
        assert u.rol == [1, 2]

    def test_user_create_email_invalido_falla(self):
        from app.schemas.usuario import UserCreate
        from pydantic import ValidationError
        with pytest.raises(ValidationError):
            UserCreate(email="no-es-un-email", rol=[])

    def test_user_update_rol_lista(self):
        from app.schemas.usuario import UserUpdate
        u = UserUpdate(id=1, rol=[3, 4])
        assert u.id == 1
        assert 3 in u.rol

    def test_role_create_valido(self):
        from app.schemas.usuario import RoleCreate
        r = RoleCreate(name="Admin", color="#FF0000", additionalAccess=True)
        assert r.name == "Admin"
        assert r.additionalAccess is True

    def test_role_create_additional_access_default_false(self):
        from app.schemas.usuario import RoleCreate
        r = RoleCreate(name="Guest", color="#AAAAAA")
        assert r.additionalAccess is False

    def test_edit_endpoints_lista_vacia_falla(self):
        from app.schemas.usuario import EditEndpointsRequest
        from pydantic import ValidationError
        with pytest.raises(ValidationError):
            EditEndpointsRequest(rol=1, endpoints=[], allow=True)

    def test_edit_endpoints_convierte_a_int(self):
        from app.schemas.usuario import EditEndpointsRequest
        req = EditEndpointsRequest(rol=1, endpoints=["5", "10"], allow=False)
        assert req.endpoints == [5, 10]

    def test_token_create_campos_requeridos(self):
        from app.schemas.usuario import TokenCreate
        t = TokenCreate(name="CI token", expired=1735689600)
        assert t.name == "CI token"

    def test_token_delete_requerido(self):
        from app.schemas.usuario import TokenDeleteRequest
        t = TokenDeleteRequest(tokenId=99)
        assert t.tokenId == 99


# ---------------------------------------------------------------
# Tests del servicio
# ---------------------------------------------------------------

class TestUsuariosService:

    @pytest.mark.asyncio
    async def test_hash_y_verify_password(self):
        from app.services.usuarios_service import hash_password, verify_password
        hashed = hash_password("MiContraseña123!")
        assert hashed != "MiContraseña123!"
        assert verify_password("MiContraseña123!", hashed)
        assert not verify_password("Incorrecta", hashed)

    @pytest.mark.asyncio
    async def test_crear_usuario_genera_temp_password(self, mock_db):
        with patch("app.services.usuarios_service.db_users") as mock_db_mod:
            mock_db_mod.get_user_by_email = AsyncMock(return_value=None)
            mock_db_mod.new_user = AsyncMock(return_value={"id": 10, "email": "new@test.com"})

            from app.services.usuarios_service import crear_usuario
            result = await crear_usuario(mock_db, email="new@test.com", roles=[1])

            assert result["error"] is False
            assert "temp_password" in result

    @pytest.mark.asyncio
    async def test_crear_usuario_email_duplicado_falla(self, mock_db):
        with patch("app.services.usuarios_service.db_users") as mock_db_mod:
            mock_db_mod.get_user_by_email = AsyncMock(return_value={"id": 5, "email": "dup@test.com"})

            from app.services.usuarios_service import crear_usuario
            result = await crear_usuario(mock_db, email="dup@test.com", roles=[])

            assert result["error"] is True

    @pytest.mark.asyncio
    async def test_eliminar_usuario_llama_db(self, mock_db):
        with patch("app.services.usuarios_service.db_users") as mock_db_mod:
            mock_db_mod.del_user = AsyncMock(return_value=None)

            from app.services.usuarios_service import eliminar_usuario
            result = await eliminar_usuario(mock_db, user_id=7)

            mock_db_mod.del_user.assert_called_once_with(mock_db, 7)
            assert result["error"] is False

    @pytest.mark.asyncio
    async def test_crear_token_prefijo_11(self, mock_db):
        with patch("app.services.usuarios_service.db_users") as mock_db_mod:
            mock_db_mod.create_token_user = AsyncMock(return_value={"id": 1})

            from app.services.usuarios_service import crear_token
            result = await crear_token(mock_db, user_id=1, name="test", expired_ts=0)

            assert result["error"] is False
            # El token devuelto en claro empieza por 11_
            assert result["token"].startswith("11_")

    @pytest.mark.asyncio
    async def test_eliminar_token_llama_db(self, mock_db):
        with patch("app.services.usuarios_service.db_users") as mock_db_mod:
            mock_db_mod.delete_token_user = AsyncMock(return_value=None)

            from app.services.usuarios_service import eliminar_token
            result = await eliminar_token(mock_db, token_id=42, user_id=1)

            mock_db_mod.delete_token_user.assert_called_once()
            assert result["error"] is False


# ---------------------------------------------------------------
# Tests de seguridad (fix SQL injection editEndpointsByRole)
# ---------------------------------------------------------------

class TestEditEndpointsSecuridad:

    def test_endpoints_solo_enteros(self):
        """El schema debe convertir strings a int y rechazar valores maliciosos."""
        from app.schemas.usuario import EditEndpointsRequest
        from pydantic import ValidationError

        # Valores válidos como strings (API puede mandar "5")
        req = EditEndpointsRequest(rol=2, endpoints=["3", "7"], allow=True)
        assert req.endpoints == [3, 7]

        # Intentar inyección con un valor no entero
        with pytest.raises(ValidationError):
            EditEndpointsRequest(rol=2, endpoints=["1; DROP TABLE endpoints;--"], allow=True)
