"""
Capa de acceso a datos — Evaluaciones en octopus_serv.
Equivalente a los métodos de DbOperations relacionados con evaluaciones en operationsDB.php.
"""
import json
from typing import Any, Dict, List, Optional

from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession


# ---------------------------------------------------------------
# BIA
# ---------------------------------------------------------------

async def get_bia(db: AsyncSession, activo_id: int) -> Optional[Dict]:
    """Último BIA guardado para un activo."""
    sql = text("""
        SELECT evaluaciones.id, activo_id, meta_value, fecha, users.email
        FROM evaluaciones
        LEFT JOIN octopus_users.users ON octopus_users.users.id = evaluaciones.user_id
        WHERE activo_id = :id AND meta_key = 'bia'
        ORDER BY fecha DESC LIMIT 1
    """)
    row = (await db.execute(sql, {"id": activo_id})).mappings().first()
    return dict(row) if row else None


async def save_bia(db: AsyncSession, activo_id: int, respuestas: Dict, user_id: Optional[int] = None) -> None:
    """Guarda o actualiza el BIA de un activo (inserta nuevo registro)."""
    await db.execute(
        text("""
            INSERT INTO evaluaciones (activo_id, meta_key, meta_value, user_id)
            VALUES (:id, 'bia', :datos, :user)
        """),
        {"id": activo_id, "datos": json.dumps(respuestas), "user": user_id},
    )
    await db.commit()


async def clear_bia(db: AsyncSession, activo_id: int) -> None:
    await db.execute(
        text("DELETE FROM evaluaciones WHERE activo_id = :id AND meta_key = 'bia'"),
        {"id": activo_id},
    )
    await db.commit()


# ---------------------------------------------------------------
# Evaluaciones
# ---------------------------------------------------------------

async def get_eval_by_activo_id(db: AsyncSession, activo_id: int, tipo: Optional[str] = None) -> List[Dict]:
    sql = "SELECT id, meta_key, meta_value, fecha FROM evaluaciones WHERE activo_id = :id"
    params: Dict[str, Any] = {"id": activo_id}
    if tipo:
        sql += " AND meta_key = :tipo"
        params["tipo"] = tipo
    return (await db.execute(text(sql), params)).mappings().all()


async def get_eval_by_id(db: AsyncSession, eval_id: int) -> Optional[Dict]:
    sql = text("SELECT id, meta_key, meta_value, fecha FROM evaluaciones WHERE id = :id")
    row = (await db.execute(sql, {"id": eval_id})).mappings().first()
    return dict(row) if row else None


async def set_meta_value(
    db: AsyncSession, activo_id: int, datos: Any, meta_key: str, user_id: Optional[int] = None
) -> None:
    await db.execute(
        text("""
            INSERT INTO evaluaciones (activo_id, meta_key, meta_value, user_id)
            VALUES (:id, :meta_key, :datos, :user)
        """),
        {"id": activo_id, "meta_key": meta_key, "datos": json.dumps(datos), "user": user_id},
    )
    await db.commit()


async def get_fecha_evaluaciones(db: AsyncSession, activo_id: int, all_versions: bool = False) -> List[Dict]:
    if all_versions:
        sql = text("""
            SELECT id, tipo_tabla, fecha FROM (
                SELECT id, 'evaluaciones' AS tipo_tabla, fecha
                FROM evaluaciones
                WHERE meta_key = 'preguntas' AND activo_id = :idactivo
                UNION ALL
                SELECT ev.id, 'evaluaciones_versiones' AS tipo_tabla, ev.fecha
                FROM evaluaciones e
                LEFT JOIN evaluaciones_versiones ev ON e.id = ev.evaluacion_id
                WHERE e.meta_key = 'preguntas' AND e.activo_id = :idactivo
            ) AS merged_data
            ORDER BY fecha DESC
        """)
    else:
        sql = text("""
            SELECT * FROM evaluaciones
            WHERE meta_key = 'preguntas' AND activo_id = :idactivo
            ORDER BY fecha DESC
        """)
    return (await db.execute(sql, {"idactivo": activo_id})).mappings().all()


async def get_versiones_evaluacion(db: AsyncSession, eval_id: int) -> List[Dict]:
    sql = text("SELECT id, version, nombre, fecha FROM evaluaciones_versiones WHERE evaluacion_id = :id")
    return (await db.execute(sql, {"id": eval_id})).mappings().all()


async def get_preguntas_evaluacion_by_fecha(db: AsyncSession, eval_id: int) -> Optional[Dict]:
    """Devuelve el JSON de preguntas de una evaluación por su ID (equivale a 'fecha' en PHP)."""
    sql = text("SELECT meta_value AS preguntas FROM evaluaciones WHERE id = :id AND meta_key = 'preguntas'")
    row = (await db.execute(sql, {"id": eval_id})).mappings().first()
    return dict(row) if row else None


async def get_preguntas_version_by_fecha(db: AsyncSession, version_id: int) -> Optional[Dict]:
    sql = text("SELECT meta_value AS preguntas FROM evaluaciones_versiones WHERE id = :id")
    row = (await db.execute(sql, {"id": version_id})).mappings().first()
    return dict(row) if row else None


async def get_id_activo_evaluacion_by_fecha(db: AsyncSession, eval_id: int) -> Optional[int]:
    sql = text("SELECT activo_id AS id FROM evaluaciones WHERE id = :id AND meta_key = 'preguntas'")
    row = (await db.execute(sql, {"id": eval_id})).mappings().first()
    return row["id"] if row else None


async def get_num_version_eval(db: AsyncSession, eval_id: int) -> int:
    sql = text("SELECT COUNT(*) AS version FROM evaluaciones_versiones WHERE evaluacion_id = :id")
    row = (await db.execute(sql, {"id": eval_id})).mappings().first()
    return row["version"] if row else 0


async def get_version_by_id(db: AsyncSession, version_id: int) -> Optional[Dict]:
    sql = text("SELECT id, meta_key, meta_value, fecha, evaluacion_id FROM evaluaciones_versiones WHERE id = :id")
    row = (await db.execute(sql, {"id": version_id})).mappings().first()
    return dict(row) if row else None


async def edit_eval(
    db: AsyncSession, eval_id: Optional[int], version_id: Optional[int], datos: Any, nombre: str = "Edición"
) -> None:
    """Crea una nueva versión de una evaluación (equivalente a editEval en PHP)."""
    target_eval_id = eval_id

    if version_id is not None:
        ver = await get_version_by_id(db, version_id)
        if ver and "evaluacion_id" in ver:
            target_eval_id = ver["evaluacion_id"]

    version_num = await get_num_version_eval(db, target_eval_id)
    nueva_version = version_num + 1

    await db.execute(
        text("""
            INSERT INTO evaluaciones_versiones
                (evaluacion_id, version, nombre, meta_key, meta_value)
            VALUES (:id, :version, :nombre, 'preguntas', :meta_value)
        """),
        {
            "id": target_eval_id,
            "version": nueva_version,
            "nombre": nombre,
            "meta_value": json.dumps(datos),
        },
    )
    await db.commit()


async def get_evaluaciones_sistema(db: AsyncSession, activo_id: int) -> List[Dict]:
    """Devuelve evaluaciones + versiones de un activo (equivalente a getEvaluacionesSistema en PHP)."""
    evaluaciones = await get_eval_by_activo_id(db, activo_id, tipo="preguntas")
    if not evaluaciones:
        return []

    result = []
    for ev in evaluaciones:
        ev_dict = dict(ev)
        ev_dict["version"] = False
        versiones = await get_versiones_evaluacion(db, ev_dict["id"])
        result.append(ev_dict)
        result.extend([dict(v) for v in versiones])
    return result


async def save_eval_osa(db: AsyncSession, parametros: Dict) -> None:
    """Guarda evaluación OSA en revisiones_has_osa."""
    revision_id = parametros["revision_id"]
    for osa_id, valor in parametros.items():
        if osa_id == "revision_id":
            continue
        await db.execute(
            text("""
                INSERT INTO revisiones_has_osa (revision_id, osa_id, valor)
                VALUES (:revision_id, :osa_id, :valor)
                ON DUPLICATE KEY UPDATE valor = :valor
            """),
            {"revision_id": revision_id, "osa_id": osa_id, "valor": valor},
        )
    await db.commit()


# ---------------------------------------------------------------
# PAC (Plan de Acciones Correctivas)
# ---------------------------------------------------------------

async def get_pac_eval_servicio(db: AsyncSession, activo_id: int, fecha: str) -> List[Dict]:
    sql = text("""
        SELECT * FROM evaluaciones
        WHERE activo_id = :id AND meta_key = 'pac' AND fecha >= :fecha
        ORDER BY fecha DESC LIMIT 1
    """)
    return (await db.execute(sql, {"id": activo_id, "fecha": fecha})).mappings().all()
