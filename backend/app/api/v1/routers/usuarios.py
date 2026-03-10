"""
Router FastAPI — Módulo Usuarios / Roles / Endpoints.
Equivalente a los endpoints /api/getUsers, /api/newUser, /api/getRoles, etc. de index.php.
"""
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import get_db_factory
from app.core.dependencies import get_current_user
from app.db import octopus_users as db_users
from app.schemas.common import APIResponse
from app.schemas.usuario import (
    EditEndpointsRequest,
    RoleCreate,
    RoleDeleteRequest,
    RoleUpdate,
    TokenCreate,
    TokenDeleteRequest,
    UserCreate,
    UserDeleteRequest,
    UserUpdate,
)
from app.services import usuarios_service

router = APIRouter(tags=["Usuarios"])

get_users_db = get_db_factory("octopus_users")


# ---------------------------------------------------------------
# GET /api/getUsers
# ---------------------------------------------------------------
@router.get("/api/getUsers", summary="Lista todos los usuarios con sus roles")
async def get_users(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    usuarios = await db_users.get_users(db)
    return {"error": False, "usuarios": usuarios}


# ---------------------------------------------------------------
# GET /api/getUser
# ---------------------------------------------------------------
@router.get("/api/getUser", summary="Información de un usuario por ID")
async def get_user(
    id: int = Query(..., description="ID del usuario"),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    usuario = await db_users.get_user(db, id)
    if not usuario:
        raise HTTPException(status_code=404, detail="Usuario no encontrado")
    return {"error": False, "usuario": usuario}


# ---------------------------------------------------------------
# GET /api/getInfoUser
# ---------------------------------------------------------------
@router.get("/api/getInfoUser", summary="Información del usuario autenticado")
async def get_info_user(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    user_id = current_user.get("user_id")
    result = await usuarios_service.get_info_user(db, user_id)
    return result


# ---------------------------------------------------------------
# GET /api/getRoles
# ---------------------------------------------------------------
@router.get("/api/getRoles", summary="Lista todos los roles")
async def get_roles(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    roles = await db_users.get_roles(db)
    return {"error": False, "roles": roles}


# ---------------------------------------------------------------
# GET /api/getEndpoints
# ---------------------------------------------------------------
@router.get("/api/getEndpoints", summary="Lista todos los endpoints registrados")
async def get_endpoints(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    endpoints = await db_users.get_endpoints(db)
    return {"error": False, "endpoints": endpoints}


# ---------------------------------------------------------------
# GET /api/getEndpointsByRole
# ---------------------------------------------------------------
@router.get("/api/getEndpointsByRole", summary="Endpoints asignados a un rol")
async def get_endpoints_by_role(
    id: int = Query(..., description="ID del rol"),
    includeAll: Optional[bool] = Query(default=False),
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    endpoints = await db_users.get_endpoints_by_role(db, role_id=id, include_all=includeAll)
    return {"error": False, "endpoints": endpoints}


# ---------------------------------------------------------------
# GET /api/getTokensUser
# ---------------------------------------------------------------
@router.get("/api/getTokensUser", summary="Tokens API del usuario autenticado")
async def get_tokens_user(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    user_id = current_user.get("user_id")
    tokens = await db_users.get_tokens_user(db, user_id)
    return {"error": False, "tokens": tokens}


# ---------------------------------------------------------------
# POST /api/newUser
# ---------------------------------------------------------------
@router.post("/api/newUser", response_model=APIResponse, summary="Crear nuevo usuario")
async def new_user(
    data: UserCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    return await usuarios_service.crear_usuario(db, email=str(data.email), roles=data.rol)


# ---------------------------------------------------------------
# POST /api/editUser
# ---------------------------------------------------------------
@router.post("/api/editUser", response_model=APIResponse, summary="Editar usuario")
async def edit_user(
    data: UserUpdate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    return await usuarios_service.editar_usuario(db, user_id=data.id, roles=data.rol)


# ---------------------------------------------------------------
# POST /api/deleteUser
# ---------------------------------------------------------------
@router.post("/api/deleteUser", response_model=APIResponse, summary="Eliminar usuario")
async def delete_user(
    data: UserDeleteRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    return await usuarios_service.eliminar_usuario(db, user_id=data.id)


# ---------------------------------------------------------------
# POST /api/newRol
# ---------------------------------------------------------------
@router.post("/api/newRol", response_model=APIResponse, summary="Crear nuevo rol")
async def new_rol(
    data: RoleCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    result = await db_users.new_rol(
        db,
        name=data.name,
        color=data.color,
        additional_access=1 if data.additionalAccess else 0,
    )
    if result["error"]:
        raise HTTPException(status_code=409, detail=result.get("message", "Error al crear rol"))
    return APIResponse(error=False, message="Rol creado exitosamente.")


# ---------------------------------------------------------------
# POST /api/editRol
# ---------------------------------------------------------------
@router.post("/api/editRol", response_model=APIResponse, summary="Editar rol existente")
async def edit_rol(
    data: RoleUpdate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    result = await db_users.edit_rol(
        db,
        role_id=data.id,
        additional_access=1 if data.additionalAccess else 0,
        nombre=data.nombre,
        color=data.color,
    )
    if result["error"]:
        raise HTTPException(status_code=400, detail=result.get("message"))
    return APIResponse(error=False, message=result["message"])


# ---------------------------------------------------------------
# POST /api/deleteRol
# ---------------------------------------------------------------
@router.post("/api/deleteRol", response_model=APIResponse, summary="Eliminar rol")
async def delete_rol(
    data: RoleDeleteRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    result = await db_users.delete_rol(db, role_id=data.id)
    if result["error"]:
        raise HTTPException(status_code=400, detail=result.get("message"))
    return APIResponse(error=False, message=result["message"])


# ---------------------------------------------------------------
# POST /api/editEndpointsByRole
# ---------------------------------------------------------------
@router.post(
    "/api/editEndpointsByRole",
    response_model=APIResponse,
    summary="Asignar/revocar endpoints de un rol",
)
async def edit_endpoints_by_role(
    data: EditEndpointsRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    """
    Bug fix vs PHP: la versión PHP usaba implode() sin binding → SQL injection.
    Esta versión usa queries individuales parametrizadas.
    """
    await db_users.edit_endpoints_by_role(
        db, role_id=data.rol, endpoint_ids=data.endpoints, allow=data.allow
    )
    return APIResponse(error=False, message="Endpoints actualizados exitosamente.")


# ---------------------------------------------------------------
# POST /api/createToken
# ---------------------------------------------------------------
@router.post("/api/createToken", response_model=APIResponse, summary="Crear token API Bearer")
async def create_token(
    data: TokenCreate,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    user_id = current_user.get("user_id")
    return await usuarios_service.crear_token(db, user_id=user_id, name=data.name, expired_ts=data.expired)


# ---------------------------------------------------------------
# POST /api/deleteToken
# ---------------------------------------------------------------
@router.post("/api/deleteToken", response_model=APIResponse, summary="Eliminar token API")
async def delete_token(
    data: TokenDeleteRequest,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_users_db),
):
    user_id = current_user.get("user_id")
    return await usuarios_service.eliminar_token(db, user_id=user_id, token_id=data.tokenId)
