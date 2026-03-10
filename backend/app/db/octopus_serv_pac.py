"""Capa de acceso a datos — PAC y Plan de Continuidad en octopus_serv."""
from typing import Any, Dict, List, Optional
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession


# ---------------------------------------------------------------
# PAC — Plan de Acciones Correctivas
# ---------------------------------------------------------------

async def get_pac_list(db: AsyncSession, activo_id: int) -> List[Dict]:
    sql = text("""
        SELECT p.*, u.email AS responsable_email
        FROM plan p
        LEFT JOIN octopus_users.users u ON u.id = p.user_id
        WHERE p.activo_id = :aid AND p.tipo = 'pac'
        ORDER BY p.id DESC
    """)
    return (await db.execute(sql, {"aid": activo_id})).mappings().all()


async def get_pac_by_id(db: AsyncSession, pac_id: int) -> Optional[Dict]:
    row = (await db.execute(text("SELECT * FROM plan WHERE id = :id AND tipo = 'pac'"), {"id": pac_id})).mappings().first()
    return dict(row) if row else None


async def create_pac(
    db: AsyncSession, activo_id: int, descripcion: str,
    responsable: Optional[str], prioridad: str,
    fecha_limite: Optional[str], user_id: Optional[int],
    usf_id: Optional[int] = None, pregunta_id: Optional[int] = None,
) -> int:
    result = await db.execute(
        text("""
            INSERT INTO plan
                (activo_id, descripcion, responsable, prioridad, fecha_limite, user_id, tipo, usf_id, pregunta_id)
            VALUES (:aid, :desc, :resp, :prio, :fl, :uid, 'pac', :usf, :preg)
        """),
        {"aid": activo_id, "desc": descripcion, "resp": responsable,
         "prio": prioridad, "fl": fecha_limite, "uid": user_id,
         "usf": usf_id, "preg": pregunta_id},
    )
    await db.commit()
    return result.lastrowid


async def get_seguimiento_pac(db: AsyncSession, pac_id: int) -> List[Dict]:
    sql = text("SELECT * FROM seguimientopac WHERE pac_id = :id ORDER BY id DESC")
    return (await db.execute(sql, {"id": pac_id})).mappings().all()


async def create_seguimiento(
    db: AsyncSession, pac_id: int, descripcion: str, estado: str
) -> int:
    result = await db.execute(
        text("""
            INSERT INTO seguimientopac (pac_id, descripcion, estado)
            VALUES (:pid, :desc, :estado)
        """),
        {"pid": pac_id, "desc": descripcion, "estado": estado},
    )
    await db.commit()
    return result.lastrowid


async def update_seguimiento(
    db: AsyncSession, seg_id: int, descripcion: Optional[str], estado: Optional[str]
) -> None:
    campos: Dict[str, Any] = {}
    if descripcion is not None:
        campos["descripcion"] = descripcion
    if estado is not None:
        campos["estado"] = estado
    if not campos:
        return
    set_clause = ", ".join(f"{k} = :{k}" for k in campos)
    await db.execute(text(f"UPDATE seguimientopac SET {set_clause} WHERE id = :id"), {"id": seg_id, **campos})
    await db.commit()


async def delete_seguimiento(db: AsyncSession, seg_id: int) -> None:
    await db.execute(text("DELETE FROM seguimientopac WHERE id = :id"), {"id": seg_id})
    await db.commit()


# ---------------------------------------------------------------
# Plan de Continuidad
# ---------------------------------------------------------------

async def get_planes_continuidad(db: AsyncSession, activo_id: Optional[int] = None) -> List[Dict]:
    if activo_id:
        sql = text("SELECT * FROM plan WHERE tipo = 'continuidad' AND activo_id = :aid ORDER BY id DESC")
        return (await db.execute(sql, {"aid": activo_id})).mappings().all()
    sql = text("""
        SELECT p.*, a.nombre AS activo_nombre
        FROM plan p
        LEFT JOIN activos a ON a.id = p.activo_id
        WHERE p.tipo = 'continuidad'
        ORDER BY p.id DESC
    """)
    return (await db.execute(sql)).mappings().all()


async def get_plan_by_id(db: AsyncSession, plan_id: int) -> Optional[Dict]:
    row = (await db.execute(text("SELECT * FROM plan WHERE id = :id"), {"id": plan_id})).mappings().first()
    return dict(row) if row else None


async def create_plan(
    db: AsyncSession, activo_id: int, nombre: str, descripcion: Optional[str],
    fecha_inicio: Optional[str], fecha_fin: Optional[str], user_id: Optional[int]
) -> int:
    result = await db.execute(
        text("""
            INSERT INTO plan (activo_id, nombre, descripcion, fecha_inicio, fecha_fin, user_id, tipo)
            VALUES (:aid, :nombre, :desc, :fi, :ff, :uid, 'continuidad')
        """),
        {"aid": activo_id, "nombre": nombre, "desc": descripcion,
         "fi": fecha_inicio, "ff": fecha_fin, "uid": user_id},
    )
    await db.commit()
    return result.lastrowid


async def update_plan(db: AsyncSession, plan_id: int, campos: Dict[str, Any]) -> None:
    allowed = {"nombre", "descripcion", "fecha_inicio", "fecha_fin"}
    safe = {k: v for k, v in campos.items() if k in allowed and v is not None}
    if not safe:
        return
    set_clause = ", ".join(f"{k} = :{k}" for k in safe)
    await db.execute(text(f"UPDATE plan SET {set_clause} WHERE id = :id"), {"id": plan_id, **safe})
    await db.commit()


async def delete_plan(db: AsyncSession, plan_id: int) -> None:
    await db.execute(text("DELETE FROM seguimientopac WHERE pac_id = :id"), {"id": plan_id})
    await db.execute(text("DELETE FROM plan WHERE id = :id"), {"id": plan_id})
    await db.commit()
