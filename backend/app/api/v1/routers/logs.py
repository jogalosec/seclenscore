"""Router FastAPI — Logs de auditoría."""
from typing import Optional
from fastapi import APIRouter, Depends, Query
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import get_db_factory
from app.core.dependencies import get_current_user
from app.services import logs_service

router = APIRouter(tags=["Logs"])
get_logs_db = get_db_factory("octopus_logs")


@router.get("/api/getLogsRelacion")
async def get_logs_relacion(
    fecha_inicio: Optional[str] = Query(None),
    fecha_fin: Optional[str] = Query(None),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_logs_db),
):
    return await logs_service.get_logs_relaciones(db, fecha_inicio, fecha_fin)


@router.get("/api/getEvents")
async def get_events(
    fecha_inicio: Optional[str] = Query(None),
    fecha_fin: Optional[str] = Query(None),
    limit: int = Query(500),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_logs_db),
):
    return await logs_service.get_logs_accesos(db, fecha_inicio, fecha_fin, limit=limit)


@router.post("/api/getLogsActivosProcessed")
async def get_logs_activos_processed(
    fecha_inicio: Optional[str] = None,
    fecha_fin: Optional[str] = None,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_logs_db),
):
    return await logs_service.get_logs_activos_procesados(db, fecha_inicio, fecha_fin)


@router.post("/api/getLogsActivosRaw")
async def get_logs_activos_raw(
    fecha_inicio: Optional[str] = None,
    fecha_fin: Optional[str] = None,
    limit: int = 1000,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_logs_db),
):
    return await logs_service.get_route_logs(db, fecha_inicio, fecha_fin, limit=limit)
