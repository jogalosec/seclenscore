"""Servicio de integración JIRA — capa sobre jira_client."""
from typing import Any, Dict, List, Optional

from app.integrations import jira_client


async def listar_issues(jql: Optional[str] = None, max_results: int = 100) -> Dict:
    issues = await jira_client.get_issues_pentest(jql, max_results)
    return {"error": False, "issues": issues, "total": len(issues)}


async def listar_issues_eas(max_results: int = 100) -> Dict:
    jql = 'project = "CISOCDCOIN" AND issuetype = Improvement ORDER BY created DESC'
    issues = await jira_client.get_issues_pentest(jql, max_results)
    return {"error": False, "issues": issues, "total": len(issues)}


async def obtener_issue(issue_key: str) -> Dict:
    data = await jira_client.get_issue(issue_key)
    if not data:
        return {"error": True, "message": f"Issue {issue_key} no encontrado."}
    return {"error": False, "issue": data}


async def crear_issue_pentest(
    titulo: str,
    descripcion: str,
    severidad: str = "Medium",
) -> Dict:
    resp = await jira_client.create_issue(titulo, descripcion, severidad)
    return {
        "error": False,
        "message": "Issue creado correctamente.",
        "issue_key": resp.get("key"),
        "jira_id": resp.get("id"),
    }


async def crear_issue_arquitectura(titulo: str, descripcion: str, severidad: str = "Medium") -> Dict:
    resp = await jira_client.create_issue_arquitectura(titulo, descripcion, severidad)
    return {
        "error": False,
        "message": "Issue de arquitectura creado correctamente.",
        "issue_key": resp.get("key"),
        "jira_id": resp.get("id"),
    }


async def editar_issue(issue_key: str, campos: Dict[str, Any]) -> Dict:
    ok = await jira_client.update_issue(issue_key, campos)
    return {"error": not ok, "message": "Issue actualizado." if ok else "Error al actualizar."}


async def crear_incidente(titulo: str, descripcion: str) -> Dict:
    resp = await jira_client.create_issue(titulo, descripcion, issue_type="Incident")
    return {
        "error": False,
        "message": "Incidente creado correctamente.",
        "issue_key": resp.get("key"),
    }


async def get_transitions(issue_key: str) -> Dict:
    transitions = await jira_client.get_transitions(issue_key)
    return {"error": False, "transitions": transitions}


async def transition_issue(issue_key: str, transition_id: str) -> Dict:
    ok = await jira_client.transition_issue(issue_key, transition_id)
    return {"error": not ok, "message": "Transición aplicada." if ok else "Error al aplicar transición."}
