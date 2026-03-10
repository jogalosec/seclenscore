"""
Schemas Pydantic — Módulo KPMs.

Bug fix vs PHP: los handlers de editKpms, deleteKpms, lockKpms, unlockKpms
usaban el campo 'tipo' como nombre de tabla en queries SQL sin validación
→ inyección SQL posible (UPDATE $tipo SET ...).
Aquí se usa Literal para forzar solo valores permitidos.
"""
from typing import List, Literal, Optional
from pydantic import BaseModel, field_validator

# Whitelist de tablas KPM — mismo patrón que BlockItemsRequest de activos
TipoKPM = Literal["madurez", "metricas", "csirt"]


class KpmLockRequest(BaseModel):
    tipo: TipoKPM
    id:   List[int]

    @field_validator("id")
    @classmethod
    def ids_not_empty(cls, v: List[int]) -> List[int]:
        if not v:
            raise ValueError("La lista de IDs no puede estar vacía")
        return [int(i) for i in v]


class KpmDeleteRequest(BaseModel):
    tipo: TipoKPM
    id:   List[int]

    @field_validator("id")
    @classmethod
    def ids_not_empty(cls, v: List[int]) -> List[int]:
        if not v:
            raise ValueError("La lista de IDs no puede estar vacía")
        return [int(i) for i in v]


class KpmEditRequest(BaseModel):
    """
    Edita un reporte KPM. Los campos dinámicos se pasan en 'campos'.
    Bug fix: PHP construía el UPDATE SET con nombres de columna directamente
    del request body → inyección SQL. Aquí sólo se permiten campos conocidos
    del modelo y se usan parámetros vinculados.
    """
    tipo:   TipoKPM
    id:     int
    campos: dict   # {nombre_columna: valor} — validado en el servicio


class KpmCreate(BaseModel):
    numeroKPM:           str
    descripcionCortaKPM: str
    descripcionLargaKPM: str
    grupoKPM:            str


class KpmDefinicionUpdate(BaseModel):
    """Para editar la definición de un KPM en all_metricas."""
    id:                 int
    nombre:             Optional[str] = None
    descripcion_larga:  Optional[str] = None
    descripcion_corta:  Optional[str] = None
    grupo:              Optional[str] = None


class ReporterKPMCreate(BaseModel):
    userId:    int
    idActivo:  int


class ReporterKPMDelete(BaseModel):
    idRelacion: int
