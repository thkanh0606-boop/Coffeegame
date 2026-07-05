/* api.js — thin AJAX wrapper around the PHP API. */
(function () {
  const base = window.BREW.api;

  async function req(action, method, body) {
    const opts = { method, headers: {} };
    if (body instanceof FormData) {
      opts.body = body;
    } else if (body) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    const res = await fetch(base + action, opts);
    let data;
    try { data = await res.json(); }
    catch (e) { data = { ok: false, error: 'bad_json' }; }
    if (res.status === 401) {
      window.location.href = window.BREW.base + '/index.php?url=auth/login';
    }
    return data;
  }

  window.API = {
    get:  (action) => req(action, 'GET'),
    post: (action, body) => req(action, 'POST', body),
    upload: (action, formData) => req(action, 'POST', formData),
  };
})();
