"""
Tests unitarios — Schemas y servicios Kiuwan / SDLC (Sprint 7).
Ejecutar: pytest tests/unit/test_kiuwan.py -v
"""
import pytest
from unittest.mock import AsyncMock, patch
from pydantic import ValidationError

from app.schemas.kiuwan import (
    UpdateCumpleKpmRequest,
    UpdateSonarKPMRequest,
    SdlcCreate,
    SdlcUpdate,
    SdlcDelete,
    SuscripcionRelacionCreate,
    SuscripcionRelacionDelete,
    SuscripcionRelacionEdit,
    SendEmailRequest,
)


# ── UpdateCumpleKpmRequest ─────────────────────────────────────────────────────

class TestUpdateCumpleKpmRequest:

    def test_valido(self):
        obj = UpdateCumpleKpmRequest(app_name="AppTest", cumple_kpm=1)
        assert obj.app_name == "AppTest"
        assert obj.cumple_kpm == 1

    def test_cumple_kpm_cero(self):
        obj = UpdateCumpleKpmRequest(app_name="AppTest", cumple_kpm=0)
        assert obj.cumple_kpm == 0

    def test_cumple_kpm_invalido_falla(self):
        with pytest.raises(ValidationError):
            UpdateCumpleKpmRequest(app_name="App", cumple_kpm=2)

    def test_app_name_vacio_falla(self):
        with pytest.raises(ValidationError):
            UpdateCumpleKpmRequest(app_name="   ", cumple_kpm=1)

    def test_app_name_se_normaliza(self):
        obj = UpdateCumpleKpmRequest(app_name="  App  ", cumple_kpm=0)
        assert obj.app_name == "App"


# ── UpdateSonarKPMRequest ──────────────────────────────────────────────────────

class TestUpdateSonarKPMRequest:

    def test_valido(self):
        obj = UpdateSonarKPMRequest(slot_sonarqube="sonar-slot-1", cumple_kpm_sonar=1)
        assert obj.slot_sonarqube == "sonar-slot-1"

    def test_slot_vacio_falla(self):
        with pytest.raises(ValidationError):
            UpdateSonarKPMRequest(slot_sonarqube="", cumple_kpm_sonar=1)

    def test_valor_invalido_falla(self):
        with pytest.raises(ValidationError):
            UpdateSonarKPMRequest(slot_sonarqube="slot", cumple_kpm_sonar=5)


# ── SdlcCreate ────────────────────────────────────────────────────────────────

class TestSdlcCreate:

    def test_kiuwan_valido(self):
        obj = SdlcCreate(app="Kiuwan", Direccion=1, Area=2, Producto=3, CMM="Nivel1", Analisis="Manual")
        assert obj.app == "Kiuwan"

    def test_sonarqube_valido(self):
        obj = SdlcCreate(app="Sonarqube", Direccion=1, Area=2, Producto=3, CMM="Nivel2", Analisis="Auto")
        assert obj.app == "Sonarqube"

    def test_app_invalida_falla(self):
        with pytest.raises(ValidationError):
            SdlcCreate(app="Jenkins", Direccion=1, Area=2, Producto=3, CMM="X", Analisis="Y")

    def test_campos_opcionales_default(self):
        obj = SdlcCreate(app="Kiuwan", Direccion=1, Area=2, Producto=3, CMM="A", Analisis="B")
        assert obj.Comentarios == ""
        assert obj.url_sonar == ""
        assert obj.kiuwan_id is None


# ── SdlcDelete ────────────────────────────────────────────────────────────────

class TestSdlcDelete:

    def test_valido(self):
        obj = SdlcDelete(id=1, app="Kiuwan", kiuwan_id=42)
        assert obj.id == 1
        assert obj.kiuwan_id == 42

    def test_sin_kiuwan_id(self):
        obj = SdlcDelete(id=5, app="Sonarqube")
        assert obj.kiuwan_id is None


# ── SuscripcionRelacion ────────────────────────────────────────────────────────

class TestSuscripcionRelacionSchemas:

    def test_create_valido(self):
        obj = SuscripcionRelacionCreate(
            id_activo=10,
            subscriptions=["sub-1", "sub-2"],
            subscriptionNames=["Azure Dev", "Azure Prod"],
        )
        assert len(obj.subscriptions) == 2

    def test_create_sin_names(self):
        obj = SuscripcionRelacionCreate(id_activo=1, subscriptions=["sub-1"])
        assert obj.subscriptionNames == []

    def test_delete_valido(self):
        obj = SuscripcionRelacionDelete(suscription_id="sub-abc-123")
        assert obj.suscription_id == "sub-abc-123"

    def test_edit_valido(self):
        obj = SuscripcionRelacionEdit(id=5, suscription_name="Nuevo nombre")
        assert obj.id == 5


# ── SendEmailRequest ───────────────────────────────────────────────────────────

class TestSendEmailRequest:

    def test_valido(self):
        obj = SendEmailRequest(to="user@example.com", asunto="Prueba", body="<p>Hola</p>")
        assert obj.to == "user@example.com"
        assert obj.alternbody == ""

    def test_con_alternbody(self):
        obj = SendEmailRequest(
            to="user@example.com", asunto="Test", body="HTML", alternbody="Plain text"
        )
        assert obj.alternbody == "Plain text"

    def test_faltan_campos_requeridos(self):
        with pytest.raises(ValidationError):
            SendEmailRequest(to="user@example.com")


# ── kiuwan_service ────────────────────────────────────────────────────────────

class TestKiuwanService:

    @pytest.mark.asyncio
    async def test_get_kiuwan_stored(self):
        mock_db = AsyncMock()
        with patch("app.db.octopus_serv_sdlc.get_kiuwan_data", new_callable=AsyncMock) as mock_db_fn:
            mock_db_fn.return_value = [{"id": 1, "app": "AppTest"}]
            from app.services import kiuwan_service
            result = await kiuwan_service.get_kiuwan_stored(mock_db)
        assert isinstance(result, list)

    @pytest.mark.asyncio
    async def test_update_cumple_kpm(self):
        mock_db = AsyncMock()
        with patch("app.db.octopus_serv_sdlc.update_cumple_kpm", new_callable=AsyncMock) as mock_fn:
            mock_fn.return_value = None
            from app.services import kiuwan_service
            result = await kiuwan_service.update_cumple_kpm(mock_db, "AppTest", 1)
        assert result["error"] is False

    @pytest.mark.asyncio
    async def test_update_sonar_kpm(self):
        mock_db = AsyncMock()
        with patch("app.db.octopus_serv_sdlc.update_sonar_kpm", new_callable=AsyncMock) as mock_fn:
            mock_fn.return_value = None
            from app.services import kiuwan_service
            result = await kiuwan_service.update_sonar_kpm(mock_db, "sonar-slot", 0)
        assert result["error"] is False

    @pytest.mark.asyncio
    async def test_crear_app_sdlc(self):
        mock_db = AsyncMock()
        data = {"app": "Kiuwan", "Direccion": 1, "Area": 2, "Producto": 3, "CMM": "A", "Analisis": "B"}
        with (
            patch("app.db.octopus_serv_sdlc.modificar_sdlc_by_kiuwan", new_callable=AsyncMock, return_value=False),
            patch("app.db.octopus_serv_sdlc.add_sdlc", new_callable=AsyncMock, return_value=None),
        ):
            from app.services import kiuwan_service
            result = await kiuwan_service.crear_app_sdlc(mock_db, data)
        assert result["error"] is False

    @pytest.mark.asyncio
    async def test_eliminar_app_sdlc(self):
        mock_db = AsyncMock()
        with patch("app.db.octopus_serv_sdlc.eliminar_app_sdlc", new_callable=AsyncMock) as mock_fn:
            mock_fn.return_value = None
            from app.services import kiuwan_service
            result = await kiuwan_service.eliminar_app_sdlc(mock_db, 1, 42, "Kiuwan")
        assert result["error"] is False
