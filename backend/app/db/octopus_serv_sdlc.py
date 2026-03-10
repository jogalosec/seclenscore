"""
Capa DB — Kiuwan + SDLC (tabla kiuwan y tabla sdlc en octopus_serv).
Replica los métodos de la clase Pentest de operationsDB.php relacionados con SDLC/Kiuwan.
"""
from typing import Any, Optional

from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession


# ── Kiuwan ────────────────────────────────────────────────────────────────────

async def get_kiuwan_data(db: AsyncSession) -> list[dict]:
    sql = text("""
        SELECT id, app_name, creation_date, code, analysis_code,
               analysis_url, analysis_date, cumple_kpm, registrada
        FROM kiuwan
    """)
    result = await db.execute(sql)
    return [dict(r._mapping) for r in result.fetchall()]


async def insert_kiuwan_data(db: AsyncSession, data: dict) -> None:
    sql = text("""
        INSERT INTO kiuwan
            (app_name, creation_date, code, analysis_code, analysis_url, analysis_date, cumple_kpm)
        VALUES
            (:app_name, :creation_date, :code, :analysis_code, :analysis_url, :analysis_date, :cumple_kpm)
        ON DUPLICATE KEY UPDATE
            code          = IFNULL(VALUES(code), code),
            analysis_code = IFNULL(VALUES(analysis_code), analysis_code),
            analysis_url  = IFNULL(VALUES(analysis_url), analysis_url),
            analysis_date = IFNULL(VALUES(analysis_date), analysis_date),
            cumple_kpm    = IFNULL(VALUES(cumple_kpm), cumple_kpm)
    """)
    await db.execute(sql, {
        "app_name":      data.get("app_name"),
        "creation_date": data.get("creation_date"),
        "code":          data.get("code"),
        "analysis_code": data.get("analysis_code"),
        "analysis_url":  data.get("analysis_url"),
        "analysis_date": data.get("analysis_date"),
        "cumple_kpm":    None,
    })


async def update_cumple_kpm(db: AsyncSession, app_name: str, cumple_kpm: int) -> None:
    sql = text("UPDATE kiuwan SET cumple_kpm = :cumple_kpm WHERE app_name = :app_name")
    await db.execute(sql, {"app_name": app_name, "cumple_kpm": cumple_kpm})


async def update_sonar_kpm(db: AsyncSession, slot_sonarqube: str, cumple_kpm_sonar: int) -> None:
    sql = text("UPDATE sdlc SET cumple_kpm_sonar = :cumple_kpm_sonar WHERE slot_sonarqube = :slot_sonarqube")
    await db.execute(sql, {"slot_sonarqube": slot_sonarqube, "cumple_kpm_sonar": cumple_kpm_sonar})


async def set_kiuwan_registrada(db: AsyncSession, kiuwan_id: int, registrada: int) -> None:
    sql = text("UPDATE kiuwan SET registrada = :registrada WHERE id = :id")
    await db.execute(sql, {"id": kiuwan_id, "registrada": registrada})


# ── SDLC ──────────────────────────────────────────────────────────────────────

async def get_sdlc(db: AsyncSession, app: Optional[str] = None) -> list[dict]:
    sql_str = """
        SELECT id, direccion_id, area_id, producto_id, CMM, analisis, comentarios,
               app, url_sonar, fecha_analisis_kiuwan, kiuwan_id,
               slot_sonarqube, fecha_analisis_sonar, cumple_kpm_sonar
        FROM sdlc
    """
    params: dict = {}
    if app:
        sql_str += " WHERE app = :app"
        params["app"] = app
    result = await db.execute(text(sql_str), params)
    return [dict(r._mapping) for r in result.fetchall()]


async def add_sdlc(db: AsyncSession, data: dict) -> None:
    sql = text("""
        INSERT INTO sdlc
            (direccion_id, area_id, producto_id, kiuwan_id, CMM, analisis, comentarios,
             url_sonar, fecha_analisis_kiuwan, slot_sonarqube, fecha_analisis_sonar, app)
        VALUES
            (:direccion_id, :area_id, :producto_id, :kiuwan_id, :CMM, :analisis,
             :comentarios, :url_sonar, :fecha_analisis_kiuwan,
             :slot_sonarqube, :fecha_analisis_sonar, :app)
    """)
    await db.execute(sql, {
        "direccion_id":         data.get("Direccion"),
        "area_id":              data.get("Area"),
        "producto_id":          data.get("Producto"),
        "kiuwan_id":            data.get("kiuwan_id") if data.get("app") != "Sonarqube" else None,
        "CMM":                  data.get("CMM"),
        "analisis":             data.get("Analisis"),
        "comentarios":          data.get("Comentarios", ""),
        "url_sonar":            data.get("url_sonar", ""),
        "fecha_analisis_kiuwan": data.get("fecha_analisis_kiuwan") if data.get("app") != "Sonarqube" else None,
        "slot_sonarqube":       data.get("sonarqube_slot") if data.get("app") != "Kiuwan" else None,
        "fecha_analisis_sonar": data.get("fecha_analisis_sonarqube") if data.get("app") != "Kiuwan" else None,
        "app":                  data.get("app"),
    })


async def modificar_app_sdlc(db: AsyncSession, id: int, data: dict) -> None:
    """Actualiza campos opcionales de una entrada SDLC."""
    fields = {}
    if data.get("Comentarios"):
        fields["comentarios"] = data["Comentarios"]
    if data.get("CMM") and data["CMM"] != "Ninguno":
        fields["CMM"] = data["CMM"]
    if data.get("url_sonar"):
        fields["url_sonar"] = data["url_sonar"]
    if not fields:
        return
    set_clause = ", ".join(f"{k} = :{k}" for k in fields)
    params = {**fields, "id": id}
    await db.execute(text(f"UPDATE sdlc SET {set_clause} WHERE id = :id"), params)


async def modificar_sdlc_by_kiuwan(db: AsyncSession, data: dict) -> bool:
    """Actualiza SDLC por kiuwan_id. Devuelve True si afectó alguna fila."""
    sql = text("""
        UPDATE sdlc
        SET direccion_id = :direccion_id, area_id = :area_id, producto_id = :producto_id,
            CMM = :CMM, analisis = :analisis, comentarios = :comentarios,
            url_sonar = :url_sonar, fecha_analisis_kiuwan = :fecha_analisis_kiuwan
        WHERE kiuwan_id = :kiuwan_id
    """)
    result = await db.execute(sql, {
        "direccion_id":          data.get("Direccion"),
        "area_id":               data.get("Area"),
        "producto_id":           data.get("Producto"),
        "CMM":                   data.get("CMM"),
        "analisis":              data.get("Analisis"),
        "comentarios":           data.get("Comentarios", ""),
        "url_sonar":             data.get("url_sonar", ""),
        "fecha_analisis_kiuwan": data.get("fecha_analisis_kiuwan"),
        "kiuwan_id":             data.get("kiuwan_id"),
    })
    return result.rowcount > 0


async def eliminar_app_sdlc(db: AsyncSession, id: int, kiuwan_id: Optional[int], app: str) -> None:
    await db.execute(text("DELETE FROM sdlc WHERE id = :id"), {"id": id})
    if app.lower() == "kiuwan" and kiuwan_id:
        await db.execute(
            text("UPDATE kiuwan SET registrada = 0 WHERE id = :id"),
            {"id": kiuwan_id}
        )


# ── OSA ───────────────────────────────────────────────────────────────────────

async def get_osa_by_type(db: AsyncSession, tipo: str) -> list[dict]:
    sql = text("""
        SELECT id, cod, name, description, type, ciso_value, possible_values, saw_id
        FROM osa WHERE type = :tipo
    """)
    result = await db.execute(sql, {"tipo": tipo})
    return [dict(r._mapping) for r in result.fetchall()]


async def get_osa_eval_by_revision(db: AsyncSession, revision_id: int) -> Optional[dict]:
    sql = text("SELECT * FROM revisiones_has_osa WHERE revision_id = :id")
    result = await db.execute(sql, {"id": revision_id})
    rows = result.fetchall()
    return dict(rows[0]._mapping) if rows else None


# ── Suscripciones ─────────────────────────────────────────────────────────────

async def get_activo_by_sus_id(db: AsyncSession, suscription_id: str) -> Optional[dict]:
    sql = text("""
        SELECT a.id, a.nombre, a.activo_id
        FROM activos a
        INNER JOIN activos_has_suscripciones ahs ON a.id = ahs.id_activo
        WHERE ahs.suscription_id = :suscription_id
    """)
    result = await db.execute(sql, {"suscription_id": suscription_id})
    rows = result.fetchall()
    return dict(rows[0]._mapping) if rows else None


async def get_suscription_relations(db: AsyncSession) -> list[dict]:
    sql = text("""
        SELECT a.id AS id_activo, a.nombre AS nombre_activo,
               a.activo_id AS tipo_activo,
               r.id AS id, r.suscription_name, r.suscription_id
        FROM activos_has_suscripciones r
        INNER JOIN activos a ON r.id_activo = a.id
    """)
    result = await db.execute(sql)
    return [dict(r._mapping) for r in result.fetchall()]


async def insert_suscription_relation(db: AsyncSession, id_activo: int,
                                      subscriptions: list[str],
                                      subscription_names: list[str]) -> list[dict]:
    results = []
    for i, sus_id in enumerate(subscriptions):
        sus_name = subscription_names[i] if i < len(subscription_names) else ""
        check = await db.execute(
            text("SELECT COUNT(*) FROM activos_has_suscripciones WHERE id_activo=:ia AND suscription_id=:si"),
            {"ia": id_activo, "si": sus_id}
        )
        if check.scalar() == 0:
            await db.execute(
                text("INSERT INTO activos_has_suscripciones (id_activo, suscription_id, suscription_name) VALUES (:ia, :si, :sn)"),
                {"ia": id_activo, "si": sus_id, "sn": sus_name}
            )
            results.append({"error": False, "message": "Relación creada"})
        else:
            results.append({"error": True, "message": "La relación ya existe"})
    return results


async def delete_suscription_relation(db: AsyncSession, suscription_id: str) -> dict:
    result = await db.execute(
        text("DELETE FROM activos_has_suscripciones WHERE suscription_id = :si"),
        {"si": suscription_id}
    )
    return {"error": False, "rowsAffected": result.rowcount}


async def edit_suscription_relation(db: AsyncSession, id: int, data: dict) -> None:
    fields = {k: v for k, v in data.items() if k in ("suscription_name", "suscription_id")}
    if not fields:
        return
    set_clause = ", ".join(f"{k} = :{k}" for k in fields)
    await db.execute(text(f"UPDATE activos_has_suscripciones SET {set_clause} WHERE id = :id"),
                     {**fields, "id": id})
