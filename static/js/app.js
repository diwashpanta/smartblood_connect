function getCookie(name){const v=`; ${document.cookie}`;const p=v.split(`; ${name}=`);return p.length===2?p.pop().split(';').shift():'';}

async function syncDBFromServer(){
  const res = await fetch('/app/dashboard/data/', {credentials:'same-origin'});
  if(!res.ok) return;
  const live = await res.json();

  DB.requests = (live.requests||[]).map(r=>(
    {id:'BR'+String(r.id).padStart(3,'0'), patient:r.patient, blood_type:r.blood_group, units:r.units_needed, hospital:r.hospital_name, location:'Kathmandu', urgency:(r.urgency||'urgent').toUpperCase(), status:r.status, date:(r.created_at||'').slice(0,10)}
  ));
  DB.notifications = (live.notifications||[]).map(n=>(
    {id:'N'+String(n.id).padStart(3,'0'), request_id:'BR'+String(n.request_id).padStart(3,'0'), message:`Request #${n.request_id}`, responded:n.responded, donated:n.willing, date:(n.created_at||'').slice(0,10), urgency:'HIGH'}
  ));
  DB.users = (live.users||[]).map(u=>({id:'U'+String(u.id).padStart(3,'0'), name:u.username, role:u.role, email:`${u.username}@smartblood.local`, blood_type:u.blood_group||'', status:'active'}));

  const byBlood={};
  (live.inventory||[]).forEach(i=>{byBlood[i.blood_group]=(byBlood[i.blood_group]||0)+1;});
  DB.inventory = DB.bloodGroups.map(bg=>({id:`BI-${bg}`, blood_type:bg, units:byBlood[bg]||0, status:'available'}));
}

async function postJSON(url, body){
  return fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRFToken':getCookie('csrftoken')},body:JSON.stringify(body||{})});
}
async function patchJSON(url, body){
  return fetch(url,{method:'PATCH',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRFToken':getCookie('csrftoken')},body:JSON.stringify(body||{})});
}

submitBloodRequest = async function(e){
  e.preventDefault();
  const fd = new FormData();
  fd.append('blood_group', document.getElementById('req_blood').value);
  fd.append('units_needed', document.getElementById('req_units').value);
  fd.append('hospital_name', document.getElementById('req_hospital').value);
  fd.append('hospital_address', (document.getElementById('req_location').value||'Kathmandu') + ', Nepal');
  fd.append('latitude','27.7172'); fd.append('longitude','85.3240');
  fd.append('urgency', (document.getElementById('req_urgency').value||'urgent').toLowerCase());
  const res = await fetch('/app/patient/requests/new/', {method:'POST',credentials:'same-origin',headers:{'X-CSRFToken':getCookie('csrftoken')},body:fd});
  const alertEl=document.getElementById('requestAlert');
  if(!res.ok){ alertEl.innerHTML='<div class="alert alert-danger">Request save failed.</div>'; return; }
  await syncDBFromServer();
  alertEl.innerHTML='<div class="alert alert-success">Request saved successfully.</div>';
  e.target.reset();
  navigateTo('history');
};

approveRequest = async function(idToken){
  const id=parseInt(String(idToken).replace(/\D/g,''),10);
  const res=await postJSON(`/app/api/requests/${id}/approve/`,{});
  if(!res.ok){ showAlert('Approve failed','danger'); return; }
  await syncDBFromServer();
  navigateTo('requests');
};
rejectRequest = async function(idToken){
  const id=parseInt(String(idToken).replace(/\D/g,''),10);
  const res=await patchJSON(`/app/api/requests/${id}/`,{status:'rejected'});
  if(!res.ok){ showAlert('Reject failed','danger'); return; }
  await syncDBFromServer();
  navigateTo('requests');
};
issueBlood = async function(idToken){
  const id=parseInt(String(idToken).replace(/\D/g,''),10);
  const t=DB.requests.find(r=>String(r.id).includes(String(id)));
  const res=await patchJSON(`/app/api/requests/${id}/`,{status:'fulfilled',units_fulfilled:t?t.units:1});
  if(!res.ok){ showAlert('Issue failed','danger'); return; }
  await syncDBFromServer();
  navigateTo('requests');
};

respondToNotification = async function(idToken,willing){
  const id=parseInt(String(idToken).replace(/\D/g,''),10);
  const res=await postJSON(`/app/api/notifications/${id}/respond/`,{willing:!!willing});
  if(!res.ok){ showAlert('Response failed','danger'); return; }
  await syncDBFromServer();
  navigateTo('notifications');
};
confirmDonate = (id)=>respondToNotification(id,true);
declineDonate = (id)=>respondToNotification(id,false);

(function init(){
  currentUser=getUser();
  if(!currentUser) return;
  syncDBFromServer().then(()=>{
    document.getElementById('userAvatar').textContent=(currentUser.name||'U')[0].toUpperCase();
    document.getElementById('userName').textContent=currentUser.name||currentUser.username;
    document.getElementById('userRole').textContent=(currentUser.role||'user');
    renderNavLinks(currentUser.role);
    renderPage('overview');
  });
})();
// Per-user real stats override
function currentUsername(){ return (currentUser && (currentUser.username || currentUser.name)) || ''; }

renderDonorOverview = function(){
  const uname = currentUsername();
  const mine = (DB.notifications || []).filter(n => {
    const d = (n.donor || n.donor_username || '').toString().toLowerCase();
    return d === uname.toLowerCase();
  });

  const responded = mine.filter(n => n.responded).length;
  const willing = mine.filter(n => n.donated).length;
  const totalDonations = willing; // until issuance->donation completion pipeline is added
  const rate = mine.length ? Math.round((responded / mine.length) * 100) : 0;

  return `
    <div class="page-header">
      <h1 class="page-title">Donor Dashboard</h1>
      <p class="page-subtitle">Welcome, <strong>${currentUser.name || uname}</strong> — Blood Group: <span class="badge badge-blood">${currentUser.bloodGroup || ''}</span></p>
    </div>

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-card-icon red">??</div><div class="stat-card-value">${totalDonations}</div><div class="stat-card-label">Total Donations</div></div>
      <div class="stat-card"><div class="stat-card-icon yellow">??</div><div class="stat-card-value">${mine.length}</div><div class="stat-card-label">Notifications</div></div>
      <div class="stat-card"><div class="stat-card-icon green">??</div><div class="stat-card-value">${willing}</div><div class="stat-card-label">Accepted</div></div>
      <div class="stat-card"><div class="stat-card-icon blue">??</div><div class="stat-card-value">${rate}%</div><div class="stat-card-label">Response Rate</div></div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Pending Notifications</span></div>
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead><tr><th>ID</th><th>Request</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            ${mine.map(n => `
              <tr>
                <td>${n.id}</td>
                <td>${n.request_id}</td>
                <td>${n.responded ? (n.donated ? 'Willing' : 'Declined') : 'Pending'}</td>
                <td>${n.responded ? '—' : `<button class='btn-green' onclick="confirmDonate('${n.id}')">? Willing</button> <button class='btn-outline-red' onclick="declineDonate('${n.id}')">? Not Available</button>`}</td>
              </tr>`).join('') || `<tr><td colspan='4'>No notifications yet.</td></tr>`}
          </tbody>
        </table>
      </div>
    </div>
  `;
};

renderPatientOverview = function(){
  const uname = currentUsername().toLowerCase();
  const my = (DB.requests || []).filter(r => ((r.patient||'').toString().toLowerCase() === uname));
  const pending = my.filter(r=>r.status==='pending').length;
  const fulfilled = my.filter(r=>r.status==='fulfilled').length;
  return `
  <div class="page-header"><h1 class="page-title">Patient Dashboard</h1><p class="page-subtitle">Welcome back, <strong>${currentUser.name||currentUsername()}</strong></p></div>
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-card-value">${my.length}</div><div class="stat-card-label">My Requests</div></div>
    <div class="stat-card"><div class="stat-card-value">${pending}</div><div class="stat-card-label">Pending</div></div>
    <div class="stat-card"><div class="stat-card-value">${fulfilled}</div><div class="stat-card-label">Fulfilled</div></div>
    <div class="stat-card"><div class="stat-card-value">${Math.max(my.length-fulfilled,0)}</div><div class="stat-card-label">In Progress</div></div>
  </div>`;
};
// ----- Full live sidebar page overrides (patient/donor/admin) -----
(function(){
  function uname(){ return ((currentUser&& (currentUser.username||currentUser.name))||'').toLowerCase(); }

  renderDonorPage = function(page){
    const mineN = (DB.notifications||[]).filter(n => ((n.donor||n.donor_username||'').toLowerCase()===uname()));
    const mineA = (DB.appointments||[]).filter(a => ((a.donor||'').toLowerCase()===uname()));
    if(page==='appointments'){
      return `<div class='page-header'><h1 class='page-title'>Appointments</h1></div><div class='card'><div class='card-body'><table class='data-table'><tr><th>ID</th><th>Hospital</th><th>Date</th><th>Time</th><th>Status</th></tr>${mineA.map(a=>`<tr><td>${a.id||''}</td><td>${a.hospital||a.location||''}</td><td>${(a.date||'').toString().slice(0,10)}</td><td>${a.time||''}</td><td>${a.status|| (a.completed?'completed':'scheduled')}</td></tr>`).join('') || `<tr><td colspan='5'>No appointments.</td></tr>`}</table></div></div>`;
    }
    if(page==='history'){
      const done = mineN.filter(n=>n.donated);
      return `<div class='page-header'><h1 class='page-title'>Donation History</h1></div><div class='card'><div class='card-body'><table class='data-table'><tr><th>Notification</th><th>Request</th><th>Date</th><th>Status</th></tr>${done.map(n=>`<tr><td>${n.id}</td><td>${n.request_id}</td><td>${n.date||''}</td><td>Completed</td></tr>`).join('') || `<tr><td colspan='4'>No donations yet.</td></tr>`}</table></div></div>`;
    }
    if(page==='notifications') return renderDonorOverview();
    if(page==='profile') return `<div class='page-header'><h1 class='page-title'>My Profile</h1></div><div class='card'><div class='card-body'><div class='flex-between mb-20'><span>Username</span><strong>${currentUser.username||currentUser.name}</strong></div><div class='flex-between mb-20'><span>Role</span><strong>donor</strong></div><div class='flex-between'><span>Blood Group</span><strong>${currentUser.bloodGroup||''}</strong></div></div></div>`;
    return renderDonorOverview();
  };

  renderPatientPage = function(page){
    const my = (DB.requests||[]).filter(r => ((r.patient||'').toLowerCase()===uname()));
    if(page==='history' || page==='requests'){
      return `<div class='page-header'><h1 class='page-title'>Request History</h1></div><div class='card'><div class='card-body'><table class='data-table'><tr><th>ID</th><th>Blood</th><th>Units</th><th>Hospital</th><th>Status</th></tr>${my.map(r=>`<tr><td>${r.id}</td><td>${r.blood_type}</td><td>${r.units}</td><td>${r.hospital}</td><td>${r.status}</td></tr>`).join('') || `<tr><td colspan='5'>No requests yet.</td></tr>`}</table></div></div>`;
    }
    if(page==='notifications'){
      const notes=(DB.notifications||[]).filter(n=> my.some(r=>r.id===n.request_id));
      return `<div class='page-header'><h1 class='page-title'>Notifications</h1></div><div class='card'><div class='card-body'>${notes.map(n=>`<div class='notif-item'><div class='notif-dot'></div><div class='notif-text'><div class='notif-title'>${n.request_id}</div><div class='notif-sub'>${n.responded?'Donor responded':'Awaiting donor response'}</div></div></div>`).join('') || 'No notifications.'}</div></div>`;
    }
    if(page==='profile') return `<div class='page-header'><h1 class='page-title'>My Profile</h1></div><div class='card'><div class='card-body'><div class='flex-between mb-20'><span>Username</span><strong>${currentUser.username||currentUser.name}</strong></div><div class='flex-between'><span>Blood Group</span><strong>${currentUser.bloodGroup||''}</strong></div></div></div>`;
    return renderPatientOverview();
  };

  renderAdminPage = function(page){
    if(page==='users') return `<div class='page-header'><h1 class='page-title'>Users</h1></div><div class='card'><div class='card-body'><table class='data-table'><tr><th>ID</th><th>Name</th><th>Role</th><th>Blood</th></tr>${(DB.users||[]).map(u=>`<tr><td>${u.id}</td><td>${u.name}</td><td>${u.role}</td><td>${u.blood_type||''}</td></tr>`).join('')}</table></div></div>`;
    if(page==='inventory') return `<div class='page-header'><h1 class='page-title'>Inventory</h1></div><div class='card'><div class='card-body'><table class='data-table'><tr><th>Blood</th><th>Units</th></tr>${(DB.inventory||[]).map(i=>`<tr><td>${i.blood_type}</td><td>${i.units}</td></tr>`).join('')}</table></div></div>`;
    if(page==='requests') return renderAdminRequests();
    if(page==='ml') return `<div class='page-header'><h1 class='page-title'>ML Analysis</h1></div><div class='card'><div class='card-body'>Live model panel placeholder (DB-synced pipeline active).</div></div>`;
    if(page==='reports') return `<div class='page-header'><h1 class='page-title'>Reports</h1></div><div class='card'><div class='card-body'>Total Requests: ${(DB.requests||[]).length}, Users: ${(DB.users||[]).length}</div></div>`;
    return renderAdminOverview();
  };
})();
