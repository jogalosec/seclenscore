"""
Tests unitarios para el módulo KPMs (Métricas, Madurez, CSIRT).
Ejecutar: pytest tests/unit/test_kpms.py -v
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


# ---------------------------------------------------------------
# Tests de Schemas
# ---------------------------------------------------------------

class TestKpmSchemas:

    def test_tipo_kpm_valido_metricas(self):
        from app.schemas.kpm import KpmLockRequest
        r = KpmLockRequest(tipo="metricas", id=[1, 2, 3])
        assert r.tipo == "metricas"

    def test_tipo_kpm_valido_madurez(self):
        from app.schemas.kpm import KpmLockRequest
        r = KpmLockRequest(tipo="madurez", id=[10])
        assert r.tipo == "madurez"

    def test_tipo_kpm_valido_csirt(self):
        from app.schemas.kpm import KpmLockRequest
        r = KpmLockRequest(tipo="csirt", id=[5])
        assert r.tipo == "csirt"

    def test_tipo_kpm_invalido_falla(self):
        """Tipos fuera de la whitelist deben fallar — FIX SQL injection."""
        from app.schemas.kpm import KpmLockRequest
        from pydantic import ValidationError
        with pytest.raises(ValidationError):
            KpmLockRequest(tipo="usuarios; DROP TABLE metricas;--", id=[1])

    def test_tipo_kpm_invalido_otros_falla(self):
        from app.schemas.kpm import KpmDeleteRequest
        from pydantic import ValidationError
        with pytest.raises(ValidationError):
            KpmDeleteRequest(tipo="any_other_table", id=[1])

    def test_kpm_lock_ids_no_vacia_falla(self):
        from app.schemas.kpm import KpmLockRequest
        from pydantic import ValidationError
        with pytest.raises(ValidationError):
            KpmLockRequest(tipo="metricas", id=[])

    def test_kpm_edit_request_valido(self):
        from app.schemas.kpm import KpmEditRequest
        r = KpmEditRequest(tipo="madurez", id=5, campos={"valor": 3, "comentario": "OK"})
        assert r.campos["valor"] == 3

    def test_kpm_definicion_update_opcionales(self):
        from app.schemas.kpm import KpmDefinicionUpdate
        # Todos opcionales, solo id requerido
        r = KpmDefinicionUpdate(id=1, nombre="Nuevo nombre")
        assert r.nombre == "Nuevo nombre"
        assert r.descripcion_larga is None

    def test_reporter_create_valido(self):
        from app.schemas.kpm import ReporterKPMCreate
        r = ReporterKPMCreate(userId=1, idActivo=10)
        assert r.userId == 1

    def test_reporter_delete_valido(self):
        from app.schemas.kpm import ReporterKPMDelete
        r = ReporterKPMDelete(idRelacion=99)
        assert r.idRelacion == 99


# ---------------------------------------------------------------
# Tests de la DB layer (validación whitelist tablas)
# ---------------------------------------------------------------

class TestOctopusKpmsDB:

    def test_validate_table_metricas(self):
        from app.db.octopus_kpms import _validate_table
        assert _validate_table("metricas") == "metricas"

    def test_validate_table_madurez(self):
        from app.db.octopus_kpms import _validate_table
        assert _validate_table("madurez") == "madurez"

    def test_validate_table_csirt(self):
        from app.db.octopus_kpms import _validate_table
        assert _validate_table("csirt") == "csirt"

    def test_validate_table_invalida_lanza_error(self):
        from app.db.octopus_kpms import _validate_table
        with pytest.raises(ValueError):
            _validate_table("evaluaciones; DROP TABLE metricas;")

    def test_allowed_columns_no_incluye_columnas_peligrosas(self):
        from app.db.octopus_kpms import ALLOWED_COLUMNS
        for tabla, cols in ALLOWED_COLUMNS.items():
            assert "reporter_id" not in cols, f"{tabla} no debe permitir editar reporter_id directamente"
            assert "id" not in cols, f"{tabla} no debe permitir editar el PK"
            assert "kpm_id" not in cols, f"{tabla} no debe permitir editar kpm_id"


# ---------------------------------------------------------------
# Tests del servicio
# ---------------------------------------------------------------

class TestKpmsService:

    @pytest.mark.asyncio
    async def test_get_kpms_metricas_devuelve_dict(self, mock_db):
        with patch("app.services.kpms_service.db_kpms") as mock_mod:
            mock_mod.get_metricas_by_user = AsyncMock(return_value=[
                {"id": 1, "valor": 3, "reporter_email": "user@test.com"},
            ])

            from app.services.kpms_service import get_kpms_usuario
            result = await get_kpms_usuario(mock_db, user_id=1, tipo="metricas", admin=False)

            assert result["error"] is False
            assert len(result["kpms"]) == 1

    @pytest.mark.asyncio
    async def test_get_kpms_admin_llama_sin_user_id(self, mock_db):
        """En modo admin, debe llamar a la función que devuelve todos."""
        with patch("app.services.kpms_service.db_kpms") as mock_mod:
            mock_mod.get_metricas_by_user = AsyncMock(return_value=[])

            from app.services.kpms_service import get_kpms_usuario
            await get_kpms_usuario(mock_db, user_id=1, tipo="metricas", admin=True)

            # Se llama con admin=True
            mock_mod.get_metricas_by_user.assert_called_once_with(mock_db, 1, True)

    @pytest.mark.asyncio
    async def test_bloquear_kpms_llama_lock(self, mock_db):
        with patch("app.services.kpms_service.db_kpms") as mock_mod:
            mock_mod.lock_kpms = AsyncMock(return_value=None)

            from app.services.kpms_service import bloquear_kpms
            result = await bloquear_kpms(mock_db, tipo="metricas", ids=[1, 2], bloquear=True)

            mock_mod.lock_kpms.assert_called_once_with(mock_db, "metricas", [1, 2])
            assert result["error"] is False

    @pytest.mark.asyncio
    async def test_desbloquear_kpms_llama_unlock(self, mock_db):
        with patch("app.services.kpms_service.db_kpms") as mock_mod:
            mock_mod.unlock_kpms = AsyncMock(return_value=None)

            from app.services.kpms_service import bloquear_kpms
            result = await bloquear_kpms(mock_db, tipo="madurez", ids=[5], bloquear=False)

            mock_mod.unlock_kpms.assert_called_once_with(mock_db, "madurez", [5])
            assert result["error"] is False

    @pytest.mark.asyncio
    async def test_eliminar_kpms_llama_del(self, mock_db):
        with patch("app.services.kpms_service.db_kpms") as mock_mod:
            mock_mod.del_kpms = AsyncMock(return_value=None)

            from app.services.kpms_service import eliminar_kpms
            result = await eliminar_kpms(mock_db, tipo="csirt", ids=[10, 11])

            mock_mod.del_kpms.assert_called_once_with(mock_db, "csirt", [10, 11])
            assert result["error"] is False

    @pytest.mark.asyncio
    async def test_editar_kpm_solo_campos_permitidos(self, mock_db):
        """El servicio debe pasar solo campos al DB layer; la validación real está en edit_kpm."""
        with patch("app.services.kpms_service.db_kpms") as mock_mod:
            mock_mod.edit_kpm = AsyncMock(return_value=None)

            from app.services.kpms_service import editar_kpm
            result = await editar_kpm(mock_db, tipo="metricas", kpm_id=1, campos={"valor": 3}, user_id=5)

            mock_mod.edit_kpm.assert_called_once_with(mock_db, "metricas", 1, {"valor": 3}, 5)
            assert result["error"] is False

    @pytest.mark.asyncio
    async def test_crear_reporter_llama_db(self, mock_db):
        with patch("app.services.kpms_service.db_kpms") as mock_mod:
            mock_mod.crear_reporter_kpms = AsyncMock(return_value=None)

            from app.services.kpms_service import crear_reporter
            result = await crear_reporter(mock_db, user_id=3, activo_id=7)

            mock_mod.crear_reporter_kpms.assert_called_once_with(mock_db, 3, 7)
            assert result["error"] is False

    @pytest.mark.asyncio
    async def test_eliminar_reporter_llama_db(self, mock_db):
        with patch("app.services.kpms_service.db_kpms") as mock_mod:
            mock_mod.delete_relacion_reporter = AsyncMock(return_value=None)

            from app.services.kpms_service import eliminar_reporter
            result = await eliminar_reporter(mock_db, relacion_id=42)

            mock_mod.delete_relacion_reporter.assert_called_once_with(mock_db, 42)
            assert result["error"] is False
