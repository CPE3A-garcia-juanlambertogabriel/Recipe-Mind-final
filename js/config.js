const SUPABASE_URL = 'https://odpyultsywmjbmsauszp.supabase.co';
const SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im9kcHl1bHRzeXdtamJtc2F1c3pwIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzY1MTM4OTcsImV4cCI6MjA5MjA4OTg5N30.BXvSwrZFIY3w4DZdaLYoEy38JeBF_5_rVsFApwM6Orw';


const { createClient } = supabase;
const sb = createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

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
  const { data: { session } } = await sb.auth.getSession();
  if (!session) {
    window.location.href = '/login.html';
    return null;
  }
  return session.user;
}

async function getCurrentUser() {
  const { data: { session } } = await sb.auth.getSession();
  return session?.user || null;
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
    if (userEmail) userEmail.textContent = user.email.split('@')[0];
  } else {
    if (loginBtn) loginBtn.style.display = '';
    if (registerBtn) registerBtn.style.display = '';
    if (userMenu) userMenu.style.display = 'none';
  }
}

async function logout() {
  await sb.auth.signOut();
  window.location.href = '/index.html';
}
