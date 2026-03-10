"""
Servicio Kiuwan + SDLC — orquesta client HTTP y capa DB.
Reemplaza la lógica de las rutas /api/getKiuwanApps, /api/getKiuwanAplication,
/api/obtenerSDLC, /api/crearAppSDLC, /api/eliminarAppSDLC, /api/modificarAppSDLC.
"""
from sqlalchemy.ext.asyncio import AsyncSession

from app.db import octopus_serv_sdlc as db_sdlc
from app.integrations import kiuwan_client


# ── Kiuwan API ────────────────────────────────────────────────────────────────

async def get_kiuwan_applications(db: AsyncSession) -> list[dict]:
    """
    Obtiene apps de la API de Kiuwan, sincroniza en la tabla kiuwan de la BD
    y devuelve los datos almacenados — equivale a GET /api/getKiuwanApps.
    """
    apps = await kiuwan_client.get_applications("/apps/list")
    for app_data in apps:
        await db_sdlc.insert_kiuwan_data(db, {
            "app_name":      app_data.get("name"),
            "creation_date": app_data.get("creationDate"),
            "code":          app_data.get("name"),          # Kiuwan usa el name como código
            "analysis_code": app_data.get("lastAnalysisCode"),
            "analysis_url":  app_data.get("deliveryUrl"),
            "analysis_date": app_data.get("lastAnalysisDate"),
        })
    return await db_sdlc.get_kiuwan_data(db)


async def get_kiuwan_stored(db: AsyncSession) -> list[dict]:
    """Devuelve los datos de Kiuwan almacenados en BD — GET /api/getKiuwanAplication."""
    data = await db_sdlc.get_kiuwan_data(db)
    return data


async def update_cumple_kpm(db: AsyncSession, app_name: str, cumple_kpm: int) -> dict:
    await db_sdlc.update_cumple_kpm(db, app_name, cumple_kpm)
    return {"error": False, "message": f"cumple_kpm actualizado para {app_name}"}


async def update_sonar_kpm(db: AsyncSession, slot_sonarqube: str, cumple_kpm_sonar: int) -> dict:
    await db_sdlc.update_sonar_kpm(db, slot_sonarqube, cumple_kpm_sonar)
    return {"error": False, "message": f"cumple_kpm_sonar actualizado para {slot_sonarqube}"}


# ── SDLC ─────────────────────────────────────────────────────────────────────

async def obtener_sdlc(db: AsyncSession, app: str | None = None,
                       id: int | None = None) -> list[dict] | dict:
    """
    Devuelve aplicaciones SDLC enriquecidas con datos de Kiuwan
    — equivale a GET /api/obtenerSDLC.
    """
    from app.db.octopus_serv import get_activo_by_id  # import lazy para evitar circular
    kiuwan_data = await db_sdlc.get_kiuwan_data(db)
    kiuwan_index = {k["id"]: k for k in kiuwan_data}

    aplicaciones = await db_sdlc.get_sdlc(db, app=app)

    for entry in aplicaciones:
        # Enriquecer con nombres de activos
        for field in ("direccion_id", "area_id", "producto_id"):
            activo_id = entry.get(field)
            if activo_id:
                try:
                    activo = await get_activo_by_id(db, activo_id)
                    key = field.replace("_id", "")
                    entry[key] = activo["nombre"] if activo else None
                    if field == "producto_id" and activo:
                        entry["criticidad"] = activo.get("critico")
                        entry["exposicion"] = activo.get("expuesto")
                except Exception:
                    pass

        # Enriquecer con datos de Kiuwan
        kiuwan_id = entry.get("kiuwan_id")
        if kiuwan_id and kiuwan_id in kiuwan_index:
            slot = kiuwan_index[kiuwan_id]
            entry["kiuwan_slot"] = slot["app_name"]
            entry["cumple_kpm"]  = slot["cumple_kpm"]
        else:
            entry["kiuwan_slot"] = "N/A"
            entry["cumple_kpm"]  = 0

    if id is not None:
        for e in aplicaciones:
            if e["id"] == id:
                return e
        return {}

    return aplicaciones


async def crear_app_sdlc(db: AsyncSession, data: dict) -> dict:
    """
    Crea o actualiza una entrada SDLC — equivale a POST /api/crearAppSDLC.
    Devuelve el objeto creado/actualizado o {"Created": "No"} si ya existía.
    """
    ya_existe = await db_sdlc.modificar_sdlc_by_kiuwan(db, data)
    if ya_existe:
        return {"Created": "No", "Error": "No"}

    await db_sdlc.add_sdlc(db, data)
    if data.get("app") == "Kiuwan" and data.get("kiuwan_id"):
        await db_sdlc.set_kiuwan_registrada(db, data["kiuwan_id"], 1)

    kiuwan_data = await db_sdlc.get_kiuwan_data(db)
    kiuwan_slot = kiuwan_data[0]["app_name"] if kiuwan_data else "N/A"

    return {
        "Direccion":   data.get("Direccion"),
        "Area":        data.get("Area"),
        "Producto":    data.get("Producto"),
        "kiuwan_id":   data.get("kiuwan_id"),
        "kiuwan_slot": kiuwan_slot if data.get("app") == "Kiuwan" else None,
        "sonarqube_slot": data.get("sonarqube_slot"),
        "CMM":         data.get("CMM"),
        "Analisis":    data.get("Analisis"),
        "Comentarios": data.get("Comentarios", ""),
        "Created": "Yes",
        "Error":   "No",
    }


async def modificar_app_sdlc(db: AsyncSession, id: int, data: dict) -> dict:
    await db_sdlc.modificar_app_sdlc(db, id, data)
    return {"error": False, "message": "App modificada"}


async def eliminar_app_sdlc(db: AsyncSession, id: int, kiuwan_id: int | None, app: str) -> dict:
    await db_sdlc.eliminar_app_sdlc(db, id, kiuwan_id, app)
    return {"error": False, "message": "App eliminada"}
