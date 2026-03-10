"""
Schemas Pydantic — Módulo Normativas / Controles / USFs / Preguntas.
"""
from typing import Any, Dict, List, Optional
from pydantic import BaseModel, field_validator


# ---------------------------------------------------------------
# Normativas
# ---------------------------------------------------------------

class NormativaCreate(BaseModel):
    nombre: str
    version: int

    @field_validator("nombre")
    @classmethod
    def nombre_not_empty(cls, v: str) -> str:
        if not v.strip():
            raise ValueError("El nombre no puede estar vacío")
        return v.strip()


class NormativaUpdate(BaseModel):
    idNormativa: int
    nombre: str
    enabled: bool


class NormativaDeleteRequest(BaseModel):
    idNormativa: int


# ---------------------------------------------------------------
# Controles
# ---------------------------------------------------------------

class ControlCreate(BaseModel):
    codigo: str
    nombre: str
    descripcion: str
    dominio: str
    idNormativa: int


class ControlDeleteRequest(BaseModel):
    idControl: int


# ---------------------------------------------------------------
# USF (Unidad de Servicios Funcionales)
# ---------------------------------------------------------------

class USFCreate(BaseModel):
    codigo: str
    nombre: str
    descripcion: str
    dominio: str
    tipo: str
    IdPAC: int


class USFDeleteRequest(BaseModel):
    idUSF: int


# ---------------------------------------------------------------
# Preguntas
# ---------------------------------------------------------------

class PreguntaCreate(BaseModel):
    duda: str
    nivel: int

    @field_validator("duda")
    @classmethod
    def duda_not_empty(cls, v: str) -> str:
        if not v.strip():
            raise ValueError("La duda no puede estar vacía")
        return v.strip()


class PreguntaDeleteRequest(BaseModel):
    idPregunta: int


# ---------------------------------------------------------------
# Relaciones Marco
# ---------------------------------------------------------------

class RelacionItem(BaseModel):
    idUSF: int
    preguntas: List[Dict[str, Any]]


class RelacionCompletaRequest(BaseModel):
    """Crea relaciones control-USF-pregunta de forma masiva."""
    id: int              # id del control
    relaciones: List[RelacionItem]


class RelacionPreguntasRequest(BaseModel):
    """Relaciona un control con preguntas cuyos USFs ya están asociados."""
    preguntas: List[Dict[str, Any]]   # [{id: int, ...}]
    control: int


class RelacionDeleteRequest(BaseModel):
    idRelacion: int
