"""
Router FastAPI — Módulo Evaluaciones.
Endpoints equivalentes a los de evaluaciones en index.php.
"""
from typing import Optional

from fastapi import APIRouter, Depends, Query
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import get_db_factory
from app.core.dependencies import get_current_user
from app.schemas.evaluacion import (
    BiaCreateRequest,
    EvalCreateRequest,
    EvalOsaRequest,
    EvalSaveRequest,
)
from app.services import evaluaciones_service

router = APIRouter(tags=["Evaluaciones"])

get_serv_db = get_db_factory("octopus_serv")


# ---------------------------------------------------------------
# GET /api/getBia
# ---------------------------------------------------------------
@router.get("/api/getBia", summary="Obtener BIA de un activo")
async def get_bia(
    id: int = Query(..., description="ID del activo"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    bia = await evaluaciones_service.get_bia_activo(db, id)
    return {"error": False, "bia": bia}


# ---------------------------------------------------------------
# POST /api/saveBia
# ---------------------------------------------------------------
@router.post("/api/saveBia", summary="Guardar BIA de un activo")
async def save_bia(
    data: BiaCreateRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await evaluaciones_service.guardar_bia(
        db, data.activo_id, data.respuestas, current_user.get("id")
    )


# ---------------------------------------------------------------
# GET /api/getEvaluaciones
# ---------------------------------------------------------------
@router.get("/api/getEvaluaciones", summary="Evaluaciones de un activo")
async def get_evaluaciones(
    id: int = Query(..., description="ID del activo"),
    tipo: Optional[str] = Query(None, description="Filtrar por meta_key"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    data = await evaluaciones_service.get_evaluaciones_activo(db, id, tipo)
    return {"error": False, "evaluaciones": data}


# ---------------------------------------------------------------
# GET /api/getEvaluacion
# ---------------------------------------------------------------
@router.get("/api/getEvaluacion", summary="Evaluación por ID")
async def get_evaluacion(
    id: int = Query(..., description="ID de la evaluación"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    data = await evaluaciones_service.get_evaluacion_by_id(db, id)
    return {"error": False, "evaluacion": data}


# ---------------------------------------------------------------
# POST /api/saveEvaluacion
# ---------------------------------------------------------------
@router.post("/api/saveEvaluacion", summary="Guardar evaluación (preguntas)")
async def save_evaluacion(
    data: EvalSaveRequest,
    activo_id: int = Query(...),
    meta_key: str = Query("preguntas"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await evaluaciones_service.guardar_evaluacion(
        db, activo_id, data.datos, meta_key, current_user.get("id")
    )


# ---------------------------------------------------------------
# POST /api/editEvaluacion
# ---------------------------------------------------------------
@router.post("/api/editEvaluacion", summary="Crear versión de evaluación")
async def edit_evaluacion(
    data: EvalCreateRequest,
    eval_id: Optional[int] = Query(None),
    version_id: Optional[int] = Query(None),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    nombre = data.version or "Edición"
    datos = data.evaluate or {}
    return await evaluaciones_service.editar_evaluacion(db, eval_id, version_id, datos, nombre)


# ---------------------------------------------------------------
# GET /api/getFechaEvaluaciones
# ---------------------------------------------------------------
@router.get("/api/getFechaEvaluaciones", summary="Historial de evaluaciones")
async def get_fecha_evaluaciones(
    id: int = Query(..., description="ID del activo"),
    allVersions: bool = Query(False),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    data = await evaluaciones_service.get_historial_evaluaciones(db, id, allVersions)
    return {"error": False, "evaluaciones": data}


# ---------------------------------------------------------------
# GET /api/getEvaluacionesSistema
# ---------------------------------------------------------------
@router.get("/api/getEvaluacionesSistema", summary="Evaluaciones + versiones de un activo")
async def get_evaluaciones_sistema(
    id: int = Query(..., description="ID del activo"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    data = await evaluaciones_service.get_evaluaciones_sistema(db, id)
    return {"error": False, "evaluaciones": data}


# ---------------------------------------------------------------
# GET /api/getPreguntasEvaluacion
# ---------------------------------------------------------------
@router.get("/api/getPreguntasEvaluacion", summary="Preguntas de una evaluación")
async def get_preguntas_evaluacion(
    id: int = Query(..., description="ID de la evaluación o versión"),
    esVersion: bool = Query(False),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    data = await evaluaciones_service.get_preguntas_evaluacion(db, id, esVersion)
    return {"error": False, "preguntas": data}


# ---------------------------------------------------------------
# POST /api/saveEvalOsa
# ---------------------------------------------------------------
@router.post("/api/saveEvalOsa", summary="Guardar evaluación OSA")
async def save_eval_osa(
    data: EvalOsaRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await evaluaciones_service.guardar_eval_osa(db, data.revision_id, data.datos)


# ---------------------------------------------------------------
# GET /api/getPacEval
# ---------------------------------------------------------------
@router.get("/api/getPacEval", summary="PAC de evaluación por activo y fecha")
async def get_pac_eval(
    id: int = Query(..., description="ID del activo"),
    fecha: str = Query(..., description="Fecha mínima (YYYY-MM-DD)"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    data = await evaluaciones_service.get_pac_eval(db, id, fecha)
    return {"error": False, "pac": data}
