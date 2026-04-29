// ============================================================
// SmartBlood Connect — Patient Module (patient.js)
// ============================================================

function renderPatientPage(page) {
  switch(page) {
    case 'overview':    return renderPatientOverview();
    case 'requests':    return renderNewRequestPage();
    case 'history':     return renderPatientHistory();
    case 'notifications': return renderPatientNotifications();
    case 'profile':     return renderPatientProfile();
    default:            return renderPatientOverview();
  }
}

function renderPatientOverview() {
  const myRequests = DB.requests.filter(r => r.patient === currentUser.name || true).slice(0, 4);
  const pending = DB.requests.filter(r => r.status === 'pending').length;
  const fulfilled = DB.requests.filter(r => r.status === 'fulfilled').length;
  const waiting = DB.requests.filter(r => r.status === 'waiting_for_donor').length;

  return `
    <div class="page-header">
      <h1 class="page-title">Patient Dashboard</h1>
      <p class="page-subtitle">Welcome back, <strong>${currentUser.name}</strong> — Blood Group: <span class="badge badge-blood">${currentUser.bloodGroup || 'A+'}</span></p>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card-icon red">🩸</div>
        <div class="stat-card-value">${DB.requests.length}</div>
        <div class="stat-card-label">Total Requests</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon yellow">⏳</div>
        <div class="stat-card-value">${pending}</div>
        <div class="stat-card-label">Pending</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon green">✅</div>
        <div class="stat-card-value">${fulfilled}</div>
        <div class="stat-card-label">Fulfilled</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon blue">🔍</div>
        <div class="stat-card-value">${waiting}</div>
        <div class="stat-card-label">Awaiting Donor</div>
      </div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Recent Requests</span>
          <button class="btn-primary" onclick="navigateTo('requests')">+ New Request</button>
        </div>
        <div class="card-body" style="padding:0">
          <table class="data-table">
            <thead><tr><th>ID</th><th>Blood</th><th>Units</th><th>Urgency</th><th>Status</th></tr></thead>
            <tbody>
              ${myRequests.map(r => `
                <tr>
                  <td class="fw-bold text-red">${r.id}</td>
                  <td><span class="badge badge-blood">${r.blood_type}</span></td>
                  <td>${r.units}</td>
                  <td><span class="badge ${DB.getUrgencyBadge(r.urgency)}">${r.urgency}</span></td>
                  <td><span class="badge ${DB.getStatusBadge(r.status)}">${r.status.replace(/_/g,' ')}</span></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><span class="card-title">Blood Inventory Status</span></div>
        <div class="card-body">
          ${DB.inventory.map(inv => `
            <div class="flex-between mb-20">
              <div class="flex-row">
                <span class="badge badge-blood">${inv.blood_type}</span>
                <span class="text-sm">${inv.units} units available</span>
              </div>
              <span class="text-sm fw-bold ${inv.units < 4 ? 'text-red' : 'text-green'}">${inv.units < 4 ? '⚠ Low' : '✓ OK'}</span>
            </div>
            <div class="progress-bar-wrap mb-20">
              <div class="progress-bar ${inv.units < 4 ? '' : 'green'}" style="width:${Math.min(inv.units*5, 100)}%"></div>
            </div>
          `).join('')}
        </div>
      </div>
    </div>
  `;
}

function renderNewRequestPage() {
  return `
    <div class="page-header">
      <h1 class="page-title">New Blood Request</h1>
      <p class="page-subtitle">Submit a request for blood — our system will match you with available inventory or nearby donors.</p>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header"><span class="card-title">Request Details</span></div>
        <div class="card-body">
          <div id="requestAlert"></div>
          <form onsubmit="submitBloodRequest(event)">
            <div class="form-grid">
              <div class="field-group">
                <label>Blood Group Required</label>
                <select id="req_blood" required>
                  <option value="">Select Blood Group</option>
                  ${DB.bloodGroups.map(g => `<option>${g}</option>`).join('')}
                </select>
              </div>
              <div class="field-group">
                <label>Units Required</label>
                <input type="number" id="req_units" min="1" max="10" placeholder="e.g. 2" required/>
              </div>
              <div class="field-group form-full">
                <label>Hospital Name</label>
                <input type="text" id="req_hospital" placeholder="City Medical Center" required/>
              </div>
              <div class="field-group">
                <label>Location / City</label>
                <input type="text" id="req_location" placeholder="Kathmandu" required/>
              </div>
              <div class="field-group">
                <label>Urgency Level</label>
                <select id="req_urgency" required>
                  <option value="">Select Urgency</option>
                  <option>Routine</option>
                  <option>High</option>
                  <option>Critical</option>
                </select>
              </div>
              <div class="field-group form-full">
                <label>Additional Notes</label>
                <input type="text" id="req_notes" placeholder="Any additional information..."/>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-primary">Submit Request</button>
              <button type="button" class="btn-secondary" onclick="navigateTo('overview')">Cancel</button>
            </div>
          </form>
        </div>
      </div>

      <div>
        <div class="card mb-20">
          <div class="card-header"><span class="card-title">How It Works</span></div>
          <div class="card-body">
            <div class="notif-item">
              <div class="notif-dot" style="background:#264653"></div>
              <div class="notif-text">
                <div class="notif-title">1. Submit Request</div>
                <div class="notif-sub">Fill in your blood group, units needed, hospital, and urgency level.</div>
              </div>
            </div>
            <div class="notif-item">
              <div class="notif-dot" style="background:#e63946"></div>
              <div class="notif-text">
                <div class="notif-title">2. Inventory Check</div>
                <div class="notif-sub">System automatically checks available blood inventory matching your group.</div>
              </div>
            </div>
            <div class="notif-item">
              <div class="notif-dot" style="background:#2d6a4f"></div>
              <div class="notif-text">
                <div class="notif-title">3. Donor Matching (ML)</div>
                <div class="notif-sub">If no stock, Random Forest & Logistic Regression identify nearest eligible donors.</div>
              </div>
            </div>
            <div class="notif-item">
              <div class="notif-dot" style="background:#f4a261"></div>
              <div class="notif-text">
                <div class="notif-title">4. Admin Approval</div>
                <div class="notif-sub">Admin reviews, assigns units, and finalizes the issuance to your hospital.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">📍 Nearest Donors (ML Matched)</span></div>
          <div class="card-body" style="padding:0">
            <div class="map-mock">
              <span class="map-label">Kathmandu Region</span>
              <span class="map-pin hospital">🏥</span>
              <span class="map-pin donor1">🩸</span>
              <span class="map-pin donor2">🩸</span>
              <span class="map-pin donor3">🩸</span>
            </div>
            ${DB.donors.filter(d=>d.eligible).slice(0,3).map(d => `
              <div style="padding:12px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
                <div>
                  <div class="fw-bold text-sm">${d.name}</div>
                  <div class="text-xs text-slate">${d.location} · ${d.blood_type}</div>
                </div>
                <div style="text-align:right">
                  <div class="text-sm fw-bold text-red">${DB.predictDonorResponse(d)}% match</div>
                  <div class="text-xs text-slate">${(Math.random()*4+1).toFixed(1)} km away</div>
                </div>
              </div>
            `).join('')}
          </div>
        </div>
      </div>
    </div>
  `;
}

function renderPatientHistory() {
  return `
    <div class="page-header">
      <h1 class="page-title">Request History</h1>
      <p class="page-subtitle">All your past and current blood requests.</p>
    </div>
    <div class="card">
      <div class="card-header">
        <span class="card-title">All Requests</span>
        <button class="btn-primary" onclick="navigateTo('requests')">+ New Request</button>
      </div>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead><tr><th>Request ID</th><th>Blood Type</th><th>Units</th><th>Hospital</th><th>Location</th><th>Urgency</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            ${DB.requests.map(r => `
              <tr>
                <td class="fw-bold text-red">${r.id}</td>
                <td><span class="badge badge-blood">${r.blood_type}</span></td>
                <td>${r.units}</td>
                <td>${r.hospital}</td>
                <td>${r.location}</td>
                <td><span class="badge ${DB.getUrgencyBadge(r.urgency)}">${r.urgency}</span></td>
                <td><span class="badge ${DB.getStatusBadge(r.status)}">${r.status.replace(/_/g,' ')}</span></td>
                <td class="text-slate">${r.date}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

function renderPatientNotifications() {
  return `
    <div class="page-header">
      <h1 class="page-title">Notifications</h1>
      <p class="page-subtitle">Updates on your blood requests and donor matches.</p>
    </div>
    <div class="card">
      <div class="card-body">
        <div class="notif-item">
          <div class="notif-dot"></div>
          <div class="notif-text">
            <div class="notif-title">Donor matched for Request BR003</div>
            <div class="notif-sub">Kiran Budhathoki (O+) has agreed to donate. Appointment scheduled at Teaching Hospital on July 21.</div>
          </div>
          <span class="notif-time">2h ago</span>
        </div>
        <div class="notif-item">
          <div class="notif-dot gray"></div>
          <div class="notif-text">
            <div class="notif-title">Request BR001 fulfilled</div>
            <div class="notif-sub">Your request for 2 units of A+ has been fulfilled by City Medical Center inventory.</div>
          </div>
          <span class="notif-time">3d ago</span>
        </div>
        <div class="notif-item">
          <div class="notif-dot gray"></div>
          <div class="notif-text">
            <div class="notif-title">Admin approved request BR004</div>
            <div class="notif-sub">Your AB+ request has been approved and units are being reserved.</div>
          </div>
          <span class="notif-time">1d ago</span>
        </div>
      </div>
    </div>
  `;
}

function renderPatientProfile() {
  return `
    <div class="page-header">
      <h1 class="page-title">My Profile</h1>
      <p class="page-subtitle">Manage your personal information and medical details.</p>
    </div>
    <div class="grid-2">
      <div class="card">
        <div class="card-header"><span class="card-title">Personal Information</span></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="field-group">
              <label>Full Name</label>
              <input type="text" value="${currentUser.name}" />
            </div>
            <div class="field-group">
              <label>Email</label>
              <input type="email" value="${currentUser.email}" />
            </div>
            <div class="field-group">
              <label>Blood Group</label>
              <select>
                ${DB.bloodGroups.map(g => `<option ${g === (currentUser.bloodGroup||'A+') ? 'selected' : ''}>${g}</option>`).join('')}
              </select>
            </div>
            <div class="field-group">
              <label>Phone</label>
              <input type="text" placeholder="98XXXXXXXX" />
            </div>
            <div class="field-group form-full">
              <label>Address</label>
              <input type="text" placeholder="Kathmandu, Nepal" />
            </div>
          </div>
          <div class="form-actions">
            <button class="btn-primary" onclick="showAlert('Profile updated successfully!')">Save Changes</button>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Activity Summary</span></div>
        <div class="card-body">
          <div class="flex-between mb-20">
            <span class="text-sm">Total Requests Made</span>
            <span class="fw-bold">${DB.requests.length}</span>
          </div>
          <div class="flex-between mb-20">
            <span class="text-sm">Fulfilled Requests</span>
            <span class="fw-bold text-green">${DB.requests.filter(r=>r.status==='fulfilled').length}</span>
          </div>
          <div class="flex-between mb-20">
            <span class="text-sm">Pending Requests</span>
            <span class="fw-bold text-red">${DB.requests.filter(r=>r.status==='pending').length}</span>
          </div>
          <div class="flex-between">
            <span class="text-sm">Member Since</span>
            <span class="fw-bold">2025</span>
          </div>
        </div>
      </div>
    </div>
  `;
}

function attachPatientEvents(page) {
  // Handled inline via onsubmit
}

function submitBloodRequest(e) {
  e.preventDefault();
  const blood = document.getElementById('req_blood').value;
  const units = parseInt(document.getElementById('req_units').value);
  const hospital = document.getElementById('req_hospital').value.trim();
  const location = document.getElementById('req_location').value.trim();
  const urgency = document.getElementById('req_urgency').value;

  const alertEl = document.getElementById('requestAlert');
  if (!blood || !units || !hospital || !location || !urgency) {
    alertEl.innerHTML = '<div class="alert alert-danger">Please fill in all required fields.</div>';
    return;
  }
  if (units < 1) {
    alertEl.innerHTML = '<div class="alert alert-danger">Units must be a positive integer.</div>';
    return;
  }

  const inv = DB.getInventoryByType(blood);
  const newId = 'BR00' + (DB.requests.length + 1);
  let status = 'pending';

  if (inv && inv.units >= units) {
    inv.units -= units;
    status = 'fulfilled';
  } else {
    status = 'waiting_for_donor';
  }

  DB.requests.unshift({ id: newId, patient: currentUser.name, blood_type: blood, units, hospital, urgency, status, date: new Date().toISOString().slice(0,10), location });

  const msg = status === 'fulfilled'
    ? `✅ Request ${newId} created and fulfilled from inventory!`
    : `🔔 Request ${newId} submitted — searching for nearest eligible donors...`;

  alertEl.innerHTML = `<div class="alert alert-${status === 'fulfilled' ? 'success' : 'info'}">${msg}</div>`;
  e.target.reset();
}