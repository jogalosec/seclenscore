"""Schemas Pydantic — Módulo PAC (Plan de Acciones Correctivas) y Continuidad."""
from typing import Literal, Optional
from pydantic import BaseModel

EstadoPAC = Literal["pendiente", "en_curso", "completado", "cancelado"]


class PacCreate(BaseModel):
    activo_id: int
    descripcion: str
    responsable: Optional[str] = None
    prioridad: Optional[str] = "media"
    fecha_limite: Optional[str] = None
    usf_id: Optional[int] = None
    pregunta_id: Optional[int] = None


class PacSeguimientoCreate(BaseModel):
    pac_id: int
    descripcion: str
    estado: EstadoPAC = "pendiente"


class PacSeguimientoUpdate(BaseModel):
    id: int
    descripcion: Optional[str] = None
    estado: Optional[EstadoPAC] = None


class PacSeguimientoDelete(BaseModel):
    id: int


class PlanCreate(BaseModel):
    activo_id: int
    nombre: str
    descripcion: Optional[str] = None
    fecha_inicio: Optional[str] = None
    fecha_fin: Optional[str] = None


class PlanUpdate(BaseModel):
    id: int
    nombre: Optional[str] = None
    descripcion: Optional[str] = None
    fecha_inicio: Optional[str] = None
    fecha_fin: Optional[str] = None


class PlanDelete(BaseModel):
    id: int
