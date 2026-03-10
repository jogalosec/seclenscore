"""
Lógica de negocio — Módulo Normativas.
Equivalente a los handlers de /api/getNormativas, /api/newNormativa, etc. en index.php.
"""
from sqlalchemy.ext.asyncio import AsyncSession

from app.db import octopus_new as db_norm
from app.schemas.common import APIResponse


async def get_normativas_completas(db: AsyncSession) -> dict:
    """
    Devuelve todas las normativas enriquecidas con sus controles y relaciones.
    Equivalente al bloque PHP que itera normativas → controles → relaciones.
    """
    normativas = [dict(n) for n in await db_norm.get_normativas(db)]
    for norm in normativas:
        controles = [dict(c) for c in await db_norm.get_controles_by_norm(db, norm["id"])]
        for control in controles:
            control["relacion"] = [
                dict(r) for r in await db_norm.get_relaciones_control(db, control["id"])
            ]
        norm["controles"] = controles
    return {"error": False, "normativas": normativas,
            "message": "Se han obtenido todas las normativas con sus controles."}


async def get_preguntas_completas(db: AsyncSession) -> dict:
    """Devuelve todas las preguntas con sus relaciones marco."""
    preguntas = [dict(p) for p in await db_norm.get_preguntas(db)]
    for pregunta in preguntas:
        pregunta["relacion"] = [
            dict(r) for r in await db_norm.get_relaciones_pregunta(db, pregunta["id"])
        ]
    return {"error": False, "Preguntas": preguntas,
            "message": "Se han obtenido todas las preguntas."}


async def get_usfs_completas(db: AsyncSession) -> dict:
    """Devuelve todos los USFs con sus relaciones marco."""
    usfs = [dict(u) for u in await db_norm.get_usfs(db)]
    for usf in usfs:
        usf["relacion"] = [
            dict(r) for r in await db_norm.get_relaciones_usf(db, usf["id"])
        ]
    return {"error": False, "USFs": usfs,
            "message": "Se han obtenido todos los USFs."}


async def crear_relacion_completa(db: AsyncSession, control_id: int, relaciones: list) -> dict:
    """
    Crea relaciones masivas control-USF-pregunta.
    Equivalente al handler /api/crearRelacionCompleta de index.php.
    """
    for rel in relaciones:
        usf_id = rel["idUSF"]
        for pregunta in rel["preguntas"]:
            await db_norm.new_relacion_pregunta_control(
                db, pregunta_id=pregunta["id"], control_id=control_id, usf_id=usf_id
            )
    await db.commit()
    relaciones_result = [
        dict(r) for r in await db_norm.get_relaciones_control(db, control_id)
    ]
    return {"error": False, "relaciones": relaciones_result,
            "message": "Relación creada correctamente."}


async def crear_relacion_preguntas(db: AsyncSession, preguntas: list, control_id: int) -> dict:
    """
    Relaciona un control con preguntas cuyos USFs ya están asociados.
    Equivalente al handler /api/crearRelacionPreguntas de index.php.
    """
    for pregunta in preguntas:
        usfs = await db_norm.get_relaciones_pregunta_usf(db, pregunta["id"])
        for usf in usfs:
            await db_norm.new_relacion_pregunta_control(
                db, pregunta_id=pregunta["id"], control_id=control_id, usf_id=usf["id_usf"]
            )
    await db.commit()
    relaciones_result = [
        dict(r) for r in await db_norm.get_relaciones_control(db, control_id)
    ]
    return {"error": False, "relaciones": relaciones_result,
            "message": "Relación creada correctamente."}


async def crear_usf(db: AsyncSession, data: dict) -> dict:
    """Crea un nuevo USF y devuelve el registro creado."""
    await db_norm.new_usf(
        db,
        codigo=data["codigo"],
        nombre=data["nombre"],
        descripcion=data["descripcion"],
        dominio=data["dominio"],
        tipo=data["tipo"],
        id_pac=data["IdPAC"],
    )
    usf = await db_norm.get_usf_by_codigo(db, data["codigo"])
    return {"error": False, "USF": usf, "message": "USF creado correctamente."}


async def crear_pregunta(db: AsyncSession, duda: str, nivel: int) -> dict:
    """Crea una nueva pregunta y devuelve el registro creado con sus relaciones."""
    await db_norm.new_pregunta(db, duda=duda, nivel=nivel)
    pregunta = await db_norm.get_pregunta_by_duda(db, duda)
    if pregunta:
        pregunta["relacion"] = [
            dict(r) for r in await db_norm.get_relaciones_pregunta(db, pregunta["id"])
        ]
    return {"error": False, "pregunta": pregunta, "message": "Pregunta creada correctamente."}
