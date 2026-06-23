# Modificaciones de RBAC — para evaluar replicar en Modux core

> **Contexto.** Al implementar el RBAC por recurso del módulo IVA (proteger las rutas con
> `PermissionMiddleware`), aparecieron **dos limitaciones del framework** que hubo que
> resolver para que el control de permisos funcionara de verdad. Este proyecto se armó por
> *clone* de `cynchro/modux`, así que estos cambios viven sólo acá. Son genéricos (no tienen
> nada de IVA) y **convendría portarlos al core de Modux**: cualquier app que use
> `PermissionMiddleware` los necesita.
>
> Estado: implementado y verificado en este repo (510 tests verdes, PHPStan 6, PHPCS limpio).

---

## Resumen

| # | Cambio | Archivo | ¿Va al core? |
|---|--------|---------|--------------|
| 1 | El super-permiso `Acceso Total` habilita cualquier permiso | `app/Support/Auth/PermissionChecker.php` | **Sí** |
| 2 | El JWT incluye el claim `rol` | `app/Support/JWTConfig.php` | **Sí** |
| 3 | `login`/`refresh` pasan el rol al generar el token | `app/Modules/Auth/Services/AuthService.php` | **Sí** |
| 4 | Tests: el `FeatureTestCase` siembra rol admin + `Acceso Total`; el token de test lleva rol | `tests/Feature/FeatureTestCase.php` | Sí (patrón de test) |
| — | Rutas IVA protegidas + taxonomía `iva.*` + `PermisosIvaSeeder` | módulo IVA | **No** (específico de la app) |

---

## Cambio 1 — `Acceso Total` debe habilitar cualquier permiso

### Qué hacía antes
`PermissionChecker::level($rolId, $key)` resolvía el nivel (0/1/2) buscando **exactamente** esa
`key` en los permisos del rol (y sus ancestros). El super-permiso `Roles::SUPER_PERMISSION`
(`'Acceso Total'`) **no** otorgaba nivel sobre otras keys: sólo `AdminMiddleware` lo
contemplaba (para las rutas `/admin`). `PermissionMiddleware` no.

### Por qué era un problema
Si protegés una ruta con `PermissionMiddleware::class . ':ventas'`, el **admin** (rol con
`Acceso Total`) recibía **403**, porque no tenía la key `ventas` asignada explícitamente.
Es decir: aplicar permisos finos rompía el acceso del administrador, salvo que se le
asignara, una por una, **todas** las keys de la app. Inviable de mantener.

### Para qué el cambio
Que `Acceso Total` se comporte como lo que su nombre promete: **acceso total**. Un rol que lo
tiene (propio o heredado por jerarquía) obtiene `LEVEL_WRITE` sobre cualquier key, sin
asignaciones explícitas. Los roles acotados siguen necesitando sus permisos puntuales.

### Cómo se implementó
`level()` ahora resuelve en **una sola query** el nivel de la key pedida **o** el del
super-permiso (si lo tiene, gana con nivel escritura):

```sql
-- antes:  SELECT MAX(rp.estado) ... WHERE p.`key` = ?
-- ahora:
SELECT MAX(CASE WHEN p.`key` = :superCase THEN 2 ELSE rp.estado END) AS estado
FROM ancestors a
JOIN roles_permisos rp ON rp.rol = a.id
JOIN permisos p ON rp.permiso = p.id
WHERE p.`key` IN (:key, :super)
```

- Si el rol (o un ancestro) tiene `Acceso Total` → el `CASE` da `2` → `MAX = 2` → escritura.
- Si no lo tiene → la `key` ajena no entra al `IN`, el `MAX` queda igual que antes → **sin
  cambio de comportamiento para roles normales**.

> El `2` se interpola desde `self::LEVEL_WRITE` (constante de la propia clase, no input). El
> super-permiso se pasa como dos named params (`:super`, `:superCase`) para no depender de
> reutilización de placeholders entre drivers PDO.

### Riesgos / compatibilidad
- **Bajo.** Para roles sin `Acceso Total` el resultado es idéntico (la key extra del `IN` no
  matchea). Verificado: `PermissionCheckerTest`, `PermissionMiddlewareTest`,
  `RoleHierarchyTest`, `AdminMiddlewareTest` siguen verdes sin tocarlos.
- Mantiene la herencia por `roles.parent_id` (el CTE recursivo no cambió).

---

## Cambio 2 — El JWT debe transportar el `rol`

### Qué hacía antes
`JWTConfig::generateToken($userId, $tenantId)` armaba el payload con `iss/iat/exp/sub` y, si
había, `tenant_id`. **No incluía el rol.** El `JwtGuard` ya intentaba leerlo
(`rol => $payload['rol'] ?? null`) y `AuthMiddleware` expone los claims como "user array"
(`$request->setUser($principal->claims)`), que es de donde `PermissionMiddleware` saca
`$user['rol']`.

### Por qué era un problema
Como el token **nunca** llevaba `rol`, `$user['rol']` era siempre `null` → el middleware
usaba `rolId = 0` → `PermissionChecker::level(0, …)` no encuentra rol → **403 para todos**.
Era un **gap latente**: el mecanismo de permisos estaba completo, pero el dato del rol no
llegaba al punto de control. No se había notado porque **ninguna ruta usaba
`PermissionMiddleware`** todavía.

### Para qué el cambio
Que el rol viaje en el token, así `PermissionMiddleware` puede autorizar sin recargar el
usuario desde la base en cada request.

### Cómo se implementó
Parámetro opcional `?int $rol` en `generateToken`, que se agrega al payload si viene:

```php
public static function generateToken(int|string $userId, ?string $tenantId = null, ?int $rol = null): string
{
    $payload = ['iss' => ..., 'iat' => ..., 'exp' => ..., 'sub' => $userId];
    if ($tenantId !== null) { $payload['tenant_id'] = $tenantId; }
    if ($rol !== null)      { $payload['rol'] = $rol; }   // ← nuevo
    return JWT::encode($payload, self::secretKey(), self::algorithm());
}
```

### Riesgos / compatibilidad
- **Bajo.** El parámetro es opcional con default `null`: las llamadas viejas siguen
  compilando y produciendo el mismo token (sin `rol`). Sólo agrega un claim cuando se pasa.
- Tokens ya emitidos (sin `rol`) siguen siendo válidos; simplemente no pasarán
  `PermissionMiddleware` hasta renovarse (re-login/refresh). En despliegue, considerar que
  los usuarios deban re-loguearse para obtener un token con rol.

---

## Cambio 3 — `login` y `refresh` pasan el rol

`AuthService::login()` y `refreshTokens()` ahora pasan el rol del usuario a `generateToken`:

```php
$accessToken = JWTConfig::generateToken(
    $userId,
    $user['tenant_id'] ?? null,
    isset($user['rol']) ? (int) $user['rol'] : null,
);
```

El `SELECT` de usuario ya traía `rol`, así que no hubo cambio de repositorio. Sin esto, el
Cambio 2 no tendría efecto en el flujo real (el token se emite en login/refresh).

---

## Cambio 4 — Soporte en tests (patrón, no core estricto)

Para que las rutas protegidas se puedan testear:

- `FeatureTestCase::seedAuthBase()` siembra, dentro de la transacción de cada test, el rol
  `1` (`Administrador`) con el permiso `Acceso Total` — refleja lo que `RolesUsersSeeder`
  hace en producción. Así `actingAsUser()` (rol 1 por defecto) tiene acceso total.
- `actingAsUser()` genera el token con el rol del usuario (`generateToken($id, $tenant, $rol)`),
  para que `PermissionMiddleware` reciba el rol también en tests.

Si Modux trae su propio `FeatureTestCase` / helper de auth en el core, conviene portar el
mismo patrón ahí.

---

## Lo específico de esta app (NO va al core)

- **Rutas del módulo IVA** protegidas con `PermissionMiddleware` (`app/Modules/Iva/routes.php`).
- **Taxonomía `iva.*`**: `iva.clientes`, `iva.proveedores`, `iva.ventas`, `iva.compras`,
  `iva.libro`, `iva.padron`, `iva.facturacion`, `iva.puntos-venta`. Convención: GET = lectura,
  POST/PUT/DELETE = escritura (`:write`).
- **`seeders/PermisosIvaSeeder.php`**: registra esas keys para asignarlas a roles acotados.
- **`tests/Feature/IvaRbacTest.php`**: sin permiso → 403; lectura no habilita escritura.

---

## Checklist para replicar en Modux core

1. [ ] Portar el `CASE` del super-permiso a `PermissionChecker::level()` (Cambio 1).
2. [ ] Agregar el parámetro `?int $rol` a `JWTConfig::generateToken()` (Cambio 2).
3. [ ] Hacer que `AuthService::login()`/`refreshTokens()` pasen el rol (Cambio 3).
4. [ ] Ajustar el `FeatureTestCase`/helpers de test del core para sembrar admin + emitir token
       con rol (Cambio 4).
5. [ ] Correr la suite de auth del core: `PermissionCheckerTest`, `PermissionMiddlewareTest`,
       `RoleHierarchyTest`, `AdminMiddlewareTest` deberían pasar sin modificarse.
6. [ ] (Opcional) Documentar en `docs/auth-and-tenancy.md` del core que `Acceso Total`
       bypassa `PermissionMiddleware` y que el token transporta `rol`.

## Decisión de diseño abierta (por si el core prefiere otra cosa)
Se eligió que `Acceso Total` otorgue **escritura** sobre todo. Alternativa posible: un nivel
"super" aparte, o que el bypass sólo aplique a `allows()` y no a `level()` (para no "mentir"
el nivel real). Acá se hizo en `level()` porque es la única fuente de verdad y así todos los
consumidores (middleware, `allows`, y cualquier `level()` directo) quedan consistentes.
