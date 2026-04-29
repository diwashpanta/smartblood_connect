from pathlib import Path
import numpy as np
from joblib import dump, load
from sklearn.ensemble import RandomForestClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.preprocessing import StandardScaler

MODEL_DIR = Path(__file__).resolve().parent / 'models'
MODEL_DIR.mkdir(exist_ok=True)
RF_FILE = MODEL_DIR / 'rf.pkl'
LR_FILE = MODEL_DIR / 'lr.pkl'

def train_models():
    x = np.random.rand(400, 6)
    y1 = (x[:,0] + x[:,4] > 0.8).astype(int)
    scaler = StandardScaler().fit(x)
    xs = scaler.transform(x)
    rf = RandomForestClassifier(n_estimators=80, random_state=42)
    rf.fit(xs, y1)
    dump({'m': rf, 's': scaler}, RF_FILE)

    x2 = np.random.rand(400, 2)
    y2 = (x2[:,0] + x2[:,1] > 1.0).astype(int)
    lr = LogisticRegression(max_iter=300)
    lr.fit(x2, y2)
    dump({'m': lr}, LR_FILE)

def predict_response_probability(features):
    if not RF_FILE.exists():
        train_models()
    obj = load(RF_FILE)
    arr = np.array(features).reshape(1, -1)
    arr = obj['s'].transform(arr)
    return float(obj['m'].predict_proba(arr)[0][1])
