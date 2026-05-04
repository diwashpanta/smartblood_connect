async function hydrateFromServer() {
  try {
    const res = await fetch('/app/dashboard/data/', { credentials: 'same-origin' });
    if (!res.ok) return;
    const data = await res.json();

    DB.requests = (data.requests || []).map((r) => ({
      id: `BR${r.id}`,
      db_id: r.id,
      patient: r.patient,
      blood_type: r.blood_group,
      units: r.units_needed,
      hospital: r.hospital_name,
      location: r.hospital_address,
      urgency: r.urgency,
      status: r.status,
      date: (r.created_at || '').slice(0, 10),
    }));

    DB.notifications = (data.notifications || []).map((n) => ({
      id: `N${n.id}`,
      db_id: n.id,
      request_id: `BR${n.request_id}`,
      donor: n.donor,
      responded: n.responded,
      donated: n.willing,
      date: (n.created_at || '').slice(0, 10),
      urgency: 'High',
      message: `Request ${n.request_id} needs response`,
    }));

    DB.inventory = (data.inventory || []).map((i) => ({
      id: `BI${i.id}`,
      db_id: i.id,
      blood_type: i.blood_group,
      units: 1,
      status: i.status,
    }));

    DB.users = (data.users || []).map((u) => ({
      id: `U${u.id}`,
      db_id: u.id,
      name: u.username,
      role: u.role,
      email: `${u.username}@smartblood.local`,
      blood_type: u.blood_group,
      status: 'active',
    }));

    DB.donors = DB.users
      .filter((u) => u.role === 'donor')
      .map((u) => ({
        id: `D${u.db_id}`,
        name: u.name,
        blood_type: u.blood_type,
        eligible: true,
        response_rate: 0,
        total_donations: 0,
        location: 'Kathmandu',
      }));

    DB.appointments = (data.appointments || []).map((a) => ({
      id: `A${a.id}`,
      db_id: a.id,
      request_id: `BR${a.request_id}`,
      donor: a.donor,
      hospital: a.location,
      date: (a.scheduled_at || '').slice(0, 10),
      time: (a.scheduled_at || '').slice(11, 16),
      status: a.completed ? 'completed' : 'scheduled',
    }));

    DB.current_role = data.role;
  } catch (e) {
    console.warn('dashboard data load failed', e);
  }
}

(function init() {
  currentUser = getUser();
  if (!currentUser) return;

  document.getElementById('userAvatar').textContent = (currentUser.name || 'U').charAt(0).toUpperCase();
  document.getElementById('userName').textContent = currentUser.name;
  document.getElementById('userRole').textContent = currentUser.role.charAt(0).toUpperCase() + currentUser.role.slice(1);

  hydrateFromServer().finally(() => {
    renderNavLinks(currentUser.role);
    renderPage('overview');
  });
})();
