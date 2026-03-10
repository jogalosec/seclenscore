"""
Punto de entrada del backend FastAPI de SecLensCore.
Equivalente a la configuración inicial de Slim 4 en public/index.php.
"""
from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.middleware.trustedhost import TrustedHostMiddleware
from fastapi.responses import JSONResponse, RedirectResponse
from slowapi import Limiter, _rate_limit_exceeded_handler
from slowapi.errors import RateLimitExceeded
from slowapi.middleware import SlowAPIMiddleware
from slowapi.util import get_remote_address
from starlette.middleware.base import BaseHTTPMiddleware

from app.api.v1.router import api_router
from app.core.config import get_settings
from app.middleware.permissions_middleware import PermissionsMiddleware
from app.middleware.token_middleware import TokenMiddleware

settings = get_settings()

# ---------------------------------------------------------------
# Rate limiter (protección fuerza bruta en login)
# ---------------------------------------------------------------
limiter = Limiter(key_func=get_remote_address)

# ---------------------------------------------------------------
# Instancia FastAPI
# ---------------------------------------------------------------
app = FastAPI(
    title="API SecLensCore (11CertTool)",
    description="API REST para SecLensCore — migrada de PHP/Slim4 a FastAPI",
    version=settings.APP_VERSION,
    # Ocultar docs en producción
    docs_url=None if settings.is_production else "/docs",
    redoc_url=None if settings.is_production else "/redoc",
    openapi_url=None if settings.is_production else "/openapi.json",
)

# ---------------------------------------------------------------
# Rate limiting
# ---------------------------------------------------------------
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)
app.add_middleware(SlowAPIMiddleware)

# ---------------------------------------------------------------
# Security Headers Middleware
# ---------------------------------------------------------------
class SecurityHeadersMiddleware(BaseHTTPMiddleware):
    """Añade cabeceras de seguridad HTTP en todas las respuestas."""
    async def dispatch(self, request: Request, call_next):
        response = await call_next(request)
        response.headers["X-Content-Type-Options"] = "nosniff"
        response.headers["X-Frame-Options"] = "DENY"
        response.headers["X-XSS-Protection"] = "1; mode=block"
        response.headers["Referrer-Policy"] = "strict-origin-when-cross-origin"
        if settings.is_production:
            response.headers["Strict-Transport-Security"] = "max-age=31536000; includeSubDomains"
        # Ocultar que el servidor es Python/FastAPI
        response.headers.pop("server", None)
        return response

app.add_middleware(SecurityHeadersMiddleware)

# ---------------------------------------------------------------
# CORS — orígenes permitidos del frontend Vue.js
# ---------------------------------------------------------------
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.ALLOWED_ORIGINS,
    allow_credentials=True,   # Necesario para enviar/recibir cookies
    allow_methods=["GET", "POST", "PUT", "DELETE", "OPTIONS"],
    allow_headers=["Content-Type", "Authorization", "X-Requested-With", "X-Token"],
    max_age=3600,
)

# ---------------------------------------------------------------
# Middlewares de autenticación y permisos
# El orden importa: Token primero, luego Permissions
# ---------------------------------------------------------------
app.add_middleware(PermissionsMiddleware)
app.add_middleware(TokenMiddleware)

# ---------------------------------------------------------------
# Routers
# ---------------------------------------------------------------
app.include_router(api_router)

# ---------------------------------------------------------------
# Rutas base
# ---------------------------------------------------------------

@app.get("/", include_in_schema=False)
async def root():
    """Redirige la raíz al login (equivalente al índice en index.php)."""
    return RedirectResponse(url="/login")


@app.get("/health", include_in_schema=False)
async def health():
    """Endpoint de salud para Docker y load balancers."""
    return {"status": "ok", "version": settings.APP_VERSION}


# ---------------------------------------------------------------
# Manejador global de errores no controlados
# ---------------------------------------------------------------
@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    """
    Captura errores no controlados.
    IMPORTANTE: nunca exponer stack traces en producción.
    Corrige el bug del PHP donde los errores devolvían HTTP 200.
    """
    if settings.is_production:
        return JSONResponse(
            status_code=500,
            content={"error": True, "message": "Error interno del servidor"},
        )
    # En desarrollo mostrar el error completo
    return JSONResponse(
        status_code=500,
        content={"error": True, "message": str(exc), "type": type(exc).__name__},
    )
