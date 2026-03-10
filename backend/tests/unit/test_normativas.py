"""
Tests unitarios para el módulo Normativas / Controles / USFs / Preguntas / Marco.
Ejecutar: pytest tests/unit/test_normativas.py -v
"""
import pytest
from unittest.mock import AsyncMock, MagicMock, patch


# ---------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------

@pytest.fixture
def mock_db():
    db = MagicMock()
    db.execute = AsyncMock()
    db.commit  = AsyncMock()
    return db


@pytest.fixture
def normativa_sample():
    return {"id": 1, "nombre": "ISO 27001", "version": "2022", "enabled": 1}


@pytest.fixture
def control_sample():
    return {"id": 10, "codigo": "A.5.1", "nombre": "Políticas de SI", "dominio": "A.5"}


# ---------------------------------------------------------------
# Tests de Schemas
# ---------------------------------------------------------------

class TestNormativaSchemas:

    def test_normativa_create_valida(self):
        from app.schemas.normativa import NormativaCreate
        n = NormativaCreate(nombre="ISO 27001", version="2022")
        assert n.nombre == "ISO 27001"
        assert n.version == "2022"

    def test_normativa_create_nombre_requerido(self):
        from app.schemas.normativa import NormativaCreate
        from pydantic import ValidationError
        with pytest.raises(ValidationError):
            NormativaCreate(version="2022")

    def test_normativa_update_enabled_bool(self):
        from app.schemas.normativa import NormativaUpdate
        u = NormativaUpdate(idNormativa=1, nombre="ISO 27001", enabled=True)
        assert u.enabled is True

    def test_control_create_valido(self):
        from app.schemas.normativa import ControlCreate
        c = ControlCreate(
            codigo="A.5.1",
            nombre="Políticas",
            descripcion="Descripción",
            dominio="A.5",
            idNormativa=1,
        )
        assert c.codigo == "A.5.1"
        assert c.idNormativa == 1

    def test_usf_create_valido(self):
        from app.schemas.normativa import USFCreate
        u = USFCreate(
            codigo="USF-001",
            nombre="Control acceso",
            descripcion="Desc",
            dominio="Gestión",
            tipo="preventivo",
            IdPAC=None,
        )
        assert u.codigo == "USF-001"

    def test_pregunta_create_valida(self):
        from app.schemas.normativa import PreguntaCreate
        p = PreguntaCreate(duda="¿Se aplica MFA?", nivel=2)
        assert p.nivel == 2

    def test_relacion_completa_request(self):
        from app.schemas.normativa import RelacionCompletaRequest, RelacionItem
        req = RelacionCompletaRequest(
            id=10,
            relaciones=[
                RelacionItem(idUSF=1, preguntas=[{"id": 5}, {"id": 6}]),
            ],
        )
        assert req.id == 10
        assert len(req.relaciones) == 1

    def test_relacion_delete_request(self):
        from app.schemas.normativa import RelacionDeleteRequest
        r = RelacionDeleteRequest(idRelacion=99)
        assert r.idRelacion == 99


# ---------------------------------------------------------------
# Tests del servicio
# ---------------------------------------------------------------

class TestNormativasService:

    @pytest.mark.asyncio
    async def test_get_normativas_completas_vacio(self, mock_db):
        with patch("app.services.normativas_service.db_norm") as mock_mod:
            mock_mod.get_normativas = AsyncMock(return_value=[])

            from app.services.normativas_service import get_normativas_completas
            result = await get_normativas_completas(mock_db)

            assert isinstance(result, list)
            assert len(result) == 0

    @pytest.mark.asyncio
    async def test_get_normativas_completas_enriquece_controles(self, mock_db):
        normativas_mock = [{"id": 1, "nombre": "ISO 27001", "version": "2022", "enabled": 1}]
        controles_mock = [{"id": 10, "codigo": "A.5.1", "nombre": "Políticas"}]

        with patch("app.services.normativas_service.db_norm") as mock_mod:
            mock_mod.get_normativas = AsyncMock(return_value=normativas_mock)
            mock_mod.get_controles_by_norm = AsyncMock(return_value=controles_mock)
            mock_mod.get_relaciones_control = AsyncMock(return_value=[])

            from app.services.normativas_service import get_normativas_completas
            result = await get_normativas_completas(mock_db)

            assert len(result) == 1
            assert "controles" in result[0]
            assert len(result[0]["controles"]) == 1

    @pytest.mark.asyncio
    async def test_crear_usf_llama_db(self, mock_db):
        with patch("app.services.normativas_service.db_norm") as mock_mod:
            mock_mod.get_usf_by_codigo = AsyncMock(return_value=None)
            mock_mod.new_usf = AsyncMock(return_value=None)

            from app.services.normativas_service import crear_usf
            data = {
                "codigo": "USF-NEW",
                "nombre": "Nuevo USF",
                "descripcion": "Desc",
                "dominio": "Dom",
                "tipo": "preventivo",
                "IdPAC": None,
            }
            result = await crear_usf(mock_db, data)
            assert result["error"] is False

    @pytest.mark.asyncio
    async def test_crear_usf_codigo_duplicado_falla(self, mock_db):
        with patch("app.services.normativas_service.db_norm") as mock_mod:
            mock_mod.get_usf_by_codigo = AsyncMock(return_value={"id": 5, "codigo": "USF-DUP"})

            from app.services.normativas_service import crear_usf
            result = await crear_usf(mock_db, {"codigo": "USF-DUP", "nombre": "X", "descripcion": "", "dominio": "", "tipo": "", "IdPAC": None})
            assert result["error"] is True

    @pytest.mark.asyncio
    async def test_crear_pregunta_nueva(self, mock_db):
        with patch("app.services.normativas_service.db_norm") as mock_mod:
            mock_mod.get_pregunta_by_duda = AsyncMock(return_value=None)
            mock_mod.new_pregunta = AsyncMock(return_value={"id": 20})

            from app.services.normativas_service import crear_pregunta
            result = await crear_pregunta(mock_db, duda="¿Pregunta nueva?", nivel=1)
            assert result["error"] is False

    @pytest.mark.asyncio
    async def test_crear_relacion_completa_itera_usfs(self, mock_db):
        relaciones = [
            {"idUSF": 1, "preguntas": [{"id": 5}, {"id": 6}]},
            {"idUSF": 2, "preguntas": [{"id": 7}]},
        ]
        with patch("app.services.normativas_service.db_norm") as mock_mod:
            mock_mod.new_relacion_pregunta_control = AsyncMock(return_value=None)

            from app.services.normativas_service import crear_relacion_completa
            result = await crear_relacion_completa(mock_db, control_id=10, relaciones=relaciones)

            # Debe haberse llamado 3 veces (una por pregunta)
            assert mock_mod.new_relacion_pregunta_control.call_count == 3
            assert result["error"] is False
