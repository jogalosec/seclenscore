"""
Schemas Pydantic — Módulo Usuarios / Roles / Endpoints.
"""
from typing import List, Optional
from pydantic import BaseModel, EmailStr, field_validator


# ---------------------------------------------------------------
# Usuarios
# ---------------------------------------------------------------

class RoleInfo(BaseModel):
    id: Optional[int] = None
    name: Optional[str] = None
    color: Optional[str] = None


class UserResponse(BaseModel):
    id: int
    email: str
    roles: Optional[List[RoleInfo]] = []


class UserCreate(BaseModel):
    email: EmailStr
    rol: Optional[List[int]] = []

    @field_validator("email")
    @classmethod
    def email_lower(cls, v: str) -> str:
        return v.lower().strip()


class UserUpdate(BaseModel):
    id: int
    email: Optional[EmailStr] = None
    rol: Optional[List[int]] = []


class UserDeleteRequest(BaseModel):
    id: int


# ---------------------------------------------------------------
# Roles
# ---------------------------------------------------------------

class RoleResponse(BaseModel):
    id: int
    name: str
    color: Optional[str] = None
    deletable: Optional[int] = 1
    editable: Optional[int] = 1
    additional_access: Optional[int] = 0


class RoleCreate(BaseModel):
    name: str
    color: str
    additionalAccess: bool = False

    @field_validator("name")
    @classmethod
    def name_not_empty(cls, v: str) -> str:
        if not v.strip():
            raise ValueError("El nombre del rol no puede estar vacío")
        return v.strip()


class RoleUpdate(BaseModel):
    id: int
    nombre: Optional[str] = None
    color: Optional[str] = None
    additionalAccess: bool = False


class RoleDeleteRequest(BaseModel):
    id: int


# ---------------------------------------------------------------
# Endpoints / Permisos RBAC
# ---------------------------------------------------------------

class EndpointResponse(BaseModel):
    id: int
    route: str
    method: Optional[str] = None
    description: Optional[str] = None
    tags: Optional[str] = None
    assigned: Optional[int] = None


class EditEndpointsRequest(BaseModel):
    """
    Petición para asignar/quitar endpoints de un rol.
    'allow=true' añade, 'allow=false' elimina.
    """
    rol: int
    endpoints: List[int]
    allow: bool

    @field_validator("endpoints")
    @classmethod
    def endpoints_not_empty(cls, v: List[int]) -> List[int]:
        if not v:
            raise ValueError("La lista de endpoints no puede estar vacía")
        return [int(e) for e in v]


# ---------------------------------------------------------------
# Tokens API (Bearer 11_)
# ---------------------------------------------------------------

class TokenCreate(BaseModel):
    name: str
    expired: int   # unix timestamp


class TokenDeleteRequest(BaseModel):
    tokenId: int
