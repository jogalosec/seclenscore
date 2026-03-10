"""
Cliente async para la API de Kiuwan — reemplaza includes/Kiuwan.php.
Usa httpx en lugar de curl_multi para peticiones paralelas.
"""
import base64
from typing import Any, Optional

import httpx

from app.core.config import get_settings

settings = get_settings()

KIUWAN_BASE_URL = "https://api.kiuwan.com"


def _auth_header() -> dict:
    credentials = f"{settings.KIUWAN_USERNAME}:{settings.KIUWAN_PASSWORD.get_secret_value()}"
    encoded = base64.b64encode(credentials.encode()).decode()
    return {
        "Authorization": f"Basic {encoded}",
        "Content-Type": "application/json",
        "X-KW-CORPORATE-DOMAIN-ID": settings.KIUWAN_DOMAIN_ID,
    }


async def get_applications(endpoint: str = "/apps/list") -> list[dict]:
    """Obtiene la lista de aplicaciones de Kiuwan — equivale a getApplications() de Kiuwan.php."""
    url = KIUWAN_BASE_URL + endpoint
    async with httpx.AsyncClient(timeout=30) as client:
        resp = await client.get(url, headers=_auth_header())
        resp.raise_for_status()
    data = resp.json()
    if not isinstance(data, list) or len(data) == 0:
        raise ValueError("No se encontraron aplicaciones en la respuesta de Kiuwan.")
    return data


async def get_last_analysis(app_name: str) -> Optional[dict]:
    """Obtiene el último análisis de una aplicación."""
    endpoint = f"/apps/analysis/last?app={app_name}"
    url = KIUWAN_BASE_URL + endpoint
    async with httpx.AsyncClient(timeout=30) as client:
        resp = await client.get(url, headers=_auth_header())
        if resp.status_code == 404:
            return None
        resp.raise_for_status()
    return resp.json()


async def execute_multi_requests(endpoints: list[str]) -> list[Any]:
    """
    Ejecuta múltiples peticiones en paralelo con httpx — equivale a
    executeMultiCurlRequests() de Kiuwan.php que usaba curl_multi_*.
    """
    headers = _auth_header()
    async with httpx.AsyncClient(timeout=30) as client:
        import asyncio
        tasks = [client.get(KIUWAN_BASE_URL + ep, headers=headers) for ep in endpoints]
        responses = await asyncio.gather(*tasks, return_exceptions=True)

    results = []
    for resp in responses:
        if isinstance(resp, Exception):
            raise resp
        resp.raise_for_status()
        results.append(resp.json())
    return results
