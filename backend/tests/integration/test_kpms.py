"""
Tests de integración para los endpoints del módulo KPMs.
Ejecutar: pytest tests/integration/test_kpms.py -v
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
    return {"id": 1, "user_id": 1, "email": "analyst@test.com", "additionalAccess": False}


@pytest.fixture
def mock_admin():
    return {"id": 2, "user_id": 2, "email": "admin@test.com", "additionalAccess": True}


# ---------------------------------------------------------------
# Auth requerida — 401 sin token
# ---------------------------------------------------------------

class TestKpmsAuthRequired:

    @pytest.mark.asyncio
    async def test_get_kpms_sin_auth(self, client):
        resp = await client.get("/api/getKpms", params={"tipo": "metricas"})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_lock_kpms_sin_auth(self, client):
        resp = await client.post("/api/lockKpms", json={"tipo": "metricas", "id": [1]})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_del_kpms_sin_auth(self, client):
        resp = await client.post("/api/delKpms", json={"tipo": "madurez", "id": [2]})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_edit_kpm_sin_auth(self, client):
        resp = await client.post("/api/editKpm", json={"tipo": "csirt", "id": 1, "campos": {}})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_new_reporter_sin_auth(self, client):
        resp = await client.post("/api/newReporterKpms", json={"userId": 1, "idActivo": 5})
        assert resp.status_code == 401


# ---------------------------------------------------------------
# Validación de schema (422) — sin necesidad de auth
# ---------------------------------------------------------------

class TestKpmsSchemaValidation:

    @pytest.mark.asyncio
    async def test_tipo_invalido_lock_422(self, client, mock_user):
        """Un tipo fuera de la whitelist debe devolver 422."""
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post("/api/lockKpms", json={
                "tipo": "evaluaciones; DROP TABLE metricas;--",
                "id": [1],
            })
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_ids_vacios_lock_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post("/api/lockKpms", json={"tipo": "metricas", "id": []})
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_tipo_invalido_del_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post("/api/delKpms", json={"tipo": "any_other_table", "id": [1]})
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_tipo_requerido_get_kpms_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.get("/api/getKpms")
        assert resp.status_code == 422


# ---------------------------------------------------------------
# Tests con auth mockeada
# ---------------------------------------------------------------

class TestGetKpms:

    @pytest.mark.asyncio
    async def test_get_kpms_metricas(self, client, mock_user):
        kpms_mock = [{"id": 1, "valor": 3, "reporter_email": "u@test.com"}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.kpms_service.get_kpms_usuario",
                new=AsyncMock(return_value={"error": False, "kpms": kpms_mock, "message": "OK"}),
            ),
        ):
            resp = await client.get("/api/getKpms", params={"tipo": "metricas"})

        assert resp.status_code == 200
        assert resp.json()["error"] is False
        assert len(resp.json()["kpms"]) == 1

    @pytest.mark.asyncio
    async def test_get_kpms_madurez(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.kpms_service.get_kpms_usuario",
                new=AsyncMock(return_value={"error": False, "kpms": [], "message": "OK"}),
            ),
        ):
            resp = await client.get("/api/getKpms", params={"tipo": "madurez"})

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_get_last_report_kpms(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.kpms_service.get_ultimo_reporte",
                new=AsyncMock(return_value={"error": False, "reporte": []}),
            ),
        ):
            resp = await client.get("/api/getLastReportKpms", params={"tipo": "csirt"})

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_get_preguntas_kpms_formulario(self, client, mock_user):
        preguntas_mock = [{"id": 1, "numeroKPM": "KPM-01"}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.kpms_service.get_preguntas_formulario",
                new=AsyncMock(return_value={"error": False, "preguntas": preguntas_mock}),
            ),
        ):
            resp = await client.get("/api/getPreguntasKpms")

        assert resp.status_code == 200


class TestAccionesMasivas:

    @pytest.mark.asyncio
    async def test_lock_kpms_valido(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.kpms_service.bloquear_kpms",
                new=AsyncMock(return_value={"error": False, "message": "2 KPM(s) bloqueados correctamente."}),
            ),
        ):
            resp = await client.post("/api/lockKpms", json={"tipo": "metricas", "id": [1, 2]})

        assert resp.status_code == 200
        assert resp.json()["error"] is False

    @pytest.mark.asyncio
    async def test_unlock_kpms_valido(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.kpms_service.bloquear_kpms",
                new=AsyncMock(return_value={"error": False, "message": "1 KPM(s) desbloqueados correctamente."}),
            ),
        ):
            resp = await client.post("/api/unlockKpms", json={"tipo": "madurez", "id": [5]})

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_del_kpms_valido(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.kpms_service.eliminar_kpms",
                new=AsyncMock(return_value={"error": False, "message": "3 KPM(s) eliminados correctamente."}),
            ),
        ):
            resp = await client.post("/api/delKpms", json={"tipo": "csirt", "id": [10, 11, 12]})

        assert resp.status_code == 200


class TestEdicionKpm:

    @pytest.mark.asyncio
    async def test_edit_kpm_valido(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.kpms_service.editar_kpm",
                new=AsyncMock(return_value={"error": False, "message": "KPM actualizado correctamente."}),
            ),
        ):
            resp = await client.post("/api/editKpm", json={
                "tipo": "metricas",
                "id": 1,
                "campos": {"valor": 3.5, "comentario": "Revisado"},
            })

        assert resp.status_code == 200
        assert resp.json()["error"] is False

    @pytest.mark.asyncio
    async def test_edit_kpm_definicion(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.kpms_service.actualizar_definicion_kpm",
                new=AsyncMock(return_value={"error": False, "message": "Definición KPM actualizada correctamente."}),
            ),
        ):
            resp = await client.post("/api/editKpmDefinicion", json={
                "id": 5,
                "nombre": "KPM-05 Actualizado",
                "descripcion_larga": "Descripción completa actualizada",
            })

        assert resp.status_code == 200


class TestReporters:

    @pytest.mark.asyncio
    async def test_get_reporters_kpms(self, client, mock_user):
        reporters_mock = [{"id": 1, "email": "r@test.com", "activo_nombre": "Servidor"}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.kpms_service.get_reporters_kpms",
                new=AsyncMock(return_value={"error": False, "reporters": reporters_mock}),
            ),
        ):
            resp = await client.get("/api/getReportersKpms")

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_new_reporter_valido(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.kpms_service.crear_reporter",
                new=AsyncMock(return_value={"error": False, "message": "Reporter KPM creado correctamente."}),
            ),
        ):
            resp = await client.post("/api/newReporterKpms", json={"userId": 3, "idActivo": 7})

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_delete_reporter_valido(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.kpms_service.eliminar_reporter",
                new=AsyncMock(return_value={"error": False, "message": "Reporter KPM eliminado correctamente."}),
            ),
        ):
            resp = await client.post("/api/deleteReporterKpms", json={"idRelacion": 42})

        assert resp.status_code == 200
