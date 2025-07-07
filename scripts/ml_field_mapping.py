#!/usr/bin/env python3
"""
ML Field Mapping System for Predictive Analytics
Improves field mapping accuracy through machine learning models
"""

import os
import json
import pickle
import logging
import sqlite3
import numpy as np
import pandas as pd
from datetime import datetime, timedelta
from typing import Dict, List, Tuple, Optional, Any
from dataclasses import dataclass, asdict
from sklearn.ensemble import RandomForestClassifier, GradientBoostingClassifier
from sklearn.neural_network import MLPClassifier
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.preprocessing import LabelEncoder, StandardScaler
import xgboost as xgb
from fuzzywuzzy import fuzz
import joblib

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@dataclass
class FieldMappingRecord:
    """Record for field mapping training data"""
    timestamp: str
    source_field: str
    target_field: str
    manufacturer: str
    document_type: str
    confidence_score: float
    user_feedback: Optional[str] = None
    success: bool = True
    mapping_method: str = "unknown"
    field_context: Dict[str, Any] = None

@dataclass
class ModelPrediction:
    """ML model prediction result"""
    predicted_field: str
    confidence: float
    alternative_suggestions: List[Tuple[str, float]]
    model_used: str
    feature_importance: Dict[str, float]

class FieldMappingDataCollector:
    """Collects and stores field mapping training data"""
    
    def __init__(self, db_path: str = "ml_field_mapping.db"):
        self.db_path = db_path
        self.init_database()
    
    def init_database(self):
        """Initialize SQLite database for training data"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS field_mappings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    timestamp TEXT NOT NULL,
                    source_field TEXT NOT NULL,
                    target_field TEXT NOT NULL,
                    manufacturer TEXT NOT NULL,
                    document_type TEXT NOT NULL,
                    confidence_score REAL NOT NULL,
                    user_feedback TEXT,
                    success INTEGER NOT NULL,
                    mapping_method TEXT NOT NULL,
                    field_context TEXT
                )
            ''')
            
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS model_performance (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    model_name TEXT NOT NULL,
                    timestamp TEXT NOT NULL,
                    accuracy REAL NOT NULL,
                    precision_avg REAL NOT NULL,
                    recall_avg REAL NOT NULL,
                    f1_avg REAL NOT NULL,
                    training_samples INTEGER NOT NULL
                )
            ''')
            
            conn.commit()
            conn.close()
            logger.info("Database initialized successfully")
        except Exception as e:
            logger.error(f"Failed to initialize database: {e}")
    
    def record_mapping(self, record: FieldMappingRecord):
        """Store field mapping record for training"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
                INSERT INTO field_mappings 
                (timestamp, source_field, target_field, manufacturer, 
                 document_type, confidence_score, user_feedback, success, 
                 mapping_method, field_context)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ''', (
                record.timestamp,
                record.source_field,
                record.target_field,
                record.manufacturer,
                record.document_type,
                record.confidence_score,
                record.user_feedback,
                1 if record.success else 0,
                record.mapping_method,
                json.dumps(record.field_context) if record.field_context else None
            ))
            
            conn.commit()
            conn.close()
            logger.info(f"Recorded mapping: {record.source_field} -> {record.target_field}")
        except Exception as e:
            logger.error(f"Failed to record mapping: {e}")
    
    def get_training_data(self, min_samples: int = 50) -> pd.DataFrame:
        """Retrieve training data from database"""
        try:
            conn = sqlite3.connect(self.db_path)
            
            query = '''
                SELECT * FROM field_mappings 
                WHERE timestamp > datetime('now', '-30 days')
                ORDER BY timestamp DESC
            '''
            
            df = pd.read_sql_query(query, conn)
            conn.close()
            
            if len(df) < min_samples:
                logger.warning(f"Only {len(df)} samples available, need at least {min_samples}")
                return self._generate_synthetic_data(min_samples)
            
            return df
        except Exception as e:
            logger.error(f"Failed to retrieve training data: {e}")
            return pd.DataFrame()
    
    def _generate_synthetic_data(self, samples: int) -> pd.DataFrame:
        """Generate synthetic training data for initial model training"""
        synthetic_data = []
        
        # Common field mapping patterns
        patterns = [
            ("patient_name", "patient_full_name", "BIOWOUND SOLUTIONS", "INSURANCE_CARD", 0.95),
            ("patient_first_name", "patient_name_first", "ADVANCED SOLUTION", "INSURANCE_CARD", 0.90),
            ("patient_last_name", "patient_name_last", "ADVANCED SOLUTION", "INSURANCE_CARD", 0.90),
            ("dob", "date_of_birth", "SKYE Biologics", "INSURANCE_CARD", 0.85),
            ("insurance_id", "member_id", "CENTURION THERAPEUTICS", "INSURANCE_CARD", 0.90),
            ("wound_description", "clinical_notes", "BioWerX", "CLINICAL_NOTE", 0.80),
            ("diagnosis_code", "icd_code", "MEDLIFE SOLUTIONS", "CLINICAL_NOTE", 0.95),
            ("physician_name", "provider_name", "Total Ancillary", "CLINICAL_NOTE", 0.85),
        ]
        
        for i in range(samples):
            pattern = patterns[i % len(patterns)]
            synthetic_data.append({
                'timestamp': (datetime.now() - timedelta(days=i)).isoformat(),
                'source_field': pattern[0],
                'target_field': pattern[1],
                'manufacturer': pattern[2],
                'document_type': pattern[3],
                'confidence_score': pattern[4] + np.random.normal(0, 0.05),
                'user_feedback': None,
                'success': 1,
                'mapping_method': 'synthetic',
                'field_context': None
            })
        
        return pd.DataFrame(synthetic_data)

class FieldMappingMLModel:
    """ML Model for field mapping predictions"""
    
    def __init__(self, model_dir: str = "ml_models"):
        self.model_dir = model_dir
        os.makedirs(model_dir, exist_ok=True)
        
        # Initialize models
        self.models = {
            'random_forest': RandomForestClassifier(n_estimators=100, random_state=42),
            'xgboost': xgb.XGBClassifier(random_state=42),
            'neural_network': MLPClassifier(hidden_layer_sizes=(100, 50), random_state=42),
            'gradient_boosting': GradientBoostingClassifier(random_state=42)
        }
        
        # Feature extractors
        self.tfidf_vectorizer = TfidfVectorizer(max_features=1000, ngram_range=(1, 3))
        self.label_encoder = LabelEncoder()
        self.scaler = StandardScaler()
        
        # Model metadata
        self.model_metadata = {}
        self.load_models()
    
    def extract_features(self, df: pd.DataFrame) -> np.ndarray:
        """Extract features from field mapping data"""
        features = []
        
        for _, row in df.iterrows():
            feature_vector = []
            
            # Text similarity features
            source_field = str(row['source_field']).lower()
            target_field = str(row['target_field']).lower()
            
            # Fuzzy matching features
            feature_vector.extend([
                fuzz.ratio(source_field, target_field) / 100.0,
                fuzz.partial_ratio(source_field, target_field) / 100.0,
                fuzz.token_sort_ratio(source_field, target_field) / 100.0,
                fuzz.token_set_ratio(source_field, target_field) / 100.0,
            ])
            
            # Length features
            feature_vector.extend([
                len(source_field),
                len(target_field),
                abs(len(source_field) - len(target_field)),
                len(source_field.split('_')),
                len(target_field.split('_')),
            ])
            
            # Categorical features (encoded)
            manufacturer = str(row['manufacturer'])
            document_type = str(row['document_type'])
            
            # One-hot encode manufacturer (simplified)
            manufacturer_features = [1 if m in manufacturer else 0 for m in 
                                   ['BIOWOUND', 'ADVANCED', 'SKYE', 'CENTURION', 'MEDLIFE']]
            feature_vector.extend(manufacturer_features)
            
            # Document type features
            doc_type_features = [1 if dt in document_type else 0 for dt in 
                               ['INSURANCE_CARD', 'CLINICAL_NOTE', 'ORDER_FORM']]
            feature_vector.extend(doc_type_features)
            
            # Confidence score feature
            feature_vector.append(float(row['confidence_score']))
            
            features.append(feature_vector)
        
        return np.array(features)
    
    def train_models(self, training_data: pd.DataFrame) -> Dict[str, Dict[str, float]]:
        """Train all ML models"""
        results = {}
        
        try:
            # Prepare features and targets
            X = self.extract_features(training_data)
            y = training_data['target_field'].values
            
            # Encode labels
            y_encoded = self.label_encoder.fit_transform(y)
            
            # Scale features
            X_scaled = self.scaler.fit_transform(X)
            
            # Split data
            X_train, X_test, y_train, y_test = train_test_split(
                X_scaled, y_encoded, test_size=0.2, random_state=42
            )
            
            # Train each model
            for model_name, model in self.models.items():
                logger.info(f"Training {model_name}...")
                
                # Train model
                model.fit(X_train, y_train)
                
                # Evaluate model
                y_pred = model.predict(X_test)
                accuracy = accuracy_score(y_test, y_pred)
                
                # Cross-validation
                cv_scores = cross_val_score(model, X_train, y_train, cv=5)
                
                results[model_name] = {
                    'accuracy': accuracy,
                    'cv_mean': cv_scores.mean(),
                    'cv_std': cv_scores.std(),
                    'training_samples': len(X_train)
                }
                
                # Save model
                model_path = os.path.join(self.model_dir, f"{model_name}.pkl")
                joblib.dump(model, model_path)
                
                logger.info(f"{model_name} accuracy: {accuracy:.4f} (±{cv_scores.std():.4f})")
            
            # Save feature extractors
            joblib.dump(self.tfidf_vectorizer, os.path.join(self.model_dir, "tfidf_vectorizer.pkl"))
            joblib.dump(self.label_encoder, os.path.join(self.model_dir, "label_encoder.pkl"))
            joblib.dump(self.scaler, os.path.join(self.model_dir, "scaler.pkl"))
            
            # Save metadata
            self.model_metadata = {
                'last_trained': datetime.now().isoformat(),
                'training_samples': len(training_data),
                'unique_targets': len(set(y)),
                'results': results
            }
            
            metadata_path = os.path.join(self.model_dir, "metadata.json")
            with open(metadata_path, 'w') as f:
                json.dump(self.model_metadata, f, indent=2)
            
            logger.info("Model training completed successfully")
            return results
            
        except Exception as e:
            logger.error(f"Model training failed: {e}")
            return {}
    
    def load_models(self):
        """Load trained models"""
        try:
            metadata_path = os.path.join(self.model_dir, "metadata.json")
            if os.path.exists(metadata_path):
                with open(metadata_path, 'r') as f:
                    self.model_metadata = json.load(f)
            
            # Load models
            for model_name in self.models.keys():
                model_path = os.path.join(self.model_dir, f"{model_name}.pkl")
                if os.path.exists(model_path):
                    self.models[model_name] = joblib.load(model_path)
            
            # Load feature extractors
            tfidf_path = os.path.join(self.model_dir, "tfidf_vectorizer.pkl")
            if os.path.exists(tfidf_path):
                self.tfidf_vectorizer = joblib.load(tfidf_path)
            
            label_path = os.path.join(self.model_dir, "label_encoder.pkl")
            if os.path.exists(label_path):
                self.label_encoder = joblib.load(label_path)
            
            scaler_path = os.path.join(self.model_dir, "scaler.pkl")
            if os.path.exists(scaler_path):
                self.scaler = joblib.load(scaler_path)
            
            logger.info("Models loaded successfully")
            
        except Exception as e:
            logger.error(f"Failed to load models: {e}")
    
    def predict_field_mapping(self, source_field: str, manufacturer: str, 
                            document_type: str) -> ModelPrediction:
        """Predict field mapping using ensemble of models"""
        try:
            # Create sample data for prediction
            sample_data = pd.DataFrame([{
                'source_field': source_field,
                'target_field': '',  # Will be predicted
                'manufacturer': manufacturer,
                'document_type': document_type,
                'confidence_score': 0.5  # Default
            }])
            
            # Extract features
            X = self.extract_features(sample_data)
            X_scaled = self.scaler.transform(X)
            
            # Get predictions from all models
            predictions = {}
            probabilities = {}
            
            for model_name, model in self.models.items():
                if hasattr(model, 'predict_proba'):
                    pred_proba = model.predict_proba(X_scaled)[0]
                    pred_class = model.predict(X_scaled)[0]
                    
                    predictions[model_name] = pred_class
                    probabilities[model_name] = pred_proba
            
            # Ensemble prediction (majority vote with confidence weighting)
            if predictions:
                # Get the most confident prediction
                best_model = max(probabilities.keys(), 
                               key=lambda k: max(probabilities[k]))
                
                predicted_class = predictions[best_model]
                confidence = max(probabilities[best_model])
                
                # Decode prediction
                predicted_field = self.label_encoder.inverse_transform([predicted_class])[0]
                
                # Get alternative suggestions
                top_indices = np.argsort(probabilities[best_model])[-5:][::-1]
                alternatives = [
                    (self.label_encoder.inverse_transform([idx])[0], 
                     probabilities[best_model][idx])
                    for idx in top_indices[1:]  # Skip the top prediction
                ]
                
                return ModelPrediction(
                    predicted_field=predicted_field,
                    confidence=float(confidence),
                    alternative_suggestions=alternatives,
                    model_used=best_model,
                    feature_importance={}
                )
            
            else:
                # Fallback to simple heuristic
                return ModelPrediction(
                    predicted_field=source_field,
                    confidence=0.5,
                    alternative_suggestions=[],
                    model_used='fallback',
                    feature_importance={}
                )
            
        except Exception as e:
            logger.error(f"Prediction failed: {e}")
            return ModelPrediction(
                predicted_field=source_field,
                confidence=0.1,
                alternative_suggestions=[],
                model_used='error',
                feature_importance={}
            )

class FieldMappingMLSystem:
    """Complete ML system for field mapping"""
    
    def __init__(self):
        self.data_collector = FieldMappingDataCollector()
        self.ml_model = FieldMappingMLModel()
        self.training_threshold = 100  # Minimum samples for retraining
        self.last_training = None
    
    def record_mapping_result(self, source_field: str, target_field: str,
                            manufacturer: str, document_type: str,
                            confidence: float, success: bool,
                            mapping_method: str = "ai", user_feedback: str = None):
        """Record a field mapping result for training"""
        record = FieldMappingRecord(
            timestamp=datetime.now().isoformat(),
            source_field=source_field,
            target_field=target_field,
            manufacturer=manufacturer,
            document_type=document_type,
            confidence_score=confidence,
            user_feedback=user_feedback,
            success=success,
            mapping_method=mapping_method
        )
        
        self.data_collector.record_mapping(record)
        
        # Check if we should retrain
        self._check_retrain()
    
    def predict_field_mapping(self, source_field: str, manufacturer: str,
                            document_type: str) -> ModelPrediction:
        """Get ML-powered field mapping prediction"""
        return self.ml_model.predict_field_mapping(source_field, manufacturer, document_type)
    
    def train_models(self, force: bool = False) -> Dict[str, Dict[str, float]]:
        """Train ML models with latest data"""
        training_data = self.data_collector.get_training_data()
        
        if len(training_data) < self.training_threshold and not force:
            logger.info(f"Not enough data for training: {len(training_data)} < {self.training_threshold}")
            return {}
        
        results = self.ml_model.train_models(training_data)
        self.last_training = datetime.now()
        
        return results
    
    def _check_retrain(self):
        """Check if models should be retrained"""
        if self.last_training is None:
            return
        
        # Retrain weekly or when we have enough new data
        days_since_training = (datetime.now() - self.last_training).days
        
        if days_since_training > 7:
            logger.info("Triggering model retraining due to time threshold")
            self.train_models()
    
    def get_analytics(self) -> Dict[str, Any]:
        """Get ML system analytics"""
        try:
            conn = sqlite3.connect(self.data_collector.db_path)
            
            # Get basic statistics
            stats = {}
            
            # Total mappings
            cursor = conn.cursor()
            cursor.execute("SELECT COUNT(*) FROM field_mappings")
            stats['total_mappings'] = cursor.fetchone()[0]
            
            # Success rate
            cursor.execute("SELECT AVG(success) FROM field_mappings")
            stats['success_rate'] = cursor.fetchone()[0] or 0
            
            # Average confidence
            cursor.execute("SELECT AVG(confidence_score) FROM field_mappings")
            stats['avg_confidence'] = cursor.fetchone()[0] or 0
            
            # Top manufacturers
            cursor.execute("""
                SELECT manufacturer, COUNT(*) as count 
                FROM field_mappings 
                GROUP BY manufacturer 
                ORDER BY count DESC 
                LIMIT 5
            """)
            stats['top_manufacturers'] = cursor.fetchall()
            
            # Recent performance
            cursor.execute("""
                SELECT AVG(confidence_score) as avg_conf, AVG(success) as success_rate
                FROM field_mappings 
                WHERE timestamp > datetime('now', '-7 days')
            """)
            recent = cursor.fetchone()
            stats['recent_performance'] = {
                'avg_confidence': recent[0] or 0,
                'success_rate': recent[1] or 0
            }
            
            conn.close()
            
            # Add model metadata
            stats['model_metadata'] = self.ml_model.model_metadata
            
            return stats
            
        except Exception as e:
            logger.error(f"Failed to get analytics: {e}")
            return {}

# Global ML system instance
ml_system = FieldMappingMLSystem()

def initialize_ml_system():
    """Initialize the ML system with initial training"""
    logger.info("Initializing ML Field Mapping System...")
    
    # Force initial training with synthetic data
    results = ml_system.train_models(force=True)
    
    if results:
        logger.info("ML system initialized successfully")
        for model_name, metrics in results.items():
            logger.info(f"{model_name}: {metrics['accuracy']:.4f} accuracy")
    else:
        logger.warning("ML system initialization failed")
    
    return ml_system

if __name__ == "__main__":
    # Initialize and test the ML system
    ml_system = initialize_ml_system()
    
    # Test prediction
    prediction = ml_system.predict_field_mapping(
        source_field="patient_name",
        manufacturer="BIOWOUND SOLUTIONS",
        document_type="INSURANCE_CARD"
    )
    
    print(f"Prediction: {prediction.predicted_field} (confidence: {prediction.confidence:.4f})")
    print(f"Alternatives: {prediction.alternative_suggestions}")
    
    # Get analytics
    analytics = ml_system.get_analytics()
    print(f"Analytics: {json.dumps(analytics, indent=2)}") 