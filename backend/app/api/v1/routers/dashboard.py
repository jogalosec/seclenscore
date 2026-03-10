"""Router FastAPI — Dashboard."""
from fastapi import APIRouter, Depends
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.database import get_db_factory
from app.core.dependencies import get_current_user
from app.services import dashboard_service

router = APIRouter(tags=["Dashboard"])
get_serv_db = get_db_factory("octopus_serv")


@router.get("/api/getDashboard")
async def get_dashboard(
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_serv_db),
):
    return await dashboard_service.get_dashboard_completo(db)


@router.get("/api/getDashboardActivos")
async def get_dashboard_activos(current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    data = await dashboard_service.get_dashboard_activos(db)
    return {"error": False, **data}


@router.get("/api/getDashboardBia")
async def get_dashboard_bia(current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    data = await dashboard_service.get_dashboard_bia(db)
    return {"error": False, **data}


@router.get("/api/getDashboardEcr")
async def get_dashboard_ecr(current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    data = await dashboard_service.get_dashboard_ecr(db)
    return {"error": False, **data}


@router.get("/api/getDashboardPentest")
async def get_dashboard_pentest(current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    data = await dashboard_service.get_dashboard_pentest(db)
    return {"error": False, **data}


@router.get("/api/getDashboardPac")
async def get_dashboard_pac(current_user: dict = Depends(get_current_user), db: AsyncSession = Depends(get_serv_db)):
    data = await dashboard_service.get_dashboard_pac(db)
    return {"error": False, **data}
