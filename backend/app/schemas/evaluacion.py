"""
Schemas Pydantic — Módulo Evaluaciones / BIA.
"""
from typing import Any, Dict, List, Optional
from pydantic import BaseModel, field_validator


class EvalCreateRequest(BaseModel):
    """
    Cuerpo para POST /api/newEval/{id}.
    Puede ser una evaluación nueva o editar una existente (fecha/version).
    """
    evaluate: Optional[Dict[str, Any]] = None   # respuestas del cuestionario
    comment:  Optional[Dict[str, Any]] = None   # comentarios opcionales
    fecha:    Optional[str] = None              # id de evaluacion existente (editar)
    version:  Optional[str] = None             # id de versión existente (editar)
    editEval: Optional[bool] = False            # si True no recalcula PAC


class EvalSaveRequest(BaseModel):
    """Cuerpo para POST /api/saveEval/{id} (guardar borrador)."""
    datos: Dict[str, Any]


class BiaCreateRequest(BaseModel):
    """Cuerpo para guardar/actualizar un BIA."""
    activo_id: int
    respuestas: Dict[str, Any]


class EvalOsaRequest(BaseModel):
    """Cuerpo para POST /api/saveEvalOsa."""
    revision_id: int
    datos: Dict[str, Any]


class EvalNoEvaluadosParams(BaseModel):
    norma: str
    fecha: str
    idVersion: Optional[str] = None
