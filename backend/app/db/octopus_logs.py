"""Capa de acceso a datos — Logs de auditoría en octopus_logs."""
from typing import Any, Dict, List, Optional
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession


async def get_logs_activos(
    db: AsyncSession,
    fecha_inicio: Optional[str] = None,
    fecha_fin: Optional[str] = None,
    user_id: Optional[int] = None,
    limit: int = 500,
) -> List[Dict]:
    conditions = []
    params: Dict[str, Any] = {"limit": limit}

    if fecha_inicio:
        conditions.append("fecha >= :fecha_inicio")
        params["fecha_inicio"] = fecha_inicio
    if fecha_fin:
        conditions.append("fecha <= :fecha_fin")
        params["fecha_fin"] = fecha_fin
    if user_id:
        conditions.append("user_id = :user_id")
        params["user_id"] = user_id

    where = ("WHERE " + " AND ".join(conditions)) if conditions else ""
    sql = text(f"SELECT * FROM accesos {where} ORDER BY fecha DESC LIMIT :limit")
    return (await db.execute(sql, params)).mappings().all()


async def get_relation_changes(
    db: AsyncSession,
    fecha_inicio: Optional[str] = None,
    fecha_fin: Optional[str] = None,
    limit: int = 500,
) -> List[Dict]:
    params: Dict[str, Any] = {"limit": limit}
    conditions = []
    if fecha_inicio:
        conditions.append("fecha >= :fecha_inicio")
        params["fecha_inicio"] = fecha_inicio
    if fecha_fin:
        conditions.append("fecha <= :fecha_fin")
        params["fecha_fin"] = fecha_fin
    where = ("WHERE " + " AND ".join(conditions)) if conditions else ""
    sql = text(f"SELECT * FROM relation_changes {where} ORDER BY fecha DESC LIMIT :limit")
    return (await db.execute(sql, params)).mappings().all()


async def get_new_activos_log(
    db: AsyncSession,
    fecha_inicio: Optional[str] = None,
    fecha_fin: Optional[str] = None,
) -> List[Dict]:
    params: Dict[str, Any] = {}
    conditions = []
    if fecha_inicio:
        conditions.append("fecha >= :fecha_inicio")
        params["fecha_inicio"] = fecha_inicio
    if fecha_fin:
        conditions.append("fecha <= :fecha_fin")
        params["fecha_fin"] = fecha_fin
    where = ("WHERE " + " AND ".join(conditions)) if conditions else ""
    sql = text(f"SELECT * FROM new_activos {where} ORDER BY fecha DESC")
    return (await db.execute(sql, params)).mappings().all()


async def get_deleted_activos_log(
    db: AsyncSession,
    fecha_inicio: Optional[str] = None,
    fecha_fin: Optional[str] = None,
) -> List[Dict]:
    params: Dict[str, Any] = {}
    conditions = []
    if fecha_inicio:
        conditions.append("fecha >= :fecha_inicio")
        params["fecha_inicio"] = fecha_inicio
    if fecha_fin:
        conditions.append("fecha <= :fecha_fin")
        params["fecha_fin"] = fecha_fin
    where = ("WHERE " + " AND ".join(conditions)) if conditions else ""
    sql = text(f"SELECT * FROM deleted_activos {where} ORDER BY fecha DESC")
    return (await db.execute(sql, params)).mappings().all()


async def get_modified_activos_log(
    db: AsyncSession,
    fecha_inicio: Optional[str] = None,
    fecha_fin: Optional[str] = None,
) -> List[Dict]:
    params: Dict[str, Any] = {}
    conditions = []
    if fecha_inicio:
        conditions.append("fecha >= :fecha_inicio")
        params["fecha_inicio"] = fecha_inicio
    if fecha_fin:
        conditions.append("fecha <= :fecha_fin")
        params["fecha_fin"] = fecha_fin
    where = ("WHERE " + " AND ".join(conditions)) if conditions else ""
    sql = text(f"SELECT * FROM activo_modificado {where} ORDER BY fecha DESC")
    return (await db.execute(sql, params)).mappings().all()


async def get_route_logs(
    db: AsyncSession,
    fecha_inicio: Optional[str] = None,
    fecha_fin: Optional[str] = None,
    user_id: Optional[int] = None,
    limit: int = 1000,
) -> List[Dict]:
    params: Dict[str, Any] = {"limit": limit}
    conditions = []
    if fecha_inicio:
        conditions.append("created_at >= :fecha_inicio")
        params["fecha_inicio"] = fecha_inicio
    if fecha_fin:
        conditions.append("created_at <= :fecha_fin")
        params["fecha_fin"] = fecha_fin
    if user_id:
        conditions.append("user_id = :user_id")
        params["user_id"] = user_id
    where = ("WHERE " + " AND ".join(conditions)) if conditions else ""
    sql = text(f"SELECT * FROM route_update_log {where} ORDER BY created_at DESC LIMIT :limit")
    return (await db.execute(sql, params)).mappings().all()
