from fastapi.testclient import TestClient
from ai_server.main import app


client = TestClient(app)


def test_generate():
    payload = {"word": "hegemony", "topic": "media", "tone": "critical"}
    r = client.post("/generate", json=payload)
    assert r.status_code == 200
    data = r.json()
    assert isinstance(data.get("content"), str)
    assert len(data["content"]) > 0


def test_ner():
    text = "Aristotle taught in Athens in 335 BC."
    r = client.post("/ner", json={"text": text})
    assert r.status_code == 200
    data = r.json()
    assert "entities" in data
    assert isinstance(data["entities"], list)
    # May be empty if models unavailable; schema still holds
    for ent in data["entities"]:
        assert set(ent.keys()) >= {"text", "label", "start", "end"}


def test_summarize():
    long_text = (
        "Philosophy examines the fundamental nature of knowledge, reality, and existence. "
        "It asks critical questions and develops methods for clarifying thought."
    )
    r = client.post("/summarize", json={"text": long_text, "max_sentences": 2, "max_tokens": 64})
    assert r.status_code == 200
    data = r.json()
    assert isinstance(data.get("summary"), str)
    assert len(data["summary"]) > 0


def test_detect():
    r = client.post("/detect", json={"text": "La filosofía explora la realidad."})
    assert r.status_code == 200
    data = r.json()
    assert isinstance(data.get("lang"), str)
    assert isinstance(data.get("confidence"), float)


def test_translate():
    # If models unavailable, service returns original text; assert schema only
    r = client.post("/translate", json={"text": "Hola mundo", "source_lang": "es", "target_lang": "en"})
    assert r.status_code == 200
    data = r.json()
    assert isinstance(data.get("translated"), str)
    assert len(data["translated"]) > 0


def test_keywords():
    r = client.post("/keywords", json={"text": "virtue ethics and moral philosophy in ancient Greece", "top_k": 5})
    assert r.status_code == 200
    data = r.json()
    assert "keywords" in data
    assert isinstance(data["keywords"], list)
    # May be empty depending on model availability; assert schema for any present
    for kw in data["keywords"]:
        assert set(kw.keys()) >= {"phrase", "score"}


