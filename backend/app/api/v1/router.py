"""
Router principal APIv1 — registra todos los sub-routers de los módulos.
Se irán añadiendo routers conforme avanza la migración por sprints.
"""
from fastapi import APIRouter

from app.api.v1.routers import auth

api_router = APIRouter()

# Sprint 1 — Autenticación
api_router.include_router(auth.router)

# Sprint 2 — Activos
from app.api.v1.routers import activos
api_router.include_router(activos.router)

# Sprint 3 — Usuarios + Normativas
from app.api.v1.routers import usuarios, normativas
api_router.include_router(usuarios.router)
api_router.include_router(normativas.router)

# Sprint 4 — Evaluaciones + KPMs
from app.api.v1.routers import evaluaciones, kpms
api_router.include_router(evaluaciones.router)
api_router.include_router(kpms.router)

# Sprint 5 — EVS/Pentest + JIRA + PAC + Continuidad
from app.api.v1.routers import evs, pac, jira_router
api_router.include_router(evs.router)
api_router.include_router(pac.router)
api_router.include_router(jira_router.router)

# Sprint 6 — Dashboard + Logs
from app.api.v1.routers import dashboard, logs
api_router.include_router(dashboard.router)
api_router.include_router(logs.router)
