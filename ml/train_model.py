#!/usr/bin/env python3
"""
Train donor likelihood models for SmartBlood Connect.
Creates:
  - ml/model.pkl (logistic regression bundle)
  - ml/rf_model.pkl (random forest bundle)
  - ml/metrics.json
"""

from __future__ import annotations

import json
import pickle
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import accuracy_score, classification_report
from sklearn.model_selection import train_test_split


BASE_DIR = Path(__file__).resolve().parent
DATA_PATH = BASE_DIR / "sample_donor_data.csv"
MODEL_PATH = BASE_DIR / "model.pkl"
RF_MODEL_PATH = BASE_DIR / "rf_model.pkl"
METRICS_PATH = BASE_DIR / "metrics.json"

FEATURES = [
    "age",
    "weight",
    "blood_group",
    "days_since_last_donation",
    "past_donations",
    "medical_condition_flag",
    "availability_flag",
    "distance_km",
    "previous_response_rate",
]
TARGET = "responded"


def load_data() -> pd.DataFrame:
    if not DATA_PATH.exists():
        raise FileNotFoundError(f"Dataset not found: {DATA_PATH}")
    df = pd.read_csv(DATA_PATH)
    missing = [c for c in FEATURES + [TARGET] if c not in df.columns]
    if missing:
        raise ValueError(f"Dataset missing columns: {missing}")
    return df


def prepare_features(df: pd.DataFrame) -> tuple[pd.DataFrame, pd.Series]:
    X = df[FEATURES].copy()
    y = df[TARGET].astype(int)
    X = pd.get_dummies(X, columns=["blood_group"], drop_first=False)
    return X, y


def train_models(X: pd.DataFrame, y: pd.Series) -> dict:
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.25, random_state=42, stratify=y
    )

    logistic = LogisticRegression(max_iter=2000)
    logistic.fit(X_train, y_train)
    logistic_pred = logistic.predict(X_test)
    logistic_prob = logistic.predict_proba(X_test)[:, 1]

    rf = RandomForestClassifier(
        n_estimators=200,
        random_state=42,
        class_weight="balanced_subsample",
        min_samples_leaf=2,
    )
    rf.fit(X_train, y_train)
    rf_pred = rf.predict(X_test)
    rf_prob = rf.predict_proba(X_test)[:, 1]

    metrics = {
        "dataset_size": int(len(X)),
        "feature_count": int(X.shape[1]),
        "logistic_regression": {
            "accuracy": float(accuracy_score(y_test, logistic_pred)),
            "report": classification_report(y_test, logistic_pred, output_dict=True),
            "avg_probability": float(np.mean(logistic_prob)),
        },
        "random_forest": {
            "accuracy": float(accuracy_score(y_test, rf_pred)),
            "report": classification_report(y_test, rf_pred, output_dict=True),
            "avg_probability": float(np.mean(rf_prob)),
        },
    }

    with MODEL_PATH.open("wb") as fp:
        pickle.dump(
            {
                "model_name": "logistic_regression",
                "model": logistic,
                "feature_columns": list(X.columns),
            },
            fp,
        )

    with RF_MODEL_PATH.open("wb") as fp:
        pickle.dump(
            {
                "model_name": "random_forest",
                "model": rf,
                "feature_columns": list(X.columns),
            },
            fp,
        )

    with METRICS_PATH.open("w", encoding="utf-8") as fp:
        json.dump(metrics, fp, indent=2)

    return metrics


def main() -> None:
    df = load_data()
    X, y = prepare_features(df)
    metrics = train_models(X, y)
    print(json.dumps(metrics, indent=2))


if __name__ == "__main__":
    main()

