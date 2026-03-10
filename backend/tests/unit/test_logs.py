"""
Tests unitarios — Módulo Logs (lógica del servicio).
Ejecutar: pytest tests/unit/test_logs.py -v
"""
import pytest
from unittest.mock import AsyncMock, MagicMock, patch
from datetime import datetime


# ── Servicio Logs ─────────────────────────────────────────────────────────────

class TestLogsRelaciones:

    @pytest.mark.asyncio
    async def test_get_logs_relaciones_devuelve_lista(self):
        from app.services import logs_service
        mock_db = AsyncMock()
        with patch("app.services.logs_service.db_logs") as mock_db_logs:
            mock_db_logs.get_relation_changes = AsyncMock(return_value=[])
            result = await logs_service.get_logs_relaciones(mock_db, None, None)
        assert isinstance(result, list)

    @pytest.mark.asyncio
    async def test_get_logs_relaciones_con_fechas(self):
        from app.services import logs_service
        mock_db = AsyncMock()
        with patch("app.services.logs_service.db_logs") as mock_db_logs:
            mock_db_logs.get_relation_changes = AsyncMock(return_value=[{"id": 1}])
            result = await logs_service.get_logs_relaciones(mock_db, "2026-01-01", "2026-03-31")
        assert len(result) == 1


class TestLogsAccesos:

    @pytest.mark.asyncio
    async def test_get_logs_accesos_devuelve_lista(self):
        from app.services import logs_service
        mock_db = AsyncMock()
        with patch("app.services.logs_service.db_logs") as mock_db_logs:
            mock_db_logs.get_route_logs = AsyncMock(return_value=[])
            result = await logs_service.get_logs_accesos(mock_db, None, None)
        assert isinstance(result, list)


class TestLogsActivosProcesados:
    """
    get_logs_activos_procesados combina nuevos + eliminados + modificados
    y los ordena por fecha descendente.
    """

    @pytest.mark.asyncio
    async def test_timeline_ordenado_por_fecha(self):
        from app.services import logs_service
        mock_db = AsyncMock()
        with patch("app.services.logs_service.db_logs") as mock_db_logs:
            nuevo   = {"fecha": "2026-03-10 10:00:00", "tipo": "nuevo", "nombre": "Activo A"}
            modific = {"fecha": "2026-03-11 09:00:00", "tipo": "modificado", "nombre": "Activo B"}
            elim    = {"fecha": "2026-03-09 08:00:00", "tipo": "eliminado", "nombre": "Activo C"}

            mock_db_logs.get_new_activos_log      = AsyncMock(return_value=[nuevo])
            mock_db_logs.get_modified_activos_log = AsyncMock(return_value=[modific])
            mock_db_logs.get_deleted_activos_log  = AsyncMock(return_value=[elim])

            result = await logs_service.get_logs_activos_procesados(mock_db, None, None)

        assert isinstance(result, list)
        # El más reciente debe estar primero
        if len(result) > 1:
            fechas = [r["fecha"] for r in result]
            assert fechas == sorted(fechas, reverse=True)

    @pytest.mark.asyncio
    async def test_timeline_vacio_sin_datos(self):
        from app.services import logs_service
        mock_db = AsyncMock()
        with patch("app.services.logs_service.db_logs") as mock_db_logs:
            mock_db_logs.get_new_activos_log      = AsyncMock(return_value=[])
            mock_db_logs.get_modified_activos_log = AsyncMock(return_value=[])
            mock_db_logs.get_deleted_activos_log  = AsyncMock(return_value=[])

            result = await logs_service.get_logs_activos_procesados(mock_db, None, None)

        assert result == []

    @pytest.mark.asyncio
    async def test_timeline_combina_tres_fuentes(self):
        from app.services import logs_service
        mock_db = AsyncMock()
        with patch("app.services.logs_service.db_logs") as mock_db_logs:
            mock_db_logs.get_new_activos_log      = AsyncMock(return_value=[{"fecha": "2026-01-01", "tipo": "nuevo"}])
            mock_db_logs.get_modified_activos_log = AsyncMock(return_value=[{"fecha": "2026-01-02", "tipo": "modificado"}])
            mock_db_logs.get_deleted_activos_log  = AsyncMock(return_value=[{"fecha": "2026-01-03", "tipo": "eliminado"}])

            result = await logs_service.get_logs_activos_procesados(mock_db, None, None)

        assert len(result) == 3


class TestLogsRouteLogs:

    @pytest.mark.asyncio
    async def test_get_route_logs_con_limit(self):
        from app.services import logs_service
        mock_db = AsyncMock()
        with patch("app.services.logs_service.db_logs") as mock_db_logs:
            mock_db_logs.get_route_logs = AsyncMock(return_value=[{"id": i} for i in range(100)])
            result = await logs_service.get_route_logs(mock_db, None, None, limit=100)
        assert isinstance(result, list)

    @pytest.mark.asyncio
    async def test_get_route_logs_sin_datos(self):
        from app.services import logs_service
        mock_db = AsyncMock()
        with patch("app.services.logs_service.db_logs") as mock_db_logs:
            mock_db_logs.get_route_logs = AsyncMock(return_value=[])
            result = await logs_service.get_route_logs(mock_db, None, None)
        assert result == []
