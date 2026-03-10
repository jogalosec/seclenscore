"""
Capa de base de datos para SecLensCore.
Gestiona 6 conexiones MySQL async independientes — equivalente a la clase DB de DB.php.
Cada base de datos tiene su propio engine y session factory.
"""
from typing import AsyncGenerator

from sqlalchemy.ext.asyncio import (
    AsyncSession,
    async_sessionmaker,
    create_async_engine,
)
from sqlalchemy.orm import DeclarativeBase

from app.core.config import get_settings

settings = get_settings()


def _build_url(dbname: str) -> str:
    """
    Construye la URL de conexión MySQL async.
    Equivalente al DSN de PDO en DB.php.
    """
    password = settings.db_password_str
    ssl_args = ""
    if settings.DB_SSL:
        ssl_args = f"?ssl_ca={settings.DB_SSL_CA}&ssl_verify_cert=true"

    return (
        f"mysql+aiomysql://{settings.DB_USER}:{password}"
        f"@{settings.DB_HOST}:{settings.DB_PORT}/{dbname}"
        f"?charset=utf8mb4{ssl_args}"
    )


# Un engine por base de datos (equivalente a new DB("octopus_serv") en PHP)
_DB_NAMES = {
    "octopus_new":   settings.DB_NEW,
    "octopus_serv":  settings.DB_SERV,
    "octopus_kpms":  settings.DB_KPMS,
    "octopus_users": settings.DB_USER_DB,
    "octopus_cache": settings.DB_CACHE,
    "octopus_logs":  settings.DB_LOGS,
}

engines = {
    alias: create_async_engine(
        _build_url(dbname),
        pool_pre_ping=True,   # Reconecta si la conexión está muerta
        pool_size=10,
        max_overflow=20,
        pool_recycle=3600,    # Reciclar conexiones cada hora (evita timeouts de MySQL)
        echo=not settings.is_production,  # Log SQL en dev
    )
    for alias, dbname in _DB_NAMES.items()
}

session_factories = {
    alias: async_sessionmaker(engine, class_=AsyncSession, expire_on_commit=False)
    for alias, engine in engines.items()
}


class Base(DeclarativeBase):
    """Clase base para todos los modelos SQLAlchemy del proyecto."""
    pass


async def get_db(dbname: str = "octopus_new") -> AsyncGenerator[AsyncSession, None]:
    """
    Dependency FastAPI que provee una sesión de BD y gestiona commit/rollback.
    Uso: db: AsyncSession = Depends(get_db_factory("octopus_serv"))

    Equivalente al patrón:
        $db = new Activos(DB_SERV);
        ...operaciones...
    """
    factory = session_factories.get(dbname)
    if factory is None:
        raise ValueError(f"Base de datos desconocida: '{dbname}'. "
                         f"Opciones: {list(session_factories.keys())}")
    async with factory() as session:
        try:
            yield session
            await session.commit()
        except Exception:
            await session.rollback()
            raise


def get_db_factory(dbname: str):
    """
    Retorna una función Depends() lista para usar en routers FastAPI.

    Ejemplo de uso en un router:
        @router.get("/endpoint")
        async def mi_endpoint(db: AsyncSession = Depends(get_db_factory("octopus_serv"))):
            ...
    """
    async def _dependency() -> AsyncGenerator[AsyncSession, None]:
        async for session in get_db(dbname):
            yield session

    return _dependency
