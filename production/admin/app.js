// Admin UI helper for API calls with JWT + auto refresh
(function(){
  const API = '../api/'; // from /admin/* → /api/ (works in both dev and production subdir)

  function getTokens(){
    try { return JSON.parse(localStorage.getItem('tokens')||'null'); } catch { return null; }
  }
  function setTokens(t){ localStorage.setItem('tokens', JSON.stringify(t)); }
  function clearTokens(){ localStorage.removeItem('tokens'); }

  async function refreshIfNeeded(tokens){
    if (!tokens || !tokens.refresh_token) return null;
    const res = await fetch(API + 'token/refresh', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: tokens.refresh_token })
    });
    if (!res.ok) return null;
    const data = await res.json();
    setTokens(data);
    return data;
  }

  async function authFetch(path, options={}){
    const opts = Object.assign({ headers: {} }, options);
    let tokens = getTokens();
    if (tokens && tokens.access_token) {
      opts.headers['Authorization'] = 'Bearer ' + tokens.access_token;
    }
    const attempt = async()=> fetch(API + path, opts);
    let res = await attempt();
    if (res.status === 401 && tokens && tokens.refresh_token) {
      const newTokens = await refreshIfNeeded(tokens);
      if (newTokens) {
        opts.headers['Authorization'] = 'Bearer ' + newTokens.access_token;
        res = await attempt();
      }
    }
    return res;
  }

  async function requireAdminOrRedirect(){
    try{
      const res = await authFetch('me', { method: 'GET' });
      if (!res.ok) throw new Error('not ok');
      const me = await res.json();
      if (me.role !== 'admin') throw new Error('not admin');
      return me;
    } catch(e){
      window.location.href = 'login.html';
      throw e;
    }
  }

  async function login(email, password){
    const res = await fetch(API + 'login', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });
    if (!res.ok) {
      const t = await res.json().catch(()=>({}));
      throw new Error(t.error || 'Login failed');
    }
    const tokens = await res.json();
    setTokens(tokens);
    return tokens;
  }

  async function logoutAll(){
    const res = await authFetch('logout', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ all: true })
    });
    clearTokens();
    return res.ok;
  }

  window.AdminAPI = { authFetch, requireAdminOrRedirect, login, logoutAll, getTokens, setTokens, clearTokens };
})();
