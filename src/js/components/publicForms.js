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

  if (button) {
    button.disabled = pending;
    button.setAttribute('aria-busy', pending ? 'true' : 'false');
  }
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

  const response = await fetch(form.action, {
    method: 'POST',
    body: payload,
    credentials: 'same-origin',
    headers: {
      Accept: 'text/html',
      [csrfHeaderName]: csrfToken,
    },
    redirect: 'follow',
  });

  if (!response.ok) throw new Error(`form_submit_${response.status}`);

  window.location.assign(response.url || form.action);
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
