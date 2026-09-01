/**
 * Se la sessione socio è attiva ma la pagina è ancora la versione cached "guest",
 * forza un reload bypassando la Dynamic Cache.
 */
(function () {
  try {
    if (document.cookie.indexOf('cral_logged=1') === -1) {
      return;
    }
    if (document.querySelector('.cral-cal') || document.querySelector('[data-cral-logout]')) {
      return;
    }
    if (!document.querySelector('#cral-login-form') && !document.querySelector('.cral-login-wrap')) {
      return;
    }
    var url = new URL(window.location.href);
    if (url.searchParams.get('cral_r')) {
      return;
    }
    url.searchParams.set('cral_r', String(Date.now()));
    window.location.replace(url.toString());
  } catch (e) {}
})();
