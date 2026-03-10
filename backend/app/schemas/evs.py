"""Schemas Pydantic — Módulo EVS (Pentest + Revisiones Prisma Cloud)."""
from typing import List, Literal, Optional
from pydantic import BaseModel, field_validator

EstadoPentest = Literal["abierto", "cerrado", "en_curso"]


class PentestCreate(BaseModel):
    nombre: str
    descripcion: Optional[str] = None
    fecha_inicio: Optional[str] = None
    fecha_fin: Optional[str] = None
    activos: Optional[List[int]] = []


class PentestUpdate(BaseModel):
    id: int
    nombre: Optional[str] = None
    descripcion: Optional[str] = None
    fecha_inicio: Optional[str] = None
    fecha_fin: Optional[str] = None


class PentestStatusRequest(BaseModel):
    id: int
    estado: EstadoPentest


class PentestDeleteRequest(BaseModel):
    id: int


class PentestAddActivosRequest(BaseModel):
    pentest_id: int
    activos: List[int]

    @field_validator("activos")
    @classmethod
    def activos_not_empty(cls, v: List[int]) -> List[int]:
        if not v:
            raise ValueError("La lista de activos no puede estar vacía")
        return v


class IssueCreate(BaseModel):
    pentest_id: Optional[int] = None
    revision_id: Optional[int] = None
    titulo: str
    descripcion: str
    severidad: Optional[str] = "Medium"
    activo_id: Optional[int] = None


class IssueUpdate(BaseModel):
    issue_key: str
    titulo: Optional[str] = None
    descripcion: Optional[str] = None
    severidad: Optional[str] = None
    estado: Optional[str] = None


class SolicitudPentestCreate(BaseModel):
    nombre: str
    descripcion: Optional[str] = None
    activos: Optional[List[int]] = []
    tipo: Optional[str] = "externo"
    fecha_solicitada: Optional[str] = None
    contacto: Optional[str] = None


class SolicitudPentestAction(BaseModel):
    id: int
    comentario: Optional[str] = None


class RevisionCreate(BaseModel):
    nombre: str
    descripcion: Optional[str] = None
    activos: Optional[List[int]] = []


class RevisionAddActivosRequest(BaseModel):
    revision_id: int
    activos: List[int]


class AlertaAsignRequest(BaseModel):
    revision_id: int
    id_alert: str
    id_policy: Optional[str] = None
    resource_id: Optional[str] = None
    resource_name: Optional[str] = None


class AlertaDesasignRequest(BaseModel):
    revision_id: int
    id_alert: str


class DismissPrismaAlertRequest(BaseModel):
    alert_id: str
    razon: Optional[str] = None


class PentestAsignRequest(BaseModel):
    pentest_id: int
    user_id: int
