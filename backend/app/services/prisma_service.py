"""Servicio de integración Prisma Cloud."""
from typing import Dict, List, Optional

from app.integrations import prisma_client


async def get_clouds() -> Dict:
    accounts = await prisma_client.get_cloud_accounts()
    return {"error": False, "clouds": accounts, "total": len(accounts)}


async def get_cloud_info(account_id: str) -> Dict:
    info = await prisma_client.get_cloud_account_info(account_id)
    return {"error": False, "cloud": info}


async def get_clouds_from_tenant(tenant_id: str) -> Dict:
    subs = await prisma_client.get_cloud_from_tenant(tenant_id)
    return {"error": False, "suscripciones": subs}


async def get_alertas_cloud(
    account_id: str,
    estado: str = "open",
    limit: int = 100,
    offset: int = 0,
) -> Dict:
    data = await prisma_client.get_alerts_by_cloud(account_id, estado, limit, offset)
    items = data.get("items", data) if isinstance(data, dict) else data
    return {"error": False, "alertas": items, "total": len(items) if isinstance(items, list) else data.get("totalRows", 0)}


async def get_alerta_info(alert_id: str) -> Dict:
    data = await prisma_client.get_alert_info(alert_id)
    return {"error": False, "alerta": data}


async def get_alertas_by_policy(policy_id: str, estado: str = "open") -> Dict:
    items = await prisma_client.get_alerts_by_policy(policy_id, estado)
    return {"error": False, "alertas": items, "total": len(items)}


async def dismiss_alerta(alert_id: str, razon: Optional[str]) -> Dict:
    ok = await prisma_client.dismiss_alert(alert_id, razon)
    return {"error": not ok, "message": "Alerta descartada." if ok else "Error al descartar la alerta."}


async def reopen_alerta(alert_id: str) -> Dict:
    ok = await prisma_client.reopen_alert(alert_id)
    return {"error": not ok, "message": "Alerta reabierta." if ok else "Error al reabrir la alerta."}
