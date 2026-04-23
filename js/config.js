// MySQL Authentication (stored in localStorage)
// No longer using Supabase

function showToast(message, type = 'default') {
  const container = document.getElementById('toast-container') || createToastContainer();
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
  toast.innerHTML = `<span>${icon}</span><span>${message}</span>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(20px)';
    toast.style.transition = '0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

function createToastContainer() {
  const container = document.createElement('div');
  container.id = 'toast-container';
  container.className = 'toast-container';
  document.body.appendChild(container);
  return container;
}

function formatTime(minutes) {
  if (!minutes) return 'N/A';
  if (minutes < 60) return `${minutes}m`;
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return m ? `${h}h ${m}m` : `${h}h`;
}

async function requireAuth() {
  const token = localStorage.getItem('auth_token');
  if (!token) {
    // Redirect to login, but remember where we came from
    const returnUrl = window.location.pathname + window.location.search;
    window.location.href = '/recipe-mind-final/login.html?return=' + encodeURIComponent(returnUrl);
    return null;
  }
  return {
    id: localStorage.getItem('user_id'),
    email: localStorage.getItem('user_email'),
    username: localStorage.getItem('username')
  };
}

async function getCurrentUser() {
  const token = localStorage.getItem('auth_token');
  if (!token) return null;
  return {
    id: localStorage.getItem('user_id'),
    email: localStorage.getItem('user_email'),
    username: localStorage.getItem('username')
  };
}

async function updateNavAuth() {
  const user = await getCurrentUser();
  const loginBtn = document.getElementById('nav-login-btn');
  const registerBtn = document.getElementById('nav-register-btn');
  const userMenu = document.getElementById('nav-user-menu');
  const userEmail = document.getElementById('nav-user-email');

  if (user) {
    if (loginBtn) loginBtn.style.display = 'none';
    if (registerBtn) registerBtn.style.display = 'none';
    if (userMenu) userMenu.style.display = 'flex';
    if (userEmail) userEmail.textContent = user.username || user.email.split('@')[0];
  } else {
    if (loginBtn) loginBtn.style.display = '';
    if (registerBtn) registerBtn.style.display = '';
    if (userMenu) userMenu.style.display = 'none';
  }
}

async function logout() {
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user_id');
  localStorage.removeItem('user_email');
  localStorage.removeItem('username');
  window.location.href = 'index.html';
}
