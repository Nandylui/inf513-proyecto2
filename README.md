# Sistema Web del Consejo Facultativo — Proyecto No. 2 (INF513 · Grupo 07SA)

Aplicación web de tres capas para la gestión del **Consejo Facultativo** de la FICCT (UAGRM):
**React (Vite + Axios) → API RESTful Laravel 12 → PostgreSQL**.
Evoluciona el *Sistema Vía Mail* del primer parcial (que sigue funcionando sobre los mismos servicios de negocio).

Despliegue: <https://www.tecnoweb.org.bo/inf513/grupo07sa/proyecto2>

## Contenido

| Carpeta | Descripción |
|---|---|
| `backend/` | Laravel 12 (PHP 8.3): API `/api`, RBAC, bitácora, visitas, búsqueda, pagos, Sistema Vía Mail, migraciones, seeders y pruebas |
| `frontend/` | SPA React 18 + Vite + Tailwind v4: menú dinámico, temas, accesibilidad, tablero, búsqueda y pagos |
| `docs/` | **Documentación técnica** (`DOCUMENTACION_TECNICA.docx` / `.pdf` / `.md`), diagramas ER y de arquitectura |
| `deploy.sh` | Despliegue en el servidor de la cátedra |
| `COMANDOS_EMAIL.md` | Catálogo de comandos del Sistema Vía Mail |

## Requisitos del Proyecto No. 2 cubiertos

Arquitectura de 3 capas · menú dinámico desde la BD según permisos · RBAC con 6 perfiles de negocio + administrador y matriz de acceso editable ·
bitácora persistente (ingresos, fallos, IP, recursos más accedidos) · identidad visual Tailwind · 3 temas (Niños, Jóvenes, Adultos) + Día/Noche automático o manual ·
accesibilidad (tamaño de fuente, alto contraste, WCAG básico) · validación estricta cliente/servidor en español · contador de visitas por página en el pie ·
tablero con gráficos de negocio y de accesos · búsqueda global predictiva · pagos electrónicos (métodos, pagos únicos, planes de cuotas; pasarela simulada).
La matriz requisito → implementación → evidencia está en la sección 2 de la documentación.

## Puesta en marcha local

```bash
# 1) Backend (PostgreSQL con una base vacía creada)
cd backend
composer install
cp .env.example .env            # editar DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan key:generate
php artisan migrate --seed
php artisan serve               # http://127.0.0.1:8000

# 2) Frontend (otra terminal)
cd frontend
npm install
npm run dev                     # http://localhost:5173  (proxy /api -> :8000)
```

Usuarios de ejemplo (con `SEED_DEMO=true`): `admin@consejo.test` / `Admin2026*`; `presidente@`, `secretario@`, `titular.docente.inf@`, `titular.estudiante.inf@consejo.test` / `Consejo2026*`.

## Pruebas

```bash
cd backend  && php artisan test     # 65 pruebas, 594 aserciones
cd frontend && npm test             # 9 pruebas (Vitest)
```

## Despliegue en tecnoweb.org.bo

```bash
./deploy.sh /ruta/publicada/por/apache/inf513/grupo07sa/proyecto2 --seed
```

El script instala dependencias, crea `backend/.env` la primera vez (complete ahí las credenciales de PostgreSQL de `db.tecnoweb.org.bo`),
migra la base y publica `backend/public` (la SPA ya compilada) dejando el código de Laravel fuera de la carpeta pública.
Para recompilar la SPA: `cd frontend && npm run build` (escribe en `backend/public`). Detalles en la sección 10 de la documentación.

## Esquema propio, respaldo y restauración de la base de datos

- **Esquema propio:** en el servidor de la cátedra la base `db_grupo07sa` ya contiene tablas del Vía Email. Para no mezclarlas, cree un esquema (`CREATE SCHEMA consejo_web;`) y defina `DB_SCHEMA=consejo_web` en `backend/.env`; las migraciones crean ahí todas las tablas, funciones y triggers. En local puede dejarse `DB_SCHEMA=public`.
- **Respaldo:** `php artisan bd:respaldo` genera `storage/app/respaldos/respaldo_AAAAMMDD_HHMMSS.dump` (formato `pg_dump -Fc` del esquema; conserva los 10 más recientes).
- **Restauración:** `php artisan bd:restaurar` (el respaldo más reciente) o `php artisan bd:restaurar ruta/al/archivo.dump`; reemplaza las tablas del esquema.
- Requiere `pg_dump`/`pg_restore`. En Windows se buscan en `C:\Program Files\PostgreSQL\<versión>\bin`; si están en otro lugar, defina `PG_BIN` en el `.env`.

> `backend/.env` contiene credenciales y **no se versiona**.
