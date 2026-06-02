#!/usr/bin/env python3
"""
Predict donor donation likelihood.
Usage:
    python predict_donor.py <base64-json-features>
"""

from __future__ import annotations

import base64
import json
import pickle
import sys
from pathlib import Path

import pandas as pd


BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "model.pkl"


def heuristic_probability(features: dict) -> float:
    score = 0.45
    age = float(features.get("age", 0) or 0)
    weight = float(features.get("weight", 0) or 0)
    days = float(features.get("days_since_last_donation", 120) or 120)
    past = float(features.get("past_donations", 0) or 0)
    distance = float(features.get("distance_km", 25) or 25)
    medical_flag = int(features.get("medical_condition_flag", 0) or 0)
    availability_flag = int(features.get("availability_flag", 1) or 0)
    response_rate = float(features.get("previous_response_rate", 0) or 0)

    if 18 <= age <= 60:
        score += 0.08
    if weight >= 50:
        score += 0.08
    if days >= 90:
        score += 0.06
    if distance < 10:
        score += 0.1
    elif distance < 25:
        score += 0.04
    if medical_flag == 1:
        score -= 0.2
    if availability_flag == 1:
        score += 0.1
    score += min(0.08, past * 0.01)
    score += min(0.1, response_rate / 100.0)

    return max(0.05, min(0.95, round(score, 4)))


def load_features() -> dict:
    if len(sys.argv) < 2:
        return {}
    try:
        payload = base64.b64decode(sys.argv[1]).decode("utf-8")
        return json.loads(payload)
    except Exception:
        return {}


def make_result(probability: float, model: str) -> dict:
    predicted_class = "likely" if probability >= 0.5 else "unlikely"
    confidence = "High" if probability >= 0.75 else ("Medium" if probability >= 0.5 else "Low")
    return {
        "probability": round(float(probability), 4),
        "predicted_class": predicted_class,
        "confidence_label": confidence,
        "model": model,
    }


def predict(features: dict) -> dict:
    if not MODEL_PATH.exists():
        return make_result(heuristic_probability(features), "heuristic")

    try:
        with MODEL_PATH.open("rb") as fp:
            bundle = pickle.load(fp)
        model = bundle["model"]
        feature_columns = bundle["feature_columns"]

        row = pd.DataFrame([features])
        if "blood_group" not in row.columns:
            row["blood_group"] = "O+"
        row = pd.get_dummies(row, columns=["blood_group"], drop_first=False)

        for col in feature_columns:
            if col not in row.columns:
                row[col] = 0
        row = row[feature_columns]

        probability = float(model.predict_proba(row)[0][1])
        return make_result(probability, bundle.get("model_name", "logistic_regression"))
    except Exception:
        return make_result(heuristic_probability(features), "heuristic")


if __name__ == "__main__":
    features_data = load_features()
    result = predict(features_data)
    print(json.dumps(result))

