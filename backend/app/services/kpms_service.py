"""
Servicio de KPMs — lógica de negocio para Métricas, Madurez y CSIRT.
"""
from typing import Any, Dict, List, Optional

from sqlalchemy.ext.asyncio import AsyncSession

from app.db import octopus_kpms as db_kpms
from app.schemas.kpm import TipoKPM


async def get_kpms_usuario(
    db: AsyncSession, user_id: int, tipo: TipoKPM, admin: bool = False
) -> Dict:
    """Devuelve los KPMs del usuario para el tipo indicado."""
    if tipo == "metricas":
        rows = await db_kpms.get_metricas_by_user(db, user_id, admin)
    elif tipo == "madurez":
        rows = await db_kpms.get_madurez_by_user(db, user_id, admin)
    else:  # csirt
        rows = await db_kpms.get_csirt_by_user(db, user_id, admin)

    return {
        "error": False,
        "kpms": [dict(r) for r in rows],
        "message": f"KPMs de tipo '{tipo}' obtenidos correctamente.",
    }


async def get_ultimo_reporte(
    db: AsyncSession, user_id: int, tipo: TipoKPM
) -> Dict:
    rows = await db_kpms.get_last_report_kpms(db, user_id, tipo)
    return {
        "error": False,
        "reporte": [dict(r) for r in rows],
    }


async def get_reporters(
    db: AsyncSession,
    user_id: int,
    additional_access: bool = False,
    csirt: bool = False,
) -> Dict:
    if csirt:
        rows = await db_kpms.get_report_as_csirt(db)
    else:
        rows = await db_kpms.get_report_as(db, user_id, additional_access)
    return {
        "error": False,
        "reporters": [dict(r) for r in rows],
    }


async def get_preguntas_formulario(db: AsyncSession, tipo: Optional[str] = None) -> Dict:
    if tipo == "csirt":
        rows = await db_kpms.get_preguntas_kpms_csirt(db)
    elif tipo == "all":
        rows = await db_kpms.get_all_preguntas_kpms(db)
    else:
        rows = await db_kpms.get_preguntas_kpms_formulario(db)
    return {
        "error": False,
        "preguntas": [dict(r) for r in rows],
    }


async def bloquear_kpms(
    db: AsyncSession, tipo: TipoKPM, ids: List[int], bloquear: bool = True
) -> Dict:
    if bloquear:
        await db_kpms.lock_kpms(db, tipo, ids)
        msg = f"{len(ids)} KPM(s) bloqueados correctamente."
    else:
        await db_kpms.unlock_kpms(db, tipo, ids)
        msg = f"{len(ids)} KPM(s) desbloqueados correctamente."
    return {"error": False, "message": msg}


async def eliminar_kpms(db: AsyncSession, tipo: TipoKPM, ids: List[int]) -> Dict:
    await db_kpms.del_kpms(db, tipo, ids)
    return {"error": False, "message": f"{len(ids)} KPM(s) eliminados correctamente."}


async def editar_kpm(
    db: AsyncSession,
    tipo: TipoKPM,
    kpm_id: int,
    campos: Dict[str, Any],
    user_id: Optional[int] = None,
) -> Dict:
    await db_kpms.edit_kpm(db, tipo, kpm_id, campos, user_id)
    return {"error": False, "message": "KPM actualizado correctamente."}


async def actualizar_definicion_kpm(
    db: AsyncSession,
    kpm_id: int,
    nombre: Optional[str],
    descripcion_larga: Optional[str],
    descripcion_corta: Optional[str],
    grupo: Optional[str],
) -> Dict:
    await db_kpms.update_kpm_definicion(
        db, kpm_id, nombre, descripcion_larga, descripcion_corta, grupo
    )
    return {"error": False, "message": "Definición KPM actualizada correctamente."}


async def crear_reporter(
    db: AsyncSession, user_id: int, activo_id: int
) -> Dict:
    await db_kpms.crear_reporter_kpms(db, user_id, activo_id)
    return {"error": False, "message": "Reporter KPM creado correctamente."}


async def eliminar_reporter(db: AsyncSession, relacion_id: int) -> Dict:
    await db_kpms.delete_relacion_reporter(db, relacion_id)
    return {"error": False, "message": "Reporter KPM eliminado correctamente."}


async def get_reporters_kpms(
    db: AsyncSession,
    user_id: int,
    additional_access: bool = False,
) -> Dict:
    rows = await db_kpms.get_reporters_kpms(db, additional_access, user_id)
    return {
        "error": False,
        "reporters": [dict(r) for r in rows],
    }
