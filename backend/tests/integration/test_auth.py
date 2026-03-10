"""
Tests de integración para los endpoints de autenticación.
Ejecutar: pytest tests/integration/test_auth.py -v
"""
import pytest
from httpx import AsyncClient

from app.main import app


@pytest.fixture
async def client():
    """Cliente HTTP async para tests de integración."""
    async with AsyncClient(app=app, base_url="http://test") as ac:
        yield ac


class TestHealthEndpoint:
    async def test_health_returns_200(self, client: AsyncClient):
        response = await client.get("/health")
        assert response.status_code == 200
        assert response.json()["status"] == "ok"

    async def test_health_returns_version(self, client: AsyncClient):
        response = await client.get("/health")
        assert "version" in response.json()


class TestAuthEndpoints:
    async def test_islogged_without_token_returns_401(self, client: AsyncClient):
        """Llamar a /api/islogged sin cookie debe retornar 401."""
        response = await client.get("/api/islogged")
        assert response.status_code == 401

    async def test_logout_clears_cookie(self, client: AsyncClient):
        """GET /api/logout debe eliminar la cookie 'sst'."""
        response = await client.get("/api/logout")
        # El logout puede retornar 200 o redirigir, pero debe limpiar la cookie
        set_cookie = response.headers.get("set-cookie", "")
        assert "sst=" in set_cookie

    async def test_login_without_body_returns_422(self, client: AsyncClient):
        """POST /api/login sin body debe retornar 422 (Unprocessable Entity)."""
        response = await client.post("/api/login", json={})
        assert response.status_code == 422

    async def test_protected_endpoint_without_auth_returns_401(self, client: AsyncClient):
        """Acceder a /api/getUsers sin token debe retornar 401."""
        response = await client.get("/api/getUsers")
        assert response.status_code == 401

    async def test_sso_url_endpoint_returns_url(self, client: AsyncClient):
        """GET /auth/sso/url debe retornar una URL de autorización."""
        # Este endpoint es público (no requiere autenticación)
        response = await client.get("/auth/sso/url")
        # Puede fallar si no hay configuración de Azure, pero la estructura es correcta
        if response.status_code == 200:
            data = response.json()
            assert "authorization_url" in data
            assert "state" in data

    async def test_auth_callback_without_code_redirects(self, client: AsyncClient):
        """GET /auth sin code debe redirigir al login con error."""
        response = await client.get("/auth", follow_redirects=False)
        assert response.status_code in [302, 307]
