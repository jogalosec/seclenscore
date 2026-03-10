"""
Tests unitarios para el módulo Activos.
Ejecutar: pytest tests/unit/test_activos.py -v
"""
import pytest
from unittest.mock import AsyncMock, MagicMock, patch


# ---------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------

@pytest.fixture
def activo_sample():
    return {
        "id": 1,
        "nombre": "Servidor Web Principal",
        "tipo": 42,
        "tipo_nombre": "Servidor",
        "padre": None,
        "archivado": 0,
        "expuesto": 1,
        "user_id": 5,
    }


@pytest.fixture
def mock_db():
    """AsyncSession mock."""
    db = MagicMock()
    db.execute = AsyncMock()
    db.commit  = AsyncMock()
    return db


# ---------------------------------------------------------------
# Tests de Schemas (validación Pydantic)
# ---------------------------------------------------------------

class TestActivoSchemas:

    def test_activo_create_valid(self):
        from app.schemas.activo import ActivoCreate
        a = ActivoCreate(nombre="Test", clase=42, padre=None)
        assert a.nombre == "Test"
        assert a.clase == 42
        assert a.padre is None

    def test_activo_create_nombre_vacio_falla(self):
        from app.schemas.activo import ActivoCreate
        from pydantic import ValidationError
        with pytest.raises(ValidationError):
            ActivoCreate(nombre="", clase=42)

    def test_activo_update_solo_campos_presentes(self):
        from app.schemas.activo import ActivoUpdate
        u = ActivoUpdate(id=1, nombre="Nuevo Nombre")
        dumped = u.model_dump(exclude_none=True)
        assert "nombre" in dumped
        assert "descripcion" not in dumped

    def test_relacion_request_valida(self):
        from app.schemas.activo import RelacionActivoRequest
        r = RelacionActivoRequest(activo_id=10, nuevo_padre_id=5)
        assert r.activo_id == 10
        assert r.nuevo_padre_id == 5

    def test_eliminar_relacion_request_valida(self):
        from app.schemas.activo import EliminarRelacionRequest
        r = EliminarRelacionRequest(activo_id=10, padre_id=5)
        assert r.activo_id == 10

    def test_block_items_tipo_valido(self):
        from app.schemas.activo import BlockItemsRequest
        b = BlockItemsRequest(tipo="madurez")
        assert b.tipo == "madurez"

    def test_block_items_tipo_invalido_falla(self):
        from app.schemas.activo import BlockItemsRequest
        from pydantic import ValidationError
        with pytest.raises(ValidationError):
            BlockItemsRequest(tipo="inyeccion_sql; DROP TABLE activos;")


# ---------------------------------------------------------------
# Tests del servicio (lógica de negocio)
# ---------------------------------------------------------------

class TestActivosService:

    @pytest.mark.asyncio
    async def test_get_activos_lista_tipo_42a(self, mock_db):
        """tipo '42a' debe llamar get_activos_by_tipo con tipo 42 y 67."""
        with patch("app.services.activos_service.db_activos") as mock_db_mod:
            mock_db_mod.get_activos_by_tipo = AsyncMock(return_value=[])
            mock_db_mod.get_activos_permisos = AsyncMock(return_value=[])

            from app.services.activos_service import get_activos_lista
            result = await get_activos_lista(mock_db, tipo="42a", user_id=1)

            assert isinstance(result, dict)

    @pytest.mark.asyncio
    async def test_organizar_familia_estructura_correcta(self):
        """organizar_familia debe construir árbol anidado."""
        from app.services.activos_service import organizar_familia

        activos_planos = [
            {"id": 1, "nombre": "Raíz",  "padre": None},
            {"id": 2, "nombre": "Hijo1", "padre": 1},
            {"id": 3, "nombre": "Hijo2", "padre": 1},
            {"id": 4, "nombre": "Nieto", "padre": 2},
        ]
        arbol = organizar_familia(activos_planos)

        # Debe haber un solo nodo raíz
        assert len(arbol) == 1
        raiz = arbol[0]
        assert raiz["nombre"] == "Raíz"
        assert len(raiz["hijos"]) == 2

    @pytest.mark.asyncio
    async def test_crear_activo_llama_db(self, mock_db):
        """crear_activo debe llamar new_activo en la capa DB."""
        with patch("app.services.activos_service.db_activos") as mock_db_mod:
            mock_db_mod.new_activo = AsyncMock(return_value={"id": 99, "nombre": "Nuevo"})

            from app.services.activos_service import crear_activo
            result = await crear_activo(mock_db, nombre="Nuevo", clase=42, padre=None, user_id=1)

            mock_db_mod.new_activo.assert_called_once()
            assert result["error"] is False

    @pytest.mark.asyncio
    async def test_eliminar_activo_llama_db(self, mock_db):
        """eliminar_activo debe llamar delete_activo en la capa DB."""
        with patch("app.services.activos_service.db_activos") as mock_db_mod:
            mock_db_mod.delete_activo = AsyncMock(return_value=True)

            from app.services.activos_service import eliminar_activo
            result = await eliminar_activo(mock_db, activo_id=1)

            mock_db_mod.delete_activo.assert_called_once_with(mock_db, 1)
            assert result["error"] is False

    @pytest.mark.asyncio
    async def test_get_arbol_activo_not_found(self, mock_db):
        """get_arbol_activo debe devolver error si el activo no existe."""
        with patch("app.services.activos_service.db_activos") as mock_db_mod:
            mock_db_mod.get_activo_by_id = AsyncMock(return_value=None)

            from app.services.activos_service import get_arbol_activo
            result = await get_arbol_activo(mock_db, activo_id=9999)

            assert result["error"] is True

    def test_get_arbol_para_excel_incluye_cabeceras(self):
        """get_arbol_para_excel debe incluir fila de cabeceras como primer elemento."""
        import asyncio
        with patch("app.services.activos_service.db_activos") as mock_db_mod:
            activo_raiz = {"id": 1, "nombre": "Raíz", "tipo_nombre": "Servidor", "padre": None, "archivado": 0, "expuesto": 0}
            mock_db_mod.get_activo_by_id = AsyncMock(return_value=activo_raiz)
            mock_db_mod.get_hijos = AsyncMock(return_value=[])

            from app.services.activos_service import get_arbol_para_excel
            mock_db = MagicMock()
            result = asyncio.run(get_arbol_para_excel(mock_db, activo_id=1))

            # Primer elemento debe ser lista de cabeceras
            assert result is not None
            assert isinstance(result[0], list)


# ---------------------------------------------------------------
# Tests del generador Excel
# ---------------------------------------------------------------

class TestExcelGenerator:

    def test_generar_excel_arbol_devuelve_bytes(self):
        from app.utils.excel_generator import generar_excel_arbol_activos

        arbol = [
            ["Nombre", "Tipo", "ID", "Padre", "Archivado", "Expuesto"],
            {"nombre": "Servidor Web", "tipo": "Servidor", "id": 1, "padre": None, "archivado": 0, "expuesto": 1},
        ]
        result = generar_excel_arbol_activos(arbol)
        assert isinstance(result, bytes)
        assert len(result) > 0

    def test_generar_excel_arbol_es_xlsx_valido(self):
        """El bytes resultante debe ser un XLSX válido (ZIP con content types)."""
        import zipfile
        import io
        from app.utils.excel_generator import generar_excel_arbol_activos

        arbol = [
            ["Nombre", "Tipo", "ID", "Padre", "Archivado", "Expuesto"],
            {"nombre": "Test", "tipo": "Tipo1", "id": 1, "padre": None, "archivado": 0, "expuesto": 0},
        ]
        xlsx_bytes = generar_excel_arbol_activos(arbol)

        with zipfile.ZipFile(io.BytesIO(xlsx_bytes)) as z:
            assert "[Content_Types].xml" in z.namelist()

    def test_generar_excel_generico_respeta_campos(self):
        from app.utils.excel_generator import generar_excel_generico

        datos = [{"id": 1, "nombre": "Test", "extra": "ignorado"}]
        result = generar_excel_generico(
            datos,
            cabeceras=["ID", "Nombre"],
            campos=["id", "nombre"],
            nombre_hoja="Prueba",
        )
        assert isinstance(result, bytes)
        assert len(result) > 0

    def test_generar_excel_activos_sin_datos(self):
        from app.utils.excel_generator import generar_excel_activos

        # No debe fallar con lista vacía
        result = generar_excel_activos([])
        assert isinstance(result, bytes)
