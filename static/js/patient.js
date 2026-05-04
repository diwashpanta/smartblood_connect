function renderPatientPage(page) {
  if (page === 'overview') return renderPatientOverview();
  if (page === 'requests') return renderNewRequestPage();
  if (page === 'history') return renderPatientHistory();
  if (page === 'notifications') return renderPatientNotifications();
  return renderPatientProfile();
}

function meName() { return (currentUser.username || currentUser.name || '').toLowerCase(); }
function myRequests() { const me = meName(); return (DB.requests || []).filter((r) => (r.patient || '').toLowerCase() === me); }
function p(val,total){return total?Math.round((val/total)*100):0;}

function renderPatientOverview() {
  const mine = myRequests();
  const pending = mine.filter((r) => r.status === 'pending').length;
  const approved = mine.filter((r) => r.status === 'approved').length;
  const fulfilled = mine.filter((r) => r.status === 'fulfilled').length;
  return `
  <div class="page-header"><h1 class="page-title">Patient Dashboard</h1><p class="page-subtitle">Welcome, <strong>${currentUser.name}</strong></p></div>
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-card-icon red">??</div><div class="stat-card-value">${mine.length}</div><div class="stat-card-label">My Requests</div></div>
    <div class="stat-card"><div class="stat-card-icon yellow">?</div><div class="stat-card-value">${pending}</div><div class="stat-card-label">Pending</div></div>
    <div class="stat-card"><div class="stat-card-icon blue">?</div><div class="stat-card-value">${approved}</div><div class="stat-card-label">Approved</div></div>
    <div class="stat-card"><div class="stat-card-icon green">??</div><div class="stat-card-value">${fulfilled}</div><div class="stat-card-label">Fulfilled</div></div>
  </div>
  <div class='grid-2'>
    <div class='card'><div class='card-header'><span class='card-title'>Request Progress</span></div><div class='card-body'>
      <div class='mb-20'><div class='flex-between'><span>Pending</span><strong>${p(pending,mine.length)}%</strong></div><div class='progress-bar-wrap'><div class='progress-bar' style='width:${p(pending,mine.length)}%'></div></div></div>
      <div class='mb-20'><div class='flex-between'><span>Approved</span><strong>${p(approved,mine.length)}%</strong></div><div class='progress-bar-wrap'><div class='progress-bar' style='width:${p(approved,mine.length)}%;background:#3b82f6'></div></div></div>
      <div><div class='flex-between'><span>Fulfilled</span><strong>${p(fulfilled,mine.length)}%</strong></div><div class='progress-bar-wrap'><div class='progress-bar green' style='width:${p(fulfilled,mine.length)}%'></div></div></div>
    </div></div>
    <div class='card'><div class='card-header'><span class='card-title'>Recent Requests</span><button class='btn-primary' onclick="navigateTo('requests')">+ New Request</button></div><div style='overflow-x:auto'><table class='data-table'><thead><tr><th>ID</th><th>Blood</th><th>Units</th><th>Status</th></tr></thead><tbody>${(mine.slice(0,8).map((r)=>`<tr><td>${r.id}</td><td><span class='badge badge-blood'>${r.blood_type}</span></td><td>${r.units}</td><td><span class='badge ${DB.getStatusBadge ? DB.getStatusBadge(r.status) : 'badge-gray'}'>${r.status}</span></td></tr>`).join('')) || '<tr><td colspan="4">No requests yet.</td></tr>'}</tbody></table></div></div>
  </div>`;
}

function renderNewRequestPage() { return `<div class="page-header"><h1 class="page-title">New Blood Request</h1></div><div class="card"><div class="card-body"><div id="requestAlert"></div><form onsubmit="submitBloodRequest(event)"><div class="form-grid"><div class="field-group"><label>Blood Group</label><select id="req_blood" required>${DB.bloodGroups.map((g)=>`<option value="${g}">${g}</option>`).join('')}</select></div><div class="field-group"><label>Units</label><input id="req_units" type="number" min="1" required></div><div class="field-group form-full"><label>Hospital</label><input id="req_hospital" required></div><div class="field-group"><label>Location</label><input id="req_location" required></div><div class="field-group"><label>Urgency</label><select id="req_urgency"><option>routine</option><option>high</option><option>critical</option></select></div></div><div class="form-actions"><button class="btn-primary" type="submit">Submit Request</button></div></form></div></div>`; }
async function submitBloodRequest(e){e.preventDefault();const payload={blood_group:document.getElementById('req_blood').value,units_needed:Number(document.getElementById('req_units').value),hospital_name:document.getElementById('req_hospital').value.trim(),hospital_address:document.getElementById('req_location').value.trim(),urgency:document.getElementById('req_urgency').value,latitude:27.7172,longitude:85.3240};const alertEl=document.getElementById('requestAlert');try{const res=await fetch('/app/api/requests/',{method:'POST',headers:{'Content-Type':'application/json','X-CSRFToken':getCookie('csrftoken')},credentials:'same-origin',body:JSON.stringify(payload)});if(!res.ok){let msg='Request save failed. Please check fields.';try{const j=await res.json();if(j.detail) msg=j.detail;else{const k=Object.keys(j||{});if(k.length) msg=`${k[0]}: ${Array.isArray(j[k[0]])?j[k[0]][0]:j[k[0]]}`;}}catch(_x){}throw new Error(msg);}await hydrateFromServer();alertEl.innerHTML='<div class="alert alert-success">Request submitted successfully.</div>';navigateTo('history')}catch(err){alertEl.innerHTML=`<div class="alert alert-danger">${err.message||'Request save failed. Please check fields.'}</div>`}}
function renderPatientHistory(){const mine=myRequests();return `<div class="page-header"><h1 class="page-title">Request History</h1></div><div class="card"><div style="overflow-x:auto"><table class="data-table"><thead><tr><th>ID</th><th>Blood</th><th>Units</th><th>Hospital</th><th>Status</th><th>Date</th></tr></thead><tbody>${(mine.map((r)=>`<tr><td>${r.id}</td><td>${r.blood_type}</td><td>${r.units}</td><td>${r.hospital}</td><td>${r.status}</td><td>${r.date}</td></tr>`).join(''))||'<tr><td colspan="6">No history yet.</td></tr>'}</tbody></table></div></div>`}
function renderPatientNotifications(){return `<div class="page-header"><h1 class="page-title">Notifications</h1></div><div class="card"><div class="card-body">No notifications.</div></div>`}
function renderPatientProfile(){return `<div class="page-header"><h1 class="page-title">My Profile</h1></div><div class="card"><div class="card-body">${currentUser.name}</div></div>`}
function attachPatientEvents(_page){}
function getCookie(name){const v=`; ${document.cookie}`;const p=v.split(`; ${name}=`);if(p.length===2) return p.pop().split(';').shift();}
