// ============================================================
// SmartBlood Connect — Admin Module (admin.js)
// ============================================================

function renderAdminPage(page) {
  switch(page) {
    case 'overview':   return renderAdminOverview();
    case 'requests':   return renderAdminRequests();
    case 'inventory':  return renderAdminInventory();
    case 'users':      return renderAdminUsers();
    case 'ml':         return renderAdminML();
    case 'reports':    return renderAdminReports();
    default:           return renderAdminOverview();
  }
}

function renderAdminOverview() {
  const totalDonors = DB.donors.length;
  const eligibleDonors = DB.donors.filter(d => d.eligible).length;
  const totalReqs = DB.requests.length;
  const pendingReqs = DB.requests.filter(r => r.status === 'pending' || r.status === 'waiting_for_donor').length;

  return `
    <div class="page-header">
      <h1 class="page-title">Admin Dashboard</h1>
      <p class="page-subtitle">System overview and blood bank management center.</p>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card-icon red">🩸</div>
        <div class="stat-card-value">${totalReqs}</div>
        <div class="stat-card-label">Total Requests</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon yellow">⏳</div>
        <div class="stat-card-value">${pendingReqs}</div>
        <div class="stat-card-label">Awaiting Action</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon green">👥</div>
        <div class="stat-card-value">${eligibleDonors}/${totalDonors}</div>
        <div class="stat-card-label">Eligible Donors</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon blue">🧪</div>
        <div class="stat-card-value">${DB.inventory.reduce((s,i) => s + i.units, 0)}</div>
        <div class="stat-card-label">Total Units in Stock</div>
      </div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Blood Inventory</span>
          <button class="btn-outline-red" onclick="navigateTo('inventory')">Manage</button>
        </div>
        <div class="inventory-grid" style="padding:20px">
          ${DB.inventory.map(inv => `
            <div class="inv-card ${inv.units < 4 ? 'low' : 'ok'}">
              <div class="inv-blood-type">${inv.blood_type}</div>
              <div class="inv-units">${inv.units}</div>
              <div class="inv-label">units</div>
              <div class="inv-status">
                <span class="badge ${inv.units < 4 ? 'badge-red' : 'badge-green'}">${inv.units < 4 ? 'LOW' : 'OK'}</span>
              </div>
            </div>
          `).join('')}
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <span class="card-title">Pending Requests</span>
          <button class="btn-outline-red" onclick="navigateTo('requests')">View All</button>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead><tr><th>ID</th><th>Blood</th><th>Urgency</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
              ${DB.requests.filter(r => r.status !== 'fulfilled').map(r => `
                <tr>
                  <td class="fw-bold text-red">${r.id}</td>
                  <td><span class="badge badge-blood">${r.blood_type}</span></td>
                  <td><span class="badge ${DB.getUrgencyBadge(r.urgency)}">${r.urgency}</span></td>
                  <td><span class="badge ${DB.getStatusBadge(r.status)}">${r.status.replace(/_/g,' ')}</span></td>
                  <td>
                    ${r.status === 'pending' ? `<button class="btn-green" onclick="approveRequest('${r.id}')">Approve</button>` : '—'}
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card mt-20">
      <div class="card-header"><span class="card-title">Recent Donor Activity</span></div>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead><tr><th>Donor</th><th>Blood Type</th><th>Location</th><th>Donations</th><th>Response Rate</th><th>Eligible</th></tr></thead>
          <tbody>
            ${DB.donors.map(d => `
              <tr>
                <td class="fw-bold">${d.name}</td>
                <td><span class="badge badge-blood">${d.blood_type}</span></td>
                <td>${d.location}</td>
                <td>${d.total_donations}</td>
                <td>
                  <div class="progress-bar-wrap" style="width:80px;display:inline-block">
                    <div class="progress-bar green" style="width:${d.response_rate*100}%"></div>
                  </div>
                  <span class="text-xs text-slate">&nbsp;${Math.round(d.response_rate*100)}%</span>
                </td>
                <td><span class="badge ${d.eligible ? 'badge-green' : 'badge-red'}">${d.eligible ? 'Yes' : 'No'}</span></td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

function renderAdminRequests() {
  return `
    <div class="page-header">
      <h1 class="page-title">Blood Requests</h1>
      <p class="page-subtitle">Manage and process all incoming blood requests.</p>
    </div>
    <div class="card">
      <div class="card-header">
        <span class="card-title">All Requests (${DB.requests.length})</span>
        <div class="flex-row">
          <input type="text" placeholder="Search requests..." style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;outline:none;" />
        </div>
      </div>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead><tr><th>ID</th><th>Patient</th><th>Blood</th><th>Units</th><th>Hospital</th><th>Urgency</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
          <tbody>
            ${DB.requests.map(r => `
              <tr>
                <td class="fw-bold text-red">${r.id}</td>
                <td>${r.patient}</td>
                <td><span class="badge badge-blood">${r.blood_type}</span></td>
                <td>${r.units}</td>
                <td>${r.hospital}</td>
                <td><span class="badge ${DB.getUrgencyBadge(r.urgency)}">${r.urgency}</span></td>
                <td><span class="badge ${DB.getStatusBadge(r.status)}">${r.status.replace(/_/g,' ')}</span></td>
                <td class="text-slate">${r.date}</td>
                <td>
                  <div class="flex-row">
                    ${r.status === 'pending' ? `<button class="btn-green" onclick="approveRequest('${r.id}')">✓</button>` : ''}
                    ${r.status === 'approved' ? `<button class="btn-primary" style="font-size:12px;padding:6px 10px" onclick="issueBlood('${r.id}')">Issue</button>` : ''}
                    ${r.status === 'pending' ? `<button class="btn-outline-red" onclick="rejectRequest('${r.id}')">✗</button>` : ''}
                  </div>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

function renderAdminInventory() {
  return `
    <div class="page-header">
      <h1 class="page-title">Blood Inventory</h1>
      <p class="page-subtitle">Monitor and manage blood unit stock levels.</p>
    </div>

    <div class="card mb-20">
      <div class="card-header"><span class="card-title">Current Stock Overview</span></div>
      <div class="inventory-grid" style="padding:24px">
        ${DB.inventory.map(inv => `
          <div class="inv-card ${inv.units < 4 ? 'low' : 'ok'}">
            <div class="inv-blood-type">${inv.blood_type}</div>
            <div class="inv-units">${inv.units}</div>
            <div class="inv-label">units available</div>
            <div class="inv-status">
              <span class="badge ${inv.units < 4 ? 'badge-red' : 'badge-green'}">${inv.units < 4 ? '⚠ LOW STOCK' : '✓ Adequate'}</span>
            </div>
            <div style="margin-top:12px">
              <div class="progress-bar-wrap"><div class="progress-bar ${inv.units < 4 ? '' : 'green'}" style="width:${Math.min(inv.units*5,100)}%"></div></div>
            </div>
          </div>
        `).join('')}
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title">Add Blood Units</span>
      </div>
      <div class="card-body">
        <div id="invAlert"></div>
        <div class="form-grid">
          <div class="field-group">
            <label>Blood Type</label>
            <select id="inv_type">
              ${DB.bloodGroups.map(g => `<option>${g}</option>`).join('')}
            </select>
          </div>
          <div class="field-group">
            <label>Units to Add</label>
            <input type="number" id="inv_units" min="1" placeholder="e.g. 5" />
          </div>
          <div class="field-group">
            <label>Donor Name</label>
            <input type="text" id="inv_donor" placeholder="Donor name" />
          </div>
          <div class="field-group">
            <label>Expiry Date</label>
            <input type="date" id="inv_expiry" />
          </div>
        </div>
        <div class="form-actions">
          <button class="btn-primary" onclick="addInventory()">Add to Inventory</button>
        </div>
      </div>
    </div>
  `;
}

function renderAdminUsers() {
  return `
    <div class="page-header">
      <h1 class="page-title">User Management</h1>
      <p class="page-subtitle">Manage all patients, donors, and admin accounts.</p>
    </div>
    <div class="card">
      <div class="card-header">
        <span class="card-title">All Users (${DB.users.length})</span>
        <button class="btn-primary" onclick="addUserModal()">+ Add User</button>
      </div>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead><tr><th>ID</th><th>Name</th><th>Role</th><th>Email</th><th>Blood Type</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            ${DB.users.map(u => `
              <tr>
                <td class="fw-bold text-red">${u.id}</td>
                <td>${u.name}</td>
                <td><span class="badge ${u.role === 'patient' ? 'badge-blue' : u.role === 'donor' ? 'badge-green' : 'badge-red'}">${u.role}</span></td>
                <td class="text-slate">${u.email}</td>
                <td><span class="badge badge-blood">${u.blood_type}</span></td>
                <td><span class="badge badge-green">${u.status}</span></td>
                <td>
                  <div class="flex-row">
                    <button class="btn-outline-red" onclick="showAlert('User ${u.id} edited.', 'info')">Edit</button>
                    <button class="btn-secondary" onclick="deleteUser('${u.id}')">Delete</button>
                  </div>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

function renderAdminML() {
  const rf = DB.mlResults.randomForest;
  const lr = DB.mlResults.logisticReg;
  return `
    <div class="page-header">
      <h1 class="page-title">ML Analysis & Model Training</h1>
      <p class="page-subtitle">Machine learning model performance for donor prediction and response analysis.</p>
    </div>

    <div class="grid-2 mb-20">
      <!-- Random Forest -->
      <div class="card">
        <div class="card-header"><span class="card-title">🌲 Random Forest – Donor Response</span></div>
        <div class="card-body">
          <div class="alert alert-info">AUC Score: <strong>${rf.auc}</strong> — Stronger discriminative power</div>
          <table class="data-table">
            <thead><tr><th>Class</th><th>Precision</th><th>Recall</th><th>F1-Score</th></tr></thead>
            <tbody>
              <tr><td>No Response</td><td>${rf.precision_no}</td><td>${rf.recall_no}</td><td>${rf.f1_no}</td></tr>
              <tr><td>Responded</td><td>${rf.precision_yes}</td><td>${rf.recall_yes}</td><td>${rf.f1_yes}</td></tr>
              <tr><td class="fw-bold">Accuracy</td><td colspan="3" class="fw-bold text-red">${rf.accuracy}</td></tr>
            </tbody>
          </table>
          <div class="mt-20">
            <div class="text-sm fw-bold mb-20">Model Accuracy: ${Math.round(rf.accuracy * 100)}%</div>
            <div class="progress-bar-wrap"><div class="progress-bar" style="width:${rf.accuracy*100}%"></div></div>
          </div>
        </div>
      </div>

      <!-- Logistic Regression -->
      <div class="card">
        <div class="card-header"><span class="card-title">📊 Logistic Regression – Donation Outcome</span></div>
        <div class="card-body">
          <div class="alert alert-info">AUC Score: <strong>${lr.auc}</strong> — Moderate discriminative power</div>
          <table class="data-table">
            <thead><tr><th>Class</th><th>Precision</th><th>Recall</th><th>F1-Score</th></tr></thead>
            <tbody>
              <tr><td>No Response</td><td>${lr.precision_no}</td><td>${lr.recall_no}</td><td>${lr.f1_no}</td></tr>
              <tr><td>Responded</td><td>${lr.precision_yes}</td><td>${lr.recall_yes}</td><td>${lr.f1_yes}</td></tr>
              <tr><td class="fw-bold">Accuracy</td><td colspan="3" class="fw-bold text-red">${lr.accuracy}</td></tr>
            </tbody>
          </table>
          <div class="mt-20">
            <div class="text-sm fw-bold mb-20">Model Accuracy: ${Math.round(lr.accuracy * 100)}%</div>
            <div class="progress-bar-wrap"><div class="progress-bar" style="width:${lr.accuracy*100}%"></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Notification funnel stats -->
    <div class="card mb-20">
      <div class="card-header"><span class="card-title">📊 Notification & Response Funnel</span></div>
      <div class="card-body">
        <div class="grid-3">
          <div class="stat-card"><div class="stat-card-icon blue">📨</div><div class="stat-card-value">118</div><div class="stat-card-label">Total Notifications Sent</div></div>
          <div class="stat-card"><div class="stat-card-icon yellow">💬</div><div class="stat-card-value">19 <span style="font-size:16px;color:var(--slate-light)">(16.1%)</span></div><div class="stat-card-label">Total Responded</div></div>
          <div class="stat-card"><div class="stat-card-icon red">🩸</div><div class="stat-card-value">0</div><div class="stat-card-label">Actually Donated</div></div>
        </div>
        <div class="alert alert-danger mt-20">
          ⚠️ <strong>Gap identified:</strong> Zero conversion from response to actual donation — operational improvements needed (appointment reminders, easier booking, prescreening).
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">🔄 Retrain Models</span></div>
      <div class="card-body">
        <p class="text-sm text-slate" style="margin-bottom:16px">Retrain models using updated donor notification and donation records to improve accuracy.</p>
        <div class="flex-row">
          <button class="btn-primary" onclick="retrainModel('Random Forest')">Retrain Random Forest</button>
          <button class="btn-secondary" onclick="retrainModel('Logistic Regression')">Retrain Logistic Regression</button>
        </div>
      </div>
    </div>
  `;
}

function renderAdminReports() {
  return `
    <div class="page-header">
      <h1 class="page-title">Reports & Analytics</h1>
      <p class="page-subtitle">System-wide analytics and summary reports.</p>
    </div>

    <div class="stats-grid mb-20">
      <div class="stat-card"><div class="stat-card-icon red">📋</div><div class="stat-card-value">${DB.requests.length}</div><div class="stat-card-label">Total Requests</div></div>
      <div class="stat-card"><div class="stat-card-icon green">✅</div><div class="stat-card-value">${DB.requests.filter(r=>r.status==='fulfilled').length}</div><div class="stat-card-label">Fulfilled</div></div>
      <div class="stat-card"><div class="stat-card-icon blue">👥</div><div class="stat-card-value">${DB.users.length}</div><div class="stat-card-label">Registered Users</div></div>
      <div class="stat-card"><div class="stat-card-icon yellow">💉</div><div class="stat-card-value">${DB.appointments.filter(a=>a.status==='completed').length}</div><div class="stat-card-label">Completed Donations</div></div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header"><span class="card-title">Requests by Urgency</span></div>
        <div class="card-body">
          ${['Critical','High','Routine'].map(u => {
            const count = DB.requests.filter(r => r.urgency === u).length;
            const pct = Math.round(count / DB.requests.length * 100);
            return `
              <div class="flex-between mb-20">
                <span class="badge ${DB.getUrgencyBadge(u)}">${u}</span>
                <span class="text-sm fw-bold">${count} requests (${pct}%)</span>
              </div>
              <div class="progress-bar-wrap mb-20">
                <div class="progress-bar ${u==='Routine'?'green':''}" style="width:${pct}%"></div>
              </div>
            `;
          }).join('')}
        </div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Requests by Blood Type</span></div>
        <div class="card-body">
          ${DB.bloodGroups.map(g => {
            const count = DB.requests.filter(r => r.blood_type === g).length;
            return `
              <div class="flex-between mb-20">
                <span class="badge badge-blood">${g}</span>
                <span class="text-sm fw-bold">${count} request${count !== 1 ? 's' : ''}</span>
              </div>
            `;
          }).join('')}
        </div>
      </div>
    </div>
  `;
}

function attachAdminEvents(page) {}

// Admin Actions
function approveRequest(id) {
  const r = DB.requests.find(r => r.id === id);
  if (r) { r.status = 'approved'; showAlert(`Request ${id} approved.`); navigateTo(currentPage); }
}

function rejectRequest(id) {
  const r = DB.requests.find(r => r.id === id);
  if (r) { r.status = 'rejected'; showAlert(`Request ${id} rejected.`, 'danger'); navigateTo(currentPage); }
}

function issueBlood(id) {
  const r = DB.requests.find(r => r.id === id);
  const inv = DB.getInventoryByType(r.blood_type);

  if (!inv || inv.units < r.units) {
    showAlert(`No available ${r.blood_type} units to issue.`, 'danger');
    return;
  }
  openModal(`
    <p class="text-sm" style="margin-bottom:16px">Issue <strong>${r.units} units of ${r.blood_type}</strong> to <strong>${r.hospital}</strong> for patient <strong>${r.patient}</strong>?</p>
    <div class="alert alert-info">Available stock: ${inv.units} units of ${r.blood_type}</div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModalDirect()">Cancel</button>
      <button class="btn-primary" onclick="confirmIssue('${id}')">Confirm Issuance</button>
    </div>
  `, 'Issue Blood Units');
}

function confirmIssue(id) {
  const r = DB.requests.find(r => r.id === id);
  const inv = DB.getInventoryByType(r.blood_type);
  inv.units -= r.units;
  r.status = 'fulfilled';
  closeModalDirect();
  showAlert(`✅ ${r.units} units of ${r.blood_type} issued to ${r.hospital}. Request ${id} fulfilled.`);
  navigateTo(currentPage);
}

function addInventory() {
  const type = document.getElementById('inv_type').value;
  const units = parseInt(document.getElementById('inv_units').value);
  const alertEl = document.getElementById('invAlert');

  if (!units || units < 1) {
    alertEl.innerHTML = '<div class="alert alert-danger">Please enter a valid number of units.</div>';
    return;
  }
  const inv = DB.getInventoryByType(type);
  if (inv) inv.units += units;
  alertEl.innerHTML = `<div class="alert alert-success">✅ Added ${units} units of ${type} to inventory.</div>`;
  navigateTo('inventory');
}

function addUserModal() {
  openModal(`
    <div class="field-group"><label>Full Name</label><input type="text" id="nu_name" placeholder="Name"/></div>
    <div class="field-group"><label>Email</label><input type="email" id="nu_email" placeholder="email@example.com"/></div>
    <div class="field-row">
      <div class="field-group"><label>Role</label><select id="nu_role"><option>patient</option><option>donor</option></select></div>
      <div class="field-group"><label>Blood Type</label><select id="nu_blood">${DB.bloodGroups.map(g=>`<option>${g}</option>`).join('')}</select></div>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModalDirect()">Cancel</button>
      <button class="btn-primary" onclick="createUser()">Create User</button>
    </div>
  `, 'Add New User');
}

function createUser() {
  const name = document.getElementById('nu_name').value;
  const email = document.getElementById('nu_email').value;
  const role = document.getElementById('nu_role').value;
  const blood = document.getElementById('nu_blood').value;
  if (!name || !email) return;
  DB.users.push({ id: 'U00' + (DB.users.length+1), name, email, role, blood_type: blood, status: 'active' });
  closeModalDirect();
  showAlert(`User "${name}" created successfully.`);
  navigateTo('users');
}

function deleteUser(id) {
  const idx = DB.users.findIndex(u => u.id === id);
  if (idx > -1) { DB.users.splice(idx, 1); showAlert('User deleted.', 'danger'); navigateTo('users'); }
}

function retrainModel(name) {
  showAlert(`🔄 ${name} model retraining initiated. This may take a few minutes.`, 'info');
}