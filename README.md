# WordWisdom

A Laravel + Vue app for generating concise, profound insights about a word and topic, powered by a small Python AI service. It adds learning aids like Named Entity Recognition (NER), summarization, language detection/translation, and keyword extraction to make cross-cultural, interactive exploration easy and educational.

## Why this stack
- Laravel 11 + Inertia + Vue 3 + Tailwind: modern, full‑stack DX with server-driven routing and rich reactive UI without duplicating routes/API schemas.
- Python FastAPI sidecar: clean separation for NLP/ML features using battle‑tested Python libraries (spaCy, transformers, langdetect, KeyBERT). Keeps the PHP app lean and fast.
- Service-first architecture: PHP Controllers stay slim; logic lives in Services. Form Requests handle validation. Laravel 11 conventions for minimal boilerplate.

## Core features
- Insight generation
  - Generate 1–2 sentence insights given a `word`, `topic` (or pasted article), and optional `tone`.
- NER highlighting (optional)
  - Extract entities from the generated insight and highlight them in the UI.
- Summarization (optional)
  - If user pastes an article, produce a concise 1–2 sentence summary to aid quick sharing/understanding.
- Language detection & translation (optional)
  - Detects input language and translates the generated insight to a selected target language for cross‑cultural sharing.
- Keyword extraction & “Wisdom Chain” (optional)
  - Extracts key phrases; suggests related curated philosophical words to continue exploration.
- Image export
  - Generate a shareable image version of the insight (via existing `ImageController`).
- Save to profile
  - Authenticated users can save generated insights and view them later.
- User toggles
  - Checkboxes to enable/disable NER, Summarization, Keywords, Translation per‑generation.

## What’s used under the hood (and why)
- PHP/Laravel side
  - `app/Services/AIInsightService.php`: Thin HTTP client to the Python AI service; contains fallbacks for offline use.
  - `app/Http/Requests/GenerateInsightRequest.php`: Validates inputs and feature toggles.
  - `app/Http/Controllers/InsightController.php`: Slim controller orchestrating generation + optional NLP features.
  - Inertia + Vue 3 (`resources/js/pages/Insights/Generator.vue`): delightful UX, reactive toggles, entity highlighting, and exploration.
- Python side (`ai_server`)
  - FastAPI: small, fast web layer for ML endpoints.
  - spaCy: NER with graceful regex fallback if no model is installed.
  - transformers (Hugging Face): summarization and translation pipelines with safe fallbacks.
  - langdetect: fast language identification.
  - KeyBERT + sentence-transformers: keyword extraction, with fallback to simple frequency scoring.

## Project layout (high‑level)
- `app/Services/AIInsightService.php` — calls the Python AI server (`/generate`, `/ner`, `/summarize`, `/detect`, `/translate`, `/keywords`)
- `app/Http/Controllers/InsightController.php` — orchestrates generation, optional NER/summary/translation/keywords
- `app/Http/Requests/GenerateInsightRequest.php` — validates inputs & feature toggles
- `resources/js/pages/Insights/Generator.vue` — main UI page, toggles + highlighting + summary + translation + wisdom chain
- `ai_server/main.py` — FastAPI server exposing ML endpoints

## Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- Python 3.10+
- SQLite (default) or another DB supported by Laravel

## Local setup
1) Clone and install PHP deps
```bash
composer install
cp .env.example .env
php artisan key:generate
```

2) Configure database (defaults to `database/database.sqlite`)
```bash
php artisan migrate
```

3) Frontend assets
```bash
npm install
npm run dev
```

4) Python AI server
```bash
cd ai_server
python -m venv .venv
# Windows PowerShell: .venv\Scripts\Activate.ps1
# macOS/Linux: source .venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt
uvicorn ai_server.main:app --reload --port 8001
```

5) Point Laravel to the AI server
- In your Laravel `.env`, set:
```
AI_SERVER_URL=http://localhost:8001
```

6) (Optional) Install spaCy English model for better NER
```bash
python -m spacy download en_core_web_sm
```

## Running the app
- Laravel Herd or `php artisan serve` (defaults to `http://127.0.0.1:8000`).
- Vite dev server via `npm run dev`.
- Python AI server via `uvicorn` (step 4).

Then visit `http://wordwisdom.test` (Herd) or your `php artisan serve` URL.

## Using the generator
- Fill `Word` and `Topic` or paste an `Article`.
- Choose optional `Tone` and `Target language`.
- Use toggles to enable/disable: NER, Summarize, Extract Keywords, Translate to target.
- Click “Generate Insight”.
- View:
  - Highlighted entities
  - Summary (if article provided / toggle enabled)
  - Detected language + translation
  - Keywords and the Wisdom Chain (click to explore related curated words)
  - Share, copy, download image, or save to profile

## API endpoints (Python AI service)
- `POST /generate` → `{ content }`
- `POST /ner` → `{ entities: [{ text, label, start, end }] }`
- `POST /summarize` → `{ summary }`
- `POST /detect` → `{ lang, confidence }`
- `POST /translate` → `{ translated }`
- `POST /keywords` → `{ keywords: [{ phrase, score }] }`

## Configuration
- `.env`
  - `AI_SERVER_URL` — base URL of the FastAPI server
  - `OPENAI_*` — optional (used if Python server is not set and you wish to call OpenAI for generation)
- Frontend
  - `resources/js/pages/Insights/Generator.vue` controls toggles and rendering.

## Queues / async (optional roadmap)
- The UI includes an “Process asynchronously” toggle. To complete async processing:
  - Create a `GenerateInsightJob` that calls `AIInsightService` for generation + optional features.
  - Store progress/result in cache (e.g., `Cache::put("insight:{id}", ...)`).
  - Add API endpoints to enqueue work and poll by id.
  - Switch the Vue form to enqueue when `async=true` and poll until done.

## Evaluation metrics (for educational context)
While the app runs models in inference mode, you can document or run basic offline evaluations:

- NER (ConLL‑2003)
  - Metric: F1‑score (entity‑level). Tools: `seqeval` or spaCy’s built‑in `spacy evaluate`.
  - Example (if you train/evaluate a spaCy model):
```bash
python -m spacy evaluate /path/to/model en_conll03.md  # example, adjust dataset path
```

- Summarization (CNN/DailyMail)
  - Metric: ROUGE‑1/ROUGE‑2/ROUGE‑L. Tools: `rouge-score` or `evaluate` (Hugging Face).
  - Example (pseudo‑script):
```python
from datasets import load_dataset
from evaluate import load
from transformers import pipeline

rouge = load('rouge')
ds = load_dataset('cnn_dailymail', '3.0.0', split='validation[:100]')
summarizer = pipeline('summarization', model='facebook/bart-large-cnn')

preds, refs = [], []
for row in ds:
    out = summarizer(row['article'], max_length=128, min_length=32, do_sample=False)[0]['summary_text']
    preds.append(out)
    refs.append(row['highlights'])
print(rouge.compute(predictions=preds, references=refs))
```

These examples are for transparency and education—no training is required to use the app.

## Troubleshooting
- Python server not reachable
  - Ensure `uvicorn` is running and `AI_SERVER_URL` is set.
- spaCy NER returns no entities
  - Install `en_core_web_sm` or rely on the built‑in regex fallback.
- Translation model downloads are slow
  - First run downloads models. Subsequent runs use local cache. Consider pinning smaller Helsinki‑NLP models.
- Memory constraints
  - For low‑memory environments, disable heavy features (translation/summarization) or rely on fallbacks.

## License
MIT (or your preferred license)
