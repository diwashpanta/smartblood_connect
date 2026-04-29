// ============================================================
// SmartBlood Connect — Dashboard Core (dashboard.js)
// ============================================================

let currentUser = null;
let currentPage = 'overview';

function getUser() {
  const raw = sessionStorage.getItem('sb_user');
  if (!raw) { window.location.href = '../index.html'; return null; }
  return JSON.parse(raw);
}

function logout() {
  sessionStorage.clear();
  window.location.href = '../index.html';
}

function openModal(contentHTML, title = '') {
  document.getElementById('modalContent').innerHTML = `
    ${title ? `<div class="modal-title">${title}</div>` : ''}
    ${contentHTML}
  `;
  document.getElementById('modalOverlay').classList.add('open');
}

function closeModal(e) {
  if (!e || e.target === document.getElementById('modalOverlay')) {
    document.getElementById('modalOverlay').classList.remove('open');
  }
}

function closeModalDirect() {
  document.getElementById('modalOverlay').classList.remove('open');
}

function showAlert(msg, type = 'success') {
  const alertDiv = document.createElement('div');
  alertDiv.className = `alert alert-${type}`;
  alertDiv.textContent = msg;
  const content = document.getElementById('appContent');
  content.insertBefore(alertDiv, content.firstChild);
  setTimeout(() => alertDiv.remove(), 3500);
}

function renderNavLinks(role) {
  const navMap = {
    patient: [
      { icon: '📊', label: 'Dashboard', page: 'overview' },
      { icon: '🩸', label: 'Blood Requests', page: 'requests' },
      { icon: '📋', label: 'Request History', page: 'history' },
      { icon: '🔔', label: 'Notifications', page: 'notifications' },
      { icon: '👤', label: 'My Profile', page: 'profile' },
    ],
    donor: [
      { icon: '📊', label: 'Dashboard', page: 'overview' },
      { icon: '📍', label: 'Nearby Requests', page: 'nearby' },
      { icon: '🔔', label: 'My Notifications', page: 'notifications' },
      { icon: '📅', label: 'Appointments', page: 'appointments' },
      { icon: '📜', label: 'Donation History', page: 'history' },
      { icon: '👤', label: 'My Profile', page: 'profile' },
    ],
    admin: [
      { icon: '📊', label: 'Dashboard', page: 'overview' },
      { icon: '🩸', label: 'Blood Requests', page: 'requests' },
      { icon: '🧪', label: 'Inventory', page: 'inventory' },
      { icon: '👥', label: 'Users', page: 'users' },
      { icon: '🤖', label: 'ML Analysis', page: 'ml' },
      { icon: '📊', label: 'Reports', page: 'reports' },
    ],
  };

  const links = navMap[role] || [];
  const container = document.getElementById('navLinks');
  container.innerHTML = `
    <div class="sidebar-section-label">Menu</div>
    ${links.map(l => `
      <button class="nav-link ${currentPage === l.page ? 'active' : ''}" onclick="navigateTo('${l.page}')">
        <span class="nav-icon">${l.icon}</span> ${l.label}
      </button>
    `).join('')}
  `;
}

function navigateTo(page) {
  currentPage = page;
  renderNavLinks(currentUser.role);
  renderPage(page);
}

function renderPage(page) {
  const content = document.getElementById('appContent');
  const role = currentUser.role;

  if (role === 'patient') {
    content.innerHTML = renderPatientPage(page);
    attachPatientEvents(page);
  } else if (role === 'donor') {
    content.innerHTML = renderDonorPage(page);
    attachDonorEvents(page);
  } else if (role === 'admin') {
    content.innerHTML = renderAdminPage(page);
    attachAdminEvents(page);
  }
}