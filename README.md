# 37agents

**37agents** (formerly WPAgent / WPAgents) is a WordPress plugin for creating and deploying multi-purpose AI agents with local RAG knowledge, Auto-RAG indexing of WordPress content, user memory, progressive SSE streaming, Gutenberg integration, LGPD/GDPR privacy tools, and multi-provider support.

## Highlights

- **Multi-Provider AI & Coding Plan Subscriptions:**
  - **WordPress AI / Connectors:** Native integration with the official WordPress AI Client (`wp_ai_client_prompt`).
  - **Subscription & OpenAI-Compatible AI Plans:** Built-in presets and encrypted credential storage for **Xiaomi MiMo Token Plan** (`tp-...` / `ttp-...` & `sk-...`), **Alibaba Cloud Qwen / Bailian Coding Plan**, **Zhipu AI GLM Coding Plan / Z.ai**, **Moonshot Kimi Code**, **MiniMax M Plan**, **Volcengine (ByteDance) Ark**, **DeepSeek**, **SiliconFlow**, and custom OpenCode / OpenAI endpoints (with global settings and per-agent overrides).
  - **OpenRouter:** Direct or fallback access to hundreds of models plus optional semantic embeddings.
- **Gutenberg Block & Shortcodes:** Visual `37agents/agent-widget` block (`layout` and `theme` inspector controls), `[37agents_chat agent="slug"]`, and full backward compatibility with `[wpagent_chat]`.
- **Three Frontend Layouts:**
  - **Chat:** Streaming Server-Sent Events (`/wpagent/v1/chat/stream` with JSON fallback), Markdown rendering, light/dark mode, conversation sidebar, optional project tabs, and guest document uploads (`.txt`, `.md`, `.csv`, `.json`, `.pdf`, `.docx`).
  - **STEPS:** Guided step-by-step diagnostic questionnaire with saved progress and AI-generated final reports.
  - **EAD (LMS):** Course module/lesson navigation, AI syllabus generator, completion tracking, and integrated per-lesson AI tutor.
- **1-Click Starter Templates:** Pre-configured presets for Atendimento & FAQ, Diagnóstico / Orçamento, Mini-Curso Interativo EAD, and Copiloto Editorial Admin.
- **Document RAG & Auto-RAG:** Index uploaded files (TXT, Markdown, CSV, JSON, HTML, DOCX, best-effort PDF) and automatically index published `post`, `page`, WooCommerce `product`, and public custom post types on `save_post` or manual sync.
- **Lead Capture & Webhooks:** Optional guest lead capture bar (`POST /wpagent/v1/leads`) and SSRF-safe non-blocking outbound webhooks (`lead_captured`, `conversation_started`, `steps_report_generated`).
- **Privacy & LGPD/GDPR Tools:** Registers WordPress Personal Data Exporters, Personal Data Eraser, Privacy Policy Guide suggestions (`WPAgent_Privacy`), and an optional per-agent privacy consent notice bar.
- **Editorial & Admin Copilots:** Comment moderation & reply suggestions, bulk post summarization, periodic scheduled content drafts, conversation summaries, WordPress Abilities API confirmation workflows, token usage limits, and multi-language support (`pt_BR`, `es_ES`, `en_US`).

## Installation

1. Download or build a release zip (`dist/37agents-0.8.0.zip` or `37agents-0.8.0.zip`).
2. Upload the `wpagent` folder to `/wp-content/plugins/` (the internal directory and `wpagent` text domain remain unchanged for seamless upgrades from earlier releases).
3. Activate **37agents** in the WordPress admin.
4. Configure an AI provider under **37agents > Configurações**:
   - **WordPress AI / Connectors:** Install the official WordPress AI plugin and configure a connector under `Settings > Connectors`.
   - **Planos de IA & APIs Compatíveis:** Select a preset (Xiaomi MiMo Token Plan, Qwen Coding Plan, GLM Coding Plan, Kimi Code, MiniMax M Plan, DeepSeek, etc.) or enter a custom OpenAI-compatible Base URL and API key.
   - **OpenRouter:** Enter an OpenRouter API key for direct use or fallback.
5. Create or edit an agent under **37agents > Agentes** (or use a 1-click Starter Template).
6. Insert the Gutenberg block **37agents/agent-widget** or embed a shortcode:

```text
[37agents_chat agent="default"]
```

Or customize layout and theme (the legacy `[wpagent_chat]` shortcode remains fully supported):

```text
[37agents_chat agent="educadoria" layout="steps" theme="dark" title="Diagnóstico"]
```

## Local Development

This workspace is prepared for WordPress Playground.

```bash
npm install
npm run wp:start
```

The Playground blueprint installs and activates:

- WordPress AI
- AI Provider for OpenRouter
- **37agents** mounted from `./wpagent`

Admin login in the local Playground blueprint:

- Username: `admin`
- Password: `password`

## Scripts

```bash
npm run lint:php
npm run build:zip
```

`npm run lint:php` runs lightweight plugin sanity checks.

`npm run build:zip` creates `dist/37agents-0.8.0.zip` and stages `readme.txt` and `LICENSE` inside the packaged `wpagent` folder.

## Architecture

### PHP Backend (`wpagent/includes/`)

- `wpagent/wpagent.php`: Plugin bootstrap (`37agents`, v0.8.0).
- `wpagent/includes/class-wpagent-plugin.php`: Wires services, blocks, privacy hooks, and REST routes.
- `wpagent/includes/class-wpagent-settings.php`: Global settings, encrypted secrets, and Subscription / OpenAI-Compatible AI presets.
- `wpagent/includes/class-wpagent-agents.php`: Multi-agent CPT, tabbed metabox, Starter Templates, Auto-RAG sync, and per-agent provider/privacy/webhook configuration.
- `wpagent/includes/class-wpagent-ai-client.php`: Unified AI client for WordPress AI, Compatible Coding/Token Plans, and OpenRouter.
- `wpagent/includes/class-wpagent-document-indexer.php`: Document text extraction, chunking, and `index_wp_post()` / `sync_agent_wp_posts()` for Auto-RAG.
- `wpagent/includes/class-wpagent-embeddings.php`: Semantic chunk embeddings and similarity search.
- `wpagent/includes/class-wpagent-prompt-builder.php`: Combines system prompt, indexed knowledge, memory, and recent interactions.
- `wpagent/includes/class-wpagent-repository.php`: Persistence layer for conversations, interactions, memories, sources, chunks, embeddings, progress, and email events.
- `wpagent/includes/class-wpagent-rest-controller.php`: REST API routes including `chat`, SSE `chat/stream`, `leads`, `steps`, `ead`, and SSRF-safe webhook dispatch.
- `wpagent/includes/class-wpagent-shortcode.php`: Front-end rendering for Chat, STEPS, EAD, consent notices, and lead capture bars (`[37agents_chat]` & `[wpagent_chat]`).
- `wpagent/includes/class-wpagent-blocks.php`: Gutenberg block `37agents/agent-widget` registration.
- `wpagent/includes/class-wpagent-privacy.php`: WordPress Personal Data Exporters, Personal Data Eraser, and Privacy Policy Guide suggestions.
- `wpagent/includes/class-wpagent-admin-abilities.php`, `class-wpagent-admin-ui.php`, `class-wpagent-dashboard.php`, `class-wpagent-i18n.php`, `class-wpagent-comment-moderation.php`, `class-wpagent-comment-suggest.php`, `class-wpagent-bulk-summary.php`, `class-wpagent-content-provenance.php`, `class-wpagent-conversation-summaries.php`, `class-wpagent-periodic-tasks.php`, `class-wpagent-email-actions.php`, `class-wpagent-email-schedules.php`, `class-wpagent-reports.php`, `class-wpagent-activator.php`, `class-wpagent-wordpress-ai-integration.php`.

### Frontend & Admin Assets (`wpagent/assets/js/`)

- `admin.js`: Agent tabs, Starter Templates, preset syncing, and moderation AJAX controls.
- `block-editor.js`: Gutenberg editor UI and live theme/layout preview for `37agents/agent-widget`.
- `chat.js`: Chat client, SSE progressive streaming with JSON fallback, guest lead capture, and file uploads.
- `steps.js`: Guided diagnostic questionnaire workflow, lead capture, and printable final reports.
- `ead.js`: Interactive LMS course player and per-lesson AI tutor.
- `comment-suggest.js`: Admin comment reply suggestion integration.

## Security and Privacy

37agents follows the WordPress plugin security model:

- Agent configuration is restricted to administrators (`manage_options`), and admin CSS/JS assets are scoped to plugin screens with inline scripts extracted into `assets/js/admin.js`.
- OpenRouter and Compatible Plan API keys are encrypted at rest (`AES-256-CBC`) with optional `wp-config.php` constants (`WPAGENT_OPENROUTER_API_KEY`, `WPAGENT_COMPATIBLE_API_KEY`).
- Compatible API requests and outbound webhooks validate URLs (`wp_http_validate_url`) and use `wp_safe_remote_post`.
- Front-end Markdown rendering sanitizes URLs and HTML, and public uploads validate file types cleanly (`.txt`, `.md`, `.csv`, `.json`, `.pdf`, `.docx`).
- Administrative actions and email actions require explicit human confirmation before execution.
- WordPress native Privacy Tools (`Export Personal Data`, `Erase Personal Data`, and `Privacy Policy Guide`) are built in via `WPAgent_Privacy`.
- Full cleanup on uninstall is handled by `wpagent/uninstall.php`.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
