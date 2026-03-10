"""
Capa de acceso a datos — EVS (Pentest + Revisiones) en octopus_serv.
"""
from typing import Any, Dict, List, Optional
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession


# ---------------------------------------------------------------
# Pentest
# ---------------------------------------------------------------

async def get_pentests(db: AsyncSession, user_id: Optional[int] = None, admin: bool = False) -> List[Dict]:
    if admin:
        sql = text("""
            SELECT p.*, u.email AS responsable_email
            FROM pentest p
            LEFT JOIN octopus_users.users u ON u.id = p.user_id
            ORDER BY p.id DESC
        """)
        return (await db.execute(sql)).mappings().all()
    sql = text("""
        SELECT p.*, u.email AS responsable_email
        FROM pentest p
        LEFT JOIN octopus_users.users u ON u.id = p.user_id
        WHERE p.user_id = :uid
        ORDER BY p.id DESC
    """)
    return (await db.execute(sql, {"uid": user_id})).mappings().all()


async def get_pentest_by_id(db: AsyncSession, pentest_id: int) -> Optional[Dict]:
    sql = text("SELECT * FROM pentest WHERE id = :id")
    row = (await db.execute(sql, {"id": pentest_id})).mappings().first()
    return dict(row) if row else None


async def create_pentest(
    db: AsyncSession, nombre: str, descripcion: Optional[str],
    fecha_inicio: Optional[str], fecha_fin: Optional[str], user_id: Optional[int]
) -> int:
    result = await db.execute(
        text("""
            INSERT INTO pentest (nombre, descripcion, fecha_inicio, fecha_fin, user_id, estado)
            VALUES (:nombre, :desc, :fi, :ff, :uid, 'abierto')
        """),
        {"nombre": nombre, "desc": descripcion, "fi": fecha_inicio, "ff": fecha_fin, "uid": user_id},
    )
    await db.commit()
    return result.lastrowid


async def update_pentest(db: AsyncSession, pentest_id: int, campos: Dict[str, Any]) -> None:
    allowed = {"nombre", "descripcion", "fecha_inicio", "fecha_fin"}
    safe = {k: v for k, v in campos.items() if k in allowed and v is not None}
    if not safe:
        return
    set_clause = ", ".join(f"{k} = :{k}" for k in safe)
    await db.execute(text(f"UPDATE pentest SET {set_clause} WHERE id = :id"), {"id": pentest_id, **safe})
    await db.commit()


async def cambiar_estado_pentest(db: AsyncSession, pentest_id: int, estado: str) -> None:
    await db.execute(
        text("UPDATE pentest SET estado = :estado WHERE id = :id"),
        {"estado": estado, "id": pentest_id},
    )
    await db.commit()


async def delete_pentest(db: AsyncSession, pentest_id: int) -> None:
    await db.execute(text("DELETE FROM pentest_has_activos WHERE pentest_id = :id"), {"id": pentest_id})
    await db.execute(text("DELETE FROM pentest_has_vuln WHERE pentest_id = :id"), {"id": pentest_id})
    await db.execute(text("DELETE FROM pentest_has_comentarios WHERE pentest_id = :id"), {"id": pentest_id})
    await db.execute(text("DELETE FROM pentest WHERE id = :id"), {"id": pentest_id})
    await db.commit()


async def insert_activos_pentest(db: AsyncSession, pentest_id: int, activos: List[int]) -> None:
    for activo_id in activos:
        await db.execute(
            text("INSERT IGNORE INTO pentest_has_activos (pentest_id, activo_id) VALUES (:pid, :aid)"),
            {"pid": pentest_id, "aid": activo_id},
        )
    await db.commit()


async def get_activos_pentest(db: AsyncSession, pentest_id: int) -> List[Dict]:
    sql = text("""
        SELECT a.id, a.nombre, a.tipo
        FROM pentest_has_activos pha
        JOIN activos a ON a.id = pha.activo_id
        WHERE pha.pentest_id = :pid
    """)
    return (await db.execute(sql, {"pid": pentest_id})).mappings().all()


async def get_issues_pentest(db: AsyncSession, pentest_id: int) -> List[Dict]:
    sql = text("""
        SELECT * FROM pentest_has_vuln WHERE pentest_id = :pid ORDER BY id DESC
    """)
    return (await db.execute(sql, {"pid": pentest_id})).mappings().all()


async def insert_issue_pentest(
    db: AsyncSession, pentest_id: int, issue_key: str,
    titulo: str, severidad: str, activo_id: Optional[int] = None
) -> None:
    await db.execute(
        text("""
            INSERT INTO pentest_has_vuln (pentest_id, issue_key, titulo, severidad, activo_id)
            VALUES (:pid, :key, :titulo, :sev, :aid)
        """),
        {"pid": pentest_id, "key": issue_key, "titulo": titulo, "sev": severidad, "aid": activo_id},
    )
    await db.commit()


async def asignar_pentester(db: AsyncSession, pentest_id: int, user_id: int) -> None:
    await db.execute(
        text("UPDATE pentest SET pentester_id = :uid WHERE id = :id"),
        {"uid": user_id, "id": pentest_id},
    )
    await db.commit()


# ---------------------------------------------------------------
# Solicitudes de pentest
# ---------------------------------------------------------------

async def get_solicitudes(db: AsyncSession, user_id: Optional[int] = None, admin: bool = False) -> List[Dict]:
    if admin:
        sql = text("""
            SELECT pr.*, u.email AS solicitante_email
            FROM pentest_request pr
            LEFT JOIN octopus_users.users u ON u.id = pr.user_id
            ORDER BY pr.id DESC
        """)
        return (await db.execute(sql)).mappings().all()
    sql = text("""
        SELECT pr.*, u.email AS solicitante_email
        FROM pentest_request pr
        LEFT JOIN octopus_users.users u ON u.id = pr.user_id
        WHERE pr.user_id = :uid
        ORDER BY pr.id DESC
    """)
    return (await db.execute(sql, {"uid": user_id})).mappings().all()


async def create_solicitud(
    db: AsyncSession, nombre: str, descripcion: Optional[str],
    tipo: str, fecha_solicitada: Optional[str], contacto: Optional[str], user_id: Optional[int]
) -> int:
    result = await db.execute(
        text("""
            INSERT INTO pentest_request (nombre, descripcion, tipo, fecha_solicitada, contacto, user_id, estado)
            VALUES (:nombre, :desc, :tipo, :fecha, :contacto, :uid, 'pendiente')
        """),
        {"nombre": nombre, "desc": descripcion, "tipo": tipo,
         "fecha": fecha_solicitada, "contacto": contacto, "uid": user_id},
    )
    await db.commit()
    return result.lastrowid


async def aceptar_solicitud(db: AsyncSession, solicitud_id: int, comentario: Optional[str]) -> None:
    await db.execute(
        text("UPDATE pentest_request SET estado = 'aceptada', comentario_respuesta = :com WHERE id = :id"),
        {"com": comentario, "id": solicitud_id},
    )
    await db.commit()


async def rechazar_solicitud(db: AsyncSession, solicitud_id: int, comentario: Optional[str]) -> None:
    await db.execute(
        text("UPDATE pentest_request SET estado = 'rechazada', comentario_respuesta = :com WHERE id = :id"),
        {"com": comentario, "id": solicitud_id},
    )
    await db.commit()


# ---------------------------------------------------------------
# Revisiones (Prisma Cloud reviews)
# ---------------------------------------------------------------

async def get_revisiones(db: AsyncSession, user_id: Optional[int] = None, admin: bool = False) -> List[Dict]:
    if admin:
        sql = text("SELECT * FROM revisiones ORDER BY id DESC")
        return (await db.execute(sql)).mappings().all()
    sql = text("SELECT * FROM revisiones WHERE user_id = :uid ORDER BY id DESC")
    return (await db.execute(sql, {"uid": user_id})).mappings().all()


async def get_revision_by_id(db: AsyncSession, revision_id: int) -> Optional[Dict]:
    sql = text("SELECT * FROM revisiones WHERE id = :id")
    row = (await db.execute(sql, {"id": revision_id})).mappings().first()
    return dict(row) if row else None


async def create_revision(
    db: AsyncSession, nombre: str, descripcion: Optional[str], user_id: Optional[int]
) -> int:
    result = await db.execute(
        text("""
            INSERT INTO revisiones (nombre, descripcion, user_id, estado)
            VALUES (:nombre, :desc, :uid, 'abierto')
        """),
        {"nombre": nombre, "desc": descripcion, "uid": user_id},
    )
    await db.commit()
    return result.lastrowid


async def cambiar_estado_revision(db: AsyncSession, revision_id: int, estado: str) -> None:
    await db.execute(
        text("UPDATE revisiones SET estado = :estado WHERE id = :id"),
        {"estado": estado, "id": revision_id},
    )
    await db.commit()


async def delete_revision(db: AsyncSession, revision_id: int) -> None:
    await db.execute(text("DELETE FROM revisiones_has_activos WHERE revision_id = :id"), {"id": revision_id})
    await db.execute(text("DELETE FROM revisiones_has_vuln WHERE revision_id = :id"), {"id": revision_id})
    await db.execute(text("DELETE FROM revisiones_has_osa WHERE revision_id = :id"), {"id": revision_id})
    await db.execute(text("DELETE FROM revisiones WHERE id = :id"), {"id": revision_id})
    await db.commit()


async def insert_activos_revision(db: AsyncSession, revision_id: int, activos: List[int]) -> None:
    for activo_id in activos:
        await db.execute(
            text("INSERT IGNORE INTO revisiones_has_activos (revision_id, activo_id) VALUES (:rid, :aid)"),
            {"rid": revision_id, "aid": activo_id},
        )
    await db.commit()


async def get_alertas_revision(db: AsyncSession, revision_id: int) -> List[Dict]:
    sql = text("SELECT * FROM revisiones_has_vuln WHERE revision_id = :rid ORDER BY id DESC")
    return (await db.execute(sql, {"rid": revision_id})).mappings().all()


async def assign_alerta_revision(
    db: AsyncSession, revision_id: int, id_alert: str,
    id_policy: Optional[str], resource_id: Optional[str], resource_name: Optional[str]
) -> None:
    await db.execute(
        text("""
            INSERT IGNORE INTO revisiones_has_vuln
                (revision_id, id_alert, id_policy, resource_id, resource_name)
            VALUES (:rid, :alert, :policy, :res_id, :res_name)
        """),
        {"rid": revision_id, "alert": id_alert, "policy": id_policy,
         "res_id": resource_id, "res_name": resource_name},
    )
    await db.commit()


async def unassign_alerta_revision(db: AsyncSession, revision_id: int, id_alert: str) -> None:
    await db.execute(
        text("DELETE FROM revisiones_has_vuln WHERE revision_id = :rid AND id_alert = :alert"),
        {"rid": revision_id, "alert": id_alert},
    )
    await db.commit()
