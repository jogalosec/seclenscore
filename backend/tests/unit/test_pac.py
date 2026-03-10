"""
Tests unitarios — Módulo PAC (schemas + lógica de servicio).
Ejecutar: pytest tests/unit/test_pac.py -v
"""
import pytest
from pydantic import ValidationError
from unittest.mock import AsyncMock, patch


# ── Schemas PAC ───────────────────────────────────────────────────────────────

class TestPacCreate:

    def test_campos_requeridos(self):
        from app.schemas.pac import PacCreate
        p = PacCreate(activo_id=1, descripcion="Acción correctiva de test")
        assert p.activo_id == 1
        assert p.descripcion == "Acción correctiva de test"

    def test_sin_activo_id_falla(self):
        from app.schemas.pac import PacCreate
        with pytest.raises(ValidationError):
            PacCreate(descripcion="sin activo")

    def test_sin_descripcion_falla(self):
        from app.schemas.pac import PacCreate
        with pytest.raises(ValidationError):
            PacCreate(activo_id=1)

    def test_prioridad_por_defecto(self):
        from app.schemas.pac import PacCreate
        p = PacCreate(activo_id=1, descripcion="Test")
        assert p.prioridad == "media"

    def test_campos_opcionales(self):
        from app.schemas.pac import PacCreate
        p = PacCreate(activo_id=1, descripcion="Test", responsable="Juan", prioridad="alta", fecha_limite="2026-06-30")
        assert p.responsable == "Juan"
        assert p.prioridad == "alta"


class TestPacSeguimientoCreate:

    def test_campos_requeridos(self):
        from app.schemas.pac import PacSeguimientoCreate
        s = PacSeguimientoCreate(pac_id=1, descripcion="Seguimiento inicial")
        assert s.pac_id == 1

    def test_estado_por_defecto(self):
        from app.schemas.pac import PacSeguimientoCreate
        s = PacSeguimientoCreate(pac_id=1, descripcion="Test")
        assert s.estado == "pendiente"

    def test_estado_valido(self):
        from app.schemas.pac import PacSeguimientoCreate
        s = PacSeguimientoCreate(pac_id=1, descripcion="Test", estado="completado")
        assert s.estado == "completado"

    def test_estado_invalido_falla(self):
        from app.schemas.pac import PacSeguimientoCreate
        with pytest.raises(ValidationError):
            PacSeguimientoCreate(pac_id=1, descripcion="Test", estado="invalido")


class TestEstadoPAC:

    def test_todos_los_estados_validos(self):
        from app.schemas.pac import PacSeguimientoCreate
        estados_validos = ["pendiente", "en_curso", "completado", "cancelado"]
        for estado in estados_validos:
            s = PacSeguimientoCreate(pac_id=1, descripcion="Test", estado=estado)
            assert s.estado == estado


class TestPacSeguimientoUpdate:

    def test_solo_id_requerido(self):
        from app.schemas.pac import PacSeguimientoUpdate
        u = PacSeguimientoUpdate(id=5)
        assert u.id == 5
        assert u.descripcion is None
        assert u.estado is None

    def test_actualizar_estado(self):
        from app.schemas.pac import PacSeguimientoUpdate
        u = PacSeguimientoUpdate(id=5, estado="en_curso")
        assert u.estado == "en_curso"

    def test_estado_invalido_en_update_falla(self):
        from app.schemas.pac import PacSeguimientoUpdate
        with pytest.raises(ValidationError):
            PacSeguimientoUpdate(id=5, estado="wrong")


class TestPlanCreate:

    def test_campos_requeridos(self):
        from app.schemas.pac import PlanCreate
        p = PlanCreate(activo_id=1, nombre="Plan de continuidad Q1")
        assert p.nombre == "Plan de continuidad Q1"

    def test_sin_nombre_falla(self):
        from app.schemas.pac import PlanCreate
        with pytest.raises(ValidationError):
            PlanCreate(activo_id=1)

    def test_sin_activo_id_falla(self):
        from app.schemas.pac import PlanCreate
        with pytest.raises(ValidationError):
            PlanCreate(nombre="Plan")

    def test_fechas_opcionales(self):
        from app.schemas.pac import PlanCreate
        p = PlanCreate(activo_id=1, nombre="Plan", fecha_inicio="2026-01-01", fecha_fin="2026-12-31")
        assert p.fecha_inicio == "2026-01-01"


class TestPlanUpdate:

    def test_solo_id_requerido(self):
        from app.schemas.pac import PlanUpdate
        u = PlanUpdate(id=3)
        assert u.id == 3
        assert u.nombre is None

    def test_actualizar_campos(self):
        from app.schemas.pac import PlanUpdate
        u = PlanUpdate(id=3, nombre="Nuevo nombre")
        assert u.nombre == "Nuevo nombre"


# ── Servicio PAC ──────────────────────────────────────────────────────────────

class TestPacService:

    @pytest.mark.asyncio
    async def test_get_pac_list_llama_db(self):
        from app.services import pac_service
        mock_db = AsyncMock()
        with patch("app.services.pac_service.db_pac") as mock_db_pac:
            mock_db_pac.get_pac_list = AsyncMock(return_value=[])
            resultado = await pac_service.get_pac_list(mock_db, activo_id=1)
        assert isinstance(resultado, list)

    @pytest.mark.asyncio
    async def test_crear_pac_llama_db(self):
        from app.services import pac_service
        mock_db = AsyncMock()
        with patch("app.services.pac_service.db_pac") as mock_db_pac:
            mock_db_pac.create_pac = AsyncMock(return_value={"id": 1})
            resultado = await pac_service.crear_pac(mock_db, activo_id=1, descripcion="Test", responsable="Juan", prioridad="alta", fecha_limite=None, usf_id=None, pregunta_id=None)
        assert resultado is not None

    @pytest.mark.asyncio
    async def test_get_seguimiento_llama_db(self):
        from app.services import pac_service
        mock_db = AsyncMock()
        with patch("app.services.pac_service.db_pac") as mock_db_pac:
            mock_db_pac.get_seguimiento_pac = AsyncMock(return_value=[])
            resultado = await pac_service.get_seguimiento(mock_db, pac_id=1)
        assert isinstance(resultado, list)
