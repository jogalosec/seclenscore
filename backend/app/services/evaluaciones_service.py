"""
Servicio de Evaluaciones.
Incluye el cálculo BIA completo portado desde PHP (calcularBia).
"""
import json
from typing import Any, Dict, List, Optional

from sqlalchemy.ext.asyncio import AsyncSession

from app.db import octopus_serv_eval as db_eval


# ---------------------------------------------------------------
# BIA — cálculo de dimensiones
# ---------------------------------------------------------------

# Mapeo pregunta → (dimensión, sub-dimensión)
# Dimensiones: Con (Confidencialidad), Int (Integridad), Dis (Disponibilidad)
# Sub-dimensiones: Fin (Financiero), Op (Operacional), Le (Legal),
#                  Rep (Reputacional), Sal (Salud/Seguridad), Pri (Privacidad)
_PREGUNTA_MAP: Dict[str, tuple] = {
    # Disponibilidad — Financiero
    "p1":  ("Dis", "Fin"), "p2":  ("Dis", "Fin"), "p3":  ("Dis", "Fin"),
    "p4":  ("Dis", "Fin"), "p5":  ("Dis", "Fin"),
    # Disponibilidad — Operacional
    "p6":  ("Dis", "Op"),  "p7":  ("Dis", "Op"),  "p8":  ("Dis", "Op"),
    "p9":  ("Dis", "Op"),  "p10": ("Dis", "Op"),
    # Disponibilidad — Legal
    "p11": ("Dis", "Le"),  "p12": ("Dis", "Le"),  "p13": ("Dis", "Le"),
    # Disponibilidad — Reputacional
    "p14": ("Dis", "Rep"), "p15": ("Dis", "Rep"), "p16": ("Dis", "Rep"),
    # Disponibilidad — Salud
    "p17": ("Dis", "Sal"),
    # Confidencialidad — Financiero
    "p18": ("Con", "Fin"), "p19": ("Con", "Fin"),
    # Confidencialidad — Operacional
    "p20": ("Con", "Op"),  "p21": ("Con", "Op"),
    # Confidencialidad — Legal
    "p22": ("Con", "Le"),  "p23": ("Con", "Le"),  "p24": ("Con", "Le"),
    # Confidencialidad — Reputacional
    "p25": ("Con", "Rep"), "p26": ("Con", "Rep"),
    # Confidencialidad — Privacidad
    "p27": ("Con", "Pri"), "p28": ("Con", "Pri"), "p29": ("Con", "Pri"),
    # Integridad — Financiero
    "p30": ("Int", "Fin"), "p31": ("Int", "Fin"),
    # Integridad — Operacional
    "p32": ("Int", "Op"),  "p33": ("Int", "Op"),  "p34": ("Int", "Op"),
    # Integridad — Legal
    "p35": ("Int", "Le"),  "p36": ("Int", "Le"),
    # Integridad — Reputacional
    "p37": ("Int", "Rep"), "p38": ("Int", "Rep"),
    # Integridad — Salud
    "p39": ("Int", "Sal"),
    # Integridad — Privacidad
    "p40": ("Int", "Pri"), "p41": ("Int", "Pri"),
    # Disponibilidad — Privacidad (extra)
    "p42": ("Dis", "Pri"),
    # Disponibilidad — Salud (extra)
    "p43": ("Dis", "Sal"), "p44": ("Dis", "Sal"),
    # Confidencialidad — Salud (extra)
    "p45": ("Con", "Sal"),
    # Integridad — Privacidad (extra)
    "p46": ("Int", "Pri"),
}

_DIMENSIONES = ["Con", "Int", "Dis"]
_SUBDIMENSIONES = ["Fin", "Op", "Le", "Rep", "Sal", "Pri"]

# Pesos de sub-dimensiones por dimensión (pueden ajustarse)
_PESOS: Dict[str, Dict[str, float]] = {
    "Con": {"Fin": 1.0, "Op": 1.0, "Le": 1.0, "Rep": 1.0, "Sal": 1.0, "Pri": 1.0},
    "Int": {"Fin": 1.0, "Op": 1.0, "Le": 1.0, "Rep": 1.0, "Sal": 1.0, "Pri": 1.0},
    "Dis": {"Fin": 1.0, "Op": 1.0, "Le": 1.0, "Rep": 1.0, "Sal": 1.0, "Pri": 1.0},
}


def calcular_bia(respuestas: Dict[str, Any]) -> Dict[str, Any]:
    """
    Calcula las dimensiones BIA a partir de las respuestas del formulario.
    Porta la lógica de calcularBia() de PHP.

    Respuestas esperadas: {"p1": 0-4, "p2": 0-4, ..., "p46": 0-4}
    Retorna: {
        "Con": {"total": float, "Fin": float, "Op": float, ...},
        "Int": {...},
        "Dis": {...},
        "global": float
    }
    """
    # Acumuladores: suma de valores y conteo por (dimensión, sub-dimensión)
    sumas: Dict[str, Dict[str, float]] = {
        d: {s: 0.0 for s in _SUBDIMENSIONES} for d in _DIMENSIONES
    }
    conteos: Dict[str, Dict[str, int]] = {
        d: {s: 0 for s in _SUBDIMENSIONES} for d in _DIMENSIONES
    }

    for pregunta, valor in respuestas.items():
        mapping = _PREGUNTA_MAP.get(pregunta)
        if mapping is None:
            continue
        dim, subdim = mapping
        try:
            val = float(valor)
        except (TypeError, ValueError):
            continue
        sumas[dim][subdim] += val
        conteos[dim][subdim] += 1

    resultado: Dict[str, Any] = {}
    totales_dim: List[float] = []

    for dim in _DIMENSIONES:
        sub_medias: Dict[str, float] = {}
        for subdim in _SUBDIMENSIONES:
            cnt = conteos[dim][subdim]
            sub_medias[subdim] = round(sumas[dim][subdim] / cnt, 4) if cnt > 0 else 0.0

        # Total de dimensión = promedio ponderado de sub-dimensiones con datos
        pesos = _PESOS[dim]
        valores_ponderados = [
            sub_medias[s] * pesos[s]
            for s in _SUBDIMENSIONES
            if conteos[dim][s] > 0
        ]
        total_dim = round(sum(valores_ponderados) / len(valores_ponderados), 4) if valores_ponderados else 0.0

        resultado[dim] = {"total": total_dim, **sub_medias}
        totales_dim.append(total_dim)

    resultado["global"] = round(sum(totales_dim) / len(totales_dim), 4) if totales_dim else 0.0
    return resultado


# ---------------------------------------------------------------
# Servicios de evaluación
# ---------------------------------------------------------------

async def get_bia_activo(db: AsyncSession, activo_id: int) -> Optional[Dict]:
    row = await db_eval.get_bia(db, activo_id)
    if not row:
        return None
    data = dict(row)
    if isinstance(data.get("meta_value"), str):
        try:
            data["meta_value"] = json.loads(data["meta_value"])
        except (ValueError, TypeError):
            pass
    return data


async def guardar_bia(
    db: AsyncSession, activo_id: int, respuestas: Dict, user_id: Optional[int] = None
) -> Dict:
    """Guarda el BIA y devuelve el cálculo de dimensiones."""
    await db_eval.save_bia(db, activo_id, respuestas, user_id)
    calculo = calcular_bia(respuestas)
    return {"error": False, "message": "BIA guardado correctamente.", "bia": calculo}


async def get_evaluaciones_activo(
    db: AsyncSession, activo_id: int, tipo: Optional[str] = None
) -> List[Dict]:
    rows = await db_eval.get_eval_by_activo_id(db, activo_id, tipo)
    result = []
    for row in rows:
        d = dict(row)
        if isinstance(d.get("meta_value"), str):
            try:
                d["meta_value"] = json.loads(d["meta_value"])
            except (ValueError, TypeError):
                pass
        result.append(d)
    return result


async def get_evaluacion_by_id(db: AsyncSession, eval_id: int) -> Optional[Dict]:
    row = await db_eval.get_eval_by_id(db, eval_id)
    if not row:
        return None
    d = dict(row)
    if isinstance(d.get("meta_value"), str):
        try:
            d["meta_value"] = json.loads(d["meta_value"])
        except (ValueError, TypeError):
            pass
    return d


async def guardar_evaluacion(
    db: AsyncSession,
    activo_id: int,
    datos: Dict,
    meta_key: str = "preguntas",
    user_id: Optional[int] = None,
) -> Dict:
    await db_eval.set_meta_value(db, activo_id, datos, meta_key, user_id)
    return {"error": False, "message": "Evaluación guardada correctamente."}


async def editar_evaluacion(
    db: AsyncSession,
    eval_id: Optional[int],
    version_id: Optional[int],
    datos: Dict,
    nombre: str = "Edición",
) -> Dict:
    await db_eval.edit_eval(db, eval_id, version_id, datos, nombre)
    return {"error": False, "message": "Versión de evaluación creada correctamente."}


async def get_historial_evaluaciones(
    db: AsyncSession, activo_id: int, all_versions: bool = False
) -> List[Dict]:
    rows = await db_eval.get_fecha_evaluaciones(db, activo_id, all_versions)
    return [dict(r) for r in rows]


async def get_evaluaciones_sistema(db: AsyncSession, activo_id: int) -> List[Dict]:
    return await db_eval.get_evaluaciones_sistema(db, activo_id)


async def get_preguntas_evaluacion(
    db: AsyncSession, eval_id: int, es_version: bool = False
) -> Optional[Dict]:
    if es_version:
        row = await db_eval.get_preguntas_version_by_fecha(db, eval_id)
    else:
        row = await db_eval.get_preguntas_evaluacion_by_fecha(db, eval_id)

    if not row:
        return None
    d = dict(row)
    if isinstance(d.get("preguntas"), str):
        try:
            d["preguntas"] = json.loads(d["preguntas"])
        except (ValueError, TypeError):
            pass
    return d


async def guardar_eval_osa(db: AsyncSession, revision_id: int, datos: Dict) -> Dict:
    parametros = {"revision_id": revision_id, **datos}
    await db_eval.save_eval_osa(db, parametros)
    return {"error": False, "message": "Evaluación OSA guardada correctamente."}


async def get_pac_eval(db: AsyncSession, activo_id: int, fecha: str) -> List[Dict]:
    rows = await db_eval.get_pac_eval_servicio(db, activo_id, fecha)
    result = []
    for row in rows:
        d = dict(row)
        if isinstance(d.get("meta_value"), str):
            try:
                d["meta_value"] = json.loads(d["meta_value"])
            except (ValueError, TypeError):
                pass
        result.append(d)
    return result
