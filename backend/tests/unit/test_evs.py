"""
Tests unitarios — Módulo EVS (schemas + lógica de servicio).
Ejecutar: pytest tests/unit/test_evs.py -v
"""
import pytest
from pydantic import ValidationError
from unittest.mock import AsyncMock, MagicMock, patch


# ── Schemas ───────────────────────────────────────────────────────────────────

class TestPentestCreate:

    def test_nombre_requerido(self):
        from app.schemas.evs import PentestCreate
        p = PentestCreate(nombre="Test Pentest")
        assert p.nombre == "Test Pentest"

    def test_nombre_vacio_falla(self):
        from app.schemas.evs import PentestCreate
        with pytest.raises(ValidationError):
            PentestCreate(nombre="")

    def test_activos_por_defecto_lista_vacia(self):
        from app.schemas.evs import PentestCreate
        p = PentestCreate(nombre="Test")
        assert p.activos == []

    def test_campos_opcionales(self):
        from app.schemas.evs import PentestCreate
        p = PentestCreate(nombre="Test", descripcion="desc", fecha_inicio="2026-01-01", fecha_fin="2026-02-01", activos=[1, 2])
        assert p.fecha_inicio == "2026-01-01"
        assert p.activos == [1, 2]


class TestPentestStatusRequest:

    def test_estado_valido(self):
        from app.schemas.evs import PentestStatusRequest
        s = PentestStatusRequest(id=1, estado="abierto")
        assert s.estado == "abierto"

    def test_estado_en_curso(self):
        from app.schemas.evs import PentestStatusRequest
        s = PentestStatusRequest(id=1, estado="en_curso")
        assert s.estado == "en_curso"

    def test_estado_invalido_falla(self):
        from app.schemas.evs import PentestStatusRequest
        with pytest.raises(ValidationError):
            PentestStatusRequest(id=1, estado="desconocido")


class TestPentestAddActivosRequest:

    def test_activos_vacia_falla(self):
        from app.schemas.evs import PentestAddActivosRequest
        with pytest.raises(ValidationError):
            PentestAddActivosRequest(pentest_id=1, activos=[])

    def test_activos_validos(self):
        from app.schemas.evs import PentestAddActivosRequest
        r = PentestAddActivosRequest(pentest_id=1, activos=[10, 20])
        assert len(r.activos) == 2


class TestIssueCreate:

    def test_titulo_requerido(self):
        from app.schemas.evs import IssueCreate
        issue = IssueCreate(titulo="XSS en login", descripcion="Descripción del issue")
        assert issue.titulo == "XSS en login"

    def test_severidad_por_defecto(self):
        from app.schemas.evs import IssueCreate
        issue = IssueCreate(titulo="Test", descripcion="desc")
        assert issue.severidad == "Medium"

    def test_sin_titulo_falla(self):
        from app.schemas.evs import IssueCreate
        with pytest.raises(ValidationError):
            IssueCreate(descripcion="desc")


class TestSolicitudPentestCreate:

    def test_nombre_requerido(self):
        from app.schemas.evs import SolicitudPentestCreate
        s = SolicitudPentestCreate(nombre="Pentest web")
        assert s.nombre == "Pentest web"

    def test_tipo_por_defecto(self):
        from app.schemas.evs import SolicitudPentestCreate
        s = SolicitudPentestCreate(nombre="Test")
        assert s.tipo == "externo"


class TestRevisionCreate:

    def test_nombre_requerido(self):
        from app.schemas.evs import RevisionCreate
        r = RevisionCreate(nombre="Revisión Q1")
        assert r.nombre == "Revisión Q1"

    def test_activos_vacia_por_defecto(self):
        from app.schemas.evs import RevisionCreate
        r = RevisionCreate(nombre="Test")
        assert r.activos == []


class TestDismissPrismaAlertRequest:

    def test_alert_id_requerido(self):
        from app.schemas.evs import DismissPrismaAlertRequest
        d = DismissPrismaAlertRequest(alert_id="ALERT-123")
        assert d.alert_id == "ALERT-123"

    def test_razon_opcional(self):
        from app.schemas.evs import DismissPrismaAlertRequest
        d = DismissPrismaAlertRequest(alert_id="ALERT-123", razon="Falso positivo")
        assert d.razon == "Falso positivo"


# ── Servicio EVS ──────────────────────────────────────────────────────────────

class TestEvsService:

    @pytest.mark.asyncio
    async def test_crear_pentest_llama_db(self):
        from app.services import evs_service
        mock_db = AsyncMock()
        with patch("app.services.evs_service.db_evs") as mock_db_evs:
            mock_db_evs.create_pentest = AsyncMock(return_value={"id": 1, "nombre": "Test"})
            resultado = await evs_service.crear_pentest(mock_db, "Test", "desc", None, None, [], 1)
        assert resultado is not None

    @pytest.mark.asyncio
    async def test_get_pentests_llama_db(self):
        from app.services import evs_service
        mock_db = AsyncMock()
        with patch("app.services.evs_service.db_evs") as mock_db_evs:
            mock_db_evs.get_pentests = AsyncMock(return_value=[])
            resultado = await evs_service.get_pentests(mock_db, user_id=1, admin=False)
        assert isinstance(resultado, list)

    @pytest.mark.asyncio
    async def test_cambiar_estado_pentest_llama_db(self):
        from app.services import evs_service
        mock_db = AsyncMock()
        with patch("app.services.evs_service.db_evs") as mock_db_evs:
            mock_db_evs.cambiar_estado_pentest = AsyncMock(return_value=True)
            resultado = await evs_service.cambiar_estado_pentest(mock_db, 1, "cerrado")
        assert resultado is not None
