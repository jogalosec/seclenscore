"""
Tests de integración — Endpoints EVS (Pentest + Solicitudes + Revisiones Prisma).
Ejecutar: pytest tests/integration/test_evs.py -v
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


@pytest.fixture
def auth_headers():
    return {"Authorization": "Bearer test-token"}


# ── 401 sin autenticación ─────────────────────────────────────────────────────

class TestEvsAuthRequired:

    @pytest.mark.asyncio
    async def test_obtener_pentests_sin_auth(self, client):
        resp = await client.get("/api/obtainActivosPentest")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_crear_pentest_sin_auth(self, client):
        resp = await client.post("/api/crearPentest", json={"nombre": "Test"})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_solicitudes_sin_auth(self, client):
        resp = await client.get("/api/getSolicitudesPentest")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_pentest_request_sin_auth(self, client):
        resp = await client.post("/api/pentestRequest", json={"nombre": "Test"})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_revisiones_sin_auth(self, client):
        resp = await client.get("/api/obtainActivosRevision")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_crear_revision_sin_auth(self, client):
        resp = await client.post("/api/crearRevision", json={"nombre": "Test"})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_prisma_cloud_sin_auth(self, client):
        resp = await client.get("/api/getPrismaCloud")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_dismiss_prisma_sin_auth(self, client):
        resp = await client.post("/api/dismissPrismaAlert", json={"alert_id": "A-1"})
        assert resp.status_code == 401


# ── Validaciones 422 ──────────────────────────────────────────────────────────

class TestEvsValidaciones:

    @pytest.mark.asyncio
    async def test_crear_pentest_sin_nombre_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post("/api/crearPentest", json={}, headers={"Authorization": "Bearer t"})
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_insert_activos_lista_vacia_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post(
                "/api/insertActivosPentest",
                json={"pentest_id": 1, "activos": []},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_nuevo_issue_sin_titulo_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post(
                "/api/newIssue",
                json={"descripcion": "desc"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_solicitud_sin_nombre_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post(
                "/api/pentestRequest",
                json={},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 422


# ── Pentests ──────────────────────────────────────────────────────────────────

class TestPentestEndpoints:

    @pytest.mark.asyncio
    async def test_get_pentests_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.evs_service.get_pentests", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 1, "nombre": "Pentest Web", "estado": "abierto"}]
            resp = await client.get("/api/obtainActivosPentest", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert isinstance(data, list)
        assert data[0]["nombre"] == "Pentest Web"

    @pytest.mark.asyncio
    async def test_crear_pentest_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.evs_service.crear_pentest", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False, "id": 99, "message": "Pentest creado"}
            resp = await client.post(
                "/api/crearPentest",
                json={"nombre": "Nuevo pentest", "descripcion": "desc"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
        assert resp.json().get("error") is False

    @pytest.mark.asyncio
    async def test_cerrar_pentest_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.evs_service.cambiar_estado_pentest", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False, "message": "Estado cambiado"}
            resp = await client.get("/api/cerrarPentest", params={"id": 1}, headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_eliminar_pentest_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.evs_service.eliminar_pentest", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False, "message": "Pentest eliminado"}
            resp = await client.get("/api/eliminarPentest", params={"id": 1}, headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_pentest_by_id_no_encontrado(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_serv_evs.get_pentest_by_id", new_callable=AsyncMock, return_value=None),
        ):
            resp = await client.get("/api/getInfoPentestByID", params={"id": 9999}, headers={"Authorization": "Bearer t"})
        assert resp.status_code == 404


# ── Solicitudes ───────────────────────────────────────────────────────────────

class TestSolicitudesEndpoints:

    @pytest.mark.asyncio
    async def test_get_solicitudes_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.evs_service.get_solicitudes", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = []
            resp = await client.get("/api/getSolicitudesPentest", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_crear_solicitud_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.evs_service.crear_solicitud", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False, "id": 10}
            resp = await client.post(
                "/api/pentestRequest",
                json={"nombre": "Solicitud test"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_aceptar_solicitud_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.evs_service.aceptar_solicitud", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False}
            resp = await client.post(
                "/api/aceptarSolicitudPentest",
                json={"id": 5},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_rechazar_solicitud_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.evs_service.rechazar_solicitud", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False}
            resp = await client.post(
                "/api/rechazarSolicitudPentest",
                json={"id": 5, "comentario": "No procede"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200


# ── Revisiones Prisma ─────────────────────────────────────────────────────────

class TestRevisionesEndpoints:

    @pytest.mark.asyncio
    async def test_get_revisiones_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.evs_service.get_revisiones", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = []
            resp = await client.get("/api/obtainActivosRevision", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_crear_revision_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.evs_service.crear_revision", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False, "id": 20}
            resp = await client.post(
                "/api/crearRevision",
                json={"nombre": "Revisión Prisma Q1"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_cerrar_revision_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.evs_service.cambiar_estado_revision", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False}
            resp = await client.get("/api/cerrarRevision", params={"id": 1}, headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_dismiss_alerta_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.prisma_service.dismiss_alerta", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False}
            resp = await client.post(
                "/api/dismissPrismaAlert",
                json={"alert_id": "ALERT-001", "razon": "Falso positivo"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
