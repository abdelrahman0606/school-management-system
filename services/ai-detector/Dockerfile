# Self-hosted AI-text-detector service (see main.py's module docstring for
# why this exists and how it's wired into the LMS module). Separate image
# from the main app/Dockerfile on purpose — this needs PyTorch + a ~0.4B
# parameter model, none of which belongs anywhere near the PHP app image.
FROM python:3.11-slim

WORKDIR /app

# CPU-only PyTorch wheel — the regular PyPI wheel bundles CUDA libraries
# that are ~5x the size and useless without a GPU passed through to Docker,
# which most self-hosters running this alongside a school app won't have.
RUN pip install --no-cache-dir --extra-index-url https://download.pytorch.org/whl/cpu \
    torch==2.5.1+cpu

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY main.py .

# Bake the model into the image at build time (not on first request) so a
# fresh container starts ready to serve and never needs outbound internet
# access at runtime. Adds a few minutes and ~1.7GB to the build, not to
# every container start.
ARG AI_DETECTOR_MODEL=desklib/ai-text-detector-v1.01
ENV AI_DETECTOR_MODEL=${AI_DETECTOR_MODEL}
RUN python -c "\
from transformers import AutoTokenizer, AutoConfig, AutoModel; \
import os; \
name = os.environ['AI_DETECTOR_MODEL']; \
AutoTokenizer.from_pretrained(name); \
AutoConfig.from_pretrained(name); \
AutoModel.from_pretrained(name)"

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD python -c "import urllib.request; urllib.request.urlopen('http://localhost:8000/health', timeout=3)" || exit 1

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]
