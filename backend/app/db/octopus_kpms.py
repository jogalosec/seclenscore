"""
Capa de acceso a datos — KPMs (Métricas, Madurez, CSIRT) en octopus_kpms.
Corrige las vulnerabilidades de SQL injection del PHP original:
  - Nombres de tabla validados con Literal["madurez", "metricas", "csirt"]
  - Columnas editables definidas en whitelist ALLOWED_COLUMNS
  - Todos los valores como parámetros ligados
"""
from typing import Any, Dict, List, Literal, Optional

from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

TipoKPM = Literal["madurez", "metricas", "csirt"]

# Columnas permitidas para edición por tipo de tabla (evita SQL injection en SET clause)
ALLOWED_COLUMNS: Dict[str, set] = {
    "madurez":  {"valor", "comentario", "locked"},
    "metricas": {"valor", "comentario", "locked"},
    "csirt":    {"valor", "comentario", "locked"},
}


# ---------------------------------------------------------------
# Helpers internos
# ---------------------------------------------------------------

def _validate_table(tipo: TipoKPM) -> str:
    """Devuelve el nombre de tabla validado. Pydantic Literal ya lo garantiza,
    pero se mantiene como defensa en profundidad."""
    allowed = {"madurez", "metricas", "csirt"}
    if tipo not in allowed:
        raise ValueError(f"Tipo KPM no permitido: {tipo}")
    return tipo


# ---------------------------------------------------------------
# Métricas
# ---------------------------------------------------------------

async def get_metricas_by_user(
    db: AsyncSession, user_id: int, admin: bool = False
) -> List[Dict]:
    if admin:
        sql = text("""
            SELECT m.*, u.email AS reporter_email
            FROM metricas m
            LEFT JOIN octopus_users.users u ON u.id = m.reporter_id
            ORDER BY m.id
        """)
        return (await db.execute(sql)).mappings().all()

    sql = text("""
        SELECT m.*, u.email AS reporter_email
        FROM metricas m
        LEFT JOIN octopus_users.users u ON u.id = m.reporter_id
        WHERE m.reporter_id = :user_id
           OR m.reporter_id IN (
               SELECT activo_id FROM reporters_kpms WHERE user_id = :user_id
           )
        ORDER BY m.id
    """)
    return (await db.execute(sql, {"user_id": user_id})).mappings().all()


async def get_madurez_by_user(
    db: AsyncSession, user_id: int, admin: bool = False
) -> List[Dict]:
    if admin:
        sql = text("""
            SELECT ma.*, u.email AS reporter_email
            FROM madurez ma
            LEFT JOIN octopus_users.users u ON u.id = ma.reporter_id
            ORDER BY ma.id
        """)
        return (await db.execute(sql)).mappings().all()

    sql = text("""
        SELECT ma.*, u.email AS reporter_email
        FROM madurez ma
        LEFT JOIN octopus_users.users u ON u.id = ma.reporter_id
        WHERE ma.reporter_id = :user_id
           OR ma.reporter_id IN (
               SELECT activo_id FROM reporters_kpms WHERE user_id = :user_id
           )
        ORDER BY ma.id
    """)
    return (await db.execute(sql, {"user_id": user_id})).mappings().all()


async def get_csirt_by_user(
    db: AsyncSession, user_id: int, admin: bool = False
) -> List[Dict]:
    if admin:
        sql = text("""
            SELECT c.*, u.email AS reporter_email
            FROM csirt c
            LEFT JOIN octopus_users.users u ON u.id = c.reporter_id
            ORDER BY c.id
        """)
        return (await db.execute(sql)).mappings().all()

    sql = text("""
        SELECT c.*, u.email AS reporter_email
        FROM csirt c
        LEFT JOIN octopus_users.users u ON u.id = c.reporter_id
        WHERE c.reporter_id = :user_id
        ORDER BY c.id
    """)
    return (await db.execute(sql, {"user_id": user_id})).mappings().all()


# ---------------------------------------------------------------
# Último reporte — FIX: PHP usaba $tipo directamente en la cadena SQL
# ---------------------------------------------------------------

async def get_last_report_kpms(
    db: AsyncSession, user_id: int, tipo: TipoKPM
) -> List[Dict]:
    """Devuelve los últimos KPMs del usuario para el tipo dado.
    FIX: PHP interpolaba $tipo en la query — aquí usamos whitelist validada."""
    table = _validate_table(tipo)
    # El nombre de tabla no puede ligarse como parámetro en SQLAlchemy text(),
    # por lo que se usa f-string solo tras validación explícita con whitelist.
    sql = text(f"""
        SELECT * FROM {table}
        WHERE reporter_id = :user_id
        ORDER BY fecha DESC
    """)
    return (await db.execute(sql, {"user_id": user_id})).mappings().all()


async def get_report_as(
    db: AsyncSession, user_id: int, additional_access: bool = False
) -> List[Dict]:
    """Reportes de métricas y madurez accesibles por el usuario."""
    if additional_access:
        sql = text("""
            SELECT rk.*, u.email, a.nombre AS activo_nombre
            FROM reporters_kpms rk
            LEFT JOIN octopus_users.users u ON u.id = rk.user_id
            LEFT JOIN octopus_serv.activos a ON a.id = rk.activo_id
            ORDER BY rk.id
        """)
        return (await db.execute(sql)).mappings().all()

    sql = text("""
        SELECT rk.*, u.email, a.nombre AS activo_nombre
        FROM reporters_kpms rk
        LEFT JOIN octopus_users.users u ON u.id = rk.user_id
        LEFT JOIN octopus_serv.activos a ON a.id = rk.activo_id
        WHERE rk.user_id = :user_id
        ORDER BY rk.id
    """)
    return (await db.execute(sql, {"user_id": user_id})).mappings().all()


async def get_report_as_csirt(db: AsyncSession) -> List[Dict]:
    """Todos los reportes CSIRT (solo admin)."""
    sql = text("""
        SELECT rk.*, u.email, a.nombre AS activo_nombre
        FROM reporters_kpms rk
        LEFT JOIN octopus_users.users u ON u.id = rk.user_id
        LEFT JOIN octopus_serv.activos a ON a.id = rk.activo_id
        ORDER BY rk.id
    """)
    return (await db.execute(sql)).mappings().all()


# ---------------------------------------------------------------
# Preguntas / Formulario KPMs
# ---------------------------------------------------------------

async def get_preguntas_kpms_formulario(db: AsyncSession) -> List[Dict]:
    sql = text("""
        SELECT id, numeroKPM, descripcionCortaKPM, descripcionLargaKPM, grupoKPM
        FROM kpms
        WHERE tipo IN ('madurez', 'metricas')
        ORDER BY grupoKPM, numeroKPM
    """)
    return (await db.execute(sql)).mappings().all()


async def get_all_preguntas_kpms(db: AsyncSession) -> List[Dict]:
    sql = text("""
        SELECT id, numeroKPM, descripcionCortaKPM, descripcionLargaKPM, grupoKPM, tipo
        FROM kpms
        ORDER BY tipo, grupoKPM, numeroKPM
    """)
    return (await db.execute(sql)).mappings().all()


async def get_preguntas_kpms_csirt(db: AsyncSession) -> List[Dict]:
    sql = text("""
        SELECT id, numeroKPM, descripcionCortaKPM, descripcionLargaKPM, grupoKPM
        FROM kpms
        WHERE tipo = 'csirt'
        ORDER BY grupoKPM, numeroKPM
    """)
    return (await db.execute(sql)).mappings().all()


# ---------------------------------------------------------------
# Definiciones KPM (catálogo)
# ---------------------------------------------------------------

async def get_kpm_definicion(db: AsyncSession, kpm_id: int) -> Optional[Dict]:
    sql = text("SELECT * FROM kpms WHERE id = :id")
    row = (await db.execute(sql, {"id": kpm_id})).mappings().first()
    return dict(row) if row else None


async def update_kpm_definicion(
    db: AsyncSession,
    kpm_id: int,
    nombre: Optional[str],
    descripcion_larga: Optional[str],
    descripcion_corta: Optional[str],
    grupo: Optional[str],
) -> None:
    await db.execute(
        text("""
            UPDATE kpms
            SET descripcionCortaKPM = COALESCE(:corta, descripcionCortaKPM),
                descripcionLargaKPM = COALESCE(:larga, descripcionLargaKPM),
                numeroKPM           = COALESCE(:nombre, numeroKPM),
                grupoKPM            = COALESCE(:grupo,  grupoKPM)
            WHERE id = :id
        """),
        {
            "id": kpm_id,
            "corta": descripcion_corta,
            "larga": descripcion_larga,
            "nombre": nombre,
            "grupo": grupo,
        },
    )
    await db.commit()


# ---------------------------------------------------------------
# Lock / Unlock / Delete (masivos)
# FIX: PHP iteraba e interpolaba IDs directamente en la query
# ---------------------------------------------------------------

async def lock_kpms(db: AsyncSession, tipo: TipoKPM, ids: List[int]) -> None:
    table = _validate_table(tipo)
    for kpm_id in ids:
        await db.execute(
            text(f"UPDATE {table} SET locked = 1 WHERE id = :id"),
            {"id": kpm_id},
        )
    await db.commit()


async def unlock_kpms(db: AsyncSession, tipo: TipoKPM, ids: List[int]) -> None:
    table = _validate_table(tipo)
    for kpm_id in ids:
        await db.execute(
            text(f"UPDATE {table} SET locked = 0 WHERE id = :id"),
            {"id": kpm_id},
        )
    await db.commit()


async def del_kpms(db: AsyncSession, tipo: TipoKPM, ids: List[int]) -> None:
    table = _validate_table(tipo)
    for kpm_id in ids:
        await db.execute(
            text(f"DELETE FROM {table} WHERE id = :id"),
            {"id": kpm_id},
        )
    await db.commit()


# ---------------------------------------------------------------
# Edición de KPM individual
# FIX: PHP construía SET clause con claves del dict sin validación
# ---------------------------------------------------------------

async def edit_kpm(
    db: AsyncSession,
    tipo: TipoKPM,
    kpm_id: int,
    campos: Dict[str, Any],
    user_id: Optional[int] = None,
) -> None:
    """Actualiza campos de un KPM. Solo columnas de ALLOWED_COLUMNS son aceptadas."""
    table = _validate_table(tipo)
    allowed = ALLOWED_COLUMNS.get(table, set())
    safe_campos = {k: v for k, v in campos.items() if k in allowed}
    if not safe_campos:
        return

    set_clause = ", ".join(f"{col} = :{col}" for col in safe_campos)
    params: Dict[str, Any] = {"id": kpm_id, **safe_campos}
    if user_id is not None:
        set_clause += ", reporter_id = :reporter_id"
        params["reporter_id"] = user_id

    await db.execute(
        text(f"UPDATE {table} SET {set_clause} WHERE id = :id"),
        params,
    )
    await db.commit()


# ---------------------------------------------------------------
# Reporters KPMs
# ---------------------------------------------------------------

async def get_reporters_kpms(
    db: AsyncSession, additional_access: bool = False, user_id: Optional[int] = None
) -> List[Dict]:
    if additional_access:
        sql = text("""
            SELECT rk.id, rk.user_id, rk.activo_id,
                   u.email, a.nombre AS activo_nombre
            FROM reporters_kpms rk
            LEFT JOIN octopus_users.users u ON u.id = rk.user_id
            LEFT JOIN octopus_serv.activos a ON a.id = rk.activo_id
            ORDER BY rk.id
        """)
        return (await db.execute(sql)).mappings().all()

    sql = text("""
        SELECT rk.id, rk.user_id, rk.activo_id,
               u.email, a.nombre AS activo_nombre
        FROM reporters_kpms rk
        LEFT JOIN octopus_users.users u ON u.id = rk.user_id
        LEFT JOIN octopus_serv.activos a ON a.id = rk.activo_id
        WHERE rk.user_id = :user_id
        ORDER BY rk.id
    """)
    return (await db.execute(sql, {"user_id": user_id})).mappings().all()


async def crear_reporter_kpms(
    db: AsyncSession, user_id: int, activo_id: int
) -> None:
    await db.execute(
        text("""
            INSERT IGNORE INTO reporters_kpms (user_id, activo_id)
            VALUES (:user_id, :activo_id)
        """),
        {"user_id": user_id, "activo_id": activo_id},
    )
    await db.commit()


async def delete_relacion_reporter(db: AsyncSession, relacion_id: int) -> None:
    await db.execute(
        text("DELETE FROM reporters_kpms WHERE id = :id"),
        {"id": relacion_id},
    )
    await db.commit()


# ---------------------------------------------------------------
# Creación de registros KPM (formulario)
# ---------------------------------------------------------------

async def crear_kpm_registro(
    db: AsyncSession,
    tipo: TipoKPM,
    kpm_id: int,
    reporter_id: int,
    valor: Optional[float] = None,
    comentario: Optional[str] = None,
) -> int:
    """Inserta un nuevo registro de KPM y devuelve el ID generado."""
    table = _validate_table(tipo)
    result = await db.execute(
        text(f"""
            INSERT INTO {table} (kpm_id, reporter_id, valor, comentario)
            VALUES (:kpm_id, :reporter_id, :valor, :comentario)
        """),
        {
            "kpm_id": kpm_id,
            "reporter_id": reporter_id,
            "valor": valor,
            "comentario": comentario,
        },
    )
    await db.commit()
    return result.lastrowid


async def get_kpm_registro(
    db: AsyncSession, tipo: TipoKPM, registro_id: int
) -> Optional[Dict]:
    table = _validate_table(tipo)
    sql = text(f"SELECT * FROM {table} WHERE id = :id")
    row = (await db.execute(sql, {"id": registro_id})).mappings().first()
    return dict(row) if row else None
