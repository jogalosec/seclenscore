"""
Router FastAPI — Módulo Normativas / Controles / USFs / Preguntas / Marco.
Equivalente a los endpoints /api/getNormativas, /api/newNormativa, etc. de index.php.
"""
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import get_db_factory
from app.core.dependencies import get_current_user
from app.db import octopus_new as db_norm
from app.schemas.common import APIResponse
from app.schemas.normativa import (
    ControlCreate,
    ControlDeleteRequest,
    NormativaCreate,
    NormativaDeleteRequest,
    NormativaUpdate,
    PreguntaCreate,
    PreguntaDeleteRequest,
    RelacionCompletaRequest,
    RelacionDeleteRequest,
    RelacionPreguntasRequest,
    USFCreate,
    USFDeleteRequest,
)
from app.services import normativas_service

router = APIRouter(tags=["Normativas"])

get_new_db = get_db_factory("octopus_new")


# ---------------------------------------------------------------
# GET /api/getNormativas
# ---------------------------------------------------------------
@router.get("/api/getNormativas", summary="Todas las normativas con controles y relaciones")
async def get_normativas(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    return await normativas_service.get_normativas_completas(db)


# ---------------------------------------------------------------
# GET /api/getPreguntas
# ---------------------------------------------------------------
@router.get("/api/getPreguntas", summary="Todas las preguntas con relaciones marco")
async def get_preguntas(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    return await normativas_service.get_preguntas_completas(db)


# ---------------------------------------------------------------
# GET /api/getUSFs
# ---------------------------------------------------------------
@router.get("/api/getUSFs", summary="Todos los USFs con relaciones marco")
async def get_usfs(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    return await normativas_service.get_usfs_completas(db)


# ---------------------------------------------------------------
# GET /api/getDominiosUnicosControles
# ---------------------------------------------------------------
@router.get("/api/getDominiosUnicosControles", summary="Dominios únicos de los controles")
async def get_dominios_unicos(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    dominios = await db_norm.get_dominios_controles_unicos(db)
    return {"error": False, "dominios": dominios,
            "message": "Se han obtenido los dominios existentes en los controles."}


# ---------------------------------------------------------------
# POST /api/newNormativa
# ---------------------------------------------------------------
@router.post("/api/newNormativa", response_model=APIResponse, summary="Crear normativa")
async def new_normativa(
    data: NormativaCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    await db_norm.new_normativa(db, nombre=data.nombre, version=data.version)
    return APIResponse(error=False, message="Normativa creada correctamente.")


# ---------------------------------------------------------------
# POST /api/editNormativa
# ---------------------------------------------------------------
@router.post("/api/editNormativa", response_model=APIResponse, summary="Editar normativa")
async def edit_normativa(
    data: NormativaUpdate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    await db_norm.edit_normativa(db, nombre=data.nombre, enabled=data.enabled, norm_id=data.idNormativa)
    return APIResponse(error=False, message="Normativa actualizada correctamente.")


# ---------------------------------------------------------------
# POST /api/deleteNormativa
# ---------------------------------------------------------------
@router.post("/api/deleteNormativa", response_model=APIResponse, summary="Eliminar normativa")
async def delete_normativa(
    data: NormativaDeleteRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    """Elimina en cascada: marco → controles → normativa."""
    await db_norm.delete_normativa(db, norm_id=data.idNormativa)
    return APIResponse(error=False, message="Normativa eliminada correctamente.")


# ---------------------------------------------------------------
# POST /api/newControl
# ---------------------------------------------------------------
@router.post("/api/newControl", response_model=APIResponse, summary="Crear control")
async def new_control(
    data: ControlCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    await db_norm.new_control(
        db,
        codigo=data.codigo,
        nombre=data.nombre,
        descripcion=data.descripcion,
        dominio=data.dominio,
        norm_id=data.idNormativa,
    )
    return APIResponse(error=False, message="Control creado correctamente.")


# ---------------------------------------------------------------
# POST /api/deleteControl
# ---------------------------------------------------------------
@router.post("/api/deleteControl", response_model=APIResponse, summary="Eliminar control")
async def delete_control(
    data: ControlDeleteRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    await db_norm.delete_control(db, control_id=data.idControl)
    return APIResponse(error=False, message="Control eliminado correctamente.")


# ---------------------------------------------------------------
# POST /api/newUSF
# ---------------------------------------------------------------
@router.post("/api/newUSF", summary="Crear USF")
async def new_usf(
    data: USFCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    return await normativas_service.crear_usf(db, data.model_dump())


# ---------------------------------------------------------------
# POST /api/deleteUSF
# ---------------------------------------------------------------
@router.post("/api/deleteUSF", response_model=APIResponse, summary="Eliminar USF")
async def delete_usf(
    data: USFDeleteRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    await db_norm.delete_usf(db, usf_id=data.idUSF)
    return APIResponse(error=False, message="USF eliminado correctamente.")


# ---------------------------------------------------------------
# POST /api/newPregunta
# ---------------------------------------------------------------
@router.post("/api/newPregunta", summary="Crear pregunta")
async def new_pregunta(
    data: PreguntaCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    return await normativas_service.crear_pregunta(db, duda=data.duda, nivel=data.nivel)


# ---------------------------------------------------------------
# POST /api/deletePregunta
# ---------------------------------------------------------------
@router.post("/api/deletePregunta", response_model=APIResponse, summary="Eliminar pregunta")
async def delete_pregunta(
    data: PreguntaDeleteRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    await db_norm.delete_pregunta(db, pregunta_id=data.idPregunta)
    return APIResponse(error=False, message="Pregunta eliminada correctamente.")


# ---------------------------------------------------------------
# POST /api/crearRelacionCompleta
# ---------------------------------------------------------------
@router.post("/api/crearRelacionCompleta", summary="Crear relaciones control-USF-pregunta")
async def crear_relacion_completa(
    data: RelacionCompletaRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    return await normativas_service.crear_relacion_completa(
        db,
        control_id=data.id,
        relaciones=[r.model_dump() for r in data.relaciones],
    )


# ---------------------------------------------------------------
# POST /api/crearRelacionPreguntas
# ---------------------------------------------------------------
@router.post("/api/crearRelacionPreguntas", summary="Relacionar control con preguntas")
async def crear_relacion_preguntas(
    data: RelacionPreguntasRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    return await normativas_service.crear_relacion_preguntas(
        db, preguntas=data.preguntas, control_id=data.control
    )


# ---------------------------------------------------------------
# POST /api/deleteRelacionMarcoNormativa
# ---------------------------------------------------------------
@router.post(
    "/api/deleteRelacionMarcoNormativa",
    response_model=APIResponse,
    summary="Eliminar relación del marco normativo",
)
async def delete_relacion_marco_normativa(
    data: RelacionDeleteRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_new_db),
):
    await db_norm.delete_relacion_marco(db, relacion_id=data.idRelacion)
    return APIResponse(error=False, message="Relación marco normativo eliminada correctamente.")
