# Chasqui — Guía de integración

Chasqui es el servicio central de notificaciones del entorno. Los sistemas (Personal, Jefatura, Agentes, Salud, Liquidaciones, etc.) le piden a Chasqui "enviá este mensaje", y Chasqui se encarga de despacharlo por Slack, Telegram, WhatsApp o email según corresponda. Esta guía es para el equipo que integra un sistema **emisor**: cómo llamar, qué mandar, qué esperar de vuelta y cómo confirmar que el mensaje efectivamente salió.

## Índice

1. [URL base](#1-url-base)
2. [Autenticación](#2-autenticación)
3. [Límite de tasa](#3-límite-de-tasa)
4. [Enviar una notificación](#4-enviar-una-notificación)
5. [Qué pasa después de encolar](#5-qué-pasa-después-de-encolar)
6. [Consultar el resultado de una petición](#6-consultar-el-resultado-de-una-petición)
7. [Listar y filtrar peticiones](#7-listar-y-filtrar-peticiones)
8. [Errores](#8-errores)
9. [Referencia rápida](#9-referencia-rápida)

---

## 1. URL base

```
https://<host-interno-de-chasqui>/api
```

Chasqui vive en un entorno aislado (k8s), sin ruta pública. Pedile a infraestructura la URL interna real para tu entorno — los ejemplos de esta guía usan rutas relativas (`/api/notificar`, etc.) sobre esa base.

## 2. Autenticación

Cada sistema tiene **su propia clave**, no hay una clave compartida. Se envía en un header en cada request:

```
X-Chasqui-Key: <tu-clave>
```

- La clave identifica a tu sistema. **No hace falta (ni se debe) mandar el nombre del sistema** — Chasqui ya sabe quién sos por la clave.
- Si te falta el header, la clave no existe, o está fuera de su ventana de vigencia (`fecha_desde` / `fecha_hasta`), la respuesta es `401`.
- Si no tenés tu clave o necesitás una nueva, pedísela a quien administra Chasqui.

```json
// 401 — clave inválida, vencida o ausente
{ "error": "No autorizado." }
```

## 3. Límite de tasa

Hay un límite de requests por minuto por IP de origen (120/min por defecto, puede variar según configuración). Si lo superás, la respuesta es `429`:

```json
// 429
{ "message": "Too Many Attempts." }
```

## 4. Enviar una notificación

```
POST /api/notificar
Content-Type: application/json
X-Chasqui-Key: <tu-clave>
```

### Campos comunes (siempre)

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `mensaje` | string | Sí | El texto de la notificación. |
| `nivel` | string | Sí | Uno de: `error`, `success`, `info`. Define color/formato en Slack y Telegram. |
| `canal` | string | No | Uno de: `slack`, `telegram`, `whatsapp`, `mailrelay`. Si lo omitís, Chasqui evalúa **todos** los canales y envía por cada uno que tenga los datos necesarios (ver [broadcast](#comportamiento-sin-canal)). |

### Campos según el canal

Estos campos van al mismo nivel que `mensaje` y `nivel` en el body — no anidados.

| Canal | Campo | Tipo | Obligatorio si `canal` es este | Notas |
|---|---|---|---|---|
| `slack` | — | — | — | No requiere campos extra. |
| `telegram` | — | — | — | No requiere campos extra. |
| `whatsapp` | `telefono` | string | Sí | Solo dígitos, sin `+` ni espacios ni guiones. Ej: `5493834123456`. |
| `whatsapp` | `template` | string | No | Nombre de una plantilla aprobada. Si la mandás, se ignora `mensaje` como texto libre y se usa la plantilla. |
| `whatsapp` | `parametros` | objeto | Solo si mandás `template` | Clave/valor con las variables de la plantilla. Ej: `{"nombre": "Juan", "hora": "10:00"}`. |
| `mailrelay` | `destinatarios` | array de strings | Sí | Uno o más emails. Los que tengan formato inválido se descartan (no frenan el envío a los válidos). |
| `mailrelay` | `asunto` | string | Sí | Máximo 50 caracteres. |

<a name="comportamiento-sin-canal"></a>
**Si omitís `canal`:** Chasqui intenta por Slack y Telegram siempre (no necesitan datos extra), y por WhatsApp/Mailrelay únicamente si mandaste `telefono` o `destinatarios` respectivamente. No hace falta que armes una petición por canal — mandás todos los datos que tengas y Chasqui decide qué aplica.

### Ejemplos

**Slack / Telegram (sin canal específico → va a ambos):**

```json
POST /api/notificar
{
  "mensaje": "El proceso de liquidación terminó con errores",
  "nivel": "error"
}
```

**WhatsApp, texto libre:**

```json
POST /api/notificar
{
  "canal": "whatsapp",
  "mensaje": "Su turno es mañana a las 10:00",
  "nivel": "info",
  "telefono": "5493834123456"
}
```

**WhatsApp, con plantilla:**

```json
POST /api/notificar
{
  "canal": "whatsapp",
  "mensaje": "Recordatorio de turno",
  "nivel": "info",
  "telefono": "5493834123456",
  "template": "recordatorio_turno",
  "parametros": { "nombre": "Juan", "hora": "10:00" }
}
```

> Con `template`, `mensaje` sigue siendo obligatorio (queda registrado en la auditoría) pero no se usa como contenido del WhatsApp — el contenido lo define la plantilla + `parametros`.

**Mailrelay (email masivo):**

```json
POST /api/notificar
{
  "canal": "mailrelay",
  "mensaje": "<h1>Aumento aprobado</h1><p>Detalle...</p>",
  "nivel": "success",
  "destinatarios": ["persona1@ejemplo.com", "persona2@ejemplo.com"],
  "asunto": "Novedad de liquidación"
}
```

> `mensaje` acá es el cuerpo HTML del email.

### Respuesta

Si la petición es válida, Chasqui responde **inmediatamente** con `202` — la notificación todavía no se envió, quedó encolada:

```json
// 202
{
  "status": "Encolado",
  "id": "01a01089-ad6e-7142-aff2-d74775825e0d"
}
```

**Guardá ese `id`.** Es tu forma de confirmar más tarde qué pasó realmente con la notificación (ver sección 6).

## 5. Qué pasa después de encolar

La respuesta `202` es instantánea (validación + guardado en base, nada más) — no significa que el mensaje ya salió. Internamente:

1. Chasqui guarda la petición y te devuelve el `id` al instante.
2. Un worker en segundo plano toma la petición de la cola y la procesa: llama a Slack/Telegram/WhatsApp/Mailrelay según corresponda (en paralelo entre sí, no uno detrás del otro).
3. En condiciones normales esto tarda milisegundos a un par de segundos. **En el peor caso — un canal lento o caído — hasta ~20 segundos**, que es el timeout máximo configurado por canal antes de darlo por fallido.

Si te importa saber si realmente se entregó (no solo que fue aceptado), no lo asumas por el `202`: consultá el `id` unos minutos después contra el endpoint de auditoría.

## 6. Consultar el resultado de una petición

```
GET /api/notificaciones/{id}
X-Chasqui-Key: <tu-clave>
```

Solo podés consultar peticiones de **tu propio sistema** (identificado por tu clave). Si el `id` no existe o pertenece a otro sistema, la respuesta es `404` — no se distingue entre "no existe" y "no es tuya", para no filtrar información de otros sistemas.

**Ejemplo de respuesta (`200`):**

```json
{
  "id": "01a01089-ad6e-7142-aff2-d74775825e0d",
  "sistema": "Salud",
  "canal": "whatsapp",
  "mensaje": "Su turno es mañana a las 10:00",
  "nivel": "info",
  "datos": {
    "telefono": "5493834123456",
    "template": "recordatorio_turno",
    "parametros": { "nombre": "Juan", "hora": "10:00" }
  },
  "ip_origen": "10.0.4.12",
  "estado": "procesado",
  "created_at": "2026-08-17T16:24:13.000000Z",
  "updated_at": "2026-08-17T16:24:13.000000Z",
  "attempts": [
    {
      "id": 9,
      "notification_request_id": "01a01089-ad6e-7142-aff2-d74775825e0d",
      "canal": "whatsapp",
      "estado": "enviado",
      "http_status": 200,
      "detalle": null,
      "created_at": "2026-08-17T16:24:13.000000Z",
      "updated_at": "2026-08-17T16:24:13.000000Z"
    }
  ]
}
```

### `estado` de la petición (nivel general)

| Valor | Significa |
|---|---|
| `pendiente` | Todavía no la procesó el worker. Volvé a consultar en unos segundos. |
| `procesado` | Todo lo que aplicaba se envió con éxito. |
| `parcial` | Se envió por algunos canales y falló por otros (ej: Slack ok, WhatsApp con error). Revisá `attempts`. |
| `fallido` | No se envió por ningún canal. Revisá `attempts` para el motivo. |
| `sin_canales` | Ningún canal tenía los datos necesarios para aplicar (caso raro, normalmente en broadcast sin `telefono` ni `destinatarios`). |

### `attempts[].estado` (por canal)

| Valor | Significa |
|---|---|
| `enviado` | Ese canal confirmó el envío (respuesta HTTP exitosa). Ver `http_status`. |
| `fallido` | Se intentó pero falló — timeout, error del proveedor, o datos inválidos (ej: emails todos con formato incorrecto). El motivo va en `detalle`. |
| `omitido` | Ese canal ni se intentó porque, en modo broadcast, no tenías los datos que necesitaba (ej: WhatsApp sin `telefono`). No es un error tuyo si no lo necesitabas. |

## 7. Listar y filtrar peticiones

```
GET /api/notificaciones
X-Chasqui-Key: <tu-clave>
```

Devuelve, paginado, **solo las peticiones de tu propio sistema** (salvo que tu clave sea de auditoría/admin, en cuyo caso ves todas y podés filtrar por `sistema`).

| Query param | Tipo | Descripción |
|---|---|---|
| `canal` | string | Filtra por canal. |
| `estado` | string | Uno de los valores de la tabla de arriba. |
| `desde` | fecha (`YYYY-MM-DD`) | Peticiones creadas desde esa fecha. |
| `hasta` | fecha (`YYYY-MM-DD`) | Peticiones creadas hasta esa fecha. |
| `per_page` | int (1–100) | Tamaño de página, default 25. |
| `sistema` | string | Solo tiene efecto con clave de auditoría/admin — con una clave normal se ignora, siempre ves la tuya. |

Respuesta: paginador estándar de Laravel (`data`, `current_page`, `total`, `links`, etc.), con cada elemento igual al detalle de la sección 6, `attempts` incluido.

```
GET /api/notificaciones?estado=fallido&desde=2026-08-01&per_page=50
```

## 8. Errores

| Código | Cuándo | Ejemplo |
|---|---|---|
| `401` | Header ausente, clave inexistente, o fuera de su ventana de vigencia. | `{ "error": "No autorizado." }` |
| `422` | Falta un campo obligatorio o no cumple el formato (ver sección 4). | `{ "message": "The telefono field is required when canal is whatsapp.", "errors": { "telefono": ["The telefono field is required when canal is whatsapp."] } }` |
| `429` | Superaste el límite de tasa. | `{ "message": "Too Many Attempts." }` |
| `404` | (Solo en `/notificaciones/{id}`) el `id` no existe o no es de tu sistema. | — |

> Los mensajes de validación (`422`) vienen en inglés — es el idioma por defecto del framework, no algo que dependa del canal o del sistema emisor.

## 9. Referencia rápida

```
POST /api/notificar
  Headers:  X-Chasqui-Key: <clave>
  Body:     mensaje*, nivel* (error|success|info), canal (slack|telegram|whatsapp|mailrelay)
            + whatsapp:   telefono* (solo dígitos), template, parametros (objeto, junto con template)
            + mailrelay:  destinatarios* (array de emails), asunto* (máx 50 chars)
  Devuelve: 202 { status: "Encolado", id }
  * = obligatorio para ese canal

GET /api/notificaciones/{id}
  Devuelve: detalle de la petición + attempts por canal
  404 si no es tuya o no existe

GET /api/notificaciones?canal=&estado=&desde=&hasta=&per_page=
  Devuelve: listado paginado, acotado a tu sistema
```
