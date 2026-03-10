"""Router FastAPI — PAC (Plan de Acciones Correctivas) y Plan de Continuidad."""
from typing import Optional
from fastapi import APIRouter, Depends, HTTPException, Query, Response
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import get_db_factory
from app.core.dependencies import get_current_user
from app.schemas.pac import (
    PacCreate, PacSeguimientoCreate, PacSeguimientoDelete,
    PacSeguimientoUpdate, PlanCreate, PlanDelete, PlanUpdate,
)
from app.services import pac_service

router = APIRouter(tags=["PAC"])
get_serv_db = get_db_factory("octopus_serv")


# ── PAC ──────────────────────────────────────────────────────

@router.get("/api/getListPac")
async def get_list_pac(
    id: int = Query(..., description="ID del activo"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await pac_service.get_pac_list(db, id)


@router.post("/api/createPac")
async def create_pac(
    data: PacCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await pac_service.crear_pac(db, data.model_dump(), current_user.get("id"))


@router.get("/api/getSeguimientoByPacID")
async def get_seguimiento(
    id: int = Query(...),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await pac_service.get_seguimiento(db, id)


@router.post("/api/ModEstadoPacSeguimiento")
async def mod_estado_seguimiento(
    data: PacSeguimientoCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await pac_service.crear_seguimiento(db, data.pac_id, data.descripcion, data.estado)


@router.post("/api/editPacSeguimiento")
async def edit_seguimiento(
    data: PacSeguimientoUpdate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await pac_service.editar_seguimiento(db, data.id, data.descripcion, data.estado)


@router.post("/api/deletePacSeguimiento")
async def delete_seguimiento(
    data: PacSeguimientoDelete,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await pac_service.eliminar_seguimiento(db, data.id)


@router.get("/api/downloadPac")
async def download_pac(
    id: int = Query(..., description="ID del activo"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    from app.db.octopus_serv import get_activo_by_id
    activo = await get_activo_by_id(db, id)
    if not activo:
        raise HTTPException(status_code=404, detail="Activo no encontrado")
    doc_bytes = await pac_service.descargar_pac(db, id, dict(activo))
    return Response(
        content=doc_bytes,
        media_type="application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        headers={"Content-Disposition": f"attachment; filename=PAC_activo_{id}.docx"},
    )


# ── Plan de Continuidad ───────────────────────────────────────

@router.get("/api/getProductosContinuidad")
async def get_productos_continuidad(
    id: Optional[int] = Query(None, description="ID del activo (opcional)"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await pac_service.get_planes(db, id)


@router.post("/api/newPlan")
async def new_plan(
    data: PlanCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await pac_service.crear_plan(db, data.model_dump(), current_user.get("id"))


@router.post("/api/editPlan")
async def edit_plan(
    data: PlanUpdate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await pac_service.editar_plan(db, data.id, data.model_dump(exclude_none=True, exclude={"id"}))


@router.post("/api/deletePlan")
async def delete_plan(
    data: PlanDelete,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await pac_service.eliminar_plan(db, data.id)
