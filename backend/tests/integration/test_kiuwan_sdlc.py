"""
Tests de integración — Endpoints Kiuwan / SDLC (Sprint 7).
Ejecutar: pytest tests/integration/test_kiuwan_sdlc.py -v
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

class TestKiuwanAuthRequired:

    @pytest.mark.asyncio
    async def test_get_kiuwan_aplication_sin_auth(self, client):
        resp = await client.get("/api/getKiuwanAplication")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_kiuwan_apps_sin_auth(self, client):
        resp = await client.get("/api/getKiuwanApps")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_update_cumple_kpm_sin_auth(self, client):
        resp = await client.post("/api/updateCumpleKpm", json={"app_name": "App", "cumple_kpm": 1})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_update_sonar_kpm_sin_auth(self, client):
        resp = await client.post("/api/updateSonarKPM", json={"slot_sonarqube": "slot", "cumple_kpm_sonar": 0})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_obtener_sdlc_sin_auth(self, client):
        resp = await client.get("/api/obtenerSDLC")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_crear_app_sdlc_sin_auth(self, client):
        resp = await client.post("/api/crearAppSDLC", json={})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_modificar_app_sdlc_sin_auth(self, client):
        resp = await client.post("/api/modificarAppSDLC", params={"id": 1}, json={"id": 1})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_eliminar_app_sdlc_sin_auth(self, client):
        resp = await client.post("/api/eliminarAppSDLC", json={"id": 1, "app": "Kiuwan"})
        assert resp.status_code == 401


# ── Validaciones 422 ──────────────────────────────────────────────────────────

class TestKiuwanValidaciones:

    @pytest.mark.asyncio
    async def test_update_cumple_kpm_valor_invalido(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post(
                "/api/updateCumpleKpm",
                json={"app_name": "App", "cumple_kpm": 5},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_crear_app_sdlc_app_invalida(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post(
                "/api/crearAppSDLC",
                json={"app": "Jenkins", "Direccion": 1, "Area": 2, "Producto": 3,
                      "CMM": "A", "Analisis": "B"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_eliminar_sdlc_sin_id(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post(
                "/api/eliminarAppSDLC",
                json={"app": "Kiuwan"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 422


# ── Endpoints con auth ────────────────────────────────────────────────────────

class TestKiuwanEndpoints:

    @pytest.mark.asyncio
    async def test_get_kiuwan_aplication_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.kiuwan_service.get_kiuwan_stored", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 1, "app": "AppTest", "cumple_kpm": 1}]
            resp = await client.get("/api/getKiuwanAplication", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert isinstance(data, list)

    @pytest.mark.asyncio
    async def test_get_kiuwan_apps_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.kiuwan_service.get_kiuwan_applications", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"name": "AppRemota", "last_analysis_date": "2026-01-01"}]
            resp = await client.get("/api/getKiuwanApps", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        assert isinstance(resp.json(), list)

    @pytest.mark.asyncio
    async def test_update_cumple_kpm_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.kiuwan_service.update_cumple_kpm", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False, "message": "OK"}
            resp = await client.post(
                "/api/updateCumpleKpm",
                json={"app_name": "AppTest", "cumple_kpm": 1},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
        assert resp.json()["error"] is False

    @pytest.mark.asyncio
    async def test_update_sonar_kpm_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.kiuwan_service.update_sonar_kpm", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False}
            resp = await client.post(
                "/api/updateSonarKPM",
                json={"slot_sonarqube": "sonar-slot-1", "cumple_kpm_sonar": 0},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_obtener_sdlc_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.kiuwan_service.obtener_sdlc", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 1, "app": "Kiuwan", "CMM": "Nivel1"}]
            resp = await client.get("/api/obtenerSDLC", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        assert isinstance(resp.json(), list)

    @pytest.mark.asyncio
    async def test_obtener_sdlc_con_filtro_app(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.kiuwan_service.obtener_sdlc", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 1, "app": "Kiuwan"}]
            resp = await client.get(
                "/api/obtenerSDLC",
                params={"app": "Kiuwan"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
        call_args = mock_svc.call_args
        assert "Kiuwan" in str(call_args)

    @pytest.mark.asyncio
    async def test_crear_app_sdlc_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.kiuwan_service.crear_app_sdlc", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False, "message": "SDLC creado"}
            resp = await client.post(
                "/api/crearAppSDLC",
                json={"app": "Kiuwan", "Direccion": 1, "Area": 2, "Producto": 3,
                      "CMM": "Nivel1", "Analisis": "Manual"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
        assert resp.json()["error"] is False

    @pytest.mark.asyncio
    async def test_eliminar_app_sdlc_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.kiuwan_service.eliminar_app_sdlc", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False}
            resp = await client.post(
                "/api/eliminarAppSDLC",
                json={"id": 1, "app": "Kiuwan", "kiuwan_id": 42},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_get_kiuwan_apps_error_externo(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.kiuwan_service.get_kiuwan_applications",
                  new_callable=AsyncMock,
                  side_effect=Exception("Timeout Kiuwan")),
        ):
            resp = await client.get("/api/getKiuwanApps", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 502
        assert "Kiuwan" in resp.json()["detail"]
