# Módulos opcionales (IA, Billing)


## Opcional: módulo de IA (LLM + RAG)

Modux viene **sin** IA por defecto. La IA es un add-on opt-in construido sobre el SDK
independiente [`cynchro/modux-ia`](https://packagist.org/packages/cynchro/modux-ia) (LLM + RAG, sin Python/Node).

```bash
composer require cynchro/modux-ia
```

Después agregás un módulo `app/Modules/IA/` (autodescubierto como cualquier otro) que cablea el SDK
(`PhpAI\Bootstrap`, `PhpAI\DriverFactory`, `PhpAI\RAG\RAGEngine`) en los controllers y expone los
endpoints que necesites, p. ej. `POST /ia/chat`, `/ia/ask`, `/ia/ingest`, `/ia/retrieve`.

Configurá el driver con variables de entorno:

| Variable | Descripción |
|---|---|
| `AI_DRIVER` | `local` / `cloud` / `cluster` |
| `AI_CLOUD_PROVIDER` | p. ej. `groq`, `openai` |
| `AI_CLOUD_API_KEY` | API key del proveedor |
| `AI_CLOUD_MODEL` | Modelo de chat/completion |
| `AI_CLOUD_EMBEDDING_MODEL` | Modelo de embeddings (RAG) |
| `RAG_SQLITE_PATH` | Ruta del vector store, p. ej. `storage/ai/vectors.db` |

Si no necesitás IA, salteá esta sección por completo — nada del núcleo depende de ella.

## Opcional: módulo de Billing

Modux viene **sin** billing por defecto. Es un add-on opt-in: instalás el SDK + un adaptador
de pasarela, y el módulo incluido `app/Modules/Billing` se activa solo (protegido por
`class_exists`, así el núcleo queda limpio cuando billing no está instalado).

```bash
composer require cynchro/modux-billing cynchro/modux-billing-stripe
# o, para Argentina:  cynchro/modux-billing-mp
```

El módulo expone:

```
POST /billing/checkout              { "plan": "pro" }   → URL de checkout de la pasarela (auth + tenant)
POST /billing/webhook/{gateway}     (público, verificado por firma)
```

El webhook se verifica con el esquema de firma del adaptador, se normaliza y se aplica vía
`BillingManager::handleEvent()`, que escribe los entitlements del tenant (`source =
billing:<gateway>`) — que toma el gating de **Entitlements** de más arriba. Configurá las pasarelas en
`config/billing.php` (env: `BILLING_GATEWAY`, `STRIPE_*`, `MP_*`).

Definí tus planes + sus entitlements con el seeder idempotente (editá el array `$plans`):

```bash
php seeders/BillingPlansSeeder.php   # crea plans/plan_entitlements; al re-correr hace upsert
```

> Arquitectura: el base solo **lee** `tenant_entitlements`; billing lo **escribe** — así los
> módulos de producto nunca dependen de billing. Ver
> `docs/adr/0001-saas-identity-entitlements-billing.md`.

---
