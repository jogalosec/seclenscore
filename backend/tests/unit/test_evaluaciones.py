"""
Tests unitarios para el módulo Evaluaciones (BIA, evaluaciones, versiones, OSA, PAC).
Ejecutar: pytest tests/unit/test_evaluaciones.py -v
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
def respuestas_bia_completas():
    """46 preguntas BIA con valor 2 (impacto medio)."""
    return {f"p{i}": 2 for i in range(1, 47)}


@pytest.fixture
def respuestas_bia_alta():
    """46 preguntas BIA con valor 4 (impacto crítico)."""
    return {f"p{i}": 4 for i in range(1, 47)}


# ---------------------------------------------------------------
# Tests de Schemas
# ---------------------------------------------------------------

class TestEvaluacionSchemas:

    def test_bia_create_request_valido(self):
        from app.schemas.evaluacion import BiaCreateRequest
        r = BiaCreateRequest(activo_id=5, respuestas={"p1": 3, "p2": 1})
        assert r.activo_id == 5
        assert r.respuestas["p1"] == 3

    def test_eval_save_request_valido(self):
        from app.schemas.evaluacion import EvalSaveRequest
        r = EvalSaveRequest(datos={"q1": "respuesta"})
        assert "q1" in r.datos

    def test_eval_create_request_defaults(self):
        from app.schemas.evaluacion import EvalCreateRequest
        r = EvalCreateRequest()
        assert r.editEval is False
        assert r.evaluate is None

    def test_eval_osa_request_valido(self):
        from app.schemas.evaluacion import EvalOsaRequest
        r = EvalOsaRequest(revision_id=10, datos={"osa1": 3})
        assert r.revision_id == 10


# ---------------------------------------------------------------
# Tests del cálculo BIA
# ---------------------------------------------------------------

class TestCalcularBia:

    def test_calculo_bia_devuelve_tres_dimensiones(self, respuestas_bia_completas):
        from app.services.evaluaciones_service import calcular_bia
        resultado = calcular_bia(respuestas_bia_completas)

        assert "Con" in resultado
        assert "Int" in resultado
        assert "Dis" in resultado
        assert "global" in resultado

    def test_calculo_bia_contiene_subdimensiones(self, respuestas_bia_completas):
        from app.services.evaluaciones_service import calcular_bia
        resultado = calcular_bia(respuestas_bia_completas)

        for dim in ["Con", "Int", "Dis"]:
            assert "total" in resultado[dim]
            for sub in ["Fin", "Op", "Le", "Rep", "Sal", "Pri"]:
                assert sub in resultado[dim]

    def test_calculo_bia_valor_maximo(self, respuestas_bia_alta):
        """Con todas las respuestas a 4, el global debe ser 4.0."""
        from app.services.evaluaciones_service import calcular_bia
        resultado = calcular_bia(respuestas_bia_alta)
        assert resultado["global"] == 4.0

    def test_calculo_bia_valor_cero(self):
        """Con todas las respuestas a 0, el global debe ser 0.0."""
        from app.services.evaluaciones_service import calcular_bia
        respuestas = {f"p{i}": 0 for i in range(1, 47)}
        resultado = calcular_bia(respuestas)
        assert resultado["global"] == 0.0

    def test_calculo_bia_respuestas_parciales(self):
        """Con solo algunas preguntas respondidas no debe lanzar error."""
        from app.services.evaluaciones_service import calcular_bia
        resultado = calcular_bia({"p1": 3, "p18": 2, "p30": 1})
        assert isinstance(resultado["global"], float)

    def test_calculo_bia_ignora_preguntas_invalidas(self):
        """Preguntas fuera del mapa deben ignorarse silenciosamente."""
        from app.services.evaluaciones_service import calcular_bia
        resultado = calcular_bia({"p999": 4, "p1": 2})
        assert isinstance(resultado["global"], float)

    def test_calculo_bia_global_es_promedio_dimensiones(self, respuestas_bia_completas):
        from app.services.evaluaciones_service import calcular_bia
        resultado = calcular_bia(respuestas_bia_completas)

        promedio_manual = round(
            (resultado["Con"]["total"] + resultado["Int"]["total"] + resultado["Dis"]["total"]) / 3,
            4,
        )
        assert resultado["global"] == promedio_manual

    def test_calculo_bia_severity_alto(self, respuestas_bia_alta):
        """Todos los totales deben ser >= 3 con respuestas máximas."""
        from app.services.evaluaciones_service import calcular_bia
        resultado = calcular_bia(respuestas_bia_alta)
        for dim in ["Con", "Int", "Dis"]:
            assert resultado[dim]["total"] >= 3.0


# ---------------------------------------------------------------
# Tests del servicio
# ---------------------------------------------------------------

class TestEvaluacionesService:

    @pytest.mark.asyncio
    async def test_get_bia_activo_no_encontrado(self, mock_db):
        with patch("app.services.evaluaciones_service.db_eval") as mock_mod:
            mock_mod.get_bia = AsyncMock(return_value=None)

            from app.services.evaluaciones_service import get_bia_activo
            result = await get_bia_activo(mock_db, activo_id=999)
            assert result is None

    @pytest.mark.asyncio
    async def test_guardar_bia_devuelve_calculo(self, mock_db, respuestas_bia_completas):
        with patch("app.services.evaluaciones_service.db_eval") as mock_mod:
            mock_mod.save_bia = AsyncMock(return_value=None)

            from app.services.evaluaciones_service import guardar_bia
            result = await guardar_bia(mock_db, activo_id=1, respuestas=respuestas_bia_completas, user_id=1)

            assert result["error"] is False
            assert "bia" in result
            assert "global" in result["bia"]

    @pytest.mark.asyncio
    async def test_guardar_evaluacion_llama_set_meta_value(self, mock_db):
        with patch("app.services.evaluaciones_service.db_eval") as mock_mod:
            mock_mod.set_meta_value = AsyncMock(return_value=None)

            from app.services.evaluaciones_service import guardar_evaluacion
            result = await guardar_evaluacion(mock_db, activo_id=1, datos={"q1": 3}, meta_key="preguntas", user_id=5)

            mock_mod.set_meta_value.assert_called_once()
            assert result["error"] is False

    @pytest.mark.asyncio
    async def test_editar_evaluacion_llama_edit_eval(self, mock_db):
        with patch("app.services.evaluaciones_service.db_eval") as mock_mod:
            mock_mod.edit_eval = AsyncMock(return_value=None)

            from app.services.evaluaciones_service import editar_evaluacion
            result = await editar_evaluacion(mock_db, eval_id=10, version_id=None, datos={"q1": 2}, nombre="v2")

            mock_mod.edit_eval.assert_called_once_with(mock_db, 10, None, {"q1": 2}, "v2")
            assert result["error"] is False

    @pytest.mark.asyncio
    async def test_get_preguntas_evaluacion_parsea_json(self, mock_db):
        import json
        datos = {"p1": 3, "p2": 1}
        with patch("app.services.evaluaciones_service.db_eval") as mock_mod:
            mock_mod.get_preguntas_evaluacion_by_fecha = AsyncMock(
                return_value={"preguntas": json.dumps(datos)}
            )

            from app.services.evaluaciones_service import get_preguntas_evaluacion
            result = await get_preguntas_evaluacion(mock_db, eval_id=5, es_version=False)

            assert isinstance(result["preguntas"], dict)
            assert result["preguntas"]["p1"] == 3

    @pytest.mark.asyncio
    async def test_guardar_eval_osa_construye_parametros(self, mock_db):
        with patch("app.services.evaluaciones_service.db_eval") as mock_mod:
            mock_mod.save_eval_osa = AsyncMock(return_value=None)

            from app.services.evaluaciones_service import guardar_eval_osa
            result = await guardar_eval_osa(mock_db, revision_id=3, datos={"osa1": 4})

            # save_eval_osa debe recibir dict con revision_id
            call_args = mock_mod.save_eval_osa.call_args[0][1]
            assert call_args["revision_id"] == 3
            assert result["error"] is False
