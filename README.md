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
tablero con gráficos de negocio y de accesos · búsqueda global predictiva · pagos electrónicos (métodos, pagos únicos, planes de cuotas; pasarela simulada) ·
triggers de PostgreSQL (bitácora de solo escritura, última visita, plan completado).
La matriz requisito → implementación → evidencia está en la sección 2 de la documentación.

---

# Guía de instalación paso a paso (para correrlo en su computadora)

Esta guía lleva el proyecto desde cero hasta verlo funcionando en `http://localhost:5173`. Los comandos están escritos para **Windows (PowerShell)**; en Linux/macOS son los mismos, salvo donde se indica.

## 1. Qué debe instalar antes

| Programa | Versión | Para qué sirve | Cómo comprobar que quedó bien |
|---|---|---|---|
| **PHP** | 8.2 o superior (probado con 8.3) | Ejecuta el backend Laravel | `php -v` |
| **Composer** | 2.x | Instala las librerías de PHP | `composer -V` |
| **Node.js** | 20.19 o superior (probado con 22) | Compila y ejecuta el frontend React | `node -v` y `npm -v` |
| **PostgreSQL** | 14 o superior (probado con 16 y 17) | Base de datos | `psql --version` o abra pgAdmin |
| **Git** | cualquiera | Descarga el proyecto | `git --version` |

**Atajo recomendado en Windows: [Laragon Full](https://laragon.net/download/).** Incluye PHP, Composer, Node.js y Git ya configurados. PostgreSQL se instala aparte (con su pgAdmin) desde <https://www.postgresql.org/download/windows/>.

Después de instalar, **cierre y vuelva a abrir** VS Code o la terminal (y si sigue sin reconocer `php`, reinicie la PC) para que se actualice el `PATH`.

### Activar la extensión de PostgreSQL en PHP

PHP necesita la extensión `pdo_pgsql`. Compruébelo con:

```powershell
php -m | findstr pgsql
```

Debe mostrar `pdo_pgsql` y `pgsql`. Si no muestra nada: abra el `php.ini` (muestra su ruta con `php --ini`), quite el `;` del inicio de las líneas `extension=pdo_pgsql` y `extension=pgsql`, guarde y vuelva a abrir la terminal.

## 2. Descargar el proyecto

```powershell
git clone https://github.com/<su_cuenta>/inf513-proyecto2.git
cd inf513-proyecto2
```

> **Consejo:** no lo guarde dentro de OneDrive ni de Google Drive. La sincronización vuelve lento a `npm` y a `composer` (miles de archivos). Una carpeta como `C:\proyectos\` funciona mejor.

## 3. Crear la base de datos

En **pgAdmin**: clic derecho en *Databases* → *Create* → *Database…* → nombre `db_consejo_web` → *Save*.
O por terminal: `psql -U postgres -c "CREATE DATABASE db_consejo_web;"`

Debe quedar **vacía**; las tablas las crea el paso siguiente.

## 4. Backend (Laravel)

Abra una terminal en la carpeta del proyecto y ejecute:

```powershell
cd backend
composer install
copy .env.example .env          # en Linux/macOS: cp .env.example .env
php artisan key:generate
```

Abra el archivo `backend/.env` y ajuste **solo** las credenciales de su PostgreSQL local:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=db_consejo_web
DB_USERNAME=postgres
DB_PASSWORD=su_clave_de_postgres
DB_SCHEMA=public
```

Cree las tablas y cargue los datos de demostración, y levante el servidor:

```powershell
php artisan migrate --seed
php artisan serve               # queda corriendo en http://127.0.0.1:8000
```

Deje esa terminal abierta.

## 5. Frontend (React)

Abra **otra terminal** (el backend sigue corriendo en la primera):

```powershell
cd frontend
npm install
npm run dev                     # queda corriendo en http://localhost:5173
```

## 6. Abrir el sistema

Entre a **<http://localhost:5173>** en el navegador. En desarrollo, Vite reenvía automáticamente las peticiones `/api` al backend (puerto 8000), así que no hace falta configurar CORS.

### Usuarios de demostración

| Perfil | Correo | Contraseña |
|---|---|---|
| Administrador | `admin@consejo.test` | `Admin2026*` |
| Presidente | `presidente@consejo.test` | `Consejo2026*` |
| Secretario | `secretario@consejo.test` | `Consejo2026*` |
| Docente titular | `titular.docente.inf@consejo.test` | `Consejo2026*` |
| Docente suplente | `suplente.docente.inf@consejo.test` | `Consejo2026*` |
| Estudiante titular | `titular.estudiante.inf@consejo.test` | `Consejo2026*` |
| Estudiante suplente | `suplente.estudiante.inf@consejo.test` | `Consejo2026*` |

Hay consejeros de tres carreras: cambie `inf` por `sis` o `red` (Sistemas y Redes). Cada perfil ve un menú distinto, según los permisos que la matriz de acceso le asigna.

### Tarjetas de prueba para los pagos (pasarela simulada; nunca se hace un cargo real)

| Número | Resultado |
|---|---|
| `4111 1111 1111 1111` | Pago aprobado |
| `4000 0000 0000 0002` | Rechazada por el emisor |
| `4000 0000 0000 9995` | Fondos insuficientes |

Use vencimiento `12/29` y CVV `123`. Solo se guarda la marca y los últimos 4 dígitos.

### Recorrido sugerido para probarlo

1. Entre como **administrador** → *Seguridad → Matriz de acceso* y *Menú dinámico*; *Bitácora de accesos* y su pestaña *Análisis de accesos*.
2. Cambie de tema y modo (botón **Accesibilidad**, arriba): Niños, Jóvenes, Adultos, Día/Noche, tamaño de letra y alto contraste.
3. Entre como **secretario** y convoque una sesión; como **titular docente** registre una excusa y verá cómo se habilita al suplente.
4. Entre como **estudiante titular** → *Pagos*: registre una tarjeta, haga un pago único y un plan en cuotas.
5. Pruebe la búsqueda global del encabezado y el contador de visitas del pie de página.

## 7. Problemas frecuentes

| Síntoma | Causa y solución |
|---|---|
| `php` o `composer` "no se reconoce como comando" | No están en el `PATH`. Instale Laragon, cierre y reabra la terminal o reinicie la PC |
| `could not find driver` al migrar | Falta la extensión de PostgreSQL en PHP: vea *Activar la extensión de PostgreSQL* |
| `password authentication failed` o `database does not exist` | El usuario, la clave o el nombre en `backend/.env` no coinciden con su PostgreSQL, o no creó la base (paso 3) |
| La pantalla muestra "El servidor tardó demasiado en responder" | El backend no está corriendo, o la carpeta está dentro de OneDrive. Verifique que la terminal de `php artisan serve` siga abierta y sin errores; mueva el proyecto fuera de OneDrive |
| El puerto 8000 o 5173 está ocupado | Cierre el programa que lo usa, o use `php artisan serve --port=8001` y, en PowerShell, `$env:VITE_PROXY="http://127.0.0.1:8001"; npm run dev` (Linux/macOS: `VITE_PROXY=http://127.0.0.1:8001 npm run dev`) |
| Cambié el `.env` y no se nota | Detenga con `Ctrl + C` y vuelva a ejecutar `php artisan serve` |
| Cambié código PHP y no se nota | Los cambios en PHP se aplican al recargar; los del `.env` requieren reiniciar el servidor |
| Quiero empezar de cero en local | `php artisan migrate:fresh --seed` **(solo en su base local de pruebas: borra todas las tablas del esquema)** |

> **Nunca ejecute `migrate:fresh` ni `db:wipe` contra el servidor de la cátedra:** pueden borrar tablas que no son de este proyecto.

## 8. Pruebas automáticas

```powershell
# Backend (desde la carpeta del proyecto)
cd backend
php artisan test                    # 67 pruebas (SQLite en memoria; 1 se omite porque requiere PostgreSQL)

# Frontend (desde la carpeta del proyecto)
cd ..\frontend
npm test                            # 9 pruebas (Vitest)
```

Para ejecutar las pruebas del backend sobre PostgreSQL (incluidos los triggers): cree una base vacía de pruebas y use
`$env:DB_CONNECTION="pgsql"; $env:DB_DATABASE="db_prueba"; php artisan test` (en Linux/macOS: `DB_CONNECTION=pgsql DB_DATABASE=db_prueba php artisan test`).

---

# Operación

## Esquema propio, respaldo y restauración de la base de datos

- **Esquema propio:** en el servidor de la cátedra la base `db_grupo07sa` ya contiene tablas del Vía Email. Para no mezclarlas, cree un esquema (`CREATE SCHEMA consejo_web;`) y defina `DB_SCHEMA=consejo_web` en `backend/.env`; las migraciones crean ahí todas las tablas, funciones y triggers. En local puede dejarse `DB_SCHEMA=public`.
- **Respaldo:** `php artisan bd:respaldo` genera `storage/app/respaldos/respaldo_AAAAMMDD_HHMMSS.dump` (formato `pg_dump -Fc` del esquema; conserva los 10 más recientes). Es una copia de todas las tablas y datos en ese momento.
- **Restauración:** `php artisan bd:restaurar` (el respaldo más reciente) o `php artisan bd:restaurar ruta/al/archivo.dump`; reemplaza las tablas del esquema. Sirve para recuperar el sistema si la base se pierde o se borra.
- Requiere `pg_dump`/`pg_restore`. En Windows se buscan en `C:\Program Files\PostgreSQL\<versión>\bin`; si están en otro lugar, defina `PG_BIN` en el `.env`.
- Para operar contra otro servidor sin tocar su `.env` local, cree `backend/.env.servidor` y agregue `--env=servidor` a los comandos de `artisan` (ese archivo **no se versiona**).

## Despliegue en tecnoweb.org.bo

```bash
./deploy.sh /ruta/publicada/por/apache/inf513/grupo07sa/proyecto2 --seed
```

El script instala dependencias, crea `backend/.env` la primera vez (complete ahí las credenciales de PostgreSQL de `db.tecnoweb.org.bo` y `DB_SCHEMA`),
migra la base y publica `backend/public` (la SPA ya compilada) dejando el código de Laravel fuera de la carpeta pública.
Para recompilar la SPA: `cd frontend && npm run build` (escribe en `backend/public`). Detalles en la sección 10 de la documentación.

> `backend/.env` y cualquier `.env.*` contienen credenciales y **no se versionan** (solo `.env.example`). No suba nunca contraseñas al repositorio.
