"""
Router FastAPI — Módulo KPMs (Métricas, Madurez, CSIRT).
Equivalente a los endpoints /api/getKpms, /api/lockKpms, etc. de index.php.
"""
from typing import Optional

from fastapi import APIRouter, Depends, Query
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import get_db_factory
from app.core.dependencies import get_current_user
from app.schemas.kpm import (
    KpmCreate,
    KpmDefinicionUpdate,
    KpmDeleteRequest,
    KpmEditRequest,
    KpmLockRequest,
    ReporterKPMCreate,
    ReporterKPMDelete,
    TipoKPM,
)
from app.services import kpms_service

router = APIRouter(tags=["KPMs"])

get_kpms_db = get_db_factory("octopus_kpms")


# ---------------------------------------------------------------
# GET /api/getKpms
# ---------------------------------------------------------------
@router.get("/api/getKpms", summary="KPMs del usuario por tipo")
async def get_kpms(
    tipo: TipoKPM = Query(..., description="Tipo: madurez | metricas | csirt"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_kpms_db),
):
    admin = current_user.get("additionalAccess", False)
    return await kpms_service.get_kpms_usuario(
        db, current_user["id"], tipo, admin
    )


# ---------------------------------------------------------------
# GET /api/getLastReportKpms
# ---------------------------------------------------------------
@router.get("/api/getLastReportKpms", summary="Último reporte KPM del usuario")
async def get_last_report_kpms(
    tipo: TipoKPM = Query(...),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_kpms_db),
):
    return await kpms_service.get_ultimo_reporte(db, current_user["id"], tipo)


# ---------------------------------------------------------------
# GET /api/getReportAs
# ---------------------------------------------------------------
@router.get("/api/getReportAs", summary="Reportes accesibles por el usuario")
async def get_report_as(
    csirt: bool = Query(False),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_kpms_db),
):
    admin = current_user.get("additionalAccess", False)
    return await kpms_service.get_reporters(db, current_user["id"], admin, csirt)


# ---------------------------------------------------------------
# GET /api/getPreguntasKpms
# ---------------------------------------------------------------
@router.get("/api/getPreguntasKpms", summary="Preguntas del formulario KPMs")
async def get_preguntas_kpms(
    tipo: Optional[str] = Query(None, description="csirt | all | None (madurez+metricas)"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_kpms_db),
):
    return await kpms_service.get_preguntas_formulario(db, tipo)


# ---------------------------------------------------------------
# GET /api/getReportersKpms
# ---------------------------------------------------------------
@router.get("/api/getReportersKpms", summary="Reporters KPMs del usuario")
async def get_reporters_kpms(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_kpms_db),
):
    admin = current_user.get("additionalAccess", False)
    return await kpms_service.get_reporters_kpms(db, current_user["id"], admin)


# ---------------------------------------------------------------
# POST /api/lockKpms
# ---------------------------------------------------------------
@router.post("/api/lockKpms", summary="Bloquear KPMs")
async def lock_kpms(
    data: KpmLockRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_kpms_db),
):
    return await kpms_service.bloquear_kpms(db, data.tipo, data.id, bloquear=True)


# ---------------------------------------------------------------
# POST /api/unlockKpms
# ---------------------------------------------------------------
@router.post("/api/unlockKpms", summary="Desbloquear KPMs")
async def unlock_kpms(
    data: KpmLockRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_kpms_db),
):
    return await kpms_service.bloquear_kpms(db, data.tipo, data.id, bloquear=False)


# ---------------------------------------------------------------
# POST /api/delKpms
# ---------------------------------------------------------------
@router.post("/api/delKpms", summary="Eliminar KPMs")
async def del_kpms(
    data: KpmDeleteRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_kpms_db),
):
    return await kpms_service.eliminar_kpms(db, data.tipo, data.id)


# ---------------------------------------------------------------
# POST /api/editKpm
# ---------------------------------------------------------------
@router.post("/api/editKpm", summary="Editar campos de un KPM")
async def edit_kpm(
    data: KpmEditRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_kpms_db),
):
    return await kpms_service.editar_kpm(
        db, data.tipo, data.id, data.campos, current_user.get("id")
    )


# ---------------------------------------------------------------
# POST /api/editKpmDefinicion
# ---------------------------------------------------------------
@router.post("/api/editKpmDefinicion", summary="Actualizar definición de KPM")
async def edit_kpm_definicion(
    data: KpmDefinicionUpdate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_kpms_db),
):
    return await kpms_service.actualizar_definicion_kpm(
        db,
        data.id,
        data.nombre,
        data.descripcion_larga,
        data.descripcion_corta,
        data.grupo,
    )


# ---------------------------------------------------------------
# POST /api/newReporterKpms
# ---------------------------------------------------------------
@router.post("/api/newReporterKpms", summary="Crear reporter KPM")
async def new_reporter_kpms(
    data: ReporterKPMCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_kpms_db),
):
    return await kpms_service.crear_reporter(db, data.userId, data.idActivo)


# ---------------------------------------------------------------
# POST /api/deleteReporterKpms
# ---------------------------------------------------------------
@router.post("/api/deleteReporterKpms", summary="Eliminar reporter KPM")
async def delete_reporter_kpms(
    data: ReporterKPMDelete,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_kpms_db),
):
    return await kpms_service.eliminar_reporter(db, data.idRelacion)
