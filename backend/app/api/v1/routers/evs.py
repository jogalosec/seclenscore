"""Router FastAPI — EVS (Pentest + Solicitudes + Revisiones Prisma)."""
from typing import List, Optional
from fastapi import APIRouter, Depends, HTTPException, Query, Response
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import get_db_factory
from app.core.dependencies import get_current_user
from app.schemas.evs import (
    AlertaAsignRequest, AlertaDesasignRequest,
    DismissPrismaAlertRequest, IssueCreate, IssueUpdate,
    PentestAddActivosRequest, PentestAsignRequest,
    PentestCreate, PentestStatusRequest, PentestUpdate,
    RevisionCreate, RevisionAddActivosRequest,
    SolicitudPentestAction, SolicitudPentestCreate,
)
from app.services import evs_service, jira_service, prisma_service

router = APIRouter(tags=["EVS"])
get_serv_db = get_db_factory("octopus_serv")


# ── Pentest ──────────────────────────────────────────────────

@router.get("/api/obtainActivosPentest")
async def get_pentests(current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    admin = current_user.get("additionalAccess", False)
    return await evs_service.get_pentests(db, current_user["id"], admin)


@router.get("/api/getInfoPentestByID")
async def get_pentest_by_id(id: int = Query(...), current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    from app.db.octopus_serv_evs import get_pentest_by_id
    data = await get_pentest_by_id(db, id)
    if not data:
        raise HTTPException(status_code=404, detail="Pentest no encontrado")
    return {"error": False, "pentest": data}


@router.post("/api/crearPentest")
async def crear_pentest(data: PentestCreate, current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.crear_pentest(db, data.nombre, data.descripcion, data.fecha_inicio, data.fecha_fin, data.activos, current_user["id"])


@router.post("/api/editPentest")
async def edit_pentest(data: PentestUpdate, current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.editar_pentest(db, data.id, data.model_dump(exclude_none=True, exclude={"id"}))


@router.get("/api/cerrarPentest")
async def cerrar_pentest(id: int = Query(...), current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.cambiar_estado_pentest(db, id, "cerrado")


@router.get("/api/reabrirPentest")
async def reabrir_pentest(id: int = Query(...), current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.cambiar_estado_pentest(db, id, "abierto")


@router.get("/api/eliminarPentest")
async def eliminar_pentest(id: int = Query(...), current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.eliminar_pentest(db, id)


@router.post("/api/insertActivosPentest")
async def insert_activos_pentest(data: PentestAddActivosRequest, current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.insertar_activos_pentest(db, data.pentest_id, data.activos)


@router.post("/api/asignPentester")
async def asign_pentester(data: PentestAsignRequest, current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    from app.db.octopus_serv_evs import asignar_pentester
    await asignar_pentester(db, data.pentest_id, data.user_id)
    return {"error": False, "message": "Pentester asignado correctamente."}


@router.post("/api/exportarPentestsExcel")
async def exportar_pentests_excel(current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    from app.db.octopus_serv_evs import get_pentests as db_get_pentests
    from app.utils.excel_generator import generar_excel_generico
    admin = current_user.get("additionalAccess", False)
    rows = await db_get_pentests(db, current_user["id"], admin)
    datos = [dict(r) for r in rows]
    xlsx = generar_excel_generico(datos, ["ID", "Nombre", "Estado", "Fecha inicio", "Fecha fin"], ["id", "nombre", "estado", "fecha_inicio", "fecha_fin"], "Pentests")
    return Response(content=xlsx, media_type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", headers={"Content-Disposition": "attachment; filename=pentests.xlsx"})


# ── Issues JIRA ───────────────────────────────────────────────

@router.get("/api/obtenerIssuesPentest")
async def listar_issues(jql: Optional[str] = Query(None), current_user: dict = Depends(get_current_user)):
    return await jira_service.listar_issues(jql)


@router.get("/api/obtainPentestIssue")
async def get_issue(key: str = Query(...), current_user: dict = Depends(get_current_user)):
    return await jira_service.obtener_issue(key)


@router.post("/api/newIssue")
async def new_issue(data: IssueCreate, current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.crear_issue_jira(db, data.pentest_id, data.revision_id, data.titulo, data.descripcion, data.severidad, data.activo_id)


@router.post("/api/editIssue")
async def edit_issue(data: IssueUpdate, current_user: dict = Depends(get_current_user)):
    campos = {}
    if data.titulo:
        campos["summary"] = data.titulo
    if data.severidad:
        pmap = {"Critical": "Highest", "High": "High", "Medium": "Medium", "Low": "Low"}
        campos["priority"] = {"name": pmap.get(data.severidad, "Medium")}
    return await jira_service.editar_issue(data.issue_key, campos)


@router.post("/api/createIncident")
async def create_incident(data: IssueCreate, current_user: dict = Depends(get_current_user)):
    return await jira_service.crear_incidente(data.titulo, data.descripcion)


# ── Solicitudes ───────────────────────────────────────────────

@router.get("/api/getSolicitudesPentest")
async def get_solicitudes(current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    admin = current_user.get("additionalAccess", False)
    return await evs_service.get_solicitudes(db, current_user["id"], admin)


@router.post("/api/pentestRequest")
async def pentest_request(data: SolicitudPentestCreate, current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.crear_solicitud(db, data.model_dump(), current_user["id"])


@router.post("/api/aceptarSolicitudPentest")
async def aceptar_solicitud(data: SolicitudPentestAction, current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.aceptar_solicitud(db, data.id, data.comentario)


@router.post("/api/rechazarSolicitudPentest")
async def rechazar_solicitud(data: SolicitudPentestAction, current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.rechazar_solicitud(db, data.id, data.comentario)


# ── Revisiones (Prisma Cloud) ─────────────────────────────────

@router.get("/api/obtainActivosRevision")
async def get_revisiones(current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    admin = current_user.get("additionalAccess", False)
    return await evs_service.get_revisiones(db, current_user["id"], admin)


@router.get("/api/getInfoRevisionByID")
async def get_revision_by_id(id: int = Query(...), current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    from app.db.octopus_serv_evs import get_revision_by_id
    data = await get_revision_by_id(db, id)
    if not data:
        raise HTTPException(status_code=404, detail="Revisión no encontrada")
    return {"error": False, "revision": data}


@router.post("/api/crearRevision")
async def crear_revision(data: RevisionCreate, current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.crear_revision(db, data.nombre, data.descripcion, data.activos, current_user["id"])


@router.post("/api/crearRevisionSinActivos")
async def crear_revision_sin_activos(data: RevisionCreate, current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.crear_revision(db, data.nombre, data.descripcion, [], current_user["id"])


@router.get("/api/cerrarRevision")
async def cerrar_revision(id: int = Query(...), current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.cambiar_estado_revision(db, id, "cerrado")


@router.get("/api/reabrirRevision")
async def reabrir_revision(id: int = Query(...), current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.cambiar_estado_revision(db, id, "abierto")


@router.get("/api/eliminarRevision")
async def eliminar_revision(id: int = Query(...), current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.eliminar_revision(db, id)


@router.get("/api/getAlertasRevisionByID")
async def get_alertas_revision(id: int = Query(...), current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.get_alertas_revision(db, id)


@router.post("/api/assignPrismaAlertToReview")
async def assign_alerta(data: AlertaAsignRequest, current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.asignar_alerta(db, data.model_dump())


@router.post("/api/unassignPrismaAlertToReview")
async def unassign_alerta(data: AlertaDesasignRequest, current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    return await evs_service.desasignar_alerta(db, data.revision_id, data.id_alert)


# ── Prisma Cloud ──────────────────────────────────────────────

@router.get("/api/getPrismaCloud")
async def get_prisma_cloud(current_user: dict = Depends(get_current_user)):
    return await prisma_service.get_clouds()


@router.get("/api/getPrismaSusInfo")
async def get_sus_info(account_id: str = Query(...), current_user: dict = Depends(get_current_user)):
    return await prisma_service.get_cloud_info(account_id)


@router.get("/api/getPrismaCloudFromTenant")
async def get_from_tenant(tenant_id: str = Query(...), current_user: dict = Depends(get_current_user)):
    return await prisma_service.get_clouds_from_tenant(tenant_id)


@router.get("/api/getPrismaAlertByCloud")
async def get_alertas_cloud(account_id: str = Query(...), estado: str = Query("open"), limit: int = Query(100), offset: int = Query(0), current_user: dict = Depends(get_current_user)):
    return await prisma_service.get_alertas_cloud(account_id, estado, limit, offset)


@router.get("/api/getPrismaAlertInfo")
async def get_alerta_info(alert_id: str = Query(...), current_user: dict = Depends(get_current_user)):
    return await prisma_service.get_alerta_info(alert_id)


@router.get("/api/getPrismaAlertsByPolicy")
async def get_alertas_policy(policy_id: str = Query(...), estado: str = Query("open"), current_user: dict = Depends(get_current_user)):
    return await prisma_service.get_alertas_by_policy(policy_id, estado)


@router.post("/api/dismissPrismaAlert")
async def dismiss_alerta(data: DismissPrismaAlertRequest, current_user: dict = Depends(get_current_user)):
    return await prisma_service.dismiss_alerta(data.alert_id, data.razon)


@router.post("/api/reopenPrismaAlert")
async def reopen_alerta(alert_id: str, current_user: dict = Depends(get_current_user)):
    return await prisma_service.reopen_alerta(alert_id)
