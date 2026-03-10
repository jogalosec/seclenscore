"""
Servicio misceláneos Sprint 7:
- Organizaciones / Direcciones / Áreas
- Riesgos
- OSA
- Suscripciones
- Dashboard ERS + GBU
- Email
"""
import json
from typing import Optional

from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

from app.db import octopus_serv_sdlc as db_sdlc


# ── Organizaciones / Direcciones / Áreas ──────────────────────────────────────
# Los tipos de activo son fijos: Organizacion=94, Area=123
# Se obtienen usando los métodos de activos existentes.

async def get_organizaciones(db: AsyncSession) -> list[dict]:
    sql = text("""
        SELECT id, nombre, activo_id AS tipo, archivado
        FROM activos
        WHERE activo_id = 94 AND archivado = 0
        ORDER BY nombre
    """)
    result = await db.execute(sql)
    return [dict(r._mapping) for r in result.fetchall()]


async def get_direcciones(db: AsyncSession, organizacion_id: int) -> list[dict]:
    """Hijos de tipo 'Dirección' de la organización dada — CTE recursiva."""
    sql = text("""
        WITH RECURSIVE RecursivoHijos AS (
            SELECT id, nombre, padre, tipo, tipo_id, archivado
            FROM vistafamiliaactivos WHERE id = :id
            UNION ALL
            SELECT vp.id, vp.nombre, vp.padre, vp.tipo, vp.tipo_id, vp.archivado
            FROM vistafamiliaactivos vp
            INNER JOIN RecursivoHijos rh ON vp.padre = rh.id
        )
        SELECT padre, nombre, MIN(id) AS id, MIN(tipo) AS tipo,
               MIN(tipo_id) AS tipo_id, MIN(archivado) AS archivado
        FROM RecursivoHijos
        WHERE tipo = 'Dirección'
        GROUP BY padre, nombre
    """)
    result = await db.execute(sql, {"id": organizacion_id})
    return [dict(r._mapping) for r in result.fetchall()]


async def get_areas(db: AsyncSession) -> list[dict]:
    sql = text("""
        SELECT id, nombre, activo_id AS tipo, archivado
        FROM activos
        WHERE activo_id = 123 AND archivado = 0
        ORDER BY nombre
    """)
    result = await db.execute(sql)
    return [dict(r._mapping) for r in result.fetchall()]


# ── Riesgos ───────────────────────────────────────────────────────────────────

async def get_riesgos(db: AsyncSession, serv_id: int, sistemas: list[int]) -> list[dict]:
    """
    Calcula el vector de riesgos para un servicio + sus sistemas.
    Combina BIA del servicio con las últimas evaluaciones de cada sistema.
    Equivale a GET /api/getRiesgos de index.php.
    """
    from app.services.evaluaciones_service import calcular_bia  # import lazy

    array_eval = []

    # BIA del servicio
    bia_sql = text("""
        SELECT meta_value FROM evaluaciones
        WHERE activo_id = :id AND meta_key = 'bia'
        ORDER BY fecha DESC LIMIT 1
    """)
    bia_res = await db.execute(bia_sql, {"id": serv_id})
    bia_row = bia_res.fetchone()
    if bia_row:
        try:
            meta = bia_row._mapping["meta_value"]
            bia_data = json.loads(meta) if isinstance(meta, str) else meta
            array_eval.append(calcular_bia(bia_data))
        except Exception:
            pass

    # Evaluaciones de cada sistema
    for sistema_id in sistemas:
        eval_sql = text("""
            SELECT id, meta_key FROM evaluaciones
            WHERE activo_id = :id AND meta_key = 'preguntas'
            ORDER BY fecha DESC LIMIT 1
        """)
        eval_res = await db.execute(eval_sql, {"id": sistema_id})
        eval_row = eval_res.fetchone()
        if eval_row:
            preguntas_sql = text("""
                SELECT meta_value FROM evaluaciones
                WHERE id = :id AND meta_key = 'preguntas'
            """)
            preguntas_res = await db.execute(preguntas_sql, {"id": eval_row._mapping["id"]})
            preguntas_row = preguntas_res.fetchone()
            if preguntas_row:
                try:
                    preguntas = json.loads(preguntas_row._mapping["meta_value"])
                    array_eval.append({"sistema_id": sistema_id, "preguntas": preguntas})
                except Exception:
                    pass

    return array_eval


async def get_riesgos_servicio(db: AsyncSession, activo_id: int, fecha: str) -> list[dict]:
    """Riesgos de un servicio concreto por fecha de evaluación."""
    if fecha and fecha != "null":
        sql = text("""
            SELECT meta_value FROM evaluaciones
            WHERE id = :fecha AND meta_key = 'preguntas'
        """)
        result = await db.execute(sql, {"fecha": fecha})
        row = result.fetchone()
        if row:
            try:
                return json.loads(row._mapping["meta_value"])
            except Exception:
                pass
    return []


# ── OSA ───────────────────────────────────────────────────────────────────────

async def get_osa_by_type(db: AsyncSession, tipo: str) -> list[dict]:
    return await db_sdlc.get_osa_by_type(db, tipo)


async def get_osa_eval_by_revision(db: AsyncSession, revision_id: int):
    return await db_sdlc.get_osa_eval_by_revision(db, revision_id)


# ── Suscripciones ─────────────────────────────────────────────────────────────

async def get_relacion_suscripcion(db: AsyncSession, id_suscripcion: str) -> dict:
    activo = await db_sdlc.get_activo_by_sus_id(db, id_suscripcion)
    if activo:
        return {"error": False, "relation": True, "activo": activo}
    return {"error": False, "relation": False}


async def get_suscription_relations(db: AsyncSession) -> list[dict]:
    return await db_sdlc.get_suscription_relations(db)


async def insert_suscription_relation(db: AsyncSession, id_activo: int,
                                      subscriptions: list[str],
                                      subscription_names: list[str]) -> list[dict]:
    return await db_sdlc.insert_suscription_relation(db, id_activo, subscriptions, subscription_names)


async def delete_suscription_relation(db: AsyncSession, suscription_id: str) -> dict:
    return await db_sdlc.delete_suscription_relation(db, suscription_id)


async def edit_suscription_relation(db: AsyncSession, id: int, data: dict) -> dict:
    await db_sdlc.edit_suscription_relation(db, id, data)
    return {"error": False, "message": "Relación actualizada"}


# ── Dashboard ERS + GBU ───────────────────────────────────────────────────────

async def get_dashboard_ers(db: AsyncSession) -> dict:
    """
    Cuenta evaluaciones tipo ERS (activos expuestos evaluados).
    Equivale a GET /api/getDashboardErs.
    """
    sql = text("""
        SELECT COUNT(DISTINCT activo_id) AS total,
               COUNT(DISTINCT CASE WHEN fecha >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                              THEN activo_id END) AS ultimos_90d
        FROM evaluaciones e
        INNER JOIN activos a ON e.activo_id = a.id
        WHERE e.meta_key = 'preguntas' AND a.expuesto = 1 AND a.archivado = 0
    """)
    result = await db.execute(sql)
    row = result.fetchone()
    if row:
        return {"error": False, **dict(row._mapping)}
    return {"error": False, "total": 0, "ultimos_90d": 0}


async def get_dashboard_gbu(db: AsyncSession, gbu_id: int = 27384) -> list[dict]:
    """
    Hijos de la unidad GBU raíz — equivale a GET /api/getDashboardGBU.
    En el PHP original usa id fijo 27384.
    """
    sql = text("""
        WITH RECURSIVE RecursivoHijos AS (
            SELECT id, nombre, padre, tipo, tipo_id, archivado
            FROM vistafamiliaactivos WHERE id = :id
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
    result = await db.execute(sql, {"id": gbu_id})
    return [dict(r._mapping) for r in result.fetchall()]


# ── Email ─────────────────────────────────────────────────────────────────────

async def send_email(to: str, asunto: str, body: str, alternbody: str = "") -> dict:
    """
    Envía un email con la plantilla informacional — equivale a POST /api/sendEmail.
    Usa la configuración SMTP de settings (ya migrada de config.ini).
    """
    from app.core.config import get_settings
    settings = get_settings()

    template = """
    <html><body>
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px">
        <div style="background:#003366;padding:15px;color:white">
            <h2 style="margin:0">SecLensCore — Notificación</h2>
        </div>
        <div style="padding:20px;background:#f9f9f9">
            {info}
        </div>
        <div style="font-size:11px;color:#666;padding:10px;text-align:center">
            Este mensaje ha sido generado automáticamente.
        </div>
    </div>
    </body></html>
    """.replace("{info}", body)

    try:
        import aiosmtplib
        from email.mime.multipart import MIMEMultipart
        from email.mime.text import MIMEText

        msg = MIMEMultipart("alternative")
        msg["Subject"] = asunto
        msg["From"]    = settings.SMTP_FROM
        msg["To"]      = to

        if alternbody:
            msg.attach(MIMEText(alternbody, "plain"))
        msg.attach(MIMEText(template, "html"))

        await aiosmtplib.send(
            msg,
            hostname=settings.SMTP_HOST,
            port=settings.SMTP_PORT,
            username=settings.SMTP_USER or None,
            password=settings.SMTP_PASSWORD.get_secret_value() or None,
            use_tls=settings.SMTP_TLS,
        )
        return {"error": False, "message": "Email enviado correctamente"}
    except Exception as e:
        return {"error": True, "message": str(e)}
