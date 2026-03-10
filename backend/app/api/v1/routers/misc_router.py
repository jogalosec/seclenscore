"""Router FastAPI — Misceláneos Sprint 7.

Cubre: Organizaciones, Direcciones, Áreas, Riesgos, OSA,
Suscripciones, Dashboard ERS/GBU y Email.
"""
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import get_db_factory
from app.core.dependencies import get_current_user
from app.schemas.kiuwan import (
    SendEmailRequest,
    SuscripcionRelacionCreate,
    SuscripcionRelacionDelete,
    SuscripcionRelacionEdit,
)
from app.services import misc_service

router = APIRouter(tags=["Misceláneos"])
get_serv_db = get_db_factory("octopus_serv")
get_new_db  = get_db_factory("octopus_new")


# ── Organizaciones / Direcciones / Áreas ─────────────────────────────────────

@router.get("/api/getOrganizaciones")
async def get_organizaciones(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Devuelve activos de tipo Organización (activo_id=94)."""
    return await misc_service.get_organizaciones(db)


@router.get("/api/getDirecciones")
async def get_direcciones(
    organizacion_id: int = Query(...),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Hijos de tipo Dirección para una organización dada."""
    return await misc_service.get_direcciones(db, organizacion_id)


@router.get("/api/getAreas")
async def get_areas(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Devuelve activos de tipo Área (activo_id=123)."""
    return await misc_service.get_areas(db)


# ── Riesgos ───────────────────────────────────────────────────────────────────

@router.get("/api/getRiesgos")
async def get_riesgos(
    serv_id: int = Query(...),
    sistemas: Optional[str] = Query(None, description="IDs de sistemas separados por coma"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """
    Calcula vector de riesgos para un servicio + sus sistemas.
    `sistemas` es una lista de IDs separados por coma, ej: 1,2,3
    """
    sistema_ids: list[int] = []
    if sistemas:
        try:
            sistema_ids = [int(s.strip()) for s in sistemas.split(",") if s.strip()]
        except ValueError:
            raise HTTPException(status_code=422, detail="sistemas debe contener IDs enteros")
    return await misc_service.get_riesgos(db, serv_id, sistema_ids)


@router.get("/api/getRiesgosServicio")
async def get_riesgos_servicio(
    activo_id: int = Query(...),
    fecha: Optional[str] = Query(None),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Riesgos de un servicio concreto filtrados por fecha de evaluación."""
    return await misc_service.get_riesgos_servicio(db, activo_id, fecha or "null")


# ── OSA ───────────────────────────────────────────────────────────────────────

@router.get("/api/getOsaByType")
async def get_osa_by_type(
    tipo: str = Query(...),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """OSA filtrados por tipo."""
    return await misc_service.get_osa_by_type(db, tipo)


@router.get("/api/getOsaEvalByRevision")
async def get_osa_eval_by_revision(
    revision_id: int = Query(...),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Evaluación OSA de una revisión concreta."""
    result = await misc_service.get_osa_eval_by_revision(db, revision_id)
    if result is None:
        raise HTTPException(status_code=404, detail="Evaluación OSA no encontrada")
    return result


# ── Suscripciones ─────────────────────────────────────────────────────────────

@router.get("/api/getRelacionSuscripcion")
async def get_relacion_suscripcion(
    id_suscripcion: str = Query(...),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Comprueba si una suscripción tiene activo relacionado."""
    return await misc_service.get_relacion_suscripcion(db, id_suscripcion)


@router.get("/api/getSuscriptionRelations")
async def get_suscription_relations(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Lista todas las relaciones suscripción-activo."""
    return await misc_service.get_suscription_relations(db)


@router.post("/api/insertSuscriptionRelations")
async def insert_suscription_relations(
    data: SuscripcionRelacionCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Crea nuevas relaciones suscripción-activo."""
    return await misc_service.insert_suscription_relation(
        db, data.id_activo, data.subscriptions, data.subscriptionNames or []
    )


@router.post("/api/deleteSuscriptionRelations")
async def delete_suscription_relations(
    data: SuscripcionRelacionDelete,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Elimina una relación suscripción-activo."""
    return await misc_service.delete_suscription_relation(db, data.suscription_id)


@router.post("/api/editSuscriptionRelations")
async def edit_suscription_relations(
    data: SuscripcionRelacionEdit,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Edita una relación suscripción-activo existente."""
    return await misc_service.edit_suscription_relation(db, data.id, data.model_dump(exclude_none=True))


# ── Dashboard ERS + GBU ───────────────────────────────────────────────────────

@router.get("/api/getDashboardErs")
async def get_dashboard_ers(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Dashboard de evaluaciones ERS (activos expuestos evaluados)."""
    return await misc_service.get_dashboard_ers(db)


@router.get("/api/getDashboardGBU")
async def get_dashboard_gbu(
    gbu_id: int = Query(27384, description="ID raíz de la unidad GBU"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Árbol GBU — descendientes de la unidad raíz."""
    return await misc_service.get_dashboard_gbu(db, gbu_id)


# ── Email ─────────────────────────────────────────────────────────────────────

@router.post("/api/sendEmail")
async def send_email(
    data: SendEmailRequest,
    current_user: dict = Depends(get_current_user),
):
    """Envía un email con la plantilla corporativa."""
    return await misc_service.send_email(
        data.to, data.asunto, data.body, data.alternbody or ""
    )
