"""Router FastAPI — Kiuwan + SDLC (Sprint 7)."""
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import get_db_factory
from app.core.dependencies import get_current_user
from app.schemas.kiuwan import (
    SdlcCreate, SdlcDelete, SdlcUpdate,
    UpdateCumpleKpmRequest, UpdateSonarKPMRequest,
)
from app.services import kiuwan_service

router = APIRouter(tags=["Kiuwan / SDLC"])
get_serv_db = get_db_factory("octopus_serv")


@router.get("/api/getKiuwanAplication")
async def get_kiuwan_aplication(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Devuelve las aplicaciones Kiuwan almacenadas en BD — equivale a /api/getKiuwanAplication."""
    return await kiuwan_service.get_kiuwan_stored(db)


@router.get("/api/getKiuwanApps")
async def get_kiuwan_apps(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """
    Consulta la API de Kiuwan, sincroniza en BD y devuelve el resultado
    — equivale a /api/getKiuwanApps.
    """
    try:
        return await kiuwan_service.get_kiuwan_applications(db)
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Error comunicando con Kiuwan: {e}")


@router.post("/api/updateCumpleKpm")
async def update_cumple_kpm(
    data: UpdateCumpleKpmRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await kiuwan_service.update_cumple_kpm(db, data.app_name, data.cumple_kpm)


@router.post("/api/updateSonarKPM")
async def update_sonar_kpm(
    data: UpdateSonarKPMRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await kiuwan_service.update_sonar_kpm(db, data.slot_sonarqube, data.cumple_kpm_sonar)


@router.get("/api/obtenerSDLC")
async def obtener_sdlc(
    app: Optional[str] = Query(None),
    id: Optional[int] = Query(None),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await kiuwan_service.obtener_sdlc(db, app=app, id=id)


@router.post("/api/crearAppSDLC")
async def crear_app_sdlc(
    data: SdlcCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await kiuwan_service.crear_app_sdlc(db, data.model_dump())


@router.post("/api/modificarAppSDLC")
async def modificar_app_sdlc(
    id: int = Query(...),
    data: SdlcUpdate = ...,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await kiuwan_service.modificar_app_sdlc(db, id, data.model_dump(exclude_none=True))


@router.post("/api/eliminarAppSDLC")
async def eliminar_app_sdlc(
    data: SdlcDelete,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await kiuwan_service.eliminar_app_sdlc(db, data.id, data.kiuwan_id, data.app)
