# AI Server (FastAPI)

This sidecar service powers NLP features for WordWisdom: generation, NER, summarization, language detection/translation, and keyword extraction.

## Quick start
```bash
# In this folder
python -m venv .venv
# Windows PowerShell
.venv\Scripts\Activate.ps1
# macOS/Linux
source .venv/bin/activate

pip install --upgrade pip
pip install -r requirements.txt

# Run the server (http://127.0.0.1:8001/docs)
uvicorn ai_server.main:app --reload --port 8001
```

Set the Laravel app to point here:
```
AI_SERVER_URL=http://localhost:8001
```

## Endpoints
- POST `/generate` → `{ content }`
- POST `/ner` → `{ entities: [{ text, label, start, end }] }`
- POST `/summarize` → `{ summary }`
- POST `/detect` → `{ lang, confidence }`
- POST `/translate` → `{ translated }`
- POST `/keywords` → `{ keywords: [{ phrase, score }] }`

Open the interactive docs at `/docs` when the server is running.

## Models and fallbacks
- NER uses spaCy if available. Install a model for better results:
```bash
python -m spacy download en_core_web_sm
```
- Summarization and translation use Hugging Face `transformers`. First run downloads models and caches them.
- Keyword extraction uses KeyBERT (Sentence-Transformers). Falls back to frequency-based keywords if models are unavailable.

## Evaluation (educational snippets)
While this service runs in inference mode, you can run standard metrics offline to understand model quality.

### NER (CoNLL‑2003) — F1-score
- Metric: entity-level F1.
- Tools: spaCy or `seqeval`.
- Example (if you have a spaCy model and dataset):
```bash
python -m spacy evaluate /path/to/model en_conll03.md  # adjust dataset path/model
```

### Summarization (CNN/DailyMail) — ROUGE
- Metric: ROUGE-1/2/L.
- Tools: Hugging Face `evaluate` + `datasets`.
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

## Troubleshooting
- Slow first run: model downloads can take time; subsequent runs use local cache.
- Memory constraints: disable heavy features or rely on fallbacks.
- CORS: enabled for all origins for local development.

## License
MIT (or project license)
