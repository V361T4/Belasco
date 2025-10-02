// ========================
// Base config
// ========================
const API_BASE = (localStorage.getItem('api_base') || window.location.origin).replace(/\/$/, '');

function getToken(){ return localStorage.getItem('auth_token'); }
function setToken(t){ if(t) localStorage.setItem('auth_token', t); }
function clearToken(){ localStorage.removeItem('auth_token'); }
function authHeaders(){ const t=getToken(); return t?{ 'Authorization':'Bearer '+t }:{} }

// ========================
// CSRF helpers (Sanctum SPA)
// ========================
function getCookie(name) {
  return document.cookie
    .split('; ')
    .find(row => row.startsWith(name + '='))
    ?.split('=')[1];
}

async function ensureCsrf() {
  await fetch(API_BASE + '/sanctum/csrf-cookie', { credentials: 'include' });
}

function csrfHeaders() {
  const token = getCookie('XSRF-TOKEN');
  return token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {};
}

// ========================
// Low-level fetch helpers
// ========================
async function fetchJSON(path, { method='GET', body=null, withCsrf=false } = {}) {
  const headers = {
    'Accept':'application/json',
    ...(method !== 'GET' ? { 'Content-Type':'application/json' } : {}),
    ...authHeaders(),
    ...(withCsrf ? csrfHeaders() : {})
  };

  const res = await fetch(API_BASE + path, {
    method,
    headers,
    body: body ? JSON.stringify(body) : null,
    credentials: 'include' // allow SPA cookie flow
  });

  const text = await res.text();
  let data; try { data = JSON.parse(text) } catch { data = text }
  return { ok: res.ok, status: res.status, data };
}

async function apiGet(path) {
  const r = await fetchJSON(path);
  if (!r.ok) throw r.data;
  return r.data;
}

async function apiJSONAuto(path, method, body) {
  let r = await fetchJSON(path, { method, body, withCsrf:false });
  if (!r.ok && (r.status === 419 || r.status === 403)) {
    await ensureCsrf();
    r = await fetchJSON(path, { method, body, withCsrf:true });
  }
  if (!r.ok) throw r.data;
  return r;
}

async function apiJSON(path, method, body) {
  const r = await fetchJSON(path, { method, body, withCsrf:false });
  if (!r.ok) throw r.data;
  return r;
}

// ========================
// Auth
// ========================
async function registerUser(name,email,password){
  try {
    const r = await apiJSON('/api/register','POST',{ name,email,password,password_confirmation:password });
    if (r.data?.token) setToken(r.data.token);
    notifyCartChanged(); // user context changed
    return r.data.user;
  } catch (e) {
    const r2 = await apiJSONAuto('/api/register','POST',{ name,email,password,password_confirmation:password });
    if (r2.data?.token) setToken(r2.data.token);
    notifyCartChanged();
    return r2.data.user;
  }
}

async function loginUser(email,password){
  try {
    const r = await apiJSON('/api/login','POST',{ email,password });
    if (r.status===200 && r.data?.token) setToken(r.data.token);
    notifyCartChanged(); // update nav (account + cart)
    return r.status; // 200 (token) or 204 (SPA)
  } catch (e) {
    const r2 = await apiJSONAuto('/api/login','POST',{ email,password });
    if (r2.status===200 && r2.data?.token) setToken(r2.data.token);
    notifyCartChanged();
    return r2.status;
  }
}

async function logoutUser(){
  try { await apiJSON('/api/logout','POST'); }
  catch { await apiJSONAuto('/api/logout','POST'); }
  clearToken();
  notifyCartChanged(); // reset nav state
}

async function getMe(){ return await apiGet('/api/user'); }

// ========================
// Products (normalize paginator + image URL)
// ========================
function normalizeProduct(p){
  const rel = p.image_url || p.image || null;
  const image_url = rel
    ? (String(rel).startsWith('http') ? rel : (API_BASE + '/' + String(rel).replace(/^\/+/, '')))
    : null;
  return { ...p, image_url };
}

async function listProducts(page = 1){
  const r = await apiGet('/api/products?page=' + page);
  const items = Array.isArray(r) ? r : (r.data || []);
  return items.map(normalizeProduct);
}

async function getProduct(id){
  const p = await apiGet('/api/products/' + id);
  const entity = p && p.data && !p.id ? p.data : p;
  return normalizeProduct(entity);
}

// ========================
// Cart / Orders
// ========================
async function getCart(){ return await apiGet('/api/cart'); }

async function addToCart(product_id, quantity=1){
  const r = await apiJSONAuto('/api/cart','POST',{ product_id, quantity });
  notifyCartChanged();          // update nav count
  return r.data;
}

async function updateCartItem(cartItemId, quantity){
  const r = await apiJSONAuto('/api/cart/'+cartItemId,'PUT',{ quantity });
  notifyCartChanged();          // update nav count
  return r.data;
}

async function removeCartItem(cartItemId){
  await apiJSONAuto('/api/cart/'+cartItemId,'DELETE');
  notifyCartChanged();          // update nav count
  return true;
}

async function checkout(){
  const data = (await apiJSONAuto('/api/checkout','POST')).data;
  notifyCartChanged();          // cart cleared
  return data;
}

async function listOrders(){
  return await apiGet('/api/orders');
}

// ========================
// Small UI helpers
// ========================
function qs(s){ return document.querySelector(s) }
function qsa(s){ return Array.from(document.querySelectorAll(s)) }
function formatINR(n){ return new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR'}).format(Number(n||0)) }

// ========================
// Navbar polish: cart count + account (username)
// ========================
async function refreshNav() {
  // Cart count
  try {
    const c = await getCart();
    const n = (c.items || []).reduce((s,i)=> s + Number(i.quantity||0), 0);
    const cartLink = document.querySelector('.nav--brand a[href="cart.html"]');
    if (cartLink) cartLink.textContent = n ? `Cart (${n})` : 'Cart';
  } catch (e) {
    const cartLink = document.querySelector('.nav--brand a[href="cart.html"]');
    if (cartLink) cartLink.textContent = 'Cart';
  }

  // Account / Login (show username)
  const loginLink = document.querySelector('.nav--brand a[href="auth.html"]');
  if (!loginLink) return;

  try {
    const user = await getMe();
    const username = user?.name || (user?.email?.split('@')[0]) || 'Account';
    loginLink.textContent = `Account (${username})`;
    loginLink.href = 'auth.html';

    // Add a Logout link right after it (once)
    if (!document.getElementById('navLogout')) {
      const sep = document.createElement('span');
      sep.textContent = ' · ';
      sep.style.marginLeft = '6px';

      const a = document.createElement('a');
      a.id = 'navLogout';
      a.href = '#';
      a.style.marginLeft = '6px';
      a.textContent = 'Logout';
      a.addEventListener('click', async (e)=>{
        e.preventDefault();
        try { await logoutUser(); } finally { location.href = 'auth.html'; }
      });

      loginLink.insertAdjacentElement('afterend', a);
      loginLink.insertAdjacentElement('afterend', sep);
    }
  } catch {
    // Not logged in
    const old = document.getElementById('navLogout');
    const sep = old?.previousSibling;
    if (old) old.remove();
    if (sep && sep.nodeType === Node.ELEMENT_NODE && sep.textContent.trim() === '·') sep.remove();
    loginLink.textContent = 'Login';
    loginLink.href = 'auth.html';
  }
}

// Event to refresh nav after any cart/auth change
function notifyCartChanged() {
  document.dispatchEvent(new CustomEvent('cart:changed'));
}

// Listen & run on load
document.addEventListener('cart:changed', refreshNav);
window.addEventListener('DOMContentLoaded', refreshNav);

// ========================
// Top bar wiring
// ========================
(function(){
  const input = document.getElementById('apiBaseInput');
  const btn   = document.getElementById('apiBaseSave');
  const badge = document.getElementById('tokenBadge');
  if (input) input.value = API_BASE;
  if (btn) btn.addEventListener('click', ()=>{ localStorage.setItem('api_base', input.value.trim()); location.reload(); });
  if (badge) badge.textContent = getToken() ? 'Token: present' : 'Token: none';
})();
