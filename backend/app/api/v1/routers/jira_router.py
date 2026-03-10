"""Router FastAPI — JIRA (EAS + issues de arquitectura)."""
from fastapi import APIRouter, Depends, Query
from app.core.dependencies import get_current_user
from app.schemas.evs import IssueCreate, IssueUpdate
from app.services import jira_service

router = APIRouter(tags=["JIRA"])


@router.get("/api/getIssuesEas")
async def get_issues_eas(current_user: dict = Depends(get_current_user)):
    return await jira_service.listar_issues_eas()


@router.post("/api/newIssueArquitectura")
async def new_issue_arquitectura(data: IssueCreate, current_user: dict = Depends(get_current_user)):
    return await jira_service.crear_issue_arquitectura(data.titulo, data.descripcion, data.severidad)


@router.get("/api/getIssueDetail")
async def get_issue_detail(key: str = Query(...), current_user: dict = Depends(get_current_user)):
    return await jira_service.obtener_issue(key)


@router.get("/api/getJiraTransitions")
async def get_transitions(key: str = Query(...), current_user: dict = Depends(get_current_user)):
    return await jira_service.get_transitions(key)


@router.post("/api/transitionJiraIssue")
async def transition_issue(key: str, transition_id: str, current_user: dict = Depends(get_current_user)):
    return await jira_service.transition_issue(key, transition_id)
