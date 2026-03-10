"""
Tests de integración — Endpoints Dashboard.
Ejecutar: pytest tests/integration/test_dashboard.py -v
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

class TestDashboardAuthRequired:

    @pytest.mark.asyncio
    async def test_get_dashboard_sin_auth(self, client):
        resp = await client.get("/api/getDashboard")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_dashboard_activos_sin_auth(self, client):
        resp = await client.get("/api/getDashboardActivos")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_dashboard_bia_sin_auth(self, client):
        resp = await client.get("/api/getDashboardBia")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_dashboard_ecr_sin_auth(self, client):
        resp = await client.get("/api/getDashboardEcr")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_dashboard_pentest_sin_auth(self, client):
        resp = await client.get("/api/getDashboardPentest")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_dashboard_pac_sin_auth(self, client):
        resp = await client.get("/api/getDashboardPac")
        assert resp.status_code == 401


# ── Endpoints con auth ────────────────────────────────────────────────────────

class TestDashboardEndpoints:

    @pytest.mark.asyncio
    async def test_get_dashboard_completo_ok(self, client, mock_user):
        completo = {
            "activos": {"total": 50},
            "bia": {"critico": 2, "alto": 5},
            "ecr": {"total": 10},
            "pentest": {"abierto": 3},
            "pac": {"total": 8},
        }
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.dashboard_service.get_dashboard_completo", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = completo
            resp = await client.get("/api/getDashboard", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert "activos" in data or "error" in data

    @pytest.mark.asyncio
    async def test_get_dashboard_activos_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.dashboard_service.get_dashboard_activos", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"total": 50, "archivados": 10, "expuestos": 5}
            resp = await client.get("/api/getDashboardActivos", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert data.get("error") is False
        assert data.get("total") == 50

    @pytest.mark.asyncio
    async def test_get_dashboard_bia_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.dashboard_service.get_dashboard_bia", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"critico": 1, "alto": 3, "medio": 10, "bajo": 5}
            resp = await client.get("/api/getDashboardBia", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert data.get("error") is False
        assert "critico" in data

    @pytest.mark.asyncio
    async def test_get_dashboard_ecr_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.dashboard_service.get_dashboard_ecr", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"total": 15, "ultimos_90d": 4}
            resp = await client.get("/api/getDashboardEcr", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert data.get("total") == 15

    @pytest.mark.asyncio
    async def test_get_dashboard_pentest_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.dashboard_service.get_dashboard_pentest", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"abierto": 3, "en_curso": 1, "cerrado": 7}
            resp = await client.get("/api/getDashboardPentest", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert data.get("abierto") == 3

    @pytest.mark.asyncio
    async def test_get_dashboard_pac_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.dashboard_service.get_dashboard_pac", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"total": 20, "seguimiento_por_estado": {"completado": 8, "pendiente": 12}}
            resp = await client.get("/api/getDashboardPac", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert data.get("total") == 20

    @pytest.mark.asyncio
    async def test_dashboard_activos_sin_datos_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.dashboard_service.get_dashboard_activos", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"total": 0, "archivados": 0, "expuestos": 0}
            resp = await client.get("/api/getDashboardActivos", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        assert resp.json()["total"] == 0
