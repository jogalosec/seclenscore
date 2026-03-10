"""Servicio de Logs — agrega y procesa los logs de auditoría."""
from typing import Dict, List, Optional
from sqlalchemy.ext.asyncio import AsyncSession

from app.db import octopus_logs as db_logs


async def get_logs_accesos(
    db: AsyncSession,
    fecha_inicio: Optional[str] = None,
    fecha_fin: Optional[str] = None,
    user_id: Optional[int] = None,
    limit: int = 500,
) -> Dict:
    rows = await db_logs.get_logs_activos(db, fecha_inicio, fecha_fin, user_id, limit)
    return {"error": False, "logs": [dict(r) for r in rows], "total": len(rows)}


async def get_logs_relaciones(
    db: AsyncSession,
    fecha_inicio: Optional[str] = None,
    fecha_fin: Optional[str] = None,
) -> Dict:
    rows = await db_logs.get_relation_changes(db, fecha_inicio, fecha_fin)
    return {"error": False, "logs": [dict(r) for r in rows], "total": len(rows)}


async def get_logs_activos_procesados(
    db: AsyncSession,
    fecha_inicio: Optional[str] = None,
    fecha_fin: Optional[str] = None,
) -> Dict:
    """Combina new_activos + deleted_activos + activo_modificado en un único timeline."""
    nuevos    = await db_logs.get_new_activos_log(db, fecha_inicio, fecha_fin)
    eliminados = await db_logs.get_deleted_activos_log(db, fecha_inicio, fecha_fin)
    modificados = await db_logs.get_modified_activos_log(db, fecha_inicio, fecha_fin)

    timeline: List[Dict] = []
    for r in nuevos:
        timeline.append({"tipo": "nuevo", **dict(r)})
    for r in eliminados:
        timeline.append({"tipo": "eliminado", **dict(r)})
    for r in modificados:
        timeline.append({"tipo": "modificado", **dict(r)})

    # Ordenar por fecha descendente
    timeline.sort(key=lambda x: str(x.get("fecha", "")), reverse=True)
    return {"error": False, "logs": timeline, "total": len(timeline)}


async def get_route_logs(
    db: AsyncSession,
    fecha_inicio: Optional[str] = None,
    fecha_fin: Optional[str] = None,
    user_id: Optional[int] = None,
    limit: int = 1000,
) -> Dict:
    rows = await db_logs.get_route_logs(db, fecha_inicio, fecha_fin, user_id, limit)
    return {"error": False, "logs": [dict(r) for r in rows], "total": len(rows)}
