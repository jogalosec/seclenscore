"""
Router FastAPI — Módulo Activos.
Equivalente a todos los endpoints /api/getActivos, /api/getChild/{id},
/api/newActivo, etc. de index.php.
"""
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException, Query
from fastapi.responses import Response
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import get_db_factory
from app.core.dependencies import get_current_user
from app.schemas.activo import (
    ActivoCreate,
    ActivoUpdate,
    EliminarRelacionRequest,
    PersonasActiUpdate,
    RelacionActivoRequest,
)
from app.schemas.common import APIResponse
from app.services import activos_service
from app.db import octopus_serv as db_activos
from app.utils.excel_generator import generar_excel_arbol_activos

router = APIRouter(tags=["Activos"])

# Dependency para octopus_serv
get_serv_db = get_db_factory("octopus_serv")


# ---------------------------------------------------------------
# GET /api/getActivos
# ---------------------------------------------------------------
@router.get("/api/getActivos", summary="Lista activos por tipo y filtros")
async def get_activos(
    tipo: str = Query(..., description="Tipo de activo: 42, 67, 42a, etc."),
    archivado: Optional[str] = Query(default="0", description="0, 1 o All"),
    filtro: Optional[str] = Query(default="Todos", description="Todos, NoAct, NoECR"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """
    Devuelve los activos según tipo y filtros.
    Para tipo=42 incluye datos BIA/ECR y aplica filtros NoAct/NoECR.
    """
    user_id = current_user.get("user_id")
    result = await activos_service.get_activos_lista(
        db, tipo=tipo, user_id=user_id, archivado=archivado, filtro=filtro
    )
    return result


# ---------------------------------------------------------------
# GET /api/getChild/{id}
# ---------------------------------------------------------------
@router.get("/api/getChild/{id}", summary="Hijos directos de un activo")
async def get_child(
    id: int,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Devuelve el activo padre y sus hijos directos."""
    padre = await db_activos.get_activo_by_id(db, id)
    if not padre:
        raise HTTPException(status_code=404, detail="Activo no encontrado")
    hijos = await db_activos.get_child(db, id)
    return {"error": False, "padre": padre, "child": hijos}


# ---------------------------------------------------------------
# GET /api/getTree/{id}
# ---------------------------------------------------------------
@router.get("/api/getTree/{id}", summary="Árbol completo de un activo")
async def get_tree(
    id: int,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """Devuelve el árbol recursivo completo de hijos del activo."""
    result = await activos_service.get_arbol_activo(db, id)
    if result.get("error"):
        raise HTTPException(status_code=404, detail=result.get("message"))
    return result


# ---------------------------------------------------------------
# GET /api/downloadTree
# ---------------------------------------------------------------
@router.get("/api/downloadTree", summary="Descarga árbol de activos como Excel")
async def download_tree(
    id: int = Query(..., description="ID del activo raíz"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    """
    Genera y descarga un archivo Excel con el árbol de activos.
    Equivalente a downloadActivosTree en index.php (PHP usaba PhpSpreadsheet).
    """
    arbol = await activos_service.get_arbol_para_excel(db, id)
    if not arbol:
        raise HTTPException(status_code=404, detail="Activo no encontrado")

    excel_bytes = generar_excel_arbol_activos(arbol)
    return Response(
        content=excel_bytes,
        media_type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        headers={"Content-Disposition": f"attachment; filename=arbol_activo_{id}.xlsx"},
    )


# ---------------------------------------------------------------
# GET /api/downloadActivosTree (alias)
# ---------------------------------------------------------------
@router.get("/api/downloadActivosTree", summary="Descarga árbol de activos como Excel (alias)")
async def download_activos_tree(
    id: int = Query(...),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await download_tree(id=id, current_user=current_user, db=db)


# ---------------------------------------------------------------
# GET /api/getBrothers
# ---------------------------------------------------------------
@router.get("/api/getBrothers", summary="Hermanos de un activo")
async def get_brothers(
    id: int = Query(..., description="ID del activo"),
    padre: int = Query(..., description="ID del padre"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    hermanos = await db_activos.get_brothers(db, parent_id=padre, exclude_id=id)
    return {"error": False, "hermanos": hermanos}


# ---------------------------------------------------------------
# GET /api/obtainAllTypeActivos
# ---------------------------------------------------------------
@router.get("/api/obtainAllTypeActivos", summary="Tipos de activo disponibles")
async def obtain_all_type_activos(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    tipos = await db_activos.obtain_all_type_activos(db)
    return {"error": False, "tipos": tipos}


# ---------------------------------------------------------------
# GET /api/obtainFathersActivo
# ---------------------------------------------------------------
@router.get("/api/obtainFathersActivo", summary="Activos padre disponibles")
async def obtain_fathers_activo(
    id: int = Query(..., description="ID del activo"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    padres = await db_activos.obtain_fathers_activo(db, id)
    return {"error": False, "padres": padres}


# ---------------------------------------------------------------
# GET /api/getActivosExposicion
# ---------------------------------------------------------------
@router.get("/api/getActivosExposicion", summary="Activos con información de exposición")
async def get_activos_exposicion(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    activos = await db_activos.get_activos_exposicion(db)
    return {"error": False, "Activos": activos}


# ---------------------------------------------------------------
# GET /api/getClaseActivos
# ---------------------------------------------------------------
@router.get("/api/getClaseActivos", summary="Clases/tipos de activos")
async def get_clase_activos(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    clases = await db_activos.get_clase_activos(db)
    return {"error": False, "clases": clases}


# ---------------------------------------------------------------
# GET /api/getActivosTipo
# ---------------------------------------------------------------
@router.get("/api/getActivosTipo", summary="Activos filtrados por tipo")
async def get_activos_tipo(
    tipo: int = Query(..., description="ID del tipo de activo"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    activos = await db_activos.get_activos_by_tipo(db, tipo)
    return {"error": False, "activos": activos}


# ---------------------------------------------------------------
# GET /api/getActivosByNombre
# ---------------------------------------------------------------
@router.get("/api/getActivosByNombre", summary="Búsqueda de activos por nombre")
async def get_activos_by_nombre(
    nombre: str = Query(..., description="Texto a buscar"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    activos = await db_activos.get_activos_by_nombre(db, nombre)
    return {"error": False, "activos": activos}


# ---------------------------------------------------------------
# GET /api/getHijosTipo
# ---------------------------------------------------------------
@router.get("/api/getHijosTipo", summary="Hijos filtrados por tipo")
async def get_hijos_tipo(
    id: int = Query(..., description="ID del activo raíz"),
    tipo: str = Query(..., description="Tipo de activo a filtrar"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    hijos = await db_activos.get_hijos_tipo(db, id, tipo)
    return {"error": False, "hijos": hijos}


# ---------------------------------------------------------------
# GET /api/getPersonasActivo
# ---------------------------------------------------------------
@router.get("/api/getPersonasActivo", summary="Personas responsables de un activo")
async def get_personas_activo(
    id: int = Query(..., description="ID del activo"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    personas = await db_activos.get_personas_activo(db, id)
    return {"error": False, "personas": personas}


# ---------------------------------------------------------------
# GET /api/getLogsRelacion
# ---------------------------------------------------------------
@router.get("/api/getLogsRelacion", summary="Logs de relaciones de activos")
async def get_logs_relacion(
    id: Optional[int] = Query(default=None, description="ID del activo (opcional)"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    logs = await db_activos.get_logs_relacion(db, id)
    return {"error": False, "logs": logs}


# ---------------------------------------------------------------
# POST /api/newActivo
# ---------------------------------------------------------------
@router.post("/api/newActivo", response_model=APIResponse, summary="Crear nuevo activo")
async def new_activo(
    data: ActivoCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    user_id = current_user.get("user_id")
    result = await activos_service.crear_activo(
        db, nombre=data.nombre, clase=data.clase, padre=data.padre, user_id=user_id
    )
    return result


# ---------------------------------------------------------------
# POST /api/editActivo
# ---------------------------------------------------------------
@router.post("/api/editActivo", response_model=APIResponse, summary="Editar activo")
async def edit_activo(
    data: ActivoUpdate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    result = await activos_service.editar_activo(db, data.model_dump(exclude_none=True))
    return result


# ---------------------------------------------------------------
# POST /api/deleteActivo
# ---------------------------------------------------------------
@router.post("/api/deleteActivo", response_model=APIResponse, summary="Eliminar activo")
async def delete_activo(
    id: int,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    result = await activos_service.eliminar_activo(db, id)
    return result


# ---------------------------------------------------------------
# POST /api/cloneActivo
# ---------------------------------------------------------------
@router.post("/api/cloneActivo", response_model=APIResponse, summary="Clonar activo")
async def clone_activo(
    id: int,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    user_id = current_user.get("user_id")
    clonado = await db_activos.clone_activo(db, id, user_id)
    if not clonado:
        raise HTTPException(status_code=404, detail="Activo no encontrado")
    return APIResponse(error=False, message="Activo clonado correctamente", data=clonado)


# ---------------------------------------------------------------
# POST /api/updateArchivados
# ---------------------------------------------------------------
@router.post("/api/updateArchivados", response_model=APIResponse, summary="Actualizar archivado")
async def update_archivados(
    id: int,
    archivado: int,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    await db_activos.update_archivados(db, id, archivado)
    return APIResponse(error=False, message="Estado actualizado correctamente")


# ---------------------------------------------------------------
# POST /api/changeRelacion
# ---------------------------------------------------------------
@router.post("/api/changeRelacion", response_model=APIResponse, summary="Cambiar relación padre")
async def change_relacion(
    data: RelacionActivoRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    await db_activos.change_relacion(db, data.activo_id, data.nuevo_padre_id)
    return APIResponse(error=False, message="Relación actualizada correctamente")


# ---------------------------------------------------------------
# POST /api/eliminarRelacionActivo
# ---------------------------------------------------------------
@router.post(
    "/api/eliminarRelacionActivo",
    response_model=APIResponse,
    summary="Eliminar relación padre de activo",
)
async def eliminar_relacion_activo(
    data: EliminarRelacionRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    await db_activos.eliminar_relacion_activo(db, data.activo_id, data.padre_id)
    return APIResponse(error=False, message="Relación eliminada correctamente")


# ---------------------------------------------------------------
# POST /api/editPersonasActivo
# ---------------------------------------------------------------
@router.post(
    "/api/editPersonasActivo",
    response_model=APIResponse,
    summary="Actualizar personas responsables",
)
async def edit_personas_activo(
    data: PersonasActiUpdate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    await db_activos.edit_personas_activo(
        db, data.id, data.personas.model_dump(exclude_none=True)
    )
    return APIResponse(error=False, message="Personas actualizadas correctamente")


# ---------------------------------------------------------------
# POST /api/importActivos
# ---------------------------------------------------------------
@router.post("/api/importActivos", response_model=APIResponse, summary="Importar activos desde Excel")
async def import_activos(
    current_user: dict = Depends(get_current_user),
):
    """
    Importa activos desde un archivo XLSX.
    TODO Sprint 2b: implementar parsing con openpyxl y UploadFile de FastAPI.
    """
    raise HTTPException(
        status_code=501,
        detail="Importación de activos pendiente de implementación completa",
    )
