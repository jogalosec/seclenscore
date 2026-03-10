"""
Capa de acceso a datos — base de datos octopus_new.
Contiene queries para el módulo Normativas (normativas, ctrls, usf, preguntas, marco).
"""
from typing import Dict, List, Optional

from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession


# ---------------------------------------------------------------
# Normativas
# ---------------------------------------------------------------

async def get_normativas(db: AsyncSession) -> List[Dict]:
    sql = text("SELECT * FROM normativas")
    return (await db.execute(sql)).mappings().all()


async def new_normativa(db: AsyncSession, nombre: str, version: int) -> None:
    await db.execute(
        text("INSERT INTO normativas (nombre, version) VALUES (:nombre, :version)"),
        {"nombre": nombre, "version": version},
    )
    await db.commit()


async def edit_normativa(db: AsyncSession, nombre: str, enabled: bool, norm_id: int) -> None:
    await db.execute(
        text("UPDATE normativas SET nombre = :nombre, enabled = :enabled WHERE id = :id"),
        {"nombre": nombre, "enabled": 1 if enabled else 0, "id": norm_id},
    )
    await db.commit()


async def delete_normativa(db: AsyncSession, norm_id: int) -> None:
    """Elimina marco → controles → normativa (misma transacción)."""
    await delete_marco_from_normativa(db, norm_id)
    await delete_control_from_normativa(db, norm_id)
    await db.execute(
        text("DELETE FROM normativas WHERE id = :id"),
        {"id": norm_id},
    )
    await db.commit()


# ---------------------------------------------------------------
# Controles
# ---------------------------------------------------------------

async def get_controles_by_norm(db: AsyncSession, norm_id: int) -> List[Dict]:
    sql = text("SELECT * FROM ctrls WHERE id_normativa = :id")
    return (await db.execute(sql, {"id": norm_id})).mappings().all()


async def new_control(
    db: AsyncSession, codigo: str, nombre: str, descripcion: str, dominio: str, norm_id: int
) -> None:
    await db.execute(
        text("""
            INSERT INTO ctrls (cod, nombre, descripcion, dominio, id_normativa)
            VALUES (:cod, :nombre, :descripcion, :dominio, :norm_id)
        """),
        {"cod": codigo, "nombre": nombre, "descripcion": descripcion,
         "dominio": dominio, "norm_id": norm_id},
    )
    await db.commit()


async def delete_control(db: AsyncSession, control_id: int) -> None:
    await db.execute(text("DELETE FROM ctrls WHERE id = :id"), {"id": control_id})
    await db.commit()


async def delete_control_from_normativa(db: AsyncSession, norm_id: int) -> None:
    await db.execute(
        text("DELETE FROM ctrls WHERE id_normativa = :id"),
        {"id": norm_id},
    )


async def get_relaciones_control(db: AsyncSession, control_id: int) -> List[Dict]:
    sql = text("""
        SELECT c.cod    AS codigo_control,
               c.nombre AS nombre_control,
               u.cod    AS codigo_usf,
               u.nombre AS nombre_usf,
               p.duda,
               m.id, m.id_usf, m.id_ctrls, m.id_preguntas
        FROM marco m
        JOIN usf       u ON m.id_usf       = u.id
        JOIN preguntas p ON m.id_preguntas = p.id
        JOIN ctrls     c ON m.id_ctrls    = c.id
        WHERE m.id_ctrls = :id
    """)
    return (await db.execute(sql, {"id": control_id})).mappings().all()


async def get_dominios_controles_unicos(db: AsyncSession) -> List[str]:
    sql = text("SELECT dominio FROM ctrls GROUP BY dominio ORDER BY dominio")
    return (await db.execute(sql)).scalars().all()


# ---------------------------------------------------------------
# USF (Unidades de Servicios Funcionales)
# ---------------------------------------------------------------

async def get_usfs(db: AsyncSession) -> List[Dict]:
    sql = text("""
        SELECT u.*, p.cod AS codigo_pac
        FROM usf u
        JOIN proyectos p ON u.id_proyecto = p.id
    """)
    return (await db.execute(sql)).mappings().all()


async def get_usf_by_codigo(db: AsyncSession, codigo: str) -> Optional[Dict]:
    sql = text("""
        SELECT u.*, p.cod AS codigo_pac
        FROM usf u
        JOIN proyectos p ON u.id_proyecto = p.id
        WHERE u.cod = :cod
    """)
    row = (await db.execute(sql, {"cod": codigo})).mappings().first()
    return dict(row) if row else None


async def new_usf(
    db: AsyncSession,
    codigo: str,
    nombre: str,
    descripcion: str,
    dominio: str,
    tipo: str,
    id_pac: int,
) -> None:
    await db.execute(
        text("""
            INSERT INTO usf (cod, nombre, descripcion, dominio, tipo, id_proyecto)
            VALUES (:cod, :nombre, :descripcion, :dominio, :tipo, :id_proyecto)
        """),
        {"cod": codigo, "nombre": nombre, "descripcion": descripcion,
         "dominio": dominio, "tipo": tipo, "id_proyecto": id_pac},
    )
    await db.commit()


async def delete_usf(db: AsyncSession, usf_id: int) -> None:
    await db.execute(text("DELETE FROM usf WHERE id = :id"), {"id": usf_id})
    await db.commit()


async def get_relaciones_usf(db: AsyncSession, usf_id: int) -> List[Dict]:
    sql = text("SELECT * FROM marco WHERE id_usf = :id")
    return (await db.execute(sql, {"id": usf_id})).mappings().all()


async def get_relaciones_pregunta_usf(db: AsyncSession, pregunta_id: int) -> List[Dict]:
    sql = text("SELECT id_usf FROM marco WHERE id_preguntas = :id GROUP BY id_usf")
    return (await db.execute(sql, {"id": pregunta_id})).mappings().all()


# ---------------------------------------------------------------
# Preguntas
# ---------------------------------------------------------------

async def get_preguntas(db: AsyncSession) -> List[Dict]:
    sql = text("SELECT * FROM preguntas")
    return (await db.execute(sql)).mappings().all()


async def get_pregunta_by_duda(db: AsyncSession, duda: str) -> Optional[Dict]:
    sql = text("SELECT * FROM preguntas WHERE duda = :duda LIMIT 1")
    row = (await db.execute(sql, {"duda": duda})).mappings().first()
    return dict(row) if row else None


async def get_preguntas_by_usf(db: AsyncSession, usf_id: int) -> List[Dict]:
    sql = text("""
        SELECT DISTINCT p.id, p.duda, p.nivel
        FROM preguntas p
        LEFT JOIN marco m ON p.id = m.id_preguntas
        WHERE m.id_usf = :id
    """)
    return (await db.execute(sql, {"id": usf_id})).mappings().all()


async def get_preguntas_not_usf(db: AsyncSession, usf_id: int) -> List[Dict]:
    sql = text("""
        SELECT p.id, p.duda, p.nivel
        FROM preguntas p
        WHERE p.id NOT IN (
            SELECT p2.id
            FROM preguntas p2
            LEFT JOIN marco m ON p2.id = m.id_preguntas
            WHERE m.id_usf = :id
        )
    """)
    return (await db.execute(sql, {"id": usf_id})).mappings().all()


async def new_pregunta(db: AsyncSession, duda: str, nivel: int) -> None:
    await db.execute(
        text("INSERT INTO preguntas (duda, nivel) VALUES (:duda, :nivel)"),
        {"duda": duda, "nivel": nivel},
    )
    await db.commit()


async def delete_pregunta(db: AsyncSession, pregunta_id: int) -> None:
    await db.execute(text("DELETE FROM preguntas WHERE id = :id"), {"id": pregunta_id})
    await db.commit()


async def get_relaciones_pregunta(db: AsyncSession, pregunta_id: int) -> List[Dict]:
    sql = text("SELECT * FROM marco WHERE id_preguntas = :id")
    return (await db.execute(sql, {"id": pregunta_id})).mappings().all()


# ---------------------------------------------------------------
# Marco (relaciones control-USF-pregunta)
# ---------------------------------------------------------------

async def new_relacion_pregunta_control(
    db: AsyncSession, pregunta_id: int, control_id: int, usf_id: int
) -> None:
    await db.execute(
        text("""
            INSERT INTO marco (id_preguntas, id_ctrls, id_usf)
            VALUES (:pregunta, :control, :usf)
        """),
        {"pregunta": pregunta_id, "control": control_id, "usf": usf_id},
    )


async def delete_relacion_marco(db: AsyncSession, relacion_id: int) -> None:
    await db.execute(text("DELETE FROM marco WHERE id = :id"), {"id": relacion_id})
    await db.commit()


async def delete_marco_from_normativa(db: AsyncSession, norm_id: int) -> None:
    await db.execute(
        text("""
            DELETE FROM marco
            WHERE id_ctrls IN (
                SELECT id FROM ctrls WHERE id_normativa = :id
            )
        """),
        {"id": norm_id},
    )
