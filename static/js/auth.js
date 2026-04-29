// ============================================================
// SmartBlood Connect — Auth Module (auth.js)
// ============================================================

// Demo user database
const USERS = [
  { username: 'admin1', email: 'admin@smartblood.com', password: 'Admin123!', role: 'admin', name: 'Admin User' },
  { username: 'john_patient', email: 'john@example.com', password: 'Pass123!', role: 'patient', name: 'John Doe', bloodGroup: 'A+' },
  { username: 'kiran_donor', email: 'kiran@example.com', password: 'Pass123!', role: 'donor', name: 'Kiran Budhathoki', bloodGroup: 'O+' },
];

let selectedRole = 'patient';

function selectRole(el) {
  document.querySelectorAll('.role-pill').forEach(p => p.classList.remove('active'));
  el.classList.add('active');
  selectedRole = el.dataset.role;
}

function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach((b, i) => {
    b.classList.toggle('active', (i === 0 && tab === 'login') || (i === 1 && tab === 'register'));
  });
  document.getElementById('loginForm').classList.toggle('active', tab === 'login');
  document.getElementById('registerForm').classList.toggle('active', tab === 'register');
}

function showError(id, msg) {
  const el = document.getElementById(id);
  el.textContent = msg;
  el.classList.remove('hidden');
}

function hideError(id) {
  document.getElementById(id).classList.add('hidden');
}

function handleLogin(e) {
  e.preventDefault();
  hideError('loginError');
  const usernameInput = document.getElementById('loginUsername').value.trim();
  const password = document.getElementById('loginPassword').value;

  if (!usernameInput || !password) {
    showError('loginError', 'Username and password required.');
    return;
  }

  const user = USERS.find(u =>
    (u.username === usernameInput || u.email === usernameInput) && u.password === password
  );

  if (!user) {
    showError('loginError', 'Invalid credentials. Please try again.');
    return;
  }

  if (user.role !== selectedRole) {
    showError('loginError', `This account is registered as "${user.role}", not "${selectedRole}".`);
    return;
  }

  // Store session
  sessionStorage.setItem('sb_user', JSON.stringify(user));

  // Redirect to dashboard
  window.location.href = `pages/dashboard.html`;
}

function handleRegister(e) {
  e.preventDefault();
  hideError('regError');

  const name = document.getElementById('regName').value.trim();
  const email = document.getElementById('regEmail').value.trim();
  const username = document.getElementById('regUsername').value.trim();
  const password = document.getElementById('regPassword').value;
  const bloodGroup = document.getElementById('regBloodGroup').value;
  const role = document.getElementById('regRole').value;

  if (!name || !email || !username || !password || !bloodGroup || !role) {
    showError('regError', 'All fields are required.');
    return;
  }
  if (password.length < 6) {
    showError('regError', 'Password must be at least 6 characters.');
    return;
  }
  if (USERS.find(u => u.username === username || u.email === email)) {
    showError('regError', 'Username or email already exists.');
    return;
  }

  const newUser = { username, email, password, role, name, bloodGroup };
  USERS.push(newUser);
  sessionStorage.setItem('sb_user', JSON.stringify(newUser));
  window.location.href = `pages/dashboard.html`;
}