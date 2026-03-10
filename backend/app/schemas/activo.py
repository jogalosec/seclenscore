"""
Schemas Pydantic para el módulo de Activos.
Equivalente a la validación manual de index.php para endpoints /api/getActivos, etc.
"""
from typing import Any, List, Literal, Optional

from pydantic import BaseModel, Field


class ActivoBase(BaseModel):
    nombre: str = Field(..., min_length=1, max_length=255)
    tipo: Optional[int] = None          # activo_id en la tabla
    descripcion: Optional[str] = None
    archivado: int = 0
    externo: int = 0
    expuesto: int = 0
    critico: int = 0


class ActivoCreate(ActivoBase):
    """Payload para POST /api/newActivo."""
    clase: int                          # ID del tipo de activo (octopus_new.activos)
    padre: Optional[str] = "undefined"  # ID del padre o "undefined"


class ActivoUpdate(BaseModel):
    """Payload para POST /api/editActivo."""
    id: int
    nombre: Optional[str] = None
    tipo: Optional[int] = None
    descripcion: Optional[str] = None
    archivado: Optional[int] = None
    externo: Optional[int] = None
    expuesto: Optional[int] = None


class ActivoResponse(BaseModel):
    """Activo serializado para la respuesta JSON."""
    id: int
    nombre: str
    tipo: Optional[int] = None
    tipo_nombre: Optional[str] = None
    user_id: Optional[int] = None
    descripcion: Optional[str] = None
    archivado: int = 0
    externo: int = 0
    expuesto: int = 0
    critico: int = 0

    class Config:
        from_attributes = True


class ActivoTreeNode(BaseModel):
    """Nodo del árbol de activos (resultado de getTree/getHijos)."""
    id: int
    nombre: str
    padre: Optional[int] = None
    tipo: Optional[str] = None
    tipo_id: Optional[int] = None
    archivado: int = 0


class PersonasActivo(BaseModel):
    """Personas responsables asociadas a un activo (tabla `personas`)."""
    id: Optional[int] = None
    activo_id: int
    product_owner: Optional[str] = None
    r_seguridad: Optional[str] = None
    r_config_puesto_trabajo: Optional[str] = None
    r_operaciones: Optional[str] = None
    r_desarrollo: Optional[str] = None
    r_legal: Optional[str] = None
    r_rrhh: Optional[str] = None
    r_kpms: Optional[str] = None
    consultor_ciso: Optional[str] = None


class PersonasActiUpdate(BaseModel):
    """Payload para POST /api/editPersonasActivo."""
    id: int                             # activo_id
    personas: PersonasActivo


class ClaseActivo(BaseModel):
    """Tipo/clase de activo (tabla octopus_new.activos)."""
    id: int
    nombre: str


class GetActivosParams(BaseModel):
    """Parámetros de query para GET /api/getActivos."""
    tipo: str                           # "42", "67", "42a" (servicios+productos)
    archivado: Optional[str] = "0"      # "0", "1", "All"
    filtro: Optional[str] = "Todos"     # "Todos", "NoAct", "NoECR"


class BlockItemsRequest(BaseModel):
    """
    Payload para lockKpms/unlockKpms y similares.
    SEGURIDAD: el tipo debe ser un valor de la whitelist — evitar SQL injection.
    """
    tipo: Literal["madurez", "metricas", "csirt"]
    ids: List[int] = Field(..., min_length=1, max_length=100)


class RelacionActivoRequest(BaseModel):
    """Payload para POST /api/changeRelacion."""
    activo_id: int
    nuevo_padre_id: int


class EliminarRelacionRequest(BaseModel):
    """Payload para POST /api/eliminarRelacionActivo."""
    activo_id: int
    padre_id: int
