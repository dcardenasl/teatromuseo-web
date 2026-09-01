const csrfHeaderName = 'X-CSRF-TOKEN';

const readCookie = (name) => {
  const prefix = `${encodeURIComponent(name)}=`;
  const cookie = document.cookie
    .split(';')
    .map((part) => part.trim())
    .find((part) => part.startsWith(prefix));

  if (!cookie) return '';

  try {
    return decodeURIComponent(cookie.slice(prefix.length));
  } catch {
    return '';
  }
};

const setPending = (form, pending) => {
  form.dataset.submitting = pending ? '1' : '0';
  const button = form.querySelector('button[type="submit"]');
  if (!button) return;

  button.disabled = pending;
  button.setAttribute('aria-busy', pending ? 'true' : 'false');

  const spinner = button.querySelector('[data-public-form-spinner]');
  if (spinner) spinner.hidden = !pending;
};

const showError = (form) => {
  const error = form.querySelector('[data-public-form-error]');
  if (error) error.hidden = false;
};

const recaptchaToken = (siteKey) => new Promise((resolve, reject) => {
  if (!siteKey) {
    resolve('');
    return;
  }

  if (!window.grecaptcha || typeof window.grecaptcha.ready !== 'function') {
    reject(new Error('recaptcha_unavailable'));
    return;
  }

  window.grecaptcha.ready(() => {
    window.grecaptcha.execute(siteKey, { action: 'submit' }).then(resolve).catch(reject);
  });
});

const submitForm = async (form) => {
  const csrfCookieName = form.dataset.csrfCookieName || '';
  if (!csrfCookieName) throw new Error('csrf_configuration_missing');

  const csrfToken = readCookie(csrfCookieName);
  if (!csrfToken) throw new Error('csrf_unavailable');

  const payload = new FormData(form);
  const captchaToken = await recaptchaToken(form.dataset.recaptchaSiteKey || '');
  if (captchaToken) payload.set('g_recaptcha_response', captchaToken);

  // FormController always ends in redirect()->back() — success and
  // validation-error paths alike — with the confirmation/error message in
  // one-shot session flashdata read by the *next* request to render. With
  // `redirect: 'follow'` this fetch itself performs that next request (to
  // read the flash into a response body we then discard), so the visitor's
  // own subsequent navigation lands on a request where the flash was
  // already consumed and shows nothing. `redirect: 'manual'` stops fetch
  // from following the redirect at all — the response resolves opaque
  // (type 'opaqueredirect', status 0) on the expected PRG path — and a
  // single `reload()` becomes the one request that actually reads the
  // flash. Set-Cookie headers from the POST (including a rotated CSRF
  // token) are still applied by the browser before that reload fires.
  const response = await fetch(form.action, {
    method: 'POST',
    body: payload,
    credentials: 'same-origin',
    headers: {
      Accept: 'text/html',
      [csrfHeaderName]: csrfToken,
    },
    redirect: 'manual',
  });

  if (response.type !== 'opaqueredirect' && !response.ok) {
    throw new Error(`form_submit_${response.status}`);
  }

  window.location.reload();
};

const bindForm = (form) => {
  if (form.dataset.csrfBound === '1') return;

  form.dataset.csrfBound = '1';
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (form.dataset.submitting === '1') return;

    setPending(form, true);
    submitForm(form)
      .catch(() => {
        setPending(form, false);
        showError(form);
      });
  });
};

export const initPublicForms = () => {
  document.querySelectorAll('form[data-public-form]').forEach(bindForm);
};
