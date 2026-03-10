"""Servicio de EVS — Pentest + Revisiones Prisma Cloud."""
from typing import Any, Dict, List, Optional
from sqlalchemy.ext.asyncio import AsyncSession

from app.db import octopus_serv_evs as db_evs
from app.integrations import jira_client


# ---------------------------------------------------------------
# Pentest
# ---------------------------------------------------------------

async def get_pentests(db: AsyncSession, user_id: int, admin: bool = False) -> Dict:
    rows = await db_evs.get_pentests(db, user_id, admin)
    return {"error": False, "pentests": [dict(r) for r in rows]}


async def crear_pentest(
    db: AsyncSession,
    nombre: str,
    descripcion: Optional[str],
    fecha_inicio: Optional[str],
    fecha_fin: Optional[str],
    activos: List[int],
    user_id: Optional[int],
) -> Dict:
    pentest_id = await db_evs.create_pentest(db, nombre, descripcion, fecha_inicio, fecha_fin, user_id)
    if activos:
        await db_evs.insert_activos_pentest(db, pentest_id, activos)
    return {"error": False, "message": "Pentest creado correctamente.", "id": pentest_id}


async def editar_pentest(db: AsyncSession, pentest_id: int, campos: Dict[str, Any]) -> Dict:
    await db_evs.update_pentest(db, pentest_id, campos)
    return {"error": False, "message": "Pentest actualizado correctamente."}


async def cambiar_estado_pentest(db: AsyncSession, pentest_id: int, estado: str) -> Dict:
    await db_evs.cambiar_estado_pentest(db, pentest_id, estado)
    return {"error": False, "message": f"Pentest {estado} correctamente."}


async def eliminar_pentest(db: AsyncSession, pentest_id: int) -> Dict:
    await db_evs.delete_pentest(db, pentest_id)
    return {"error": False, "message": "Pentest eliminado correctamente."}


async def insertar_activos_pentest(db: AsyncSession, pentest_id: int, activos: List[int]) -> Dict:
    await db_evs.insert_activos_pentest(db, pentest_id, activos)
    return {"error": False, "message": f"{len(activos)} activo(s) añadidos al pentest."}


# ---------------------------------------------------------------
# JIRA Issues (pentest)
# ---------------------------------------------------------------

async def get_issues_pentest(db: AsyncSession, pentest_id: int) -> Dict:
    rows = await db_evs.get_issues_pentest(db, pentest_id)
    return {"error": False, "issues": [dict(r) for r in rows]}


async def crear_issue_jira(
    db: AsyncSession,
    pentest_id: Optional[int],
    revision_id: Optional[int],
    titulo: str,
    descripcion: str,
    severidad: str,
    activo_id: Optional[int],
) -> Dict:
    """Crea issue en JIRA y registra la relación en DB."""
    jira_resp = await jira_client.create_issue(titulo, descripcion, severidad)
    issue_key = jira_resp.get("key", "")

    if pentest_id and issue_key:
        await db_evs.insert_issue_pentest(db, pentest_id, issue_key, titulo, severidad, activo_id)

    return {
        "error": False,
        "message": "Issue creado en JIRA correctamente.",
        "issue_key": issue_key,
        "jira_id": jira_resp.get("id"),
    }


async def editar_issue_jira(issue_key: str, titulo: Optional[str], descripcion: Optional[str], severidad: Optional[str]) -> Dict:
    fields: Dict[str, Any] = {}
    if titulo:
        fields["summary"] = titulo
    if descripcion:
        fields["description"] = {
            "type": "doc", "version": 1,
            "content": [{"type": "paragraph", "content": [{"type": "text", "text": descripcion}]}],
        }
    if severidad:
        pmap = {"Critical": "Highest", "High": "High", "Medium": "Medium", "Low": "Low"}
        fields["priority"] = {"name": pmap.get(severidad, "Medium")}

    ok = await jira_client.update_issue(issue_key, fields)
    return {"error": not ok, "message": "Issue actualizado." if ok else "Error actualizando el issue."}


async def obtener_issue_jira(issue_key: str) -> Dict:
    data = await jira_client.get_issue(issue_key)
    return {"error": data is None, "issue": data}


async def listar_issues_jira(jql: Optional[str] = None) -> Dict:
    issues = await jira_client.get_issues_pentest(jql)
    return {"error": False, "issues": issues, "total": len(issues)}


# ---------------------------------------------------------------
# Solicitudes
# ---------------------------------------------------------------

async def get_solicitudes(db: AsyncSession, user_id: int, admin: bool = False) -> Dict:
    rows = await db_evs.get_solicitudes(db, user_id, admin)
    return {"error": False, "solicitudes": [dict(r) for r in rows]}


async def crear_solicitud(db: AsyncSession, data: Dict, user_id: Optional[int]) -> Dict:
    sol_id = await db_evs.create_solicitud(
        db,
        nombre=data["nombre"],
        descripcion=data.get("descripcion"),
        tipo=data.get("tipo", "externo"),
        fecha_solicitada=data.get("fecha_solicitada"),
        contacto=data.get("contacto"),
        user_id=user_id,
    )
    return {"error": False, "message": "Solicitud de pentest creada correctamente.", "id": sol_id}


async def aceptar_solicitud(db: AsyncSession, solicitud_id: int, comentario: Optional[str]) -> Dict:
    await db_evs.aceptar_solicitud(db, solicitud_id, comentario)
    return {"error": False, "message": "Solicitud aceptada correctamente."}


async def rechazar_solicitud(db: AsyncSession, solicitud_id: int, comentario: Optional[str]) -> Dict:
    await db_evs.rechazar_solicitud(db, solicitud_id, comentario)
    return {"error": False, "message": "Solicitud rechazada."}


# ---------------------------------------------------------------
# Revisiones Prisma
# ---------------------------------------------------------------

async def get_revisiones(db: AsyncSession, user_id: int, admin: bool = False) -> Dict:
    rows = await db_evs.get_revisiones(db, user_id, admin)
    return {"error": False, "revisiones": [dict(r) for r in rows]}


async def crear_revision(
    db: AsyncSession, nombre: str, descripcion: Optional[str], activos: List[int], user_id: Optional[int]
) -> Dict:
    rev_id = await db_evs.create_revision(db, nombre, descripcion, user_id)
    if activos:
        await db_evs.insert_activos_revision(db, rev_id, activos)
    return {"error": False, "message": "Revisión creada correctamente.", "id": rev_id}


async def cambiar_estado_revision(db: AsyncSession, revision_id: int, estado: str) -> Dict:
    await db_evs.cambiar_estado_revision(db, revision_id, estado)
    return {"error": False, "message": f"Revisión {estado} correctamente."}


async def eliminar_revision(db: AsyncSession, revision_id: int) -> Dict:
    await db_evs.delete_revision(db, revision_id)
    return {"error": False, "message": "Revisión eliminada correctamente."}


async def get_alertas_revision(db: AsyncSession, revision_id: int) -> Dict:
    rows = await db_evs.get_alertas_revision(db, revision_id)
    return {"error": False, "alertas": [dict(r) for r in rows]}


async def asignar_alerta(db: AsyncSession, data: Dict) -> Dict:
    await db_evs.assign_alerta_revision(
        db,
        revision_id=data["revision_id"],
        id_alert=data["id_alert"],
        id_policy=data.get("id_policy"),
        resource_id=data.get("resource_id"),
        resource_name=data.get("resource_name"),
    )
    return {"error": False, "message": "Alerta asignada a la revisión."}


async def desasignar_alerta(db: AsyncSession, revision_id: int, id_alert: str) -> Dict:
    await db_evs.unassign_alerta_revision(db, revision_id, id_alert)
    return {"error": False, "message": "Alerta desasignada de la revisión."}
