"""
Tests de integración — Endpoints Misceláneos Sprint 7.
Ejecutar: pytest tests/integration/test_misc.py -v
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

class TestMiscAuthRequired:

    @pytest.mark.asyncio
    async def test_get_organizaciones_sin_auth(self, client):
        resp = await client.get("/api/getOrganizaciones")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_direcciones_sin_auth(self, client):
        resp = await client.get("/api/getDirecciones", params={"organizacion_id": 1})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_areas_sin_auth(self, client):
        resp = await client.get("/api/getAreas")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_riesgos_sin_auth(self, client):
        resp = await client.get("/api/getRiesgos", params={"serv_id": 1})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_osa_by_type_sin_auth(self, client):
        resp = await client.get("/api/getOsaByType", params={"tipo": "SW"})
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_suscription_relations_sin_auth(self, client):
        resp = await client.get("/api/getSuscriptionRelations")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_dashboard_ers_sin_auth(self, client):
        resp = await client.get("/api/getDashboardErs")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_get_dashboard_gbu_sin_auth(self, client):
        resp = await client.get("/api/getDashboardGBU")
        assert resp.status_code == 401

    @pytest.mark.asyncio
    async def test_send_email_sin_auth(self, client):
        resp = await client.post("/api/sendEmail", json={"to": "x@x.com", "asunto": "T", "body": "B"})
        assert resp.status_code == 401


# ── Validaciones 422 ──────────────────────────────────────────────────────────

class TestMiscValidaciones:

    @pytest.mark.asyncio
    async def test_send_email_sin_campos(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post(
                "/api/sendEmail",
                json={"to": "user@example.com"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_get_direcciones_sin_organizacion_id(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.get("/api/getDirecciones", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_get_riesgos_sin_serv_id(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.get("/api/getRiesgos", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_insertar_suscripcion_sin_subscriptions(self, client, mock_user):
        with patch("app.core.dependencies.get_current_user", return_value=mock_user):
            resp = await client.post(
                "/api/insertSuscriptionRelations",
                json={"id_activo": 1},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 422


# ── Organizaciones / Direcciones / Áreas ─────────────────────────────────────

class TestOrganizacionesEndpoints:

    @pytest.mark.asyncio
    async def test_get_organizaciones_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.get_organizaciones", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 1, "nombre": "Org Principal", "tipo": 94}]
            resp = await client.get("/api/getOrganizaciones", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert isinstance(data, list)
        assert data[0]["nombre"] == "Org Principal"

    @pytest.mark.asyncio
    async def test_get_areas_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.get_areas", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 10, "nombre": "Área TI"}]
            resp = await client.get("/api/getAreas", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        assert isinstance(resp.json(), list)

    @pytest.mark.asyncio
    async def test_get_direcciones_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.get_direcciones", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 5, "nombre": "Dir. Tecnología"}]
            resp = await client.get(
                "/api/getDirecciones",
                params={"organizacion_id": 1},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
        mock_svc.assert_called_once()


# ── Suscripciones ─────────────────────────────────────────────────────────────

class TestSuscripcionesEndpoints:

    @pytest.mark.asyncio
    async def test_get_relacion_suscripcion_con_activo(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.get_relacion_suscripcion", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False, "relation": True, "activo": {"id": 5}}
            resp = await client.get(
                "/api/getRelacionSuscripcion",
                params={"id_suscripcion": "sub-abc"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
        assert resp.json()["relation"] is True

    @pytest.mark.asyncio
    async def test_get_suscription_relations_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.get_suscription_relations", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 1, "suscription_id": "sub-1", "id_activo": 10}]
            resp = await client.get("/api/getSuscriptionRelations", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        assert len(resp.json()) == 1

    @pytest.mark.asyncio
    async def test_insertar_suscripcion_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.insert_suscription_relation", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 1, "suscription_id": "sub-1"}]
            resp = await client.post(
                "/api/insertSuscriptionRelations",
                json={"id_activo": 10, "subscriptions": ["sub-1"], "subscriptionNames": ["Azure Prod"]},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_eliminar_suscripcion_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.delete_suscription_relation", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False}
            resp = await client.post(
                "/api/deleteSuscriptionRelations",
                json={"suscription_id": "sub-1"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200

    @pytest.mark.asyncio
    async def test_editar_suscripcion_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.edit_suscription_relation", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False}
            resp = await client.post(
                "/api/editSuscriptionRelations",
                json={"id": 1, "suscription_name": "Nuevo nombre"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200


# ── Dashboard ERS + GBU ───────────────────────────────────────────────────────

class TestDashboardMiscEndpoints:

    @pytest.mark.asyncio
    async def test_get_dashboard_ers_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.get_dashboard_ers", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False, "total": 30, "ultimos_90d": 10}
            resp = await client.get("/api/getDashboardErs", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        data = resp.json()
        assert data["total"] == 30
        assert data["ultimos_90d"] == 10

    @pytest.mark.asyncio
    async def test_get_dashboard_gbu_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.get_dashboard_gbu", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 27384, "nombre": "GBU Root", "padre": None}]
            resp = await client.get("/api/getDashboardGBU", headers={"Authorization": "Bearer t"})
        assert resp.status_code == 200
        assert isinstance(resp.json(), list)

    @pytest.mark.asyncio
    async def test_get_dashboard_gbu_con_id_personalizado(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.get_dashboard_gbu", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = []
            resp = await client.get(
                "/api/getDashboardGBU",
                params={"gbu_id": 999},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
        call_args = mock_svc.call_args
        assert 999 in str(call_args)


# ── Email ─────────────────────────────────────────────────────────────────────

class TestEmailEndpoints:

    @pytest.mark.asyncio
    async def test_send_email_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.send_email", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": False, "message": "Email enviado correctamente"}
            resp = await client.post(
                "/api/sendEmail",
                json={"to": "destino@example.com", "asunto": "Alerta", "body": "<p>Texto</p>"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
        assert resp.json()["error"] is False

    @pytest.mark.asyncio
    async def test_send_email_fallo_smtp(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.send_email", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"error": True, "message": "SMTP timeout"}
            resp = await client.post(
                "/api/sendEmail",
                json={"to": "dest@example.com", "asunto": "Test", "body": "Body"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
        assert resp.json()["error"] is True


# ── OSA ───────────────────────────────────────────────────────────────────────

class TestOsaEndpoints:

    @pytest.mark.asyncio
    async def test_get_osa_by_type_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.get_osa_by_type", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = [{"id": 1, "nombre": "OSA SW 1", "tipo": "SW"}]
            resp = await client.get(
                "/api/getOsaByType",
                params={"tipo": "SW"},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
        assert isinstance(resp.json(), list)

    @pytest.mark.asyncio
    async def test_get_osa_eval_not_found(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.get_osa_eval_by_revision", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = None
            resp = await client.get(
                "/api/getOsaEvalByRevision",
                params={"revision_id": 9999},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 404

    @pytest.mark.asyncio
    async def test_get_osa_eval_ok(self, client, mock_user):
        with (
            patch("app.core.dependencies.get_current_user", return_value=mock_user),
            patch("app.services.misc_service.get_osa_eval_by_revision", new_callable=AsyncMock) as mock_svc,
        ):
            mock_svc.return_value = {"id": 1, "revision_id": 5, "resultado": "OK"}
            resp = await client.get(
                "/api/getOsaEvalByRevision",
                params={"revision_id": 5},
                headers={"Authorization": "Bearer t"},
            )
        assert resp.status_code == 200
        assert resp.json()["revision_id"] == 5
