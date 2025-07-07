FROM python:3.11-slim

LABEL maintainer="MSC Wound Care Platform"
LABEL description="Medical AI Service for intelligent field mapping and medical terminology validation"

# Set working directory
WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Copy requirements and install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy service files
COPY medical_ai_service.py .
COPY ../config ./config

# Create non-root user
RUN useradd -m -u 1000 appuser && chown -R appuser:appuser /app
USER appuser

# Expose port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=30s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/health || exit 1

# Start the service
CMD ["uvicorn", "medical_ai_service:app", "--host", "0.0.0.0", "--port", "8080"] 