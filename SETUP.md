# SETUP GUIDE - SmartBlood Connect

## 1. Requirements
- XAMPP (Apache + MySQL)
- PHP 8.0+ (bundled in XAMPP)
- Python 3.10+ (for ML scripts)

## 2. Place Project
Ensure project is in:

`C:\xampp\htdocs\Bloodbank`

## 3. Configure Environment
Copy `.env.example` to `.env` if needed and edit:

```env
APP_BASE_URL=/Bloodbank
APP_TIMEZONE=Asia/Kathmandu
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=smartblood_connect
DB_USER=root
DB_PASS=
SMARTBLOOD_PYTHON=python
MAP_PROVIDER=osm
MAP_GOOGLE_API_KEY=
MAP_OSM_TILE_URL=https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png
MAP_DEFAULT_LAT=27.7172
MAP_DEFAULT_LNG=85.3240
MAP_DEFAULT_ZOOM=12
```

If Python is not in PATH, set full path, for example:

```env
SMARTBLOOD_PYTHON=C:\\Python312\\python.exe
```
- If `SMARTBLOOD_PYTHON` is set to `python`, the app auto-prefers local `.venv` python when present.


## 4. Create Database
Open phpMyAdmin or MySQL CLI and run:

1. `database/schema.sql`
2. `database/seed.sql`

This creates all required tables:
- users
- patients
- donors
- admins
- donor_locations
- blood_requests
- blood_inventory
- inventory_transactions
- donor_notifications
- patient_notifications
- donation_appointments
- blood_issuance
- ml_predictions
- audit_logs
- app_settings
- contact_messages

## 5. Start XAMPP
- Start **Apache**
- Start **MySQL**

## 6. Open App
Open browser:

`http://localhost/Bloodbank/`

## 7. Demo Login Credentials
- Admin: `admin@smartblood.test` / `Admin@123`
- Patient: `patient@smartblood.test` / `Patient@123`
- Donor: `donor@smartblood.test` / `Donor@123`

## 8. Optional ML Setup
Install Python dependencies:

```bash
pip install -r ml/requirements.txt
```

Train models:

```bash
python ml/train_model.py
```

This generates:
- `ml/model.pkl`
- `ml/rf_model.pkl`
- `ml/metrics.json`

## 9. Map and Matching Quick Test
1. Register a patient and select location using map or current location.
2. Create a blood request and choose hospital location from map.
3. Register/login donor and set donor location.
4. Check donor notifications/dashboard for nearby requests.
5. Accept request from donor side.
6. Login as admin and open request details.
7. Verify notification status, appointment flow, and issuance updates.

## 10. Security/Production Notes
- Change demo passwords before production.
- Use strong DB credentials.
- Keep `.env` private.
- Run behind HTTPS in production.

## 11. Troubleshooting
- **DB connection failed**: verify `.env` DB values and MySQL service.
- **404 pages**: confirm URL is exactly `http://localhost/Bloodbank/`.
- **ML not running**: set `SMARTBLOOD_PYTHON` to valid Python executable.
- **No demo data**: re-import `database/seed.sql`.
- **Wrong map address after clicking current location**:
  - Ensure browser location permission is granted.
  - Use `Search` or map click as fallback.
  - Hard refresh browser cache (`Ctrl+F5`) after pulling latest JS.
