"""
Capa de acceso a datos para octopus_serv — módulo Activos.
Equivalente a la clase Activos(DbOperations) de operationsDB.php.
Todas las queries usan parámetros enlazados (nunca interpolación directa).
"""
from typing import Any, Dict, List, Optional

from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession


# ---------------------------------------------------------------
# Lectura de activos
# ---------------------------------------------------------------

async def get_activo_by_id(db: AsyncSession, activo_id: int) -> Optional[Dict]:
    """
    Obtiene un activo por su ID.
    Equivalente a getActivo($id) en PHP.
    """
    sql = text("""
        SELECT id, nombre, critico, expuesto, descripcion,
               activo_id AS tipo, user_id, archivado
        FROM activos
        WHERE id = :id
        ORDER BY nombre ASC
    """)
    result = await db.execute(sql, {"id": activo_id})
    row = result.mappings().fetchone()
    return dict(row) if row else None


async def get_activos_by_tipo(
    db: AsyncSession,
    tipo: int,
    user_id: Optional[int] = None,
    archivado: str = "0",
    externo: str = "0",
    additional_access: bool = False,
) -> List[Dict]:
    """
    Lista activos por tipo con filtros opcionales.
    Equivalente a getActivosByTipo() en PHP.
    Los tipos especiales (124, 123, 122) no filtran por usuario.
    """
    # Filtro archivado
    if archivado == "All":
        archivado_filter = ""
    elif archivado == "1":
        archivado_filter = "AND archivado = 1"
    else:
        archivado_filter = "AND archivado = 0"

    # Filtro externo
    if externo == "All":
        externo_filter = ""
    elif externo == "1":
        externo_filter = "AND externo = 1"
    else:
        externo_filter = "AND externo = 0"

    # Tipos especiales no filtran por usuario
    tipos_especiales = {124, 123, 122}
    usar_filtro_usuario = (
        user_id is not None
        and not additional_access
        and tipo not in tipos_especiales
    )

    if not usar_filtro_usuario:
        sql = text(f"""
            SELECT id, nombre, activo_id AS tipo, user_id, descripcion,
                   archivado, externo, expuesto, critico
            FROM activos
            WHERE activo_id = :tipo {archivado_filter} {externo_filter}
            ORDER BY nombre ASC
        """)
        result = await db.execute(sql, {"tipo": tipo})
    else:
        sql = text(f"""
            SELECT id, nombre, activo_id AS tipo, user_id, descripcion,
                   archivado, externo, expuesto, critico
            FROM activos
            WHERE activo_id = :tipo AND user_id = :user_id {archivado_filter} {externo_filter}
            ORDER BY nombre ASC
        """)
        result = await db.execute(sql, {"tipo": tipo, "user_id": user_id})

    return [dict(row) for row in result.mappings().fetchall()]


async def get_activos_permisos(db: AsyncSession, user_id: int) -> List[Dict]:
    """
    Activos con permisos especiales para el usuario (tabla visibilidad).
    Equivalente a getActivosPermisos() en PHP.
    """
    sql = text("""
        SELECT activos.id, nombre, activos.activo_id AS tipo, user_id
        FROM visibilidad
        INNER JOIN activos ON activos.id = visibilidad.activo_id
        WHERE usuario_id = :user_id
    """)
    result = await db.execute(sql, {"user_id": user_id})
    return [dict(row) for row in result.mappings().fetchall()]


async def get_child(db: AsyncSession, activo_id: int) -> List[Dict]:
    """
    Hijos directos de un activo.
    Equivalente a getChild() en PHP.
    """
    sql = text("""
        SELECT a.id, a.nombre, a.activo_id AS tipo, a.user_id, a.archivado,
               n.nombre AS tipo_nombre
        FROM activos a
        LEFT JOIN octopus_new.activos n ON n.id = a.activo_id
        WHERE a.padre = :id
        ORDER BY a.nombre ASC
    """)
    result = await db.execute(sql, {"id": activo_id})
    return [dict(row) for row in result.mappings().fetchall()]


async def get_hijos(db: AsyncSession, activo_id: int) -> List[Dict]:
    """
    Todos los descendientes recursivos de un activo (CTE).
    Equivalente a getHijos() en PHP usando vistafamiliaactivos.
    """
    sql = text("""
        WITH RECURSIVE RecursivoHijos AS (
            SELECT id, nombre, padre, tipo, tipo_id, archivado
            FROM vistafamiliaactivos
            WHERE id = :id
            UNION ALL
            SELECT vp.id, vp.nombre, vp.padre, vp.tipo, vp.tipo_id, vp.archivado
            FROM vistafamiliaactivos vp
            INNER JOIN RecursivoHijos rh ON vp.padre = rh.id
        )
        SELECT padre, nombre, MIN(id) AS id, MIN(tipo) AS tipo,
               MIN(tipo_id) AS tipo_id, archivado
        FROM RecursivoHijos
        GROUP BY padre, nombre, archivado
    """)
    result = await db.execute(sql, {"id": activo_id})
    return [dict(row) for row in result.mappings().fetchall()]


async def get_hijos_tipo(db: AsyncSession, activo_id: int, tipo: str) -> List[Dict]:
    """
    Descendientes filtrados por tipo.
    Equivalente a getHijosTipo() en PHP.
    """
    sql = text("""
        WITH RECURSIVE RecursivoHijos AS (
            SELECT id, nombre, padre, tipo, tipo_id, archivado
            FROM vistafamiliaactivos
            WHERE id = :id
            UNION ALL
            SELECT vp.id, vp.nombre, vp.padre, vp.tipo, vp.tipo_id, vp.archivado
            FROM vistafamiliaactivos vp
            INNER JOIN RecursivoHijos rh ON vp.padre = rh.id
        )
        SELECT padre, nombre, MIN(id) AS id, MIN(tipo) AS tipo,
               MIN(tipo_id) AS tipo_id, MIN(archivado) AS archivado
        FROM RecursivoHijos
        WHERE tipo = :tipo
        GROUP BY padre, nombre
    """)
    result = await db.execute(sql, {"id": activo_id, "tipo": tipo})
    return [dict(row) for row in result.mappings().fetchall()]


async def get_tree(db: AsyncSession, activo_id: int) -> List[Dict]:
    """
    Árbol completo de un activo con información de tipo.
    Equivalente a getTree() en PHP.
    """
    hijos = await get_hijos(db, activo_id)
    raiz  = await get_activo_by_id(db, activo_id)
    if raiz:
        nombre_tipo = await get_clase_activo_by_id(db, raiz.get("tipo"))
        raiz["tipo_nombre"] = nombre_tipo.get("nombre") if nombre_tipo else None
    return [raiz] + hijos if raiz else hijos


async def get_brothers(db: AsyncSession, parent_id: int, exclude_id: int) -> List[Dict]:
    """
    Hermanos de un activo (mismo padre, excluyendo al activo dado).
    Equivalente a getBrothers() en PHP.
    """
    sql = text("""
        SELECT id, nombre, activo_id AS tipo, archivado
        FROM activos
        WHERE padre = :parent_id AND id != :exclude_id
        ORDER BY nombre ASC
    """)
    result = await db.execute(sql, {"parent_id": parent_id, "exclude_id": exclude_id})
    return [dict(row) for row in result.mappings().fetchall()]


async def get_activos_by_nombre(db: AsyncSession, nombre: str) -> List[Dict]:
    """Búsqueda de activos por nombre (LIKE)."""
    sql = text("""
        SELECT id, nombre, activo_id AS tipo, user_id, archivado
        FROM activos
        WHERE nombre LIKE :nombre
        ORDER BY nombre ASC
        LIMIT 50
    """)
    result = await db.execute(sql, {"nombre": f"%{nombre}%"})
    return [dict(row) for row in result.mappings().fetchall()]


async def get_activos_exposicion(db: AsyncSession) -> List[Dict]:
    """Activos con información de exposición."""
    sql = text("""
        SELECT id, nombre, expuesto, critico, activo_id AS tipo
        FROM activos
        WHERE activo_id IN (42, 67)
        ORDER BY nombre ASC
    """)
    result = await db.execute(sql)
    return [dict(row) for row in result.mappings().fetchall()]


# ---------------------------------------------------------------
# Tipos/clases de activos (octopus_new)
# ---------------------------------------------------------------

async def get_clase_activos(db: AsyncSession) -> List[Dict]:
    """
    Todos los tipos de activo.
    Equivalente a getClaseActivos() en PHP.
    NOTA: usa octopus_new.activos, no octopus_serv.activos.
    """
    sql = text("SELECT id, nombre FROM octopus_new.activos ORDER BY nombre ASC")
    result = await db.execute(sql)
    return [dict(row) for row in result.mappings().fetchall()]


async def get_clase_activo_by_id(db: AsyncSession, tipo_id: int) -> Optional[Dict]:
    """Tipo de activo por ID."""
    sql = text("SELECT id, nombre FROM octopus_new.activos WHERE id = :id")
    result = await db.execute(sql, {"id": tipo_id})
    row = result.mappings().fetchone()
    return dict(row) if row else None


async def obtain_all_type_activos(db: AsyncSession) -> List[Dict]:
    """Lista de tipos de activo disponibles."""
    return await get_clase_activos(db)


async def obtain_fathers_activo(db: AsyncSession, activo_id: int) -> List[Dict]:
    """Activos padre disponibles para un tipo dado."""
    activo = await get_activo_by_id(db, activo_id)
    if not activo:
        return []
    sql = text("""
        SELECT id, nombre, activo_id AS tipo
        FROM activos
        WHERE id = (SELECT padre FROM activos WHERE id = :id)
    """)
    result = await db.execute(sql, {"id": activo_id})
    return [dict(row) for row in result.mappings().fetchall()]


# ---------------------------------------------------------------
# Personas asociadas a activos
# ---------------------------------------------------------------

async def get_personas_activo(db: AsyncSession, activo_id: int) -> Optional[Dict]:
    """
    Obtiene las personas responsables de un activo.
    Si no existen, las crea con NULLs.
    Equivalente a getPersonas() en PHP.
    """
    sql = text("""
        SELECT id, activo_id, product_owner, r_seguridad, r_config_puesto_trabajo,
               r_operaciones, r_desarrollo, r_legal, r_rrhh, r_kpms, consultor_ciso
        FROM personas
        WHERE activo_id = :activo_id
    """)
    result = await db.execute(sql, {"activo_id": activo_id})
    row = result.mappings().fetchone()
    if row:
        return dict(row)

    # Crear registro vacío si no existe
    await db.execute(
        text("""
            INSERT INTO personas
            (activo_id, product_owner, r_seguridad, r_config_puesto_trabajo,
             r_operaciones, r_desarrollo, r_legal, r_rrhh, r_kpms, consultor_ciso)
            VALUES (:id, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)
        """),
        {"id": activo_id},
    )
    await db.flush()
    result = await db.execute(sql, {"activo_id": activo_id})
    row = result.mappings().fetchone()
    return dict(row) if row else None


async def edit_personas_activo(db: AsyncSession, activo_id: int, data: dict) -> bool:
    """
    Actualiza las personas responsables de un activo.
    Equivalente a editPersonasActivo() en PHP.
    """
    campos = [
        "product_owner", "r_seguridad", "r_config_puesto_trabajo",
        "r_operaciones", "r_desarrollo", "r_legal", "r_rrhh", "r_kpms", "consultor_ciso",
    ]
    set_clause = ", ".join(f"{c} = :{c}" for c in campos if c in data)
    if not set_clause:
        return False

    params = {c: data.get(c) for c in campos if c in data}
    params["activo_id"] = activo_id

    await db.execute(
        text(f"UPDATE personas SET {set_clause} WHERE activo_id = :activo_id"),
        params,
    )
    return True


# ---------------------------------------------------------------
# CRUD de activos
# ---------------------------------------------------------------

async def new_activo(
    db: AsyncSession,
    nombre: str,
    clase: int,
    padre: Optional[str],
    user_id: int,
) -> Dict:
    """
    Crea un nuevo activo.
    Equivalente a newActivo() en PHP.
    """
    padre_id = int(padre) if padre and padre != "undefined" else None
    result = await db.execute(
        text("""
            INSERT INTO activos (nombre, activo_id, padre, user_id, archivado, externo, expuesto, critico)
            VALUES (:nombre, :clase, :padre, :user_id, 0, 0, 0, 0)
        """),
        {"nombre": nombre, "clase": clase, "padre": padre_id, "user_id": user_id},
    )
    new_id = result.lastrowid
    return {"id": new_id, "nombre": nombre, "tipo": clase, "user_id": user_id}


async def edit_activo(
    db: AsyncSession,
    activo_id: int,
    nombre: Optional[str] = None,
    tipo: Optional[int] = None,
    descripcion: Optional[str] = None,
    archivado: int = 0,
    externo: int = 0,
    expuesto: int = 0,
) -> bool:
    """
    Actualiza un activo existente.
    Equivalente a editActivo() en PHP.
    """
    updates = []
    params: Dict[str, Any] = {"id": activo_id}

    if nombre is not None:
        updates.append("nombre = :nombre")
        params["nombre"] = nombre
    if tipo is not None:
        updates.append("activo_id = :tipo")
        params["tipo"] = tipo
    if descripcion is not None:
        updates.append("descripcion = :descripcion")
        params["descripcion"] = descripcion

    updates += ["archivado = :archivado", "externo = :externo", "expuesto = :expuesto"]
    params.update({"archivado": archivado, "externo": externo, "expuesto": expuesto})

    sql = text(f"UPDATE activos SET {', '.join(updates)} WHERE id = :id")
    await db.execute(sql, params)
    return True


async def delete_activo(db: AsyncSession, activo_id: int) -> bool:
    """
    Elimina un activo por ID.
    Equivalente a deleteActivo() en PHP.
    """
    await db.execute(text("DELETE FROM activos WHERE id = :id"), {"id": activo_id})
    return True


async def clone_activo(db: AsyncSession, activo_id: int, user_id: int) -> Optional[Dict]:
    """
    Clona un activo existente.
    Equivalente a cloneActivo() en PHP.
    """
    original = await get_activo_by_id(db, activo_id)
    if not original:
        return None

    result = await db.execute(
        text("""
            INSERT INTO activos (nombre, activo_id, padre, user_id, archivado, externo, expuesto, critico, descripcion)
            SELECT CONCAT(nombre, ' (copia)'), activo_id, padre, :user_id,
                   archivado, externo, expuesto, critico, descripcion
            FROM activos WHERE id = :id
        """),
        {"id": activo_id, "user_id": user_id},
    )
    return {"id": result.lastrowid, "clonado_de": activo_id}


async def update_archivados(db: AsyncSession, activo_id: int, archivado: int) -> bool:
    """Actualiza el estado archivado de un activo."""
    await db.execute(
        text("UPDATE activos SET archivado = :archivado WHERE id = :id"),
        {"archivado": archivado, "id": activo_id},
    )
    return True


async def change_relacion(db: AsyncSession, activo_id: int, nuevo_padre_id: int) -> bool:
    """Cambia la relación padre de un activo."""
    await db.execute(
        text("UPDATE activos SET padre = :padre WHERE id = :id"),
        {"padre": nuevo_padre_id, "id": activo_id},
    )
    return True


async def eliminar_relacion_activo(db: AsyncSession, activo_id: int, padre_id: int) -> bool:
    """Elimina la relación padre de un activo."""
    await db.execute(
        text("UPDATE activos SET padre = NULL WHERE id = :id AND padre = :padre"),
        {"id": activo_id, "padre": padre_id},
    )
    return True


# ---------------------------------------------------------------
# Logs de activos
# ---------------------------------------------------------------

async def get_logs_relacion(db: AsyncSession, activo_id: Optional[int] = None) -> List[Dict]:
    """Logs de cambios de relación de activos."""
    if activo_id:
        sql = text("""
            SELECT * FROM logs_relacion
            WHERE activo_id = :activo_id
            ORDER BY fecha DESC LIMIT 100
        """)
        result = await db.execute(sql, {"activo_id": activo_id})
    else:
        sql = text("SELECT * FROM logs_relacion ORDER BY fecha DESC LIMIT 100")
        result = await db.execute(sql)
    return [dict(row) for row in result.mappings().fetchall()]
