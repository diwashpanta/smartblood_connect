// ============================================================
// SmartBlood Connect — Donor Module (donor.js)
// ============================================================

function renderDonorPage(page) {
  switch(page) {
    case 'overview':      return renderDonorOverview();
    case 'nearby':        return renderNearbyRequests();
    case 'notifications': return renderDonorNotifications();
    case 'appointments':  return renderDonorAppointments();
    case 'history':       return renderDonorHistory();
    case 'profile':       return renderDonorProfile();
    default:              return renderDonorOverview();
  }
}

function getDonorData() {
  return DB.donors.find(d => d.name === currentUser.name) || DB.donors[0];
}

function renderDonorOverview() {
  const donor = getDonorData();
  const myNotifs = DB.notifications.filter(n => n.donor_id === donor.id);
  const myAppts = DB.appointments.filter(a => a.donor === donor.name);
  const pred = DB.predictDonorResponse(donor);

  return `
    <div class="page-header">
      <h1 class="page-title">Donor Dashboard</h1>
      <p class="page-subtitle">Welcome, <strong>${currentUser.name}</strong> — Blood Group: <span class="badge badge-blood">${donor.blood_type}</span> &nbsp; ${donor.eligible ? '<span class="badge badge-green">✓ Eligible to Donate</span>' : '<span class="badge badge-red">✗ Not Eligible</span>'}</p>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card-icon red">💉</div>
        <div class="stat-card-value">${donor.total_donations}</div>
        <div class="stat-card-label">Total Donations</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon yellow">🔔</div>
        <div class="stat-card-value">${myNotifs.length}</div>
        <div class="stat-card-label">Notifications</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon green">📅</div>
        <div class="stat-card-value">${myAppts.length}</div>
        <div class="stat-card-label">Appointments</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon blue">📈</div>
        <div class="stat-card-value">${Math.round(donor.response_rate * 100)}%</div>
        <div class="stat-card-label">Response Rate</div>
      </div>
    </div>

    <div class="grid-2">
      <!-- ML Prediction Panel -->
      <div class="ml-prediction">
        <div class="ml-title">🤖 ML Donor Prediction</div>
        <div class="ml-desc">Random Forest model predicting your donation likelihood based on your profile features.</div>
        <div class="ml-score">
          <div class="ml-score-value">${pred}%</div>
          <div class="ml-score-label">Estimated donation<br/>probability</div>
        </div>
        <div class="ml-bar-wrap">
          <div class="ml-bar" style="width:${pred}%"></div>
        </div>
        <div class="ml-tags">
          <span class="ml-tag">Distance: ${(Math.random()*3+1).toFixed(1)} km</span>
          <span class="ml-tag">Last donation: ${donor.last_donation}</span>
          <span class="ml-tag">Response rate: ${Math.round(donor.response_rate*100)}%</span>
          <span class="ml-tag">Age: ${donor.age}</span>
        </div>
      </div>

      <!-- Pending Notifications -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Pending Notifications</span>
          <button class="btn-outline-red" onclick="navigateTo('notifications')">View All</button>
        </div>
        <div class="card-body">
          ${DB.notifications.filter(n => !n.responded).slice(0,3).map(n => `
            <div class="notif-item">
              <div class="notif-dot"></div>
              <div class="notif-text">
                <div class="notif-title"><span class="badge ${DB.getUrgencyBadge(n.urgency)}">${n.urgency}</span> ${n.message}</div>
                <div class="notif-sub">${n.date}</div>
                <div class="notif-actions">
                  <button class="btn-green" onclick="respondNotification('${n.id}', true)">✓ Willing to Donate</button>
                  <button class="btn-secondary" onclick="respondNotification('${n.id}', false)">✗ Not Available</button>
                </div>
              </div>
            </div>
          `).join('') || '<p class="text-slate text-sm">No pending notifications.</p>'}
        </div>
      </div>
    </div>

    <div class="card mt-20">
      <div class="card-header"><span class="card-title">Upcoming Appointments</span></div>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead><tr><th>ID</th><th>Hospital</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
          <tbody>
            ${DB.appointments.map(a => `
              <tr>
                <td class="fw-bold text-red">${a.id}</td>
                <td>${a.hospital}</td>
                <td>${a.date}</td>
                <td>${a.time}</td>
                <td><span class="badge ${DB.getStatusBadge(a.status)}">${a.status}</span></td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

function renderNearbyRequests() {
  return `
    <div class="page-header">
      <h1 class="page-title">Nearby Blood Requests</h1>
      <p class="page-subtitle">Blood requests near your location, sorted by urgency and distance.</p>
    </div>

    <div class="card mb-20">
      <div class="card-body" style="padding:0">
        <div class="map-mock" style="height:260px">
          <span class="map-label">Your Location – Kathmandu</span>
          <span class="map-pin hospital" style="top:50%;left:50%">📍</span>
          <span class="map-pin donor1" style="top:30%;left:35%">🏥</span>
          <span class="map-pin donor2" style="top:65%;left:60%">🏥</span>
          <span class="map-pin donor3" style="top:25%;left:70%">🏥</span>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Open Requests Near You</span></div>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead><tr><th>ID</th><th>Blood Type</th><th>Units</th><th>Hospital</th><th>Urgency</th><th>Distance</th><th>ML Match</th><th>Action</th></tr></thead>
          <tbody>
            ${DB.requests.filter(r => r.status !== 'fulfilled').map(r => {
              const dist = (Math.random()*8+0.5).toFixed(1);
              const score = Math.round(65 + Math.random()*30);
              return `
                <tr>
                  <td class="fw-bold text-red">${r.id}</td>
                  <td><span class="badge badge-blood">${r.blood_type}</span></td>
                  <td>${r.units}</td>
                  <td>${r.hospital}</td>
                  <td><span class="badge ${DB.getUrgencyBadge(r.urgency)}">${r.urgency}</span></td>
                  <td>${dist} km</td>
                  <td><span class="text-red fw-bold">${score}%</span></td>
                  <td><button class="btn-outline-red" onclick="volunteerForRequest('${r.id}')">Volunteer</button></td>
                </tr>
              `;
            }).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

function renderDonorNotifications() {
  return `
    <div class="page-header">
      <h1 class="page-title">My Notifications</h1>
      <p class="page-subtitle">Blood request alerts sent to you based on your blood type and location.</p>
    </div>
    <div class="card">
      <div class="card-body">
        ${DB.notifications.map(n => `
          <div class="notif-item">
            <div class="notif-dot ${n.responded ? 'gray' : ''}"></div>
            <div class="notif-text">
              <div class="notif-title">
                <span class="badge ${DB.getUrgencyBadge(n.urgency)}">${n.urgency}</span>
                &nbsp;Request ${n.request_id}
              </div>
              <div class="notif-sub">${n.message}</div>
              <div class="text-xs text-slate mt-12">${n.date} &nbsp;·&nbsp;
                ${n.responded ? (n.donated ? '<span class="text-green">✓ Accepted – Donated</span>' : '<span class="text-slate">Declined</span>') : '<span class="text-red">Pending response</span>'}
              </div>
              ${!n.responded ? `
                <div class="notif-actions">
                  <button class="btn-green" onclick="respondNotification('${n.id}', true)">✓ Willing to Donate</button>
                  <button class="btn-secondary" onclick="respondNotification('${n.id}', false)">✗ Not Available</button>
                </div>
              ` : ''}
            </div>
            <span class="notif-time">${n.date}</span>
          </div>
        `).join('')}
      </div>
    </div>
  `;
}

function renderDonorAppointments() {
  return `
    <div class="page-header">
      <h1 class="page-title">Donation Appointments</h1>
      <p class="page-subtitle">Scheduled and completed donation appointments.</p>
    </div>
    <div class="card">
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead><tr><th>ID</th><th>Request</th><th>Hospital</th><th>Date</th><th>Time</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            ${DB.appointments.map(a => `
              <tr>
                <td class="fw-bold text-red">${a.id}</td>
                <td>${a.request_id}</td>
                <td>${a.hospital}</td>
                <td>${a.date}</td>
                <td>${a.time}</td>
                <td><span class="badge ${DB.getStatusBadge(a.status)}">${a.status}</span></td>
                <td>${a.status === 'scheduled' ? `<button class="btn-green" onclick="markDonated('${a.id}')">Mark Donated</button>` : '—'}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

function renderDonorHistory() {
  const donor = getDonorData();
  return `
    <div class="page-header">
      <h1 class="page-title">Donation History</h1>
      <p class="page-subtitle">Your complete record of blood donations.</p>
    </div>
    <div class="grid-2 mb-20">
      <div class="stat-card">
        <div class="stat-card-icon red">🩸</div>
        <div class="stat-card-value">${donor.total_donations}</div>
        <div class="stat-card-label">Total Donations</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon green">❤️</div>
        <div class="stat-card-value">${donor.total_donations * 3}</div>
        <div class="stat-card-label">Lives Potentially Saved</div>
      </div>
    </div>
    <div class="card">
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead><tr><th>Appointment</th><th>Hospital</th><th>Date</th><th>Blood Type</th><th>Status</th></tr></thead>
          <tbody>
            ${DB.appointments.map(a => `
              <tr>
                <td class="fw-bold text-red">${a.id}</td>
                <td>${a.hospital}</td>
                <td>${a.date}</td>
                <td><span class="badge badge-blood">${donor.blood_type}</span></td>
                <td><span class="badge ${DB.getStatusBadge(a.status)}">${a.status}</span></td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

function renderDonorProfile() {
  const donor = getDonorData();
  return `
    <div class="page-header">
      <h1 class="page-title">Donor Profile</h1>
      <p class="page-subtitle">Keep your information updated to stay eligible for donation.</p>
    </div>
    <div class="grid-2">
      <div class="card">
        <div class="card-header"><span class="card-title">Personal & Medical Details</span></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="field-group">
              <label>Full Name</label>
              <input type="text" value="${currentUser.name}" />
            </div>
            <div class="field-group">
              <label>Blood Group</label>
              <select>
                ${DB.bloodGroups.map(g => `<option ${g === donor.blood_type ? 'selected' : ''}>${g}</option>`).join('')}
              </select>
            </div>
            <div class="field-group">
              <label>Age</label>
              <input type="number" value="${donor.age}" />
            </div>
            <div class="field-group">
              <label>Weight (kg)</label>
              <input type="number" value="${donor.weight}" />
            </div>
            <div class="field-group">
              <label>Last Donation Date</label>
              <input type="date" value="${donor.last_donation}" />
            </div>
            <div class="field-group">
              <label>Location</label>
              <input type="text" value="${donor.location}" />
            </div>
          </div>
          <div class="form-actions">
            <button class="btn-primary" onclick="showAlert('Profile updated successfully!')">Save Changes</button>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Eligibility Status</span></div>
        <div class="card-body">
          <div class="alert ${donor.eligible ? 'alert-success' : 'alert-danger'}">
            ${donor.eligible ? '✅ You are currently eligible to donate blood.' : '⚠️ You are not currently eligible to donate blood.'}
          </div>
          <div class="flex-between mb-20 mt-20">
            <span class="text-sm">Minimum Weight (50kg)</span>
            <span class="fw-bold ${donor.weight >= 50 ? 'text-green' : 'text-red'}">${donor.weight >= 50 ? '✓ Pass' : '✗ Fail'}</span>
          </div>
          <div class="flex-between mb-20">
            <span class="text-sm">Age (18–65)</span>
            <span class="fw-bold ${donor.age >= 18 && donor.age <= 65 ? 'text-green' : 'text-red'}">${donor.age >= 18 && donor.age <= 65 ? '✓ Pass' : '✗ Fail'}</span>
          </div>
          <div class="flex-between">
            <span class="text-sm">Last Donation ≥ 56 days ago</span>
            <span class="fw-bold text-green">✓ Pass</span>
          </div>
        </div>
      </div>
    </div>
  `;
}

function attachDonorEvents(page) {}

function respondNotification(id, willing) {
  const n = DB.notifications.find(n => n.id === id);
  if (n) {
    n.responded = true;
    n.donated = willing;
    if (willing) {
      DB.appointments.push({
        id: 'A00' + (DB.appointments.length + 1),
        donor: currentUser.name,
        request_id: n.request_id,
        date: new Date(Date.now() + 86400000).toISOString().slice(0,10),
        time: '10:00',
        hospital: DB.requests.find(r => r.id === n.request_id)?.hospital || 'Hospital',
        status: 'scheduled'
      });
      showAlert('✅ Appointment scheduled! Thank you for volunteering to donate.');
    } else {
      showAlert('Response recorded. Thank you for letting us know.', 'info');
    }
    navigateTo(currentPage);
  }
}

function volunteerForRequest(requestId) {
  const req = DB.requests.find(r => r.id === requestId);
  openModal(`
    <p class="text-sm" style="margin-bottom:16px">You are volunteering to donate <span class="badge badge-blood">${req.blood_type}</span> for <strong>${req.hospital}</strong>.</p>
    <div class="field-group">
      <label>Preferred Date</label>
      <input type="date" id="vol_date" value="${new Date(Date.now()+86400000).toISOString().slice(0,10)}" />
    </div>
    <div class="field-group">
      <label>Preferred Time</label>
      <select id="vol_time">
        <option>09:00</option><option>10:00</option><option>11:00</option>
        <option>14:00</option><option>15:00</option><option>16:00</option>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModalDirect()">Cancel</button>
      <button class="btn-green" onclick="confirmVolunteer('${requestId}')">Confirm Donation</button>
    </div>
  `, 'Volunteer to Donate');
}

function confirmVolunteer(requestId) {
  const req = DB.requests.find(r => r.id === requestId);
  const date = document.getElementById('vol_date').value;
  const time = document.getElementById('vol_time').value;
  DB.appointments.push({
    id: 'A00' + (DB.appointments.length + 1),
    donor: currentUser.name, request_id: requestId,
    date, time, hospital: req.hospital, status: 'scheduled'
  });
  closeModalDirect();
  showAlert(`✅ Appointment scheduled at ${req.hospital} on ${date} at ${time}!`);
}

function markDonated(apptId) {
  const a = DB.appointments.find(a => a.id === apptId);
  if (a) { a.status = 'completed'; showAlert('🎉 Donation recorded! Thank you for saving lives.'); navigateTo('appointments'); }
}