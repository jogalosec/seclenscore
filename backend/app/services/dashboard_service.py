"""Servicio de Dashboard — agrega KPIs de todos los módulos."""
from typing import Any, Dict
from sqlalchemy.ext.asyncio import AsyncSession

from app.db import (
    octopus_serv as db_serv,
    octopus_serv_eval as db_eval,
    octopus_serv_evs as db_evs,
    octopus_serv_pac as db_pac,
    octopus_kpms as db_kpms,
    octopus_users as db_users,
)


async def get_dashboard_activos(db_serv_: AsyncSession) -> Dict:
    """KPIs de activos: total, archivados, expuestos, por tipo."""
    sql_total = "SELECT COUNT(*) AS total FROM activos WHERE archivado = 0"
    sql_arch  = "SELECT COUNT(*) AS total FROM activos WHERE archivado = 1"
    sql_exp   = "SELECT COUNT(*) AS total FROM activos WHERE expuesto = 1 AND archivado = 0"

    from sqlalchemy import text
    total   = (await db_serv_.execute(text(sql_total))).scalar() or 0
    archiv  = (await db_serv_.execute(text(sql_arch))).scalar() or 0
    expuest = (await db_serv_.execute(text(sql_exp))).scalar() or 0

    tipos = (await db_serv_.execute(text("""
        SELECT t.nombre AS tipo, COUNT(a.id) AS total
        FROM activos a
        JOIN tipos_activo t ON t.id = a.tipo
        WHERE a.archivado = 0
        GROUP BY t.nombre
        ORDER BY total DESC
        LIMIT 10
    """))).mappings().all()

    return {
        "total": total,
        "archivados": archiv,
        "expuestos": expuest,
        "por_tipo": [dict(r) for r in tipos],
    }


async def get_dashboard_bia(db_serv_: AsyncSession) -> Dict:
    """Distribución de niveles BIA (global) entre activos evaluados."""
    from sqlalchemy import text
    rows = (await db_serv_.execute(text("""
        SELECT activo_id, meta_value
        FROM evaluaciones
        WHERE meta_key = 'bia'
        AND fecha = (
            SELECT MAX(e2.fecha) FROM evaluaciones e2
            WHERE e2.activo_id = evaluaciones.activo_id AND e2.meta_key = 'bia'
        )
    """))).mappings().all()

    import json
    niveles = {"critico": 0, "alto": 0, "medio": 0, "bajo": 0, "sin_datos": 0}
    for row in rows:
        try:
            mv = json.loads(row["meta_value"]) if isinstance(row["meta_value"], str) else row["meta_value"]
            global_val = mv.get("global", 0) if isinstance(mv, dict) else 0
            if global_val >= 3.5:
                niveles["critico"] += 1
            elif global_val >= 2.5:
                niveles["alto"] += 1
            elif global_val >= 1.5:
                niveles["medio"] += 1
            else:
                niveles["bajo"] += 1
        except Exception:
            niveles["sin_datos"] += 1

    return {"total_evaluados": len(rows), "distribucion": niveles}


async def get_dashboard_ecr(db_serv_: AsyncSession) -> Dict:
    """Evaluaciones ECR: total, con última evaluación en últimos 90 días."""
    from sqlalchemy import text
    total = (await db_serv_.execute(text(
        "SELECT COUNT(DISTINCT activo_id) AS total FROM evaluaciones WHERE meta_key = 'preguntas'"
    ))).scalar() or 0
    recientes = (await db_serv_.execute(text(
        "SELECT COUNT(DISTINCT activo_id) AS total FROM evaluaciones WHERE meta_key = 'preguntas' AND fecha >= DATE_SUB(NOW(), INTERVAL 90 DAY)"
    ))).scalar() or 0
    return {"total_evaluados": total, "evaluados_90_dias": recientes}


async def get_dashboard_pentest(db_serv_: AsyncSession) -> Dict:
    """KPIs de pentest: por estado."""
    from sqlalchemy import text
    rows = (await db_serv_.execute(text(
        "SELECT estado, COUNT(*) AS total FROM pentest GROUP BY estado"
    ))).mappings().all()
    return {"por_estado": {r["estado"]: r["total"] for r in rows}}


async def get_dashboard_pac(db_serv_: AsyncSession) -> Dict:
    """KPIs de PAC: por estado."""
    from sqlalchemy import text
    rows = (await db_serv_.execute(text(
        "SELECT estado, COUNT(*) AS total FROM seguimientopac GROUP BY estado"
    ))).mappings().all()
    total_planes = (await db_serv_.execute(text(
        "SELECT COUNT(*) FROM plan WHERE tipo = 'pac'"
    ))).scalar() or 0
    return {"total_planes": total_planes, "seguimiento_por_estado": {r["estado"]: r["total"] for r in rows}}


async def get_dashboard_completo(
    db_serv_: AsyncSession,
) -> Dict[str, Any]:
    """Agrega todos los KPIs del dashboard en una sola respuesta."""
    activos  = await get_dashboard_activos(db_serv_)
    bia      = await get_dashboard_bia(db_serv_)
    ecr      = await get_dashboard_ecr(db_serv_)
    pentest  = await get_dashboard_pentest(db_serv_)
    pac      = await get_dashboard_pac(db_serv_)

    return {
        "error": False,
        "activos": activos,
        "bia": bia,
        "ecr": ecr,
        "pentest": pentest,
        "pac": pac,
    }
