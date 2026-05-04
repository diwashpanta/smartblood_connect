function renderDonorPage(page) {
  if (page === 'overview') return renderDonorOverview();
  if (page === 'nearby') return renderNearbyRequests();
  if (page === 'notifications') return renderDonorNotifications();
  if (page === 'appointments') return renderDonorAppointments();
  if (page === 'history') return renderDonorHistory();
  return renderDonorProfile();
}
function donorName(){return (currentUser.username||currentUser.name||'').toLowerCase();}
function myNotifications(){const me=donorName();return (DB.notifications||[]).filter((n)=>(n.donor||'').toLowerCase()===me);}
function myAppointments(){return (DB.appointments||[]).filter((a)=>(a.donor||'').toLowerCase()===donorName());}
function p(val,total){return total?Math.round((val/total)*100):0;}

function renderDonorOverview(){
  const notes=myNotifications(); const appts=myAppointments();
  const responded=notes.filter((n)=>n.responded).length; const willing=notes.filter((n)=>n.donated).length; const completed=appts.filter((a)=>a.status==='completed').length;
  return `<div class='page-header'><h1 class='page-title'>Donor Dashboard</h1></div>
  <div class='stats-grid'>
    <div class='stat-card'><div class='stat-card-icon red'>??</div><div class='stat-card-value'>${notes.length}</div><div class='stat-card-label'>Notifications</div></div>
    <div class='stat-card'><div class='stat-card-icon blue'>??</div><div class='stat-card-value'>${responded}</div><div class='stat-card-label'>Responded</div></div>
    <div class='stat-card'><div class='stat-card-icon green'>??</div><div class='stat-card-value'>${willing}</div><div class='stat-card-label'>Accepted</div></div>
    <div class='stat-card'><div class='stat-card-icon yellow'>??</div><div class='stat-card-value'>${appts.length}</div><div class='stat-card-label'>Appointments</div></div>
  </div>
  <div class='grid-2'>
    <div class='card'><div class='card-header'><span class='card-title'>Donation Funnel</span></div><div class='card-body'>
      <div class='mb-20'><div class='flex-between'><span>Responded</span><strong>${p(responded,notes.length)}%</strong></div><div class='progress-bar-wrap'><div class='progress-bar' style='width:${p(responded,notes.length)}%;background:#3b82f6'></div></div></div>
      <div class='mb-20'><div class='flex-between'><span>Accepted</span><strong>${p(willing,notes.length)}%</strong></div><div class='progress-bar-wrap'><div class='progress-bar green' style='width:${p(willing,notes.length)}%'></div></div></div>
      <div><div class='flex-between'><span>Completed</span><strong>${p(completed,appts.length)}%</strong></div><div class='progress-bar-wrap'><div class='progress-bar' style='width:${p(completed,appts.length)}%;background:#a855f7'></div></div></div>
    </div></div>
    <div class='card'><div class='card-header'><span class='card-title'>Pending Notifications</span></div><div style='overflow-x:auto'><table class='data-table'><thead><tr><th>ID</th><th>Request</th><th>Status</th><th>Action</th></tr></thead><tbody>${notes.map((n)=>`<tr><td>${n.id}</td><td>${n.request_id}</td><td>${n.responded ? (n.donated ? 'Accepted':'Declined') : 'Pending'}</td><td>${n.responded ? '-' : `<button class="btn-green" onclick="respondNotification('${n.db_id}', true)">Accept</button> <button class="btn-secondary" onclick="respondNotification('${n.db_id}', false)">Decline</button>`}</td></tr>`).join('') || '<tr><td colspan="4">No notifications yet.</td></tr>'}</tbody></table></div></div>
  </div>`;
}
function renderNearbyRequests(){const open=(DB.requests||[]).filter((r)=>r.status!=='fulfilled');return `<div class='page-header'><h1 class='page-title'>Nearby Requests</h1></div><div class='card'><div style='overflow-x:auto'><table class='data-table'><thead><tr><th>ID</th><th>Blood</th><th>Units</th><th>Hospital</th><th>Status</th></tr></thead><tbody>${open.map((r)=>`<tr><td>${r.id}</td><td>${r.blood_type}</td><td>${r.units}</td><td>${r.hospital}</td><td>${r.status}</td></tr>`).join('') || '<tr><td colspan="5">No open requests.</td></tr>'}</tbody></table></div></div>`;}
function renderDonorNotifications(){return renderDonorOverview();}
async function respondNotification(dbId,willing){try{const res=await fetch(`/app/api/notifications/${dbId}/respond/`,{method:'POST',headers:{'Content-Type':'application/json','X-CSRFToken':getCookie('csrftoken')},credentials:'same-origin',body:JSON.stringify({willing})});if(!res.ok) throw new Error('respond failed');await hydrateFromServer();navigateTo('overview')}catch(_e){showAlert('Failed to submit response','danger')}}
function renderDonorAppointments(){const appts=myAppointments();return `<div class='page-header'><h1 class='page-title'>Appointments</h1></div><div class='card'><div style='overflow-x:auto'><table class='data-table'><thead><tr><th>ID</th><th>Hospital</th><th>Date</th><th>Status</th></tr></thead><tbody>${appts.map((a)=>`<tr><td>${a.id||''}</td><td>${a.hospital||''}</td><td>${a.date||''}</td><td>${a.status||''}</td></tr>`).join('') || '<tr><td colspan="4">No appointments yet.</td></tr>'}</tbody></table></div></div>`;}
function renderDonorHistory(){return renderDonorAppointments();}
function renderDonorProfile(){return `<div class='page-header'><h1 class='page-title'>My Profile</h1></div><div class='card'><div class='card-body'>${currentUser.name}</div></div>`;}
function attachDonorEvents(_page){}
function volunteerForRequest(_requestId){}
function markDonated(_apptId){}
function getCookie(name){const v=`; ${document.cookie}`;const p=v.split(`; ${name}=`);if(p.length===2) return p.pop().split(';').shift();}
