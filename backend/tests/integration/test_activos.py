"""
Tests de integración para los endpoints del módulo Activos.
Ejecutar: pytest tests/integration/test_activos.py -v
Requiere DB de test o mocks completos.
"""
import pytest
from httpx import AsyncClient, ASGITransport
from unittest.mock import AsyncMock, patch


# ---------------------------------------------------------------
# App fixture
# ---------------------------------------------------------------

@pytest.fixture
async def client():
    """Cliente HTTP asíncrono apuntando a la app FastAPI."""
    from app.main import app
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        yield ac


@pytest.fixture
def auth_headers():
    """Cabeceras simulando Bearer token 11_ (stub para tests)."""
    return {"Authorization": "Bearer 11_test_token_for_integration"}


@pytest.fixture
def mock_user():
    return {"user_id": 1, "email": "test@test.com", "auth_mode": "cookie"}


# ---------------------------------------------------------------
# Tests de autenticación (sin token)
# ---------------------------------------------------------------

class TestActivosAuthRequired:

    @pytest.mark.asyncio
    async def test_get_activos_sin_auth_devuelve_401(self, client):
        resp = await client.get("/api/getActivos", params={"tipo": "42"})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_child_sin_auth_devuelve_401(self, client):
        resp = await client.get("/api/getChild/1")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_tree_sin_auth_devuelve_401(self, client):
        resp = await client.get("/api/getTree/1")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_post_new_activo_sin_auth_devuelve_401(self, client):
        resp = await client.post("/api/newActivo", json={"nombre": "Test", "clase": 42})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_post_delete_activo_sin_auth_devuelve_401(self, client):
        resp = await client.post("/api/deleteActivo", params={"id": 1})
        assert resp.status_code == 401


# ---------------------------------------------------------------
# Tests con auth mockeada
# ---------------------------------------------------------------

class TestActivosConAuth:

    @pytest.mark.asyncio
    async def test_get_activos_tipo_requerido(self, client, mock_user):
        """Sin parámetro tipo debe devolver 422."""
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.get("/api/getActivos")
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_get_activos_devuelve_lista(self, client, mock_user):
        """Con tipo válido y DB mockeada debe devolver lista."""
        activos_mock = [{"id": 1, "nombre": "Test", "tipo": 42}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.activos_service.get_activos_lista", new=AsyncMock(return_value={"error": False, "activos": activos_mock})),
        ):
            resp = await client.get("/api/getActivos", params={"tipo": "42"})

        assert resp.status_code == 200
        data = resp.json()
        assert data["error"] is False

    @pytest.mark.asyncio
    async def test_get_child_not_found(self, client, mock_user):
        """Activo inexistente debe devolver 404."""
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_serv.get_activo_by_id", new=AsyncMock(return_value=None)),
        ):
            resp = await client.get("/api/getChild/99999")

        assert resp.status_code == 404

    @pytest.mark.asyncio
    async def test_get_brothers_devuelve_lista(self, client, mock_user):
        hermanos_mock = [{"id": 2, "nombre": "Hermano"}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_serv.get_brothers", new=AsyncMock(return_value=hermanos_mock)),
        ):
            resp = await client.get("/api/getBrothers", params={"id": 1, "padre": 10})

        assert resp.status_code == 200
        assert "hermanos" in resp.json()

    @pytest.mark.asyncio
    async def test_new_activo_nombre_requerido(self, client, mock_user):
        """Cuerpo sin nombre debe devolver 422."""
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post("/api/newActivo", json={"clase": 42})
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_new_activo_crea_correctamente(self, client, mock_user):
        nuevo_mock = {"id": 10, "nombre": "Nuevo Activo", "tipo": 42}

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.activos_service.crear_activo", new=AsyncMock(
                return_value={"error": False, "message": "Activo creado", "data": nuevo_mock}
            )),
        ):
            resp = await client.post("/api/newActivo", json={"nombre": "Nuevo Activo", "clase": 42})

        assert resp.status_code == 200
        assert resp.json()["error"] is False

    @pytest.mark.asyncio
    async def test_import_activos_devuelve_501(self, client, mock_user):
        """importActivos debe devolver 501 hasta que esté implementado."""
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post("/api/importActivos")
        assert resp.status_code == 501

    @pytest.mark.asyncio
    async def test_obtain_all_type_activos(self, client, mock_user):
        tipos_mock = [{"id": 42, "nombre": "Servidor"}, {"id": 67, "nombre": "Aplicación"}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_serv.obtain_all_type_activos", new=AsyncMock(return_value=tipos_mock)),
        ):
            resp = await client.get("/api/obtainAllTypeActivos")

        assert resp.status_code == 200
        assert "tipos" in resp.json()

    @pytest.mark.asyncio
    async def test_change_relacion_valida(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_serv.change_relacion", new=AsyncMock(return_value=None)),
        ):
            resp = await client.post(
                "/api/changeRelacion",
                json={"activo_id": 5, "nuevo_padre_id": 10},
            )

        assert resp.status_code == 200
        assert resp.json()["error"] is False

    @pytest.mark.asyncio
    async def test_download_tree_activo_no_encontrado(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.activos_service.get_arbol_para_excel", new=AsyncMock(return_value=None)),
        ):
            resp = await client.get("/api/downloadTree", params={"id": 9999})

        assert resp.status_code == 404

    @pytest.mark.asyncio
    async def test_download_tree_devuelve_xlsx(self, client, mock_user):
        arbol_mock = [
            ["Nombre", "Tipo", "ID", "Padre", "Archivado", "Expuesto"],
            {"nombre": "Test", "tipo": "Servidor", "id": 1, "padre": None, "archivado": 0, "expuesto": 0},
        ]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.activos_service.get_arbol_para_excel", new=AsyncMock(return_value=arbol_mock)),
        ):
            resp = await client.get("/api/downloadTree", params={"id": 1})

        assert resp.status_code == 200
        assert "spreadsheetml" in resp.headers.get("content-type", "")
        assert "attachment" in resp.headers.get("content-disposition", "")
