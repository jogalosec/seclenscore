"""Servicio de PAC (Plan de Acciones Correctivas) y Plan de Continuidad."""
from typing import Any, Dict, List, Optional
from sqlalchemy.ext.asyncio import AsyncSession

from app.db import octopus_serv_pac as db_pac
from app.utils.word_generator import generar_pac


# ---------------------------------------------------------------
# PAC
# ---------------------------------------------------------------

async def get_pac_list(db: AsyncSession, activo_id: int) -> Dict:
    rows = await db_pac.get_pac_list(db, activo_id)
    return {"error": False, "pacs": [dict(r) for r in rows]}


async def crear_pac(db: AsyncSession, data: Dict, user_id: Optional[int]) -> Dict:
    pac_id = await db_pac.create_pac(
        db,
        activo_id=data["activo_id"],
        descripcion=data["descripcion"],
        responsable=data.get("responsable"),
        prioridad=data.get("prioridad", "media"),
        fecha_limite=data.get("fecha_limite"),
        user_id=user_id,
        usf_id=data.get("usf_id"),
        pregunta_id=data.get("pregunta_id"),
    )
    return {"error": False, "message": "PAC creado correctamente.", "id": pac_id}


async def get_seguimiento(db: AsyncSession, pac_id: int) -> Dict:
    rows = await db_pac.get_seguimiento_pac(db, pac_id)
    return {"error": False, "seguimiento": [dict(r) for r in rows]}


async def crear_seguimiento(db: AsyncSession, pac_id: int, descripcion: str, estado: str) -> Dict:
    seg_id = await db_pac.create_seguimiento(db, pac_id, descripcion, estado)
    return {"error": False, "message": "Seguimiento creado correctamente.", "id": seg_id}


async def editar_seguimiento(db: AsyncSession, seg_id: int, descripcion: Optional[str], estado: Optional[str]) -> Dict:
    await db_pac.update_seguimiento(db, seg_id, descripcion, estado)
    return {"error": False, "message": "Seguimiento actualizado correctamente."}


async def eliminar_seguimiento(db: AsyncSession, seg_id: int) -> Dict:
    await db_pac.delete_seguimiento(db, seg_id)
    return {"error": False, "message": "Seguimiento eliminado correctamente."}


async def descargar_pac(db: AsyncSession, activo_id: int, activo: Dict) -> bytes:
    """Genera el documento Word del PAC para un activo."""
    pacs_data = await db_pac.get_pac_list(db, activo_id)
    acciones = [dict(r) for r in pacs_data]
    return generar_pac(activo, acciones)


# ---------------------------------------------------------------
# Plan de Continuidad
# ---------------------------------------------------------------

async def get_planes(db: AsyncSession, activo_id: Optional[int] = None) -> Dict:
    rows = await db_pac.get_planes_continuidad(db, activo_id)
    return {"error": False, "planes": [dict(r) for r in rows]}


async def crear_plan(db: AsyncSession, data: Dict, user_id: Optional[int]) -> Dict:
    plan_id = await db_pac.create_plan(
        db,
        activo_id=data["activo_id"],
        nombre=data["nombre"],
        descripcion=data.get("descripcion"),
        fecha_inicio=data.get("fecha_inicio"),
        fecha_fin=data.get("fecha_fin"),
        user_id=user_id,
    )
    return {"error": False, "message": "Plan de continuidad creado correctamente.", "id": plan_id}


async def editar_plan(db: AsyncSession, plan_id: int, campos: Dict) -> Dict:
    await db_pac.update_plan(db, plan_id, campos)
    return {"error": False, "message": "Plan actualizado correctamente."}


async def eliminar_plan(db: AsyncSession, plan_id: int) -> Dict:
    await db_pac.delete_plan(db, plan_id)
    return {"error": False, "message": "Plan eliminado correctamente."}
