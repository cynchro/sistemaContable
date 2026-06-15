# Módulos opcionales (IA, Billing)


## Optional: AI module (LLM + RAG)

Modux ships **without** any AI by default. AI is an opt-in add-on built on the standalone
[`cynchro/modux-ia`](https://packagist.org/packages/cynchro/modux-ia) SDK (LLM + RAG, no Python/Node).

```bash
composer require cynchro/modux-ia
```

Then add an `app/Modules/IA/` module (auto-discovered like any other) that wires the SDK
(`PhpAI\Bootstrap`, `PhpAI\DriverFactory`, `PhpAI\RAG\RAGEngine`) into controllers and exposes the
endpoints you need, e.g. `POST /ia/chat`, `/ia/ask`, `/ia/ingest`, `/ia/retrieve`.

Configure the driver via environment variables:

| Variable | Description |
|---|---|
| `AI_DRIVER` | `local` / `cloud` / `cluster` |
| `AI_CLOUD_PROVIDER` | e.g. `groq`, `openai` |
| `AI_CLOUD_API_KEY` | Provider API key |
| `AI_CLOUD_MODEL` | Chat/completion model |
| `AI_CLOUD_EMBEDDING_MODEL` | Embedding model (RAG) |
| `RAG_SQLITE_PATH` | Vector store path, e.g. `storage/ai/vectors.db` |

If you don't need AI, skip this section entirely — nothing in the core depends on it.

## Optional: Billing module

Modux ships **without** billing by default. It's an opt-in add-on: install the SDK + a
gateway adapter, and the bundled `app/Modules/Billing` activates itself (guarded by
`class_exists`, so the core stays clean when billing isn't installed).

```bash
composer require cynchro/modux-billing cynchro/modux-billing-stripe
# or, for Argentina:  cynchro/modux-billing-mp
```

The module exposes:

```
POST /billing/checkout              { "plan": "pro" }   → gateway checkout URL (auth + tenant)
POST /billing/webhook/{gateway}     (public, verified by signature)
```

The webhook is verified with the adapter's signature scheme, normalized, and applied via
`BillingManager::handleEvent()`, which writes the tenant's entitlements (`source =
billing:<gateway>`) — picked up by the **Entitlements** gating above. Configure gateways in
`config/billing.php` (env: `BILLING_GATEWAY`, `STRIPE_*`, `MP_*`).

Define your plans + their entitlements with the idempotent seeder (edit the `$plans` array):

```bash
php seeders/BillingPlansSeeder.php   # creates plans/plan_entitlements; re-running upserts
```

> Architecture: the base only **reads** `tenant_entitlements`; billing **writes** it — so
> product modules never depend on billing. See
> `docs/adr/0001-saas-identity-entitlements-billing.md`.

---

