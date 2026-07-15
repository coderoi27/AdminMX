# Reconciliación Productiva de Claim

## Causa Raíz Ajustada

El schema productivo de Claim quedó aplicado total o parcialmente, pero Doctrine Migrations conserva migraciones históricas como pendientes. Por eso `doctrine:migrations:migrate` intenta correr primero migraciones antiguas no idempotentes y falla antes de llegar a la reconciliación posterior:

- `Version20260618001945` falla si `claim_uuid` ya existe.
- `Version20260620000000` falla si `submission_mode` ya existe.
- `Version20260626000000` no puede iniciar el bootstrap si las históricas pendientes fallan antes.

La solución operativa ya no depende del runner normal de migraciones para arrancar. Se agrega `app:claim:reconcile-schema`, un comando controlado que inspecciona, aplica solo DDL faltante, verifica contratos y registra metadata de migraciones únicamente cuando la estructura correspondiente existe.

## Canonicalización De Metadata

Doctrine Migrations espera guardar versiones con el FQCN canónico:

```text
DoctrineMigrations\Version20260618001945
```

Una implementación anterior armaba el `INSERT` de metadata como SQL literal. En MariaDB/MySQL el backslash dentro de strings puede tratarse como escape, por lo que se persistieron filas legacy sin `\`:

```text
DoctrineMigrationsVersion20260618001945
```

El reconciliador actual usa parámetros DBAL para insertar metadata. Nunca inserta formatos legacy como:

- `DoctrineMigrationsVersionYYYYMMDDHHMMSS`
- `VersionYYYYMMDDHHMMSS`
- `YYYYMMDDHHMMSS`

El diagnóstico separa:

- `claim_migrations_canonical`
- `claim_migrations_legacy`
- `metadata_consistent`

`ready=true` solo es válido cuando el schema está completo, metadata storage está correcto, todas las versiones Claim canónicas existen y no queda ninguna fila legacy Claim.

## Reparación De Filas Legacy

Para cada versión Claim administrada:

1. Si solo existe legacy y el contrato estructural está completo, inserta la versión canónica y después elimina la legacy.
2. Si existen canónica y legacy, conserva canónica y elimina legacy.
3. Si el contrato está incompleto, no registra canónica y no elimina legacy automáticamente.
4. Si schema y metadata ya son canónicos, devuelve `changed=false`, `plan=[]`, `executed=[]`.

El comando no modifica versiones ajenas al conjunto Claim administrado.

## Estrategia Final De Bootstrap

1. `app:claim:diagnose-schema` sigue siendo read-only.
2. `app:claim:reconcile-schema --dry-run` genera el plan sin modificar BD.
3. `app:claim:reconcile-schema --confirm` aplica solo cambios aditivos y no destructivos.
4. El comando registra versiones en `doctrine_migration_versions` solo después de verificar el contrato estructural completo.

No se editan migraciones históricas, no se borra información y no se hace `schema:update --force`.

## Metadata Storage

La tabla esperada es:

```text
doctrine_migration_versions
```

Definición mínima esperada por Doctrine Migrations:

- `version VARCHAR(191) NOT NULL`
- `executed_at DATETIME DEFAULT NULL`
- `execution_time INT DEFAULT NULL`
- primary key sobre `version`

Si la tabla existe con definición incompatible, el reconciliador corrige solo su estructura técnica y conserva filas existentes. Si hay versiones duplicadas, aborta con `migration_metadata_duplicates_detected`.

## Contratos Por Migración

`DoctrineMigrations\Version20260618001945`

- Columnas incrementales de `location_claim_requests`: `claim_uuid`, datos del reclamante, propuesta de datos, coordenadas confirmadas, verificación de email, legal, resume token, timestamps operativos y expiración/cancelación.
- Índice único sobre `location_claim_requests.claim_uuid`.
- Tabla `location_claim_evidences` con columnas, índice `claim_id` y FK a `location_claim_requests`.
- Tabla `location_claim_otps` con columnas, índice `claim_id` y FK a `location_claim_requests`.

`DoctrineMigrations\Version20260618082000`

- Tabla `location_claim_access_sessions`.
- Índice único sobre `token_hash`.
- Índices operativos sobre `claim_id`, `expires_at`, `revoked_at` y `created_from_otp_id`.
- FK `claim_id -> location_claim_requests.id`.
- FK `created_from_otp_id -> location_claim_otps.id`.

`DoctrineMigrations\Version20260620000000`

- `claimant_name` nullable.
- `email` nullable.
- `submission_mode` presente.

`DoctrineMigrations\Version20260626000000`

- Se conserva como respaldo idempotente para instalaciones donde Doctrine sí pueda correr el historial.
- En producción parcialmente aplicada, el bootstrap primario es `app:claim:reconcile-schema`.
- Tras reconciliar correctamente, el comando también registra esta versión para evitar doble DDL.

## Duplicados De Claim UUID

Antes de crear el índice único sobre `claim_uuid`, el reconciliador busca duplicados no nulos. MariaDB permite múltiples `NULL`, por lo que esos no bloquean.

Si detecta duplicados no nulos:

- aborta sin modificar datos;
- devuelve `claim_uuid_duplicates_detected`;
- enmascara UUIDs en el reporte;
- exige resolver el dato manualmente antes de continuar.

## Seguridad De Progress

`PATCH /api/v1/location-claims/{claimUuid}/progress` ya no devuelve eco del payload, correo ni teléfono en éxito. La respuesta queda limitada a:

```json
{
  "data": {
    "status": "pending_email_verification",
    "last_completed_step": "claimant"
  }
}
```

En errores inesperados devuelve `error_code`, `request_id` y mensaje estable, sin exponer excepción, token, OTP, correo, teléfono, dirección ni body.

## Plataforma DB

El diagnóstico productivo reportó:

- `server_version: 11.8.6-MariaDB-log`
- `platform: Doctrine\DBAL\Platforms\MySQLPlatform`

Con DBAL 4.4.x es esperable que MariaDB use una plataforma MySQL genérica cuando el driver resuelve compatibilidad por familia. Lo importante es no declarar una segunda fuente contradictoria. `DATABASE_URL` debe ser la única fuente de verdad de `serverVersion`; no se debe agregar `doctrine.dbal.server_version` en paralelo.

## Procedimiento Productivo Final

Ejecutar desde `Sistemas/Admin-MiMonchisMX`:

```bash
php bin/console app:claim:diagnose-schema --json
php bin/console app:claim:reconcile-schema --dry-run --json
php bin/console app:claim:reconcile-schema --confirm --json
php bin/console app:claim:diagnose-schema --json
```

Si el segundo comando reporta `claim_uuid_duplicates_detected`, detenerse. No ejecutar `--confirm` hasta resolver duplicados.

## Riesgos

- Si existen tablas Claim creadas manualmente con estructura incompatible no cubierta por las migraciones históricas, el reconciliador abortará o dejará diferencias para revisión.
- El comando no borra ni corrige datos duplicados; esa decisión requiere intervención humana.
- `Version20260626000000` queda como respaldo, pero en producción parcialmente aplicada no debe ser el primer mecanismo de reparación.
