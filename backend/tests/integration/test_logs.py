"""
Tests de integración — Endpoints Logs de auditoría.
Ejecutar: pytest tests/integration/test_logs.py -v
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

class TestLogsAuthRequired:

    @pytest.mark.asyncio
    async def test_get_logs_relacion_sin_auth(self, client):
        resp = await client.get("/api/getLogsRelacion")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_events_sin_auth(self, client):
        resp = await client.get("/api/getEvents")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_logs_activos_processed_sin_auth(self, client):
        resp = await client.post("/api/getLogsActivosProcessed")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_logs_activos_raw_sin_auth(self, client):
        resp = await client.post("/api/getLogsActivosRaw")
        assert resp.status_code == 401


# ── Endpoints con auth ────────────────────────────────────────────────────────

class TestLogsEndpoints:

    @pytest.mark.asyncio
    async def test_get_logs_relacion_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.logs_service.get_logs_relaciones", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 1, "accion": "INSERT", "tabla": "activos"}]
            resp = await client.get("/api/getLogsRelacion", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert isinstance(data, list)
        assert data[0]["accion"] == "INSERT"

    @pytest.mark.asyncio
    async def test_get_logs_relacion_con_fechas(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.logs_service.get_logs_relaciones", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = []
            resp = await client.get(
                "/api/getLogsRelacion",
                params={"fecha_inicio": "2026-01-01", "fecha_fin": "2026-03-31"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
        mock_svc.assert_called_once()
        # Verificar que los parámetros de fecha se pasaron al servicio
        call_args = mock_svc.call_args
        assert "2026-01-01" in str(call_args)
        assert "2026-03-31" in str(call_args)

    @pytest.mark.asyncio
    async def test_get_events_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.logs_service.get_logs_accesos", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 1, "evento": "login", "usuario": "admin"}]
            resp = await client.get("/api/getEvents", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert isinstance(data, list)

    @pytest.mark.asyncio
    async def test_get_events_con_limit(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.logs_service.get_logs_accesos", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = []
            resp = await client.get(
                "/api/getEvents",
                params={"limit": 100},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
        # Verificar que limit se pasó al servicio
        call_args = mock_svc.call_args
        assert 100 in str(call_args) or "100" in str(call_args)

    @pytest.mark.asyncio
    async def test_get_logs_activos_processed_ok(self, client, mock_user):
        timeline = [
            {"fecha": "2026-03-10 12:00:00", "tipo": "nuevo", "nombre": "Activo A"},
            {"fecha": "2026-03-09 08:00:00", "tipo": "modificado", "nombre": "Activo B"},
        ]
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.logs_service.get_logs_activos_procesados", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = timeline
            resp = await client.post("/api/getLogsActivosProcessed", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert isinstance(data, list)
        assert len(data) == 2

    @pytest.mark.asyncio
    async def test_get_logs_activos_raw_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.logs_service.get_route_logs", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 1, "tipo": "eliminado", "activo_id": 42}]
            resp = await client.post("/api/getLogsActivosRaw", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert isinstance(data, list)
        assert data[0]["tipo"] == "eliminado"

    @pytest.mark.asyncio
    async def test_get_logs_activos_processed_vacio(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.logs_service.get_logs_activos_procesados", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = []
            resp = await client.post("/api/getLogsActivosProcessed", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        assert resp.json() == []

    @pytest.mark.asyncio
    async def test_get_logs_relacion_vacio(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.logs_service.get_logs_relaciones", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = []
            resp = await client.get("/api/getLogsRelacion", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        assert resp.json() == []
