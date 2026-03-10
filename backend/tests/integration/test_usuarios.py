"""
Tests de integración para los endpoints del módulo Usuarios / Roles / Endpoints / Tokens.
Ejecutar: pytest tests/integration/test_usuarios.py -v
"""
import pytest
from httpx import AsyncClient, ASGITransport
from unittest.mock import AsyncMock, patch


# ---------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------

@pytest.fixture
async def client():
    from app.main import app
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        yield ac


@pytest.fixture
def mock_user():
    return {"id": 1, "user_id": 1, "email": "admin@test.com", "additionalAccess": True}


# ---------------------------------------------------------------
# Auth requerida — 401 sin token
# ---------------------------------------------------------------

class TestUsuariosAuthRequired:

    @pytest.mark.asyncio
    async def test_get_users_sin_auth(self, client):
        resp = await client.get("/api/getUsers")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_user_sin_auth(self, client):
        resp = await client.get("/api/getUser", params={"id": 1})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_new_user_sin_auth(self, client):
        resp = await client.post("/api/newUser", json={"email": "x@x.com", "rol": []})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_roles_sin_auth(self, client):
        resp = await client.get("/api/getRoles")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_endpoints_sin_auth(self, client):
        resp = await client.get("/api/getEndpoints")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_create_token_sin_auth(self, client):
        resp = await client.post("/api/createToken", json={"name": "t", "expired": 0})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_delete_token_sin_auth(self, client):
        resp = await client.post("/api/deleteToken", json={"tokenId": 1})
        assert resp.status_code == 401


# ---------------------------------------------------------------
# Tests con auth mockeada
# ---------------------------------------------------------------

class TestGetUsers:

    @pytest.mark.asyncio
    async def test_get_users_devuelve_lista(self, client, mock_user):
        users_mock = [{"id": 1, "email": "a@b.com", "roles": []}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_users.get_users", new=AsyncMock(return_value=users_mock)),
        ):
            resp = await client.get("/api/getUsers")

        assert resp.status_code == 200
        data = resp.json()
        assert "usuarios" in data or "users" in data or isinstance(data, list)

    @pytest.mark.asyncio
    async def test_get_user_no_encontrado(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_users.get_user", new=AsyncMock(return_value=None)),
        ):
            resp = await client.get("/api/getUser", params={"id": 9999})

        assert resp.status_code in (200, 404)


class TestNewUser:

    @pytest.mark.asyncio
    async def test_new_user_email_invalido_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post("/api/newUser", json={"email": "no-es-email", "rol": []})
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_new_user_crea_correctamente(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.usuarios_service.crear_usuario",
                new=AsyncMock(return_value={
                    "error": False,
                    "message": "Usuario creado.",
                    "temp_password": "Abc123!",
                }),
            ),
        ):
            resp = await client.post("/api/newUser", json={"email": "nuevo@test.com", "rol": [1]})

        assert resp.status_code == 200
        assert resp.json()["error"] is False
        assert "temp_password" in resp.json()

    @pytest.mark.asyncio
    async def test_edit_user_valido(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.usuarios_service.editar_usuario",
                new=AsyncMock(return_value={"error": False, "message": "Actualizado."}),
            ),
        ):
            resp = await client.post("/api/editUser", json={"id": 2, "rol": [1, 3]})

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_delete_user_valido(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.usuarios_service.eliminar_usuario",
                new=AsyncMock(return_value={"error": False, "message": "Eliminado."}),
            ),
        ):
            resp = await client.post("/api/deleteUser", json={"id": 5})

        assert resp.status_code == 200


class TestRoles:

    @pytest.mark.asyncio
    async def test_get_roles_devuelve_lista(self, client, mock_user):
        roles_mock = [{"id": 1, "name": "Admin", "color": "#f00"}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_users.get_roles", new=AsyncMock(return_value=roles_mock)),
        ):
            resp = await client.get("/api/getRoles")

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_new_rol_crea_correctamente(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_users.new_rol", new=AsyncMock(return_value=None)),
        ):
            resp = await client.post("/api/newRol", json={"name": "Analyst", "color": "#00f", "additionalAccess": False})

        assert resp.status_code == 200
        assert resp.json()["error"] is False

    @pytest.mark.asyncio
    async def test_delete_rol_sin_id_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post("/api/deleteRol", json={})
        assert resp.status_code == 422


class TestEndpoints:

    @pytest.mark.asyncio
    async def test_get_endpoints_devuelve_lista(self, client, mock_user):
        endpoints_mock = [{"id": 1, "path": "/api/getUsers", "method": "GET"}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_users.get_endpoints", new=AsyncMock(return_value=endpoints_mock)),
        ):
            resp = await client.get("/api/getEndpoints")

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_edit_endpoints_lista_vacia_422(self, client, mock_user):
        """Lista vacía de endpoints debe devolver 422 (validado por schema)."""
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post("/api/editEndpointsByRole", json={"rol": 1, "endpoints": [], "allow": True})
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_edit_endpoints_valido(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_users.edit_endpoints_by_role", new=AsyncMock(return_value=None)),
        ):
            resp = await client.post("/api/editEndpointsByRole", json={"rol": 2, "endpoints": [1, 3, 5], "allow": True})

        assert resp.status_code == 200


class TestTokens:

    @pytest.mark.asyncio
    async def test_create_token_devuelve_token_con_prefijo(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.usuarios_service.crear_token",
                new=AsyncMock(return_value={"error": False, "token": "11_abc123", "message": "Token creado."}),
            ),
        ):
            resp = await client.post("/api/createToken", json={"name": "CI", "expired": 0})

        assert resp.status_code == 200
        data = resp.json()
        assert data["error"] is False
        assert data["token"].startswith("11_")

    @pytest.mark.asyncio
    async def test_get_tokens_user_devuelve_lista(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_users.get_tokens_user", new=AsyncMock(return_value=[])),
        ):
            resp = await client.get("/api/getTokensUser")

        assert resp.status_code == 200
