"""
Cliente HTTP para la API de Prisma Cloud (Palo Alto Networks).
Equivalente a includes/Prisma.php.
Autenticación: POST /login → x-redlock-auth header.
"""
from __future__ import annotations

import logging
from typing import Any, Dict, List, Optional

import httpx

from app.core.config import get_settings

logger = logging.getLogger(__name__)

_token_cache: Optional[str] = None


def _base() -> str:
    return get_settings().PRISMA_URL.rstrip("/")


async def _get_token() -> str:
    """Obtiene y cachea el token de autenticación Prisma Cloud."""
    global _token_cache
    if _token_cache:
        return _token_cache
    settings = get_settings()
    async with httpx.AsyncClient(timeout=20) as client:
        resp = await client.post(
            f"{_base()}/login",
            json={
                "username": settings.PRISMA_USER,
                "password": settings.PRISMA_PASSWORD.get_secret_value(),
            },
            headers={"Content-Type": "application/json"},
        )
        resp.raise_for_status()
        _token_cache = resp.json().get("token")
        return _token_cache


def _invalidate_token() -> None:
    global _token_cache
    _token_cache = None


async def _headers() -> Dict[str, str]:
    token = await _get_token()
    return {
        "x-redlock-auth": token,
        "Content-Type": "application/json",
    }


# ---------------------------------------------------------------
# Cuentas cloud
# ---------------------------------------------------------------

async def get_cloud_accounts() -> List[Dict]:
    """Devuelve todas las cuentas cloud registradas en Prisma."""
    hdrs = await _headers()
    async with httpx.AsyncClient(timeout=20) as client:
        resp = await client.get(f"{_base()}/cloud", headers=hdrs)
        if resp.status_code == 401:
            _invalidate_token()
            hdrs = await _headers()
            resp = await client.get(f"{_base()}/cloud", headers=hdrs)
        resp.raise_for_status()
        return resp.json()


async def get_cloud_account_info(account_id: str) -> Dict:
    hdrs = await _headers()
    async with httpx.AsyncClient(timeout=20) as client:
        resp = await client.get(f"{_base()}/cloud/{account_id}", headers=hdrs)
        resp.raise_for_status()
        return resp.json()


# ---------------------------------------------------------------
# Alertas
# ---------------------------------------------------------------

async def get_alerts_by_cloud(
    account_id: str,
    estado: str = "open",
    limit: int = 100,
    offset: int = 0,
) -> Dict:
    """Devuelve alertas para una cuenta cloud específica."""
    hdrs = await _headers()
    payload = {
        "filters": [
            {"name": "cloud.account", "operator": "=", "value": account_id},
            {"name": "alert.status", "operator": "=", "value": estado},
        ],
        "limit": limit,
        "offset": offset,
        "timeRange": {"type": "relative", "value": {"unit": "month", "amount": 3}},
    }
    async with httpx.AsyncClient(timeout=30) as client:
        resp = await client.post(f"{_base()}/v2/alert", headers=hdrs, json=payload)
        resp.raise_for_status()
        return resp.json()


async def get_alert_info(alert_id: str) -> Dict:
    hdrs = await _headers()
    async with httpx.AsyncClient(timeout=15) as client:
        resp = await client.get(f"{_base()}/v2/alert/{alert_id}", headers=hdrs)
        resp.raise_for_status()
        return resp.json()


async def dismiss_alert(alert_id: str, razon: Optional[str] = None) -> bool:
    """Descarta una alerta en Prisma Cloud."""
    hdrs = await _headers()
    payload: Dict[str, Any] = {
        "alerts": [alert_id],
        "dismissalNote": razon or "Dismissed via SecLensCore",
        "filter": {"timeRange": {"type": "relative", "value": {"unit": "month", "amount": 1}}},
    }
    async with httpx.AsyncClient(timeout=15) as client:
        resp = await client.post(f"{_base()}/alert/dismiss", headers=hdrs, json=payload)
        return resp.status_code in (200, 204)


async def reopen_alert(alert_id: str) -> bool:
    """Reabre una alerta previamente descartada."""
    hdrs = await _headers()
    payload: Dict[str, Any] = {
        "alerts": [alert_id],
        "filter": {"timeRange": {"type": "relative", "value": {"unit": "month", "amount": 1}}},
    }
    async with httpx.AsyncClient(timeout=15) as client:
        resp = await client.post(f"{_base()}/alert/reopen", headers=hdrs, json=payload)
        return resp.status_code in (200, 204)


async def get_alerts_by_policy(policy_id: str, estado: str = "open") -> List[Dict]:
    hdrs = await _headers()
    payload = {
        "filters": [
            {"name": "policy.id", "operator": "=", "value": policy_id},
            {"name": "alert.status", "operator": "=", "value": estado},
        ],
        "limit": 200,
        "timeRange": {"type": "relative", "value": {"unit": "month", "amount": 3}},
    }
    async with httpx.AsyncClient(timeout=30) as client:
        resp = await client.post(f"{_base()}/v2/alert", headers=hdrs, json=payload)
        resp.raise_for_status()
        return resp.json().get("items", [])


# ---------------------------------------------------------------
# Suscripciones / Tenants Azure
# ---------------------------------------------------------------

async def get_cloud_from_tenant(tenant_id: str) -> List[Dict]:
    """Devuelve las suscripciones Azure hijas de un tenant."""
    hdrs = await _headers()
    async with httpx.AsyncClient(timeout=20) as client:
        resp = await client.get(
            f"{_base()}/cloud/azure/{tenant_id}/project",
            headers=hdrs,
        )
        resp.raise_for_status()
        return resp.json()
