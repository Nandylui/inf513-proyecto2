# Comandos del Sistema Vía Mail — grupo07sa

Se envían en el **asunto** del correo a `grupo07sa@tecnoweb.org.bo`, con el formato `COMANDO["parametro1","parametro2"]`. El remitente debe ser un usuario registrado (su correo en la tabla `usuario`). `*` omite un filtro opcional. Generado con `php artisan correo:comandos --markdown`.

### CU1 - Usuarios y roles

| Comando | Sintaxis | Descripción | Permiso |
|---|---|---|---|
| `ELIUSU` | `ELIUSU["ci"]` | Desactiva un usuario (no se borra: se conserva el historial y la auditoría). | usuarios.gestionar |
| `INSUSU` | `INSUSU["ci","nombres","apellidos","correo","rol","telefono"]` | Registra un usuario. Roles: ADMINISTRADOR, PRESIDENTE, SECRETARIO, TITULAR_DOCENTE, SUPLENTE_DOCENTE, TITULAR_ESTUDIANTE, SUPLENTE_ESTUDIANTE. Clave inicial = CI. | usuarios.gestionar |
| `LISROL` | `LISROL[]` | Lista los roles y las acciones que cada uno puede ejecutar. | cualquier usuario |
| `LISUSU` | `LISUSU["filtro"]` | Lista usuarios (filtro opcional: rol, CI, nombre o correo). | usuarios.ver |
| `MODUSU` | `MODUSU["ci","campo","valor"]` | Modifica un dato del usuario. Campos: nombres, apellidos, correo, telefono, rol, estado, password. | usuarios.gestionar |
| `VERPER` | `VERPER[]` | Muestra su perfil y su estado en el Consejo. | cualquier usuario |

### CU2 - Miembros del Consejo y quórum

| Comando | Sintaxis | Descripción | Permiso |
|---|---|---|---|
| `CONMAN` | `CONMAN["id_mandato"]` | Concluye un mandato (pasa al historial). | miembros.gestionar |
| `INSMAN` | `INSMAN["ci_titular","ci_suplente","carrera","estamento","inicio","fin"]` | Registra un mandato (máx. 2 años, sin reelección, 1 titular + 1 suplente por carrera y estamento). Use "NINGUNO" si no hay suplente. | miembros.gestionar |
| `LISMAN` | `LISMAN["filtro"]` | Lista mandatos (titular y suplente por carrera/estamento). Filtro: VIGENTE, CONCLUIDO o carrera. | miembros.ver |
| `MODMAN` | `MODMAN["id_mandato","campo","valor"]` | Modifica un mandato. Campos: suplente (CI), fecha_fin. | miembros.gestionar |
| `PANSES` | `PANSES["id_sesion"]` | Panel del Consejo: titulares, suplentes y quién está habilitado (con sesión) o presente. | miembros.ver |
| `VERQUO` | `VERQUO["id_sesion"]` | Calcula el quórum de una sesión (mayoría absoluta de miembros, Art. 53). | miembros.ver |

### CU3 - Convocatorias, excusas y suplentes

| Comando | Sintaxis | Descripción | Permiso |
|---|---|---|---|
| `ESTSES` | `ESTSES["id_sesion","estado"]` | Cambia el estado: EN_CURSO (exige quórum), FINALIZADA o CANCELADA. | sesion.convocar |
| `HABSUP` | `HABSUP["id_sesion","ci_titular","motivo"]` | Habilita manualmente al suplente (por ejemplo, ausencia sin excusa previa). | miembros.gestionar |
| `INSEXC` | `INSEXC["id_sesion","ci_titular","motivo"]` | Registra la excusa de un titular; el suplente se habilita automáticamente. El titular puede enviarla desde su propio correo. | sesion.excusa |
| `INSPUN` | `INSPUN["id_sesion","titulo","descripcion"]` | Agrega un punto al orden del día. | sesion.convocar |
| `INSSES` | `INSSES["fecha_hora","lugar","tipo","orden_del_dia","solicitantes"]` | Convoca una sesión. Fecha: AAAA-MM-DD HH:MM. Tipo: ORDINARIA o EXTRAORDINARIA. Los puntos del orden del día se separan con ";". Extraordinaria por miembros: indique solicitantes >= 3. | sesion.convocar |
| `LISEXC` | `LISEXC["id_sesion"]` | Lista las excusas de una sesión. | sesion.ver |
| `LISSES` | `LISSES["filtro"]` | Lista sesiones (filtro opcional: estado, tipo o lugar). | sesion.ver |
| `MODSES` | `MODSES["id_sesion","campo","valor"]` | Modifica una sesión PROGRAMADA. Campos: lugar, fecha_hora, tipo. | sesion.convocar |
| `VERHAB` | `VERHAB["id_sesion"]` | Muestra quiénes están habilitados para asistir a la sesión. | sesion.ver |
| `VERSES` | `VERSES["id_sesion"]` | Detalle de una sesión: orden del día, habilitaciones y quórum. | sesion.ver |

### CU4 - Repositorio documental y flujo de validación

| Comando | Sintaxis | Descripción | Permiso |
|---|---|---|---|
| `CONTRA` | `CONTRA["id_tipo","roles","mayoria"]` | Configura el flujo de un trámite. Roles separados por ";" en orden. Mayoría: ABSOLUTA o DOS_TERCIOS. | tramite.configurar |
| `INSDOC` | `INSDOC["titulo","id_sesion","tipo","punto","prioridad","descripcion"]` | Registra un documento para una sesión (debe presentarse con 48 h de anticipación). Prioridad 1 (alta) a 5 (baja). | documento.registrar |
| `LISDOC` | `LISDOC["sesion","estado","tipo","texto"]` | Lista documentos del repositorio, ordenados por prioridad. Use "*" para omitir un filtro. | documento.ver |
| `LISTRA` | `LISTRA[]` | Lista los tipos de trámite con su flujo de validación y la mayoría requerida. | documento.ver |
| `SUBVER` | `SUBVER["id_documento","comentario"]` | Registra una nueva versión del documento (el archivo se adjunta desde el sistema web). | documento.registrar |
| `VALDOC` | `VALDOC["id_documento","decision","observaciones"]` | Valida el documento en su paso del flujo. Decisión: APROBADO, OBSERVADO o RECHAZADO (las dos últimas exigen observaciones). Solo el rol del paso actual puede validar. | documento.validar |
| `VERDOC` | `VERDOC["id_documento"]` | Detalle del documento y su historial de validaciones. | documento.ver |
| `VERFLU` | `VERFLU["id_documento"]` | Trazabilidad: pasos del flujo, quién validó, cuándo y con qué observaciones. | documento.ver |

### CU5 - Asistencia, quórum y votaciones

| Comando | Sintaxis | Descripción | Permiso |
|---|---|---|---|
| `CERVOT` | `CERVOT["id_sesion","id_documento"]` | Cierra la votación, determina el resultado según la mayoría y genera la resolución numerada. | votacion.cerrar |
| `LISASI` | `LISASI["id_sesion"]` | Lista los asistentes de la sesión y el estado del quórum. | asistencia.ver |
| `REGASI` | `REGASI["id_sesion","ci","metodo"]` | Registra la asistencia de un consejero (simula la marca del reloj biométrico). Método: HUELLA, FACIAL o MANUAL. Al lograr quórum la sesión se instala. | asistencia.registrar |
| `REGVOT` | `REGVOT["id_sesion","id_documento","voto","ci"]` | Emite un voto: A FAVOR, EN CONTRA o BLANCO. Sin "ci" vota el propio remitente; el Presidente/Secretario pueden registrar el voto de otro consejero presente. El voto es secreto en la auditoría. | votar |
| `VERVOT` | `VERVOT["id_sesion","id_documento"]` | Conteo de la votación y mayoría requerida. | asistencia.ver |

### CU6 - Resoluciones, veto y actas

| Comando | Sintaxis | Descripción | Permiso |
|---|---|---|---|
| `GENACT` | `GENACT["id_sesion"]` | Genera el acta de la sesión (asistentes, quórum, suplentes, resoluciones) con sello SHA-256. | acta.generar |
| `LISRES` | `LISRES["filtro"]` | Lista resoluciones (filtro opcional: estado o número). | resolucion.ver |
| `MANRES` | `MANRES["id_resolucion","votos_favor","presentes"]` | Resuelve el veto: si hay dos tercios la resolución se mantiene; si no, queda sin efecto. | resolucion.mantener |
| `PRORES` | `PRORES["id_resolucion"]` | Promulga la resolución (el Decano tiene 5 días hábiles, Art. 64 a). | resolucion.promulgar |
| `PUBRES` | `PUBRES["id_resolucion","gaceta"]` | Publica la resolución en la Gaceta Universitaria (Art. 57). | resolucion.publicar |
| `VERACT` | `VERACT["id_sesion"]` | Consulta el acta de la sesión. | resolucion.ver |
| `VERRES` | `VERRES["id_resolucion"]` | Detalle de una resolución. | resolucion.ver |
| `VETRES` | `VETRES["id_resolucion","fundamento"]` | Veta la resolución con fundamento (Art. 64 b): suspende su efecto. | resolucion.vetar |

### CU7 - Fiscalización, auditoría y Gaceta pública

| Comando | Sintaxis | Descripción | Permiso |
|---|---|---|---|
| `ENVNOT` | `ENVNOT["destino","asunto","mensaje"]` | Envía una notificación a un consejero (CI) o a todo el Consejo ("*"). | notificacion.enviar |
| `LEENOT` | `LEENOT["id_notificacion"]` | Lee una notificación y la marca como leída. | cualquier usuario |
| `LISAUD` | `LISAUD["ci","entidad","accion","canal"]` | Bitácora de auditoría (últimos 200 eventos). Use "*" para omitir un filtro. Canal: WEB o CORREO. | auditoria.ver |
| `LISNOT` | `LISNOT["solo_no_leidas"]` | Lista sus notificaciones (convocatorias, plazos, resoluciones). Escriba SI para ver solo las no leídas. | cualquier usuario |
| `LISPUB` | `LISPUB["SESIONES\|ACTAS\|RESOLUCIONES"]` | Consulta pública (Art. 56 y 57): sesiones, actas o resoluciones publicadas. No requiere estar registrado cuando el modo anónimo está habilitado. | cualquier usuario |
| `VERPUB` | `VERPUB["id_sesion"]` | Acta pública de una sesión. | cualquier usuario |

### CU8 - Reportes y estadísticas

| Comando | Sintaxis | Descripción | Permiso |
|---|---|---|---|
| `REPASI` | `REPASI["id_sesion \| CONSEJEROS"]` | Reporte de asistencia: por sesión (sin parámetro), de una sesión concreta, o por consejero (CONSEJEROS). | reporte.ver |
| `REPEST` | `REPEST[]` | Estadísticas generales del Consejo. | reporte.ver |
| `REPRES` | `REPRES["estado"]` | Reporte de resoluciones aprobadas/rechazadas (filtro opcional por estado). | reporte.ver |
| `REPTRA` | `REPTRA[]` | Reporte de trámites: documentos por tipo y estado. | reporte.ver |
| `REPVOT` | `REPVOT["id_sesion"]` | Reporte de votaciones de una sesión. | reporte.ver |

### Ayuda

| Comando | Sintaxis | Descripción | Permiso |
|---|---|---|---|
| `AYUDA` | `AYUDA["comando"]` | Muestra los comandos disponibles o el detalle de uno. | cualquier usuario |

