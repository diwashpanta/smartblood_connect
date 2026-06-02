#!/usr/bin/env python3
"""
Random-Forest-style proximity + eligibility donor matching.
Input: base64 encoded JSON with shape:
{
  "request": {"blood_group":"A+","hospital_latitude":27.7,"hospital_longitude":85.3},
  "donors": [{...}]
}
Output: sorted donor ranking JSON.
"""

from __future__ import annotations

import base64
import json
import math
import sys
from typing import Any


def compatible_donor_groups(recipient: str) -> list[str]:
    mapping = {
        "A+": ["A+", "A-", "O+", "O-"],
        "A-": ["A-", "O-"],
        "B+": ["B+", "B-", "O+", "O-"],
        "B-": ["B-", "O-"],
        "AB+": ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"],
        "AB-": ["A-", "B-", "AB-", "O-"],
        "O+": ["O+", "O-"],
        "O-": ["O-"],
    }
    return mapping.get(recipient, [])


def haversine_km(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    radius = 6371.0
    dlat = math.radians(lat2 - lat1)
    dlon = math.radians(lon2 - lon1)
    a = (
        math.sin(dlat / 2) ** 2
        + math.cos(math.radians(lat1))
        * math.cos(math.radians(lat2))
        * math.sin(dlon / 2) ** 2
    )
    return round(radius * (2 * math.asin(min(1, math.sqrt(a)))), 2)


def is_eligible(donor: dict[str, Any]) -> bool:
    age = int(donor.get("age", 0) or 0)
    weight = float(donor.get("weight", 0) or 0)
    medical = donor.get("medical_condition_status", "temporary_deferral")
    availability = donor.get("availability_status", "inactive")
    verified = int(donor.get("is_verified", 0) or 0)
    days_since_last = int(donor.get("days_since_last_donation", 120) or 120)

    if age < 18 or age > 60:
        return False
    if weight < 50:
        return False
    if medical != "healthy":
        return False
    if availability != "available":
        return False
    if verified != 1:
        return False
    if days_since_last < 90:
        return False
    return True


def score_donor(donor: dict[str, Any], request: dict[str, Any]) -> dict[str, Any]:
    distance = haversine_km(
        float(donor.get("latitude", 0) or 0),
        float(donor.get("longitude", 0) or 0),
        float(request.get("hospital_latitude", 0) or 0),
        float(request.get("hospital_longitude", 0) or 0),
    )

    response_rate = float(donor.get("response_rate", 0) or 0)
    past_donations = int(donor.get("past_donations", 0) or 0)

    # Forest-style weighted scoring from multiple independent factors.
    score = 0.0
    score += 0.35 if distance <= 8 else (0.24 if distance <= 20 else 0.1)
    score += 0.2 if past_donations >= 5 else (0.12 if past_donations >= 1 else 0.05)
    score += min(0.2, response_rate / 100.0 * 0.2)
    score += 0.15 if donor.get("medical_condition_status") == "healthy" else 0
    score += 0.1 if donor.get("availability_status") == "available" else 0

    donor["distance_km"] = distance
    donor["matching_score"] = round(score, 4)
    return donor


def rank_donors(payload: dict[str, Any]) -> list[dict[str, Any]]:
    request = payload.get("request", {})
    donors = payload.get("donors", [])

    allowed = set(compatible_donor_groups(request.get("blood_group", "")))
    ranked = []
    for donor in donors:
        if donor.get("blood_group") not in allowed:
            continue
        if not is_eligible(donor):
            continue
        ranked.append(score_donor(donor, request))

    ranked.sort(key=lambda d: (-d["matching_score"], d["distance_km"]))
    return ranked


def load_payload() -> dict[str, Any]:
    if len(sys.argv) < 2:
        return {}
    try:
        raw = base64.b64decode(sys.argv[1]).decode("utf-8")
        return json.loads(raw)
    except Exception:
        return {}


if __name__ == "__main__":
    data = load_payload()
    print(json.dumps(rank_donors(data)))

