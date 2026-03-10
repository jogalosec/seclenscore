"""
Capa de acceso a datos — base de datos octopus_users.
Equivalente a la clase Usuarios de operationsDB.php.
"""
from typing import Any, Dict, List, Optional

from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession


# ---------------------------------------------------------------
# Usuarios
# ---------------------------------------------------------------

async def get_users(db: AsyncSession) -> List[Dict]:
    """Lista todos los usuarios con sus roles agrupados."""
    sql = text("""
        SELECT users.id, users.email,
               roles.id    AS role_id,
               roles.name  AS role,
               roles.color AS role_color
        FROM users
        LEFT JOIN user_roles ON user_roles.user_id = users.id
        LEFT JOIN roles      ON roles.id = user_roles.role_id
        ORDER BY users.id ASC
    """)
    rows = (await db.execute(sql)).mappings().all()

    users: Dict[int, Dict] = {}
    for row in rows:
        uid = row["id"]
        if uid not in users:
            users[uid] = {"id": uid, "email": row["email"], "roles": []}
        if row["role_id"] is not None:
            users[uid]["roles"].append({
                "id":    row["role_id"],
                "name":  row["role"],
                "color": row["role_color"],
            })
    return list(users.values())


async def get_user(db: AsyncSession, user_id: int) -> Optional[Dict]:
    """Devuelve un usuario con sus roles como lista."""
    sql = text("""
        SELECT users.id, users.email,
               GROUP_CONCAT(DISTINCT roles.name ORDER BY roles.name SEPARATOR ',') AS roles
        FROM users
        LEFT JOIN user_roles ON user_roles.user_id = users.id
        LEFT JOIN roles      ON roles.id = user_roles.role_id
        WHERE users.id = :id
        GROUP BY users.id, users.email
    """)
    row = (await db.execute(sql, {"id": user_id})).mappings().first()
    if not row:
        return None
    result = dict(row)
    result["roles"] = result["roles"].split(",") if result["roles"] else []
    return result


async def auth_user(db: AsyncSession, email: str) -> Optional[Dict]:
    """Devuelve hash de password y last_login para autenticación local."""
    sql = text("SELECT id, password, last_login FROM users WHERE email = :email")
    row = (await db.execute(sql, {"email": email})).mappings().first()
    return dict(row) if row else None


async def get_user_by_email(db: AsyncSession, email: str) -> Optional[Dict]:
    """Devuelve id y email de un usuario por email."""
    sql = text("""
        SELECT users.id, users.email,
               GROUP_CONCAT(DISTINCT roles.name ORDER BY roles.name SEPARATOR ',') AS roles
        FROM users
        LEFT JOIN user_roles ON user_roles.user_id = users.id
        LEFT JOIN roles      ON roles.id = user_roles.role_id
        WHERE users.email = :email
        GROUP BY users.id, users.email
    """)
    row = (await db.execute(sql, {"email": email})).mappings().first()
    if not row:
        return None
    result = dict(row)
    result["roles"] = result["roles"].split(",") if result["roles"] else []
    return result


async def new_user(db: AsyncSession, email: str, password_hash: str, roles: List[int]) -> Dict:
    """Inserta usuario con roles. Devuelve dict con error/message."""
    try:
        await db.execute(
            text("INSERT INTO users (email, password) VALUES (:email, :password)"),
            {"email": email, "password": password_hash},
        )
        await db.flush()
        row = (await db.execute(text("SELECT LAST_INSERT_ID() AS lid"))).mappings().first()
        user_id = row["lid"]

        if not roles:
            guest = (await db.execute(
                text("SELECT id FROM roles WHERE name = 'Guest' LIMIT 1")
            )).mappings().first()
            if guest:
                roles = [guest["id"]]

        for role_id in roles:
            await db.execute(
                text("INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)"),
                {"uid": user_id, "rid": int(role_id)},
            )

        await db.commit()
        return {"error": False, "user_id": user_id}

    except Exception as e:
        await db.rollback()
        msg = "Ese correo ya está dado de alta." if "1062" in str(e) else str(e)
        return {"error": True, "message": msg}


async def edit_user(db: AsyncSession, user_id: int, roles: List[int]) -> None:
    """Actualiza los roles de un usuario (diff: añade los nuevos, elimina los que no están)."""
    roles = [int(r) for r in roles]

    current = (await db.execute(
        text("SELECT role_id FROM user_roles WHERE user_id = :id"),
        {"id": user_id},
    )).scalars().all()
    current_set = set(current)
    new_set = set(roles)

    to_delete = current_set - new_set
    for rid in to_delete:
        await db.execute(
            text("DELETE FROM user_roles WHERE user_id = :uid AND role_id = :rid"),
            {"uid": user_id, "rid": rid},
        )

    to_add = new_set - current_set
    for rid in to_add:
        await db.execute(
            text("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:uid, :rid)"),
            {"uid": user_id, "rid": rid},
        )

    await db.commit()


async def del_user(db: AsyncSession, user_id: int) -> None:
    await db.execute(text("DELETE FROM users WHERE id = :id"), {"id": user_id})
    await db.commit()


# ---------------------------------------------------------------
# Roles
# ---------------------------------------------------------------

async def get_roles(db: AsyncSession) -> List[Dict]:
    sql = text("SELECT id, name, color, deletable, editable, additional_access FROM roles")
    return (await db.execute(sql)).mappings().all()


async def get_role_by_id(db: AsyncSession, role_id: int) -> Optional[Dict]:
    sql = text("SELECT id, name, color, deletable, editable FROM roles WHERE id = :id")
    row = (await db.execute(sql, {"id": role_id})).mappings().first()
    return dict(row) if row else None


async def new_rol(db: AsyncSession, name: str, color: str, additional_access: int) -> Dict:
    try:
        await db.execute(
            text("INSERT INTO roles (name, color, additional_access) VALUES (:name, :color, :aa)"),
            {"name": name, "color": color, "aa": additional_access},
        )
        await db.commit()
        return {"error": False}
    except Exception as e:
        await db.rollback()
        msg = "Ese rol ya existe." if "1062" in str(e) else str(e)
        return {"error": True, "message": msg}


async def edit_rol(
    db: AsyncSession,
    role_id: int,
    additional_access: int,
    nombre: Optional[str] = None,
    color: Optional[str] = None,
) -> Dict:
    rol = await get_role_by_id(db, role_id)
    if not rol or not rol.get("editable"):
        return {"error": True, "message": "El rol no es editable."}

    parts = ["additional_access = :aa"]
    params: Dict[str, Any] = {"aa": additional_access, "id": role_id}
    if nombre is not None:
        parts.append("name = :nombre")
        params["nombre"] = nombre
    if color is not None:
        parts.append("color = :color")
        params["color"] = color

    sql = text(f"UPDATE roles SET {', '.join(parts)} WHERE id = :id")  # noqa: S608
    await db.execute(sql, params)
    await db.commit()
    return {"error": False, "message": "Rol actualizado exitosamente."}


async def delete_rol(db: AsyncSession, role_id: int) -> Dict:
    rol = await get_role_by_id(db, role_id)
    if not rol or not rol.get("deletable"):
        return {"error": True, "message": "El rol no es eliminable."}
    await db.execute(text("DELETE FROM roles WHERE id = :id"), {"id": role_id})
    await db.commit()
    return {"error": False, "message": "Rol eliminado exitosamente."}


# ---------------------------------------------------------------
# Endpoints / RBAC
# ---------------------------------------------------------------

async def get_endpoints(db: AsyncSession) -> List[Dict]:
    sql = text("SELECT id, route, method, description, tags, added FROM endpoints")
    return (await db.execute(sql)).mappings().all()


async def get_endpoints_by_role(db: AsyncSession, role_id: int, include_all: bool = False) -> List[Dict]:
    if include_all:
        sql = text("""
            SELECT e.id AS endpoint_id, e.route, e.method, e.description, e.tags,
                   CASE WHEN re.role_id IS NOT NULL THEN 1 ELSE 0 END AS assigned
            FROM endpoints e
            LEFT JOIN role_endpoints re ON e.id = re.endpoint_id AND re.role_id = :role
        """)
    else:
        sql = text("""
            SELECT re.role_id, e.id AS endpoint_id, e.route, e.method, e.description, e.tags
            FROM role_endpoints re
            INNER JOIN endpoints e ON e.id = re.endpoint_id
            WHERE re.role_id = :role
        """)
    return (await db.execute(sql, {"role": role_id})).mappings().all()


async def edit_endpoints_by_role(
    db: AsyncSession, role_id: int, endpoint_ids: List[int], allow: bool
) -> None:
    """
    Asigna (allow=True) o revoca (allow=False) endpoints a un rol.
    Usa queries individuales parametrizadas para evitar inyección SQL
    (la versión PHP usaba implode() — vulnerabilidad corregida aquí).
    """
    for eid in [int(e) for e in endpoint_ids]:
        if allow:
            await db.execute(
                text("INSERT IGNORE INTO role_endpoints (role_id, endpoint_id) VALUES (:rid, :eid)"),
                {"rid": role_id, "eid": eid},
            )
        else:
            await db.execute(
                text("DELETE FROM role_endpoints WHERE role_id = :rid AND endpoint_id = :eid"),
                {"rid": role_id, "eid": eid},
            )
    await db.commit()


async def get_roles_by_path(db: AsyncSession, route: str) -> List[Dict]:
    """Devuelve los roles que tienen acceso a una ruta concreta (para RBAC)."""
    sql = text("""
        SELECT re.role_id, re.endpoint_id, e.route, e.method, e.description, r.name AS role_name
        FROM role_endpoints re
        INNER JOIN endpoints e ON e.id = re.endpoint_id
        INNER JOIN roles r     ON r.id = re.role_id
        WHERE e.route = :path
    """)
    return (await db.execute(sql, {"path": route})).mappings().all()


async def get_emails_by_endpoint(db: AsyncSession, endpoint_name: str) -> List[str]:
    sql = text("""
        SELECT DISTINCT u.email
        FROM endpoints e
        JOIN role_endpoints re ON re.endpoint_id = e.id
        JOIN roles r           ON r.id = re.role_id
        JOIN user_roles ur     ON ur.role_id = r.id
        JOIN users u           ON u.id = ur.user_id
        WHERE e.route = :route
    """)
    rows = (await db.execute(sql, {"route": endpoint_name})).scalars().all()
    return list(rows)


# ---------------------------------------------------------------
# Tokens Bearer (11_)
# ---------------------------------------------------------------

async def get_tokens_user(db: AsyncSession, user_id: int) -> List[Dict]:
    sql = text("""
        SELECT t.id, t.name, t.created, t.expired
        FROM tokens t
        INNER JOIN user_tokens ut ON ut.token_id = t.id
        WHERE ut.user_id = :id
    """)
    return (await db.execute(sql, {"id": user_id})).mappings().all()


async def create_token_user(
    db: AsyncSession, user_id: int, name: str, expired_ts: int, token_hash: str
) -> str:
    """
    Inserta el token (ya hasheado externamente) y la relación user_tokens.
    Devuelve el token en texto plano para enviarlo al cliente UNA SOLA VEZ.
    """
    import datetime
    expired_dt = datetime.datetime.fromtimestamp(expired_ts).strftime("%Y-%m-%d %H:%M:%S")

    await db.execute(
        text("INSERT INTO tokens (name, expired, hash) VALUES (:name, :expired, :hash)"),
        {"name": name, "expired": expired_dt, "hash": token_hash},
    )
    await db.flush()
    row = (await db.execute(text("SELECT LAST_INSERT_ID() AS lid"))).mappings().first()
    token_id = row["lid"]

    await db.execute(
        text("INSERT INTO user_tokens (user_id, token_id) VALUES (:uid, :tid)"),
        {"uid": user_id, "tid": token_id},
    )
    await db.commit()
    return token_id


async def delete_token_user(db: AsyncSession, user_id: int, token_id: int) -> None:
    sql = text("""
        DELETE t FROM tokens t
        INNER JOIN user_tokens ut ON ut.token_id = t.id
        WHERE ut.user_id = :user AND t.id = :tid
    """)
    await db.execute(sql, {"user": user_id, "tid": token_id})
    await db.commit()


# ---------------------------------------------------------------
# Cards home (panel de inicio)
# ---------------------------------------------------------------

async def get_cards_home_by_user(db: AsyncSession, user_id: int) -> List[Dict]:
    """
    Devuelve las cards del panel de inicio visibles para el usuario.
    Cross-DB: octopus_new.cards_home + octopus_users.role_endpoints.
    """
    sql = text("""
        SELECT ch.title, ch.description, ch.position, ch.img, e.route AS url
        FROM octopus_new.cards_home ch
        INNER JOIN role_endpoints re ON re.endpoint_id = ch.endpoint_id
        INNER JOIN endpoints e       ON e.id = ch.endpoint_id
        INNER JOIN user_roles ur     ON ur.role_id = re.role_id
        WHERE ur.user_id = :id
        GROUP BY url, ch.img, ch.position, ch.description, ch.title
        ORDER BY ch.position ASC
    """)
    return (await db.execute(sql, {"id": user_id})).mappings().all()
