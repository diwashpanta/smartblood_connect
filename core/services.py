from math import radians, sin, cos, asin, sqrt
from datetime import date
from .models import UserProfile
from .ml import predict_response_probability

def haversine_km(lat1, lon1, lat2, lon2):
    dlat = radians(lat2 - lat1)
    dlon = radians(lon2 - lon1)
    a = sin(dlat/2)**2 + cos(radians(lat1))*cos(radians(lat2))*sin(dlon/2)**2
    return 2 * 6371 * asin(sqrt(a))

def donor_candidates_for_request(request_obj):
    donors = UserProfile.objects.filter(role='donor', is_verified=True)
    ranked = []
    for donor in donors:
        if donor.latitude is None or donor.longitude is None:
            continue
        dist = haversine_km(request_obj.latitude, request_obj.longitude, donor.latitude, donor.longitude)
        features = [dist, 180, 30, 0, 1 if donor.blood_group == request_obj.blood_group else 0, 2]
        score = predict_response_probability(features)
        ranked.append((donor, dist, score))
    return sorted(ranked, key=lambda x: (-x[2], x[1]))[:10]
