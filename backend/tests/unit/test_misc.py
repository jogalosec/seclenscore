"""
Tests unitarios — Servicio misceláneos Sprint 7.
Ejecutar: pytest tests/unit/test_misc.py -v
"""
import pytest
from unittest.mock import AsyncMock, MagicMock, patch


class TestMiscServiceOrganizaciones:

    @pytest.mark.asyncio
    async def test_get_organizaciones(self):
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_row = MagicMock()
        mock_row._mapping = {"id": 1, "nombre": "Org1", "tipo": 94, "archivado": 0}
        mock_result.fetchall.return_value = [mock_row]
        mock_db.execute.return_value = mock_result

        from app.services import misc_service
        result = await misc_service.get_organizaciones(mock_db)
        assert isinstance(result, list)

    @pytest.mark.asyncio
    async def test_get_areas(self):
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.fetchall.return_value = []
        mock_db.execute.return_value = mock_result

        from app.services import misc_service
        result = await misc_service.get_areas(mock_db)
        assert result == []

    @pytest.mark.asyncio
    async def test_get_direcciones(self):
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_row = MagicMock()
        mock_row._mapping = {"padre": 1, "nombre": "Dir1", "id": 2, "tipo": "Dirección",
                             "tipo_id": 3, "archivado": 0}
        mock_result.fetchall.return_value = [mock_row]
        mock_db.execute.return_value = mock_result

        from app.services import misc_service
        result = await misc_service.get_direcciones(mock_db, 1)
        assert isinstance(result, list)


class TestMiscServiceSuscripciones:

    @pytest.mark.asyncio
    async def test_get_relacion_suscripcion_con_activo(self):
        mock_db = AsyncMock()
        with patch("app.db.octopus_serv_sdlc.get_activo_by_sus_id",
                   new_callable=AsyncMock,
                   return_value={"id": 10, "nombre": "Activo A"}):
            from app.services import misc_service
            result = await misc_service.get_relacion_suscripcion(mock_db, "sub-123")
        assert result["error"] is False
        assert result["relation"] is True
        assert result["activo"]["id"] == 10

    @pytest.mark.asyncio
    async def test_get_relacion_suscripcion_sin_activo(self):
        mock_db = AsyncMock()
        with patch("app.db.octopus_serv_sdlc.get_activo_by_sus_id",
                   new_callable=AsyncMock,
                   return_value=None):
            from app.services import misc_service
            result = await misc_service.get_relacion_suscripcion(mock_db, "sub-999")
        assert result["error"] is False
        assert result["relation"] is False

    @pytest.mark.asyncio
    async def test_get_suscription_relations(self):
        mock_db = AsyncMock()
        with patch("app.db.octopus_serv_sdlc.get_suscription_relations",
                   new_callable=AsyncMock,
                   return_value=[{"id": 1, "suscription_id": "sub-1"}]):
            from app.services import misc_service
            result = await misc_service.get_suscription_relations(mock_db)
        assert len(result) == 1

    @pytest.mark.asyncio
    async def test_delete_suscription_relation(self):
        mock_db = AsyncMock()
        with patch("app.db.octopus_serv_sdlc.delete_suscription_relation",
                   new_callable=AsyncMock,
                   return_value={"error": False}):
            from app.services import misc_service
            result = await misc_service.delete_suscription_relation(mock_db, "sub-1")
        assert result["error"] is False

    @pytest.mark.asyncio
    async def test_edit_suscription_relation(self):
        mock_db = AsyncMock()
        with patch("app.db.octopus_serv_sdlc.edit_suscription_relation",
                   new_callable=AsyncMock,
                   return_value=None):
            from app.services import misc_service
            result = await misc_service.edit_suscription_relation(mock_db, 1, {"suscription_name": "Nuevo"})
        assert result["error"] is False


class TestMiscServiceDashboard:

    @pytest.mark.asyncio
    async def test_get_dashboard_ers(self):
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_row = MagicMock()
        mock_row._mapping = {"total": 25, "ultimos_90d": 8}
        mock_result.fetchone.return_value = mock_row
        mock_db.execute.return_value = mock_result

        from app.services import misc_service
        result = await misc_service.get_dashboard_ers(mock_db)
        assert result["error"] is False
        assert result["total"] == 25
        assert result["ultimos_90d"] == 8

    @pytest.mark.asyncio
    async def test_get_dashboard_ers_sin_datos(self):
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.fetchone.return_value = None
        mock_db.execute.return_value = mock_result

        from app.services import misc_service
        result = await misc_service.get_dashboard_ers(mock_db)
        assert result["error"] is False
        assert result["total"] == 0

    @pytest.mark.asyncio
    async def test_get_dashboard_gbu(self):
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_row = MagicMock()
        mock_row._mapping = {"padre": None, "nombre": "GBU Root", "id": 27384, "tipo": "GBU",
                             "tipo_id": 5, "archivado": 0}
        mock_result.fetchall.return_value = [mock_row]
        mock_db.execute.return_value = mock_result

        from app.services import misc_service
        result = await misc_service.get_dashboard_gbu(mock_db)
        assert isinstance(result, list)


class TestMiscServiceEmail:

    @pytest.mark.asyncio
    async def test_send_email_ok(self):
        with patch("aiosmtplib.send", new_callable=AsyncMock, return_value=None):
            from app.services import misc_service
            result = await misc_service.send_email(
                "dest@example.com", "Asunto Test", "<p>Cuerpo</p>", "Texto plano"
            )
        assert result["error"] is False
        assert "enviado" in result["message"].lower()

    @pytest.mark.asyncio
    async def test_send_email_falla_smtp(self):
        with patch("aiosmtplib.send", new_callable=AsyncMock, side_effect=Exception("SMTP timeout")):
            from app.services import misc_service
            result = await misc_service.send_email(
                "dest@example.com", "Test", "Body", ""
            )
        assert result["error"] is True
        assert "SMTP timeout" in result["message"]


class TestMiscServiceRiesgos:

    @pytest.mark.asyncio
    async def test_get_riesgos_servicio_sin_fecha(self):
        mock_db = AsyncMock()
        from app.services import misc_service
        result = await misc_service.get_riesgos_servicio(mock_db, 1, "null")
        assert result == []

    @pytest.mark.asyncio
    async def test_get_riesgos_servicio_sin_datos_bd(self):
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.fetchone.return_value = None
        mock_db.execute.return_value = mock_result

        from app.services import misc_service
        result = await misc_service.get_riesgos_servicio(mock_db, 1, "123")
        assert result == []
