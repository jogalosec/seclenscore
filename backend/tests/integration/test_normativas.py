"""
Tests de integración para los endpoints del módulo Normativas / Controles / USFs / Preguntas / Marco.
Ejecutar: pytest tests/integration/test_normativas.py -v
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
    return {"id": 1, "user_id": 1, "email": "admin@test.com"}


# ---------------------------------------------------------------
# Auth requerida — 401 sin token
# ---------------------------------------------------------------

class TestNormativasAuthRequired:

    @pytest.mark.asyncio
    async def test_get_normativas_sin_auth(self, client):
        resp = await client.get("/api/getNormativas")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_new_normativa_sin_auth(self, client):
        resp = await client.post("/api/newNormativa", json={"nombre": "ISO", "version": "2022"})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_delete_normativa_sin_auth(self, client):
        resp = await client.post("/api/deleteNormativa", json={"idNormativa": 1})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_usfs_sin_auth(self, client):
        resp = await client.get("/api/getUSFs")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_preguntas_sin_auth(self, client):
        resp = await client.get("/api/getPreguntas")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_crear_relacion_completa_sin_auth(self, client):
        resp = await client.post("/api/crearRelacionCompleta", json={"id": 1, "relaciones": []})
        assert resp.status_code == 401


# ---------------------------------------------------------------
# Tests con auth mockeada
# ---------------------------------------------------------------

class TestNormativas:

    @pytest.mark.asyncio
    async def test_get_normativas_devuelve_lista(self, client, mock_user):
        normativas_mock = [{"id": 1, "nombre": "ISO 27001", "controles": []}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.normativas_service.get_normativas_completas",
                new=AsyncMock(return_value=normativas_mock),
            ),
        ):
            resp = await client.get("/api/getNormativas")

        assert resp.status_code == 200
        data = resp.json()
        assert isinstance(data, list)

    @pytest.mark.asyncio
    async def test_new_normativa_valida(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_new.new_normativa", new=AsyncMock(return_value=None)),
        ):
            resp = await client.post("/api/newNormativa", json={"nombre": "ISO 27001", "version": "2022"})

        assert resp.status_code == 200
        assert resp.json()["error"] is False

    @pytest.mark.asyncio
    async def test_new_normativa_sin_nombre_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post("/api/newNormativa", json={"version": "2022"})
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_edit_normativa_valida(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_new.edit_normativa", new=AsyncMock(return_value=None)),
        ):
            resp = await client.post("/api/editNormativa", json={
                "idNormativa": 1, "nombre": "ISO 27001 Rev", "enabled": True
            })

        assert resp.status_code == 200
        assert resp.json()["error"] is False

    @pytest.mark.asyncio
    async def test_delete_normativa_valida(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_new.delete_normativa", new=AsyncMock(return_value=None)),
        ):
            resp = await client.post("/api/deleteNormativa", json={"idNormativa": 5})

        assert resp.status_code == 200


class TestControles:

    @pytest.mark.asyncio
    async def test_new_control_valido(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_new.new_control", new=AsyncMock(return_value=None)),
        ):
            resp = await client.post("/api/newControl", json={
                "codigo": "A.5.1",
                "nombre": "Políticas",
                "descripcion": "Desc",
                "dominio": "A.5",
                "idNormativa": 1,
            })

        assert resp.status_code == 200
        assert resp.json()["error"] is False

    @pytest.mark.asyncio
    async def test_new_control_sin_campos_422(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post("/api/newControl", json={"codigo": "A.5.1"})
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_delete_control_valido(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_new.delete_control", new=AsyncMock(return_value=None)),
        ):
            resp = await client.post("/api/deleteControl", json={"idControl": 10})

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_get_dominios_unicos(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_new.get_dominios_controles_unicos", new=AsyncMock(return_value=["A.5", "A.6"])),
        ):
            resp = await client.get("/api/getDominiosUnicosControles")

        assert resp.status_code == 200
        assert "dominios" in resp.json()


class TestUSFs:

    @pytest.mark.asyncio
    async def test_get_usfs_devuelve_lista(self, client, mock_user):
        usfs_mock = [{"id": 1, "codigo": "USF-001", "nombre": "Control acceso"}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.normativas_service.get_usfs_completas",
                new=AsyncMock(return_value=usfs_mock),
            ),
        ):
            resp = await client.get("/api/getUSFs")

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_new_usf_valido(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.normativas_service.crear_usf",
                new=AsyncMock(return_value={"error": False, "message": "USF creado."}),
            ),
        ):
            resp = await client.post("/api/newUSF", json={
                "codigo": "USF-NEW",
                "nombre": "Nuevo",
                "descripcion": "Desc",
                "dominio": "Dom",
                "tipo": "preventivo",
                "IdPAC": None,
            })

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_delete_usf_valido(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_new.delete_usf", new=AsyncMock(return_value=None)),
        ):
            resp = await client.post("/api/deleteUSF", json={"idUSF": 3})

        assert resp.status_code == 200


class TestPreguntas:

    @pytest.mark.asyncio
    async def test_get_preguntas_devuelve_lista(self, client, mock_user):
        preguntas_mock = [{"id": 1, "duda": "¿MFA activado?", "nivel": 2}]

        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.normativas_service.get_preguntas_completas",
                new=AsyncMock(return_value=preguntas_mock),
            ),
        ):
            resp = await client.get("/api/getPreguntas")

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_new_pregunta_valida(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.normativas_service.crear_pregunta",
                new=AsyncMock(return_value={"error": False, "message": "Pregunta creada.", "id": 20}),
            ),
        ):
            resp = await client.post("/api/newPregunta", json={"duda": "¿Se aplica cifrado?", "nivel": 3})

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_delete_pregunta_valida(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_new.delete_pregunta", new=AsyncMock(return_value=None)),
        ):
            resp = await client.post("/api/deletePregunta", json={"idPregunta": 7})

        assert resp.status_code == 200


class TestRelacionesMarco:

    @pytest.mark.asyncio
    async def test_crear_relacion_completa_valida(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch(
                "app.services.normativas_service.crear_relacion_completa",
                new=AsyncMock(return_value={"error": False, "message": "Relaciones creadas.", "total": 2}),
            ),
        ):
            resp = await client.post("/api/crearRelacionCompleta", json={
                "id": 1,
                "relaciones": [{"idUSF": 2, "preguntas": [{"id": 5}]}],
            })

        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_delete_relacion_marco_valida(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.db.octopus_new.delete_relacion_marco", new=AsyncMock(return_value=None)),
        ):
            resp = await client.post("/api/deleteRelacionMarcoNormativa", json={"idRelacion": 15})

        assert resp.status_code == 200
