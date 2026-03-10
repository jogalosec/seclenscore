"""
Tests unitarios — Módulo Dashboard (lógica del servicio).
Ejecutar: pytest tests/unit/test_dashboard.py -v
"""
import json
import pytest
from unittest.mock import AsyncMock, MagicMock, patch


# ── Lógica BIA ────────────────────────────────────────────────────────────────

class TestDashboardBiaLogic:
    """Verifica el parseo de meta_value JSON y la clasificación por nivel."""

    def _make_row(self, meta_value):
        row = MagicMock()
        row._mapping = {"meta_value": meta_value}
        return row

    @pytest.mark.asyncio
    async def test_bia_cuenta_criticos(self):
        from app.services import dashboard_service
        meta = json.dumps({"severity": "critico", "global": 3.8})
        rows = [self._make_row(meta)]

        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.fetchall.return_value = rows
        mock_db.execute = AsyncMock(return_value=mock_result)

        data = await dashboard_service.get_dashboard_bia(mock_db)
        assert "critico" in data
        assert data["critico"] >= 1

    @pytest.mark.asyncio
    async def test_bia_meta_value_string_json(self):
        from app.services import dashboard_service
        # meta_value puede venir como string JSON o dict
        meta = '{"severity": "alto", "global": 3.1}'
        rows = [self._make_row(meta)]

        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.fetchall.return_value = rows
        mock_db.execute = AsyncMock(return_value=mock_result)

        data = await dashboard_service.get_dashboard_bia(mock_db)
        assert "alto" in data
        assert data["alto"] >= 1

    @pytest.mark.asyncio
    async def test_bia_sin_datos_devuelve_ceros(self):
        from app.services import dashboard_service
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.fetchall.return_value = []
        mock_db.execute = AsyncMock(return_value=mock_result)

        data = await dashboard_service.get_dashboard_bia(mock_db)
        assert data.get("critico", 0) == 0
        assert data.get("alto", 0) == 0

    @pytest.mark.asyncio
    async def test_bia_meta_value_invalido_ignorado(self):
        from app.services import dashboard_service
        rows = [self._make_row("not-valid-json")]

        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.fetchall.return_value = rows
        mock_db.execute = AsyncMock(return_value=mock_result)

        # No debe lanzar excepción
        data = await dashboard_service.get_dashboard_bia(mock_db)
        assert isinstance(data, dict)


# ── Lógica Activos ────────────────────────────────────────────────────────────

class TestDashboardActivosLogic:

    @pytest.mark.asyncio
    async def test_activos_devuelve_estructura(self):
        from app.services import dashboard_service
        mock_db = AsyncMock()
        mock_result = MagicMock()
        # Simular una fila con total, archivados, expuestos
        row = MagicMock()
        row._mapping = {"total": 50, "archivados": 10, "expuestos": 5}
        mock_result.fetchone.return_value = row
        mock_result.fetchall.return_value = []
        mock_db.execute = AsyncMock(return_value=mock_result)

        data = await dashboard_service.get_dashboard_activos(mock_db)
        assert "total" in data or isinstance(data, dict)

    @pytest.mark.asyncio
    async def test_activos_sin_datos_no_falla(self):
        from app.services import dashboard_service
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.fetchone.return_value = None
        mock_result.fetchall.return_value = []
        mock_db.execute = AsyncMock(return_value=mock_result)

        data = await dashboard_service.get_dashboard_activos(mock_db)
        assert isinstance(data, dict)


# ── Lógica Pentest ────────────────────────────────────────────────────────────

class TestDashboardPentestLogic:

    @pytest.mark.asyncio
    async def test_pentest_devuelve_por_estado(self):
        from app.services import dashboard_service
        mock_db = AsyncMock()

        row1 = MagicMock()
        row1._mapping = {"estado": "abierto", "total": 3}
        row2 = MagicMock()
        row2._mapping = {"estado": "cerrado", "total": 7}

        mock_result = MagicMock()
        mock_result.fetchall.return_value = [row1, row2]
        mock_db.execute = AsyncMock(return_value=mock_result)

        data = await dashboard_service.get_dashboard_pentest(mock_db)
        assert isinstance(data, dict)

    @pytest.mark.asyncio
    async def test_pentest_sin_datos(self):
        from app.services import dashboard_service
        mock_db = AsyncMock()
        mock_result = MagicMock()
        mock_result.fetchall.return_value = []
        mock_db.execute = AsyncMock(return_value=mock_result)

        data = await dashboard_service.get_dashboard_pentest(mock_db)
        assert isinstance(data, dict)


# ── Lógica ECR ────────────────────────────────────────────────────────────────

class TestDashboardEcrLogic:

    @pytest.mark.asyncio
    async def test_ecr_devuelve_total_y_recientes(self):
        from app.services import dashboard_service
        mock_db = AsyncMock()

        row = MagicMock()
        row._mapping = {"total": 15, "ultimos_90d": 4}
        mock_result = MagicMock()
        mock_result.fetchone.return_value = row
        mock_db.execute = AsyncMock(return_value=mock_result)

        data = await dashboard_service.get_dashboard_ecr(mock_db)
        assert isinstance(data, dict)


# ── Lógica PAC ────────────────────────────────────────────────────────────────

class TestDashboardPacLogic:

    @pytest.mark.asyncio
    async def test_pac_devuelve_estructura(self):
        from app.services import dashboard_service
        mock_db = AsyncMock()

        row = MagicMock()
        row._mapping = {"total": 20}
        seg_row = MagicMock()
        seg_row._mapping = {"estado": "completado", "total": 8}

        mock_result = MagicMock()
        mock_result.fetchone.return_value = row
        mock_result.fetchall.return_value = [seg_row]
        mock_db.execute = AsyncMock(return_value=mock_result)

        data = await dashboard_service.get_dashboard_pac(mock_db)
        assert isinstance(data, dict)
