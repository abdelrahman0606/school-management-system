"""
Self-hosted AI-generated-text detector, used as a free/unlimited alternative
to the paid Anthropic-backed AI checker in the LMS module
(app/Modules/LMS/Gateways/AnthropicAiChecker.php).

Model: desklib/ai-text-detector-v1.01 (MIT license) — a fine-tuned
microsoft/deberta-v3-large classifier, tops the RAID AI-detection benchmark
at the time this was written. See https://huggingface.co/desklib/ai-text-detector-v1.01
for the model card and the upstream usage snippet this file's model class
and inference logic are copied from (custom PreTrainedModel subclass — the
model can't be loaded with a plain AutoModelForSequenceClassification because
it uses mean pooling + a custom classifier head, not the standard HF
sequence-classification head).

This is a genuinely free, unlimited, self-hosted replacement — no per-request
cost, no rate limit — but it's CPU/GPU inference, not a hosted API: it needs
its own container (see docker-compose.yml's ai-detector service) and won't
run on plain shared cPanel hosting the way the rest of this app does (see
docs/cpanel-deployment.md and docs/vps-deployment.md). It's opt-in via
LMS_AI_PROVIDER=self_hosted — the default stays "anthropic" so a fresh
install without Docker keeps working exactly as before.
"""

import os

import torch
import torch.nn as nn
import uvicorn
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel
from transformers import AutoConfig, AutoModel, AutoTokenizer, PreTrainedModel

MODEL_NAME = os.environ.get("AI_DETECTOR_MODEL", "desklib/ai-text-detector-v1.01")
MAX_LEN = int(os.environ.get("AI_DETECTOR_MAX_LEN", "768"))
# Shared secret so the service isn't callable by anything except the app
# container — it sits on the same private Docker network as db/redis/minio
# (no published port in docker-compose.yml), so this is defense-in-depth,
# not the only thing standing between it and the outside world.
SHARED_SECRET = os.environ.get("AI_DETECTOR_SHARED_SECRET", "")


class DesklibAIDetectionModel(PreTrainedModel):
    """Verbatim from the model card — mean-pools the transformer's last
    hidden state and runs it through a single linear classifier head. This
    is NOT the standard HF sequence-classification architecture, which is
    why AutoModelForSequenceClassification can't load it directly."""

    config_class = AutoConfig

    def __init__(self, config):
        super().__init__(config)
        self.model = AutoModel.from_config(config)
        self.classifier = nn.Linear(config.hidden_size, 1)
        self.init_weights()

    def forward(self, input_ids, attention_mask=None):
        outputs = self.model(input_ids, attention_mask=attention_mask)
        last_hidden_state = outputs[0]
        input_mask_expanded = (
            attention_mask.unsqueeze(-1).expand(last_hidden_state.size()).float()
        )
        sum_embeddings = torch.sum(last_hidden_state * input_mask_expanded, dim=1)
        sum_mask = torch.clamp(input_mask_expanded.sum(dim=1), min=1e-9)
        pooled_output = sum_embeddings / sum_mask
        logits = self.classifier(pooled_output)
        return {"logits": logits}


app = FastAPI(title="AI Text Detector", version="1.0.0")

device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
tokenizer = AutoTokenizer.from_pretrained(MODEL_NAME)
model = DesklibAIDetectionModel.from_pretrained(MODEL_NAME)
model.to(device)
model.eval()


class DetectRequest(BaseModel):
    text: str


class DetectResponse(BaseModel):
    ai_score: int
    likely_ai_generated: bool


@app.get("/health")
def health():
    return {"status": "ok", "model": MODEL_NAME, "device": str(device)}


@app.post("/detect", response_model=DetectResponse)
def detect(payload: DetectRequest, x_internal_secret: str = Header(default="")):
    if SHARED_SECRET and x_internal_secret != SHARED_SECRET:
        raise HTTPException(status_code=401, detail="Invalid or missing shared secret")

    text = payload.text.strip()
    if not text:
        raise HTTPException(status_code=422, detail="text must not be empty")

    encoded = tokenizer(
        text,
        padding="max_length",
        truncation=True,
        max_length=MAX_LEN,
        return_tensors="pt",
    )
    input_ids = encoded["input_ids"].to(device)
    attention_mask = encoded["attention_mask"].to(device)

    with torch.no_grad():
        outputs = model(input_ids=input_ids, attention_mask=attention_mask)
        probability = torch.sigmoid(outputs["logits"]).item()

    ai_score = max(0, min(100, round(probability * 100)))

    return DetectResponse(ai_score=ai_score, likely_ai_generated=ai_score >= 50)


if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
