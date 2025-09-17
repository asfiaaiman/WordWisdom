from fastapi import FastAPI
from pydantic import BaseModel
from fastapi.middleware.cors import CORSMiddleware
from typing import List, Optional
import re

try:
    import spacy  # type: ignore
    _SPACY_AVAILABLE = True
except Exception:  # pragma: no cover
    spacy = None  # type: ignore
    _SPACY_AVAILABLE = False

try:
    from transformers import pipeline  # type: ignore
    _HF_AVAILABLE = True
except Exception:  # pragma: no cover
    pipeline = None  # type: ignore
    _HF_AVAILABLE = False

try:
    from langdetect import detect, DetectorFactory  # type: ignore
    DetectorFactory.seed = 0
    _LANG_AVAILABLE = True
except Exception:
    detect = None  # type: ignore
    _LANG_AVAILABLE = False

try:
    from keybert import KeyBERT  # type: ignore
    _KB_AVAILABLE = True
except Exception:
    KeyBERT = None  # type: ignore
    _KB_AVAILABLE = False

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


class GenerateRequest(BaseModel):
    word: str
    topic: str
    tone: str | None = None


class GenerateResponse(BaseModel):
    content: str


class NerRequest(BaseModel):
    text: str
    lang: Optional[str] = "en"


class Entity(BaseModel):
    text: str
    label: str
    start: int
    end: int


class NerResponse(BaseModel):
    entities: List[Entity]


class SummarizeRequest(BaseModel):
    text: str
    max_sentences: Optional[int] = 2
    max_tokens: Optional[int] = 128


class SummarizeResponse(BaseModel):
    summary: str


class DetectRequest(BaseModel):
    text: str


class DetectResponse(BaseModel):
    lang: str
    confidence: float


class TranslateRequest(BaseModel):
    text: str
    source_lang: Optional[str] = None
    target_lang: Optional[str] = "en"


class TranslateResponse(BaseModel):
    translated: str


class KeywordsRequest(BaseModel):
    text: str
    top_k: Optional[int] = 8


class Keyword(BaseModel):
    phrase: str
    score: float


class KeywordsResponse(BaseModel):
    keywords: List[Keyword]


@app.post("/generate", response_model=GenerateResponse)
def generate(req: GenerateRequest):
    word = req.word.strip()
    topic = req.topic.strip()
    tone = (req.tone or "").strip()
    tone_text = f" in a {tone} tone" if tone else ""
    content = (
        f"{word.capitalize()} reframes {topic} as a lived tension between what is and what could be{tone_text}."
    )
    return GenerateResponse(content=content[:300])


_NLP = None


def _load_spacy(lang_code: str = "en"):
    global _NLP
    if not _SPACY_AVAILABLE:
        return None
    if _NLP is not None:
        return _NLP
    model_name = {
        "en": "en_core_web_sm",
    }.get(lang_code, "en_core_web_sm")
    try:
        _NLP = spacy.load(model_name)  # type: ignore
    except Exception:
        try:
            _NLP = spacy.blank(lang_code)  # type: ignore
        except Exception:
            _NLP = None
    return _NLP


def _regex_fallback_entities(text: str) -> List[Entity]:
    entities: List[Entity] = []
    # Very naive patterns: URLs, capitalized words sequences, simple years
    url_pattern = re.compile(r"https?://\S+")
    for m in url_pattern.finditer(text):
        entities.append(Entity(text=m.group(0), label="URL", start=m.start(), end=m.end()))

    year_pattern = re.compile(r"\b(19|20)\d{2}\b")
    for m in year_pattern.finditer(text):
        entities.append(Entity(text=m.group(0), label="DATE", start=m.start(), end=m.end()))

    proper_noun_pattern = re.compile(r"\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\b")
    for m in proper_noun_pattern.finditer(text):
        token = m.group(1)
        if token.lower() in {"i", "The".lower()}:  # skip trivial
            continue
        entities.append(Entity(text=token, label="PROPN", start=m.start(1), end=m.end(1)))
    # De-duplicate overlaps by preferring longer spans
    entities.sort(key=lambda e: (e.start, -(e.end - e.start)))
    filtered: List[Entity] = []
    last_end = -1
    for ent in entities:
        if ent.start < last_end:
            continue
        filtered.append(ent)
        last_end = ent.end
    return filtered


@app.post("/ner", response_model=NerResponse)
def ner(req: NerRequest):
    text = (req.text or "").strip()
    if not text:
        return NerResponse(entities=[])
    nlp = _load_spacy(req.lang or "en")
    if nlp is not None and hasattr(nlp, "__call__"):
        try:
            doc = nlp(text)  # type: ignore
            ents = [
                Entity(text=ent.text, label=ent.label_, start=ent.start_char, end=ent.end_char)  # type: ignore
                for ent in getattr(doc, "ents", [])
            ]
            if ents:
                return NerResponse(entities=ents)
        except Exception:
            pass
    return NerResponse(entities=_regex_fallback_entities(text))


_SUMMARIZER = None


def _load_summarizer():
    global _SUMMARIZER
    if not _HF_AVAILABLE:
        return None
    if _SUMMARIZER is not None:
        return _SUMMARIZER
    try:
        _SUMMARIZER = pipeline("summarization")  # type: ignore
    except Exception:
        _SUMMARIZER = None
    return _SUMMARIZER


def _fallback_summarize(text: str, max_sentences: int = 2) -> str:
    text = text.strip()
    if not text:
        return ""
    # crude sentence split
    parts = re.split(r"(?<=[.!?])\s+", text)
    if max_sentences <= 0:
        max_sentences = 2
    return " ".join(parts[:max_sentences]).strip()


@app.post("/summarize", response_model=SummarizeResponse)
def summarize(req: SummarizeRequest):
    text = (req.text or "").strip()
    if not text:
        return SummarizeResponse(summary="")
    max_tokens = req.max_tokens or 128
    max_sent = req.max_sentences or 2
    summarizer = _load_summarizer()
    if summarizer is not None:
        try:
            out = summarizer(text, max_length=max_tokens, min_length=max(16, max_tokens // 4), do_sample=False)  # type: ignore
            if out and isinstance(out, list) and 'summary_text' in out[0]:  # type: ignore
                return SummarizeResponse(summary=out[0]['summary_text'][:600])  # type: ignore
        except Exception:
            pass
    return SummarizeResponse(summary=_fallback_summarize(text, max_sentences=max_sent))


@app.post("/detect", response_model=DetectResponse)
def detect_lang(req: DetectRequest):
    text = (req.text or "").strip()
    if not text:
        return DetectResponse(lang="und", confidence=0.0)
    if _LANG_AVAILABLE:
        try:
            lang_code = detect(text)
            # langdetect doesn't provide confidence reliably; simple heuristic: length-based
            conf = min(1.0, max(0.3, len(text) / 200.0))
            return DetectResponse(lang=lang_code or "und", confidence=conf)
        except Exception:
            pass
    return DetectResponse(lang="und", confidence=0.0)


_TRANSLATORS: dict[str, any] = {}


def _load_translator(src: str, tgt: str):
    if not _HF_AVAILABLE:
        return None
    key = f"{src}-{tgt}"
    if key in _TRANSLATORS:
        return _TRANSLATORS[key]
    # Use NLLB or Helsinki-NLP models if available; default to t5-base for en<->xx
    model_name = None
    if src == "en" and tgt != "en":
        model_name = f"Helsinki-NLP/opus-mt-en-{tgt}"
    elif tgt == "en" and src != "en":
        model_name = f"Helsinki-NLP/opus-mt-{src}-en"
    else:
        model_name = "t5-base"
    try:
        _TRANSLATORS[key] = pipeline("translation", model=model_name)  # type: ignore
    except Exception:
        try:
            _TRANSLATORS[key] = pipeline("translation")  # type: ignore
        except Exception:
            _TRANSLATORS[key] = None
    return _TRANSLATORS[key]


@app.post("/translate", response_model=TranslateResponse)
def translate(req: TranslateRequest):
    text = (req.text or "").strip()
    if not text:
        return TranslateResponse(translated="")
    src = (req.source_lang or "und").lower()
    tgt = (req.target_lang or "en").lower()
    if src == tgt:
        return TranslateResponse(translated=text)
    trans = _load_translator(src, tgt)
    if trans is not None:
        try:
            out = trans(text)  # type: ignore
            if out and isinstance(out, list) and 'translation_text' in out[0]:  # type: ignore
                return TranslateResponse(translated=out[0]['translation_text'])  # type: ignore
        except Exception:
            pass
    # simple fallback: return original if translation unavailable
    return TranslateResponse(translated=text)


_KEYBERT = None


def _load_keybert():
    global _KEYBERT
    if not _KB_AVAILABLE:
        return None
    if _KEYBERT is not None:
        return _KEYBERT
    try:
        # Sentence-Transformers will auto-pick a default model
        _KEYBERT = KeyBERT()
    except Exception:
        _KEYBERT = None
    return _KEYBERT


def _fallback_keywords(text: str, top_k: int = 8) -> List[Keyword]:
    words = re.findall(r"[A-Za-z][A-Za-z\-]+", text)
    freq: dict[str, int] = {}
    for w in words:
        lw = w.lower()
        if len(lw) < 4:
            continue
        freq[lw] = freq.get(lw, 0) + 1
    sorted_items = sorted(freq.items(), key=lambda x: x[1], reverse=True)[:top_k]
    return [Keyword(phrase=k, score=float(v)) for k, v in sorted_items]


@app.post("/keywords", response_model=KeywordsResponse)
def keywords(req: KeywordsRequest):
    text = (req.text or "").strip()
    if not text:
        return KeywordsResponse(keywords=[])
    top_k = int(req.top_k or 8)
    kb = _load_keybert()
    if kb is not None:
        try:
            tuples = kb.extract_keywords(text, keyphrase_ngram_range=(1, 2), stop_words='english', top_n=top_k)
            kws = [Keyword(phrase=t[0], score=float(t[1])) for t in tuples]
            return KeywordsResponse(keywords=kws)
        except Exception:
            pass
    return KeywordsResponse(keywords=_fallback_keywords(text, top_k=top_k))

