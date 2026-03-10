"""
Tests de integración para los endpoints del módulo Evaluaciones.
Ejecutar: pytest tests/integration/test_evaluaciones.py -v
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
    return {"id": 1, "user_id": 1, "email": "analyst@test.com"}


@pytest.fixture
def respuestas_bia():
    return {f"p{i}": 2 for i in range(1, 47)}


# ---------------------------------------------------------------
# Auth requerida — 401 sin token
# ---------------------------------------------------------------

class TestEvaluacionesAuthRequired:

    @pytest.mark.asyncio
    async def test_get_bia_sin_auth(self, client):
        resp = await client.get("/api/getBia", params={"id": 1})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_save_bia_sin_auth(self, client):
        resp = await client.post("/api/saveBia", json={"activo_id": 1, "respuestas": {}})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_evaluaciones_sin_auth(self, client):
        resp = await client.get("/api/getEvaluaciones", params={"id": 1})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_save_evaluacion_sin_auth(self, client):
        resp = await client.post("/api/saveEvaluacion", json={"datos": {}}, params={"activo_id": 1})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_evaluaciones_sistema_sin_auth(self, client):
        resp = await client.get("/api/getEvaluacionesSistema", params={"id": 1})
        assert resp.status_code == 401


# ---------------------------------------------------------------
# Tests con auth mockeada
# ---------------------------------------------------------------

class TestBIA:

    @pytest.mark.asyncio
    async def test_get_bia_activo_sin_datos(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.evaluaciones_service.get_bia_activo",
                new=AsyncMock(return_value=None),
            ),
        ):
            resp = await client.get("/api/getBia", params={"id": 999})

        assert resp.status_code == 200
        assert resp.json()["bia"] is None

    @pytest.mark.asyncio
    async def test_get_bia_activo_con_datos(self, client, mock_user):
        bia_mock = {"id": 1, "activo_id": 5, "meta_value": {"p1": 3, "p2": 2}}

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.evaluaciones_service.get_bia_activo",
                new=AsyncMock(return_value=bia_mock),
            ),
        ):
            resp = await client.get("/api/getBia", params={"id": 5})

        assert resp.status_code == 200
        assert resp.json()["bia"]["activo_id"] == 5

    @pytest.mark.asyncio
    async def test_save_bia_devuelve_calculo(self, client, mock_user, respuestas_bia):
        calculo_mock = {
            "error": False,
            "message": "BIA guardado correctamente.",
            "bia": {"Con": {"total": 2.0}, "Int": {"total": 2.0}, "Dis": {"total": 2.0}, "global": 2.0},
        }

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.evaluaciones_service.guardar_bia",
                new=AsyncMock(return_value=calculo_mock),
            ),
        ):
            resp = await client.post("/api/saveBia", json={"activo_id": 1, "respuestas": respuestas_bia})

        assert resp.status_code == 200
        data = resp.json()
        assert data["error"] is False
        assert "bia" in data

    @pytest.mark.asyncio
    async def test_save_bia_sin_activo_id_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post("/api/saveBia", json={"respuestas": {"p1": 2}})
        assert resp.status_code == 422


class TestEvaluaciones:

    @pytest.mark.asyncio
    async def test_get_evaluaciones_devuelve_lista(self, client, mock_user):
        evals_mock = [{"id": 1, "meta_key": "preguntas", "fecha": "2024-01-01"}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.evaluaciones_service.get_evaluaciones_activo",
                new=AsyncMock(return_value=evals_mock),
            ),
        ):
            resp = await client.get("/api/getEvaluaciones", params={"id": 1})

        assert resp.status_code == 200
        assert len(resp.json()["evaluaciones"]) == 1

    @pytest.mark.asyncio
    async def test_get_evaluaciones_sin_id_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.get("/api/getEvaluaciones")
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_save_evaluacion_valida(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.evaluaciones_service.guardar_evaluacion",
                new=AsyncMock(return_value={"error": False, "message": "Evaluación guardada correctamente."}),
            ),
        ):
            resp = await client.post(
                "/api/saveEvaluacion",
                json={"datos": {"p1": 3}},
                params={"activo_id": 1, "meta_key": "preguntas"},
            )

        assert resp.status_code == 200
        assert resp.json()["error"] is False

    @pytest.mark.asyncio
    async def test_get_fecha_evaluaciones(self, client, mock_user):
        historial_mock = [{"id": 1, "fecha": "2024-01-01"}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.evaluaciones_service.get_historial_evaluaciones",
                new=AsyncMock(return_value=historial_mock),
            ),
        ):
            resp = await client.get("/api/getFechaEvaluaciones", params={"id": 1})

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_get_evaluaciones_sistema(self, client, mock_user):
        sistema_mock = [{"id": 1, "version": False}, {"id": 10, "version": 1}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.evaluaciones_service.get_evaluaciones_sistema",
                new=AsyncMock(return_value=sistema_mock),
            ),
        ):
            resp = await client.get("/api/getEvaluacionesSistema", params={"id": 1})

        assert resp.status_code == 200
        assert len(resp.json()["evaluaciones"]) == 2

    @pytest.mark.asyncio
    async def test_get_preguntas_evaluacion(self, client, mock_user):
        preguntas_mock = {"preguntas": {"p1": 3, "p2": 1}}

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.evaluaciones_service.get_preguntas_evaluacion",
                new=AsyncMock(return_value=preguntas_mock),
            ),
        ):
            resp = await client.get("/api/getPreguntasEvaluacion", params={"id": 5})

        assert resp.status_code == 200
        assert "preguntas" in resp.json()

    @pytest.mark.asyncio
    async def test_edit_evaluacion_crea_version(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.evaluaciones_service.editar_evaluacion",
                new=AsyncMock(return_value={"error": False, "message": "Versión de evaluación creada correctamente."}),
            ),
        ):
            resp = await client.post(
                "/api/editEvaluacion",
                json={"evaluate": {"p1": 2}, "version": "v2"},
                params={"eval_id": 1},
            )

        assert resp.status_code == 200


class TestOSAyPAC:

    @pytest.mark.asyncio
    async def test_save_eval_osa_valida(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.evaluaciones_service.guardar_eval_osa",
                new=AsyncMock(return_value={"error": False, "message": "Evaluación OSA guardada correctamente."}),
            ),
        ):
            resp = await client.post("/api/saveEvalOsa", json={"revision_id": 5, "datos": {"osa1": 4}})

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_get_pac_eval(self, client, mock_user):
        pac_mock = [{"id": 1, "meta_key": "pac", "fecha": "2024-01-01"}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.evaluaciones_service.get_pac_eval",
                new=AsyncMock(return_value=pac_mock),
            ),
        ):
            resp = await client.get("/api/getPacEval", params={"id": 1, "fecha": "2024-01-01"})

        assert resp.status_code == 200
