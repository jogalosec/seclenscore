"""
Tests de integración — Endpoints PAC (Plan de Acciones Correctivas + Continuidad).
Ejecutar: pytest tests/integration/test_pac.py -v
"""
import pytest
from httpx import AsyncClient, ASGITransport
from unittest.mock import AsyncMock, patch


# ── Fixtures ──────────────────────────────────────────────────────────────────

@pytest.fixture
async def client():
    from app.main import app
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        yield ac


@pytest.fixture
def mock_user():
    return {"id": 1, "user_id": 1, "email": "admin@test.com", "additionalAccess": True}


# ── 401 sin autenticación ─────────────────────────────────────────────────────

class TestPacAuthRequired:

    @pytest.mark.asyncio
    async def test_get_list_pac_sin_auth(self, client):
        resp = await client.get("/api/getListPac")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_create_pac_sin_auth(self, client):
        resp = await client.post("/api/createPac", json={"activo_id": 1, "descripcion": "Test"})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_seguimiento_sin_auth(self, client):
        resp = await client.get("/api/getSeguimientoByPacID", params={"pac_id": 1})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_new_plan_sin_auth(self, client):
        resp = await client.post("/api/newPlan", json={"activo_id": 1, "nombre": "Plan"})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_productos_sin_auth(self, client):
        resp = await client.get("/api/getProductosContinuidad")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_download_pac_sin_auth(self, client):
        resp = await client.get("/api/downloadPac", params={"pac_id": 1})
        assert resp.status_code == 401


# ── Validaciones 422 ──────────────────────────────────────────────────────────

class TestPacValidaciones:

    @pytest.mark.asyncio
    async def test_crear_pac_sin_activo_id_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post(
                "/api/createPac",
                json={"descripcion": "sin activo"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_crear_pac_sin_descripcion_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post(
                "/api/createPac",
                json={"activo_id": 1},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_new_plan_sin_nombre_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post(
                "/api/newPlan",
                json={"activo_id": 1},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_seguimiento_estado_invalido_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post(
                "/api/ModEstadoPacSeguimiento",
                json={"pac_id": 1, "descripcion": "Test", "estado": "invalido"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 422


# ── PAC CRUD ──────────────────────────────────────────────────────────────────

class TestPacCrud:

    @pytest.mark.asyncio
    async def test_get_list_pac_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.pac_service.get_pac_list", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 1, "descripcion": "Acción 1", "estado": "pendiente"}]
            resp = await client.get("/api/getListPac", params={"activo_id": 1}, headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert isinstance(data, list)

    @pytest.mark.asyncio
    async def test_crear_pac_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.pac_service.crear_pac", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False, "id": 5}
            resp = await client.post(
                "/api/createPac",
                json={"activo_id": 1, "descripcion": "Acción correctiva"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
        assert resp.json().get("error") is False

    @pytest.mark.asyncio
    async def test_get_seguimiento_pac_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.pac_service.get_seguimiento", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 1, "estado": "pendiente"}]
            resp = await client.get("/api/getSeguimientoByPacID", params={"pac_id": 1}, headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_mod_estado_seguimiento_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.pac_service.crear_seguimiento", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False}
            resp = await client.post(
                "/api/ModEstadoPacSeguimiento",
                json={"pac_id": 1, "descripcion": "Avance", "estado": "en_curso"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_edit_seguimiento_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.pac_service.editar_seguimiento", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False}
            resp = await client.post(
                "/api/editPacSeguimiento",
                json={"id": 1, "estado": "completado"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_delete_seguimiento_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.pac_service.eliminar_seguimiento", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False}
            resp = await client.post(
                "/api/deletePacSeguimiento",
                json={"id": 1},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200


# ── Planes de Continuidad ─────────────────────────────────────────────────────

class TestPlanesContinuidad:

    @pytest.mark.asyncio
    async def test_get_productos_continuidad_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.pac_service.get_planes", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 1, "nombre": "Plan DR"}]
            resp = await client.get("/api/getProductosContinuidad", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_new_plan_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.pac_service.crear_plan", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False, "id": 3}
            resp = await client.post(
                "/api/newPlan",
                json={"activo_id": 1, "nombre": "Plan continuidad Q1"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_edit_plan_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.pac_service.editar_plan", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False}
            resp = await client.post(
                "/api/editPlan",
                json={"id": 1, "nombre": "Plan actualizado"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_delete_plan_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.pac_service.eliminar_plan", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False}
            resp = await client.post(
                "/api/deletePlan",
                json={"id": 1},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
