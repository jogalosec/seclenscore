"""Schemas Pydantic — Módulo Kiuwan + SDLC."""
from typing import Literal, Optional
from pydantic import BaseModel, field_validator


class UpdateCumpleKpmRequest(BaseModel):
    app_name: str
    cumple_kpm: Literal[0, 1]

    @field_validator("app_name")
    @classmethod
    def app_name_not_empty(cls, v: str) -> str:
        if not v.strip():
            raise ValueError("app_name no puede estar vacío")
        return v.strip()


class UpdateSonarKPMRequest(BaseModel):
    slot_sonarqube: str
    cumple_kpm_sonar: Literal[0, 1]

    @field_validator("slot_sonarqube")
    @classmethod
    def slot_not_empty(cls, v: str) -> str:
        if not v.strip():
            raise ValueError("slot_sonarqube no puede estar vacío")
        return v.strip()


class SdlcCreate(BaseModel):
    app: Literal["Kiuwan", "Sonarqube"]
    Direccion: int
    Area: int
    Producto: int
    CMM: str
    Analisis: str
    Comentarios: Optional[str] = ""
    url_sonar: Optional[str] = ""
    # Kiuwan-specific
    kiuwan_id: Optional[int] = None
    fecha_analisis_kiuwan: Optional[str] = None
    # Sonarqube-specific
    sonarqube_slot: Optional[str] = None
    fecha_analisis_sonarqube: Optional[str] = None


class SdlcUpdate(BaseModel):
    id: int
    Comentarios: Optional[str] = None
    CMM: Optional[str] = None
    url_sonar: Optional[str] = None


class SdlcDelete(BaseModel):
    id: int
    app: str
    kiuwan_id: Optional[int] = None


class SuscripcionRelacionCreate(BaseModel):
    id_activo: int
    subscriptions: list[str]
    subscriptionNames: Optional[list[str]] = []


class SuscripcionRelacionDelete(BaseModel):
    suscription_id: str


class SuscripcionRelacionEdit(BaseModel):
    id: int
    suscription_name: Optional[str] = None
    suscription_id: Optional[str] = None


class SendEmailRequest(BaseModel):
    to: str
    asunto: str
    body: str
    alternbody: Optional[str] = ""
