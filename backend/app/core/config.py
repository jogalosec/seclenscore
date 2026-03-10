"""
Configuración central del backend SecLensCore.
Equivalente a constantes de index.php + secciones de config.ini.
Carga valores desde variables de entorno o archivo .env.
"""
from functools import lru_cache
from typing import List

from pydantic import SecretStr
from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    # ----- Bases de datos -----
    DB_HOST: str = "localhost"
    DB_PORT: int = 3306
    DB_USER: str
    DB_PASSWORD: SecretStr
    DB_SSL: bool = False
    DB_SSL_CA: str = "../../includes/certCaBD.pem"

    DB_NEW: str = "octopus_new"
    DB_SERV: str = "octopus_serv"
    DB_KPMS: str = "octopus_kpms"
    DB_USER_DB: str = "octopus_users"
    DB_CACHE: str = "octopus_cache"
    DB_LOGS: str = "octopus_logs"

    # ----- JWT / SSL -----
    JWT_PRIVATE_KEY_PATH: str = "../../includes/privatekey.pem"
    JWT_PRIVATE_KEY_PASSPHRASE: SecretStr  # hex string del config.ini [ssl] frase
    JWT_AES_KEY: SecretStr                 # hex string del config.ini [ssl] jtikey
    JWT_AES_IV: SecretStr                  # hex string del config.ini [ssl] jtiiv
    JWT_ALGORITHM: str = "RS256"
    JWT_ISSUER: str = "https://11certools.cisocdo.com"
    JWT_AUDIENCE: str = "11CertTool"

    # ----- Azure AD -----
    AZURE_TENANT_ID: str = "9744600e-3e04-492e-baa1-25ec245c6f10"
    AZURE_CLIENT_ID: SecretStr
    AZURE_CLIENT_SECRET: SecretStr
    AZURE_REDIRECT_URI: str = "http://localhost:8000/auth"

    # ----- Email SMTP -----
    SMTP_HOST: str = "localhost"
    SMTP_PORT: int = 587
    SMTP_USER: str = ""
    SMTP_PASSWORD: SecretStr = SecretStr("")
    SMTP_FROM: str = "noreply@seclenscore.com"
    SMTP_TLS: bool = True

    # ----- CORS -----
    ALLOWED_ORIGINS: List[str] = [
        "http://localhost:5173",
        "http://localhost:8080",
        "https://11certools.cisocdo.com",
    ]

    # ----- JIRA -----
    JIRA_BASE_URL: str = "https://jira.tid.es/rest/api"
    JIRA_TOKEN: SecretStr = SecretStr("")
    JIRA_PROJECT_KEY: str = "CISOCDCOIN"
    JIRA_PROJECT_ID: str = "54830"

    # ----- Prisma Cloud -----
    PRISMA_URL: str = "https://api.prismacloud.io"
    PRISMA_USER: str = ""
    PRISMA_PASSWORD: SecretStr = SecretStr("")

    # ----- Kiuwan -----
    KIUWAN_BASE_URL: str = "https://api.kiuwan.com"
    KIUWAN_USERNAME: str = ""
    KIUWAN_PASSWORD: SecretStr = SecretStr("")
    KIUWAN_DOMAIN_ID: str = ""

    # ----- Entorno -----
    APP_ENV: str = "development"
    APP_VERSION: str = "2.0.0"

    class Config:
        env_file = ".env"
        env_file_encoding = "utf-8"

    @property
    def is_production(self) -> bool:
        return self.APP_ENV == "production"

    @property
    def db_password_str(self) -> str:
        return self.DB_PASSWORD.get_secret_value()

    @property
    def jwt_passphrase_str(self) -> str:
        return self.JWT_PRIVATE_KEY_PASSPHRASE.get_secret_value()

    @property
    def jwt_aes_key_str(self) -> str:
        return self.JWT_AES_KEY.get_secret_value()

    @property
    def jwt_aes_iv_str(self) -> str:
        return self.JWT_AES_IV.get_secret_value()


@lru_cache()
def get_settings() -> Settings:
    """Devuelve la instancia de Settings cacheada (singleton)."""
    return Settings()
