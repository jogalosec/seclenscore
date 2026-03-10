"""
Cliente HTTP para la API REST de JIRA (Atlassian).
Equivalente a includes/JIRA.php.
Usa httpx para llamadas asíncronas.
"""
from __future__ import annotations

import logging
from typing import Any, Dict, List, Optional

import httpx

from app.core.config import get_settings

logger = logging.getLogger(__name__)

# Campos personalizados JIRA (config de instalación)
CF_ANALYSIS_TYPE = "customfield_12611"
CF_STATUS_CUSTOM  = "customfield_24501"


def _headers() -> Dict[str, str]:
    token = get_settings().JIRA_TOKEN.get_secret_value()
    return {
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json",
        "Accept": "application/json",
    }


def _base() -> str:
    return get_settings().JIRA_BASE_URL.rstrip("/")


# ---------------------------------------------------------------
# Issues
# ---------------------------------------------------------------

async def get_issues_pentest(jql: Optional[str] = None, max_results: int = 100) -> List[Dict]:
    """Devuelve issues de JIRA filtradas por JQL."""
    settings = get_settings()
    query = jql or f'project = "{settings.JIRA_PROJECT_KEY}" AND issuetype = Bug ORDER BY created DESC'
    async with httpx.AsyncClient(timeout=15) as client:
        resp = await client.get(
            f"{_base()}/2/search",
            headers=_headers(),
            params={"jql": query, "maxResults": max_results, "fields": "summary,description,status,priority,assignee,created,updated"},
        )
        resp.raise_for_status()
        data = resp.json()
        return data.get("issues", [])


async def get_issue(issue_key: str) -> Optional[Dict]:
    async with httpx.AsyncClient(timeout=15) as client:
        try:
            resp = await client.get(f"{_base()}/2/issue/{issue_key}", headers=_headers())
            resp.raise_for_status()
            return resp.json()
        except httpx.HTTPStatusError:
            return None


async def create_issue(
    titulo: str,
    descripcion: str,
    severidad: str = "Medium",
    issue_type: str = "Bug",
    extra_fields: Optional[Dict[str, Any]] = None,
) -> Dict:
    """Crea un issue en JIRA y devuelve la respuesta con key e id."""
    settings = get_settings()
    priority_map = {"Critical": "Highest", "High": "High", "Medium": "Medium", "Low": "Low", "Info": "Lowest"}
    payload: Dict[str, Any] = {
        "fields": {
            "project": {"key": settings.JIRA_PROJECT_KEY},
            "summary": titulo,
            "description": {
                "type": "doc",
                "version": 1,
                "content": [{"type": "paragraph", "content": [{"type": "text", "text": descripcion}]}],
            },
            "issuetype": {"name": issue_type},
            "priority": {"name": priority_map.get(severidad, "Medium")},
        }
    }
    if extra_fields:
        payload["fields"].update(extra_fields)

    async with httpx.AsyncClient(timeout=15) as client:
        resp = await client.post(f"{_base()}/2/issue", headers=_headers(), json=payload)
        resp.raise_for_status()
        return resp.json()


async def update_issue(issue_key: str, fields: Dict[str, Any]) -> bool:
    async with httpx.AsyncClient(timeout=15) as client:
        resp = await client.put(
            f"{_base()}/2/issue/{issue_key}",
            headers=_headers(),
            json={"fields": fields},
        )
        return resp.status_code in (200, 204)


async def add_comment(issue_key: str, texto: str) -> bool:
    payload = {
        "body": {
            "type": "doc",
            "version": 1,
            "content": [{"type": "paragraph", "content": [{"type": "text", "text": texto}]}],
        }
    }
    async with httpx.AsyncClient(timeout=15) as client:
        resp = await client.post(
            f"{_base()}/2/issue/{issue_key}/comment",
            headers=_headers(),
            json=payload,
        )
        return resp.status_code in (200, 201)


async def get_transitions(issue_key: str) -> List[Dict]:
    async with httpx.AsyncClient(timeout=15) as client:
        resp = await client.get(f"{_base()}/2/issue/{issue_key}/transitions", headers=_headers())
        resp.raise_for_status()
        return resp.json().get("transitions", [])


async def transition_issue(issue_key: str, transition_id: str) -> bool:
    async with httpx.AsyncClient(timeout=15) as client:
        resp = await client.post(
            f"{_base()}/2/issue/{issue_key}/transitions",
            headers=_headers(),
            json={"transition": {"id": transition_id}},
        )
        return resp.status_code == 204


# ---------------------------------------------------------------
# EAS — issues de arquitectura (tipo diferente)
# ---------------------------------------------------------------

async def create_issue_arquitectura(titulo: str, descripcion: str, severidad: str = "Medium") -> Dict:
    """Crea un issue de arquitectura (EAS) con campos personalizados."""
    settings = get_settings()
    extra = {
        CF_ANALYSIS_TYPE: {"value": "Architecture Review"},
    }
    return await create_issue(
        titulo=titulo,
        descripcion=descripcion,
        severidad=severidad,
        issue_type="Improvement",
        extra_fields=extra,
    )
