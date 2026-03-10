"""
Schemas Pydantic comunes reutilizados en toda la API.
Equivalente al patrón $response_data = ['error' => false, 'message' => '...'] del PHP.
"""
from typing import Any, Optional

from pydantic import BaseModel, Field


class APIResponse(BaseModel):
    """Respuesta genérica de la API — equivalente al patrón de index.php."""
    error: bool = False
    message: Optional[str] = None
    data: Optional[Any] = None


class PaginationParams(BaseModel):
    """Parámetros de paginación reutilizables."""
    total: int = Field(default=10, ge=1, le=500)
    start: int = Field(default=0, ge=0)


class ErrorResponse(BaseModel):
    """Respuesta de error con código HTTP semántico (corrige el bug del PHP que devolvía 200)."""
    error: bool = True
    message: str
    code: Optional[int] = None
