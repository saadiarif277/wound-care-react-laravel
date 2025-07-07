#!/usr/bin/env python3
"""
ML Field Mapping API Server
FastAPI server that serves ML predictions to the Laravel application
"""

import os
import json
import logging
from datetime import datetime
from typing import Dict, List, Optional, Any
from contextlib import asynccontextmanager

import uvicorn
from fastapi import FastAPI, HTTPException, BackgroundTasks
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

# Import our ML system
from ml_field_mapping import FieldMappingMLSystem, initialize_ml_system

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Global ML system instance
ml_system: Optional[FieldMappingMLSystem] = None

@asynccontextmanager
async def lifespan(app: FastAPI):
    """Initialize ML system on startup"""
    global ml_system
    logger.info("Initializing ML Field Mapping System...")
    
    try:
        ml_system = initialize_ml_system()
        logger.info("ML system initialized successfully")
    except Exception as e:
        logger.error(f"Failed to initialize ML system: {e}")
        ml_system = None
    
    yield
    
    # Cleanup on shutdown
    logger.info("Shutting down ML Field Mapping API Server")

# Create FastAPI app
app = FastAPI(
    title="ML Field Mapping API",
    description="Machine Learning API for intelligent field mapping predictions",
    version="1.0.0",
    lifespan=lifespan
)

# Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, restrict to Laravel app domain
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Pydantic models for request/response
class PredictionRequest(BaseModel):
    source_field: str
    manufacturer: str
    document_type: str = "IVR"
    context: Optional[Dict[str, Any]] = {}

class PredictionResponse(BaseModel):
    predicted_field: str
    confidence: float
    alternative_suggestions: List[tuple]
    model_used: str
    feature_importance: Dict[str, float] = {}

class MappingRecord(BaseModel):
    source_field: str
    target_field: str
    manufacturer: str
    document_type: str
    confidence: float
    success: bool
    mapping_method: str = "ai"
    user_feedback: Optional[str] = None

class TrainingRequest(BaseModel):
    force: bool = False

class AnalyticsResponse(BaseModel):
    total_mappings: int
    success_rate: float
    avg_confidence: float
    top_manufacturers: List[tuple]
    recent_performance: Dict[str, float]
    model_metadata: Dict[str, Any]
    ml_server_available: bool = True

@app.get("/")
async def root():
    """Health check endpoint"""
    return {
        "message": "ML Field Mapping API Server",
        "status": "running",
        "ml_system_available": ml_system is not None,
        "timestamp": datetime.now().isoformat()
    }

@app.get("/health")
async def health_check():
    """Detailed health check"""
    health_status = {
        "status": "healthy" if ml_system else "unhealthy",
        "ml_system_available": ml_system is not None,
        "timestamp": datetime.now().isoformat()
    }
    
    if ml_system:
        try:
            # Try to get some basic analytics to test ML system
            analytics = ml_system.get_analytics()
            health_status["ml_system_stats"] = {
                "total_mappings": analytics.get("total_mappings", 0),
                "models_trained": bool(ml_system.ml_model.model_metadata)
            }
        except Exception as e:
            health_status["ml_system_error"] = str(e)
            health_status["status"] = "degraded"
    
    return health_status

@app.post("/api/predict", response_model=PredictionResponse)
async def predict_field_mapping(request: PredictionRequest):
    """Get field mapping prediction"""
    if not ml_system:
        raise HTTPException(status_code=503, detail="ML system not available")
    
    try:
        logger.info(f"Prediction request: {request.source_field} -> {request.manufacturer}")
        
        prediction = ml_system.predict_field_mapping(
            source_field=request.source_field,
            manufacturer=request.manufacturer,
            document_type=request.document_type
        )
        
        response = PredictionResponse(
            predicted_field=prediction.predicted_field,
            confidence=prediction.confidence,
            alternative_suggestions=prediction.alternative_suggestions,
            model_used=prediction.model_used,
            feature_importance=prediction.feature_importance
        )
        
        logger.info(f"Prediction result: {response.predicted_field} (confidence: {response.confidence:.2f})")
        return response
        
    except Exception as e:
        logger.error(f"Prediction failed: {e}")
        raise HTTPException(status_code=500, detail=f"Prediction failed: {str(e)}")

@app.post("/api/predict-batch")
async def predict_batch_field_mappings(
    requests: List[PredictionRequest]
) -> List[PredictionResponse]:
    """Get multiple field mapping predictions in batch"""
    if not ml_system:
        raise HTTPException(status_code=503, detail="ML system not available")
    
    try:
        responses = []
        
        for request in requests:
            prediction = ml_system.predict_field_mapping(
                source_field=request.source_field,
                manufacturer=request.manufacturer,
                document_type=request.document_type
            )
            
            responses.append(PredictionResponse(
                predicted_field=prediction.predicted_field,
                confidence=prediction.confidence,
                alternative_suggestions=prediction.alternative_suggestions,
                model_used=prediction.model_used,
                feature_importance=prediction.feature_importance
            ))
        
        logger.info(f"Batch prediction completed: {len(responses)} predictions")
        return responses
        
    except Exception as e:
        logger.error(f"Batch prediction failed: {e}")
        raise HTTPException(status_code=500, detail=f"Batch prediction failed: {str(e)}")

@app.post("/api/record-mapping")
async def record_mapping_result(record: MappingRecord, background_tasks: BackgroundTasks):
    """Record a field mapping result for training"""
    if not ml_system:
        raise HTTPException(status_code=503, detail="ML system not available")
    
    try:
        # Record the mapping result in background
        background_tasks.add_task(
            ml_system.record_mapping_result,
            source_field=record.source_field,
            target_field=record.target_field,
            manufacturer=record.manufacturer,
            document_type=record.document_type,
            confidence=record.confidence,
            success=record.success,
            mapping_method=record.mapping_method,
            user_feedback=record.user_feedback
        )
        
        logger.info(f"Mapping result recorded: {record.source_field} -> {record.target_field}")
        
        return {
            "status": "success",
            "message": "Mapping result recorded for training",
            "timestamp": datetime.now().isoformat()
        }
        
    except Exception as e:
        logger.error(f"Failed to record mapping result: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to record mapping: {str(e)}")

@app.post("/api/train")
async def train_models(request: TrainingRequest, background_tasks: BackgroundTasks):
    """Trigger ML model training"""
    if not ml_system:
        raise HTTPException(status_code=503, detail="ML system not available")
    
    try:
        logger.info("Training request received")
        
        # Run training in background
        def run_training():
            try:
                results = ml_system.train_models(force=request.force)
                logger.info(f"Training completed: {results}")
            except Exception as e:
                logger.error(f"Training failed: {e}")
        
        background_tasks.add_task(run_training)
        
        return {
            "status": "training_started",
            "message": "Model training started in background",
            "force": request.force,
            "timestamp": datetime.now().isoformat()
        }
        
    except Exception as e:
        logger.error(f"Failed to start training: {e}")
        raise HTTPException(status_code=500, detail=f"Training failed: {str(e)}")

@app.get("/api/training-status")
async def get_training_status():
    """Get current training status"""
    if not ml_system:
        raise HTTPException(status_code=503, detail="ML system not available")
    
    try:
        metadata = ml_system.ml_model.model_metadata
        
        return {
            "last_trained": metadata.get("last_trained"),
            "training_samples": metadata.get("training_samples", 0),
            "models_available": len(metadata.get("results", {})),
            "model_results": metadata.get("results", {}),
            "timestamp": datetime.now().isoformat()
        }
        
    except Exception as e:
        logger.error(f"Failed to get training status: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to get training status: {str(e)}")

@app.get("/api/analytics", response_model=AnalyticsResponse)
async def get_analytics():
    """Get ML system analytics"""
    if not ml_system:
        raise HTTPException(status_code=503, detail="ML system not available")
    
    try:
        analytics = ml_system.get_analytics()
        
        response = AnalyticsResponse(
            total_mappings=analytics.get("total_mappings", 0),
            success_rate=analytics.get("success_rate", 0.0),
            avg_confidence=analytics.get("avg_confidence", 0.0),
            top_manufacturers=analytics.get("top_manufacturers", []),
            recent_performance=analytics.get("recent_performance", {}),
            model_metadata=analytics.get("model_metadata", {}),
            ml_server_available=True
        )
        
        return response
        
    except Exception as e:
        logger.error(f"Failed to get analytics: {e}")
        raise HTTPException(status_code=500, detail=f"Analytics failed: {str(e)}")

@app.get("/api/manufacturers")
async def get_manufacturers():
    """Get list of manufacturers with mapping data"""
    if not ml_system:
        raise HTTPException(status_code=503, detail="ML system not available")
    
    try:
        analytics = ml_system.get_analytics()
        manufacturers = [{"name": name, "count": count} 
                        for name, count in analytics.get("top_manufacturers", [])]
        
        return {
            "manufacturers": manufacturers,
            "total_count": len(manufacturers),
            "timestamp": datetime.now().isostring()
        }
        
    except Exception as e:
        logger.error(f"Failed to get manufacturers: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to get manufacturers: {str(e)}")

@app.delete("/api/models")
async def reset_models():
    """Reset and retrain all models (use with caution)"""
    if not ml_system:
        raise HTTPException(status_code=503, detail="ML system not available")
    
    try:
        logger.warning("Model reset requested")
        
        # Force retrain all models
        results = ml_system.train_models(force=True)
        
        return {
            "status": "models_reset",
            "message": "All models have been reset and retrained",
            "results": results,
            "timestamp": datetime.now().isoformat()
        }
        
    except Exception as e:
        logger.error(f"Model reset failed: {e}")
        raise HTTPException(status_code=500, detail=f"Model reset failed: {str(e)}")

# Error handlers
@app.exception_handler(404)
async def not_found_handler(request, exc):
    return {
        "error": "Not Found",
        "message": "The requested endpoint was not found",
        "available_endpoints": [
            "/api/predict",
            "/api/predict-batch", 
            "/api/record-mapping",
            "/api/train",
            "/api/analytics",
            "/health"
        ]
    }

@app.exception_handler(500)
async def internal_error_handler(request, exc):
    logger.error(f"Internal server error: {exc}")
    return {
        "error": "Internal Server Error",
        "message": "An unexpected error occurred",
        "timestamp": datetime.now().isoformat()
    }

def main():
    """Run the ML API server"""
    import argparse
    
    parser = argparse.ArgumentParser(description="ML Field Mapping API Server")
    parser.add_argument("--host", default="0.0.0.0", help="Host to bind to")
    parser.add_argument("--port", type=int, default=8000, help="Port to bind to")
    parser.add_argument("--reload", action="store_true", help="Enable auto-reload")
    parser.add_argument("--workers", type=int, default=1, help="Number of workers")
    
    args = parser.parse_args()
    
    logger.info(f"Starting ML Field Mapping API Server on {args.host}:{args.port}")
    
    uvicorn.run(
        "ml_api_server:app",
        host=args.host,
        port=args.port,
        reload=args.reload,
        workers=args.workers,
        log_level="info"
    )

if __name__ == "__main__":
    main() 