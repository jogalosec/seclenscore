"""
Servicio de lógica de negocio para el módulo Activos.
Equivalente a las funciones de functions.php relacionadas con activos:
- ordenarFamilia(), estructurarFamilia(), getActivosParaEvaluacion(), etc.
"""
from typing import Any, Dict, List, Optional

from sqlalchemy.ext.asyncio import AsyncSession

from app.db import octopus_serv as db_activos


async def get_activos_lista(
    db: AsyncSession,
    tipo: str,
    user_id: Optional[int],
    archivado: str = "0",
    filtro: str = "Todos",
    additional_access: bool = False,
) -> Dict[str, Any]:
    """
    Obtiene la lista de activos según tipo y filtros.
    Equivalente al handler GET /api/getActivos en index.php.

    Tipos especiales:
      "42a" → servicios (42) + productos (67) combinados
      "42"  → solo servicios, con enriquecimiento BIA/ECR
    """
    if tipo == "42a":
        servicios = await db_activos.get_activos_by_tipo(
            db, 42, user_id, archivado, additional_access=additional_access
        )
        productos = await db_activos.get_activos_by_tipo(
            db, 67, user_id, archivado, additional_access=additional_access
        )
        activos = servicios + productos
    else:
        tipo_int = int(tipo) if tipo.isdigit() else 42
        activos = await db_activos.get_activos_by_tipo(
            db, tipo_int, user_id, archivado, additional_access=additional_access
        )

    # Para tipo 42 (servicios): añadir permisos adicionales por visibilidad
    if tipo == "42" and user_id:
        permisos = await db_activos.get_activos_permisos(db, user_id)
        activos = activos + permisos

    # Filtros adicionales para tipo 42
    if tipo == "42" and activos:
        # TODO Sprint 4: enriquecer con datos BIA/ECR desde octopus_serv
        # (getServiciosSinBia, getServiciosBiaDesactualizado, getMediaSistemasECR)
        if filtro == "NoAct":
            activos = [a for a in activos if a.get("biaoutdated") or not a.get("bia", True)]
        elif filtro == "NoECR":
            activos = [
                a for a in activos
                if not a.get("total") or not a.get("ecr") or a.get("total") != a.get("ecr")
            ]

    return {"total": len(activos), "servicios": activos}


async def get_arbol_activo(db: AsyncSession, activo_id: int) -> Dict[str, Any]:
    """
    Obtiene el árbol completo de un activo con nombre de tipo.
    Equivalente al handler GET /api/getTree/{id} en index.php.
    """
    raiz = await db_activos.get_activo_by_id(db, activo_id)
    if not raiz:
        return {"error": True, "message": "Activo no encontrado"}

    # Obtener nombre del tipo
    tipo_info = await db_activos.get_clase_activo_by_id(db, raiz.get("tipo"))
    raiz["tipo_nombre"] = tipo_info.get("nombre") if tipo_info else None

    hijos = await db_activos.get_hijos(db, activo_id)
    tree = [raiz] + hijos
    return {"error": False, "tree": tree}


async def get_arbol_para_excel(db: AsyncSession, activo_id: int) -> List[Dict]:
    """
    Genera datos del árbol formateados para exportación Excel.
    Equivalente a downloadActivosTree en index.php.
    """
    raiz = await db_activos.get_activo_by_id(db, activo_id)
    if not raiz:
        return []

    tipo_info = await db_activos.get_clase_activo_by_id(db, raiz.get("tipo"))
    tipo_nombre = tipo_info.get("nombre") if tipo_info else ""

    hijos = await db_activos.get_hijos(db, activo_id)

    # Cabeceras como primera fila (igual que el PHP)
    cabeceras = ["NOMBRE", "TIPO", "ID", "PADRE", "ARCHIVADO", "EXPUESTO"]
    raiz_row = {
        "nombre":    raiz["nombre"],
        "tipo":      tipo_nombre,
        "id":        raiz["id"],
        "padre":     "",
        "archivado": raiz.get("archivado", 0),
        "expuesto":  raiz.get("expuesto", ""),
    }
    return [cabeceras, raiz_row] + hijos


def organizar_familia(activos: List[Dict]) -> List[Dict]:
    """
    Organiza la lista de activos en una estructura jerárquica.
    Equivalente a ordenarFamilia() / estructurarFamilia() de functions.php.
    """
    # Índice por id para acceso rápido
    index = {a["id"]: {**a, "hijos": []} for a in activos}
    raices = []

    for activo in activos:
        padre_id = activo.get("padre")
        if padre_id and padre_id in index:
            index[padre_id]["hijos"].append(index[activo["id"]])
        else:
            raices.append(index[activo["id"]])

    return raices


async def crear_activo(
    db: AsyncSession,
    nombre: str,
    clase: int,
    padre: Optional[str],
    user_id: int,
) -> Dict[str, Any]:
    """
    Crea un nuevo activo y devuelve la respuesta formateada.
    Equivalente a newActivo() + respuesta de index.php.
    """
    activo = await db_activos.new_activo(db, nombre, clase, padre, user_id)
    return {"error": False, "activo": activo, "message": "Activo creado correctamente"}


async def editar_activo(db: AsyncSession, data: dict) -> Dict[str, Any]:
    """Edita un activo existente."""
    await db_activos.edit_activo(
        db,
        activo_id=data["id"],
        nombre=data.get("nombre"),
        tipo=data.get("tipo"),
        descripcion=data.get("descripcion"),
        archivado=data.get("archivado", 0),
        externo=data.get("externo", 0),
        expuesto=data.get("expuesto", 0),
    )
    return {"error": False, "message": "Activo actualizado correctamente"}


async def eliminar_activo(db: AsyncSession, activo_id: int) -> Dict[str, Any]:
    """Elimina un activo."""
    await db_activos.delete_activo(db, activo_id)
    return {"error": False, "message": "Activo eliminado correctamente"}
