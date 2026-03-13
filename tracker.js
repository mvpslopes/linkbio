/**
 * LinkBio Analytics Tracker v2
 * Inclua na página do cliente:
 *   <script src="https://linkbio.app.br/tracker.js" data-slug="paty"></script>
 */
(function () {
  const API  = 'https://linkbio.app.br/admin/api/track.php';
  const slug = document.currentScript?.dataset?.slug || '';
  if (!slug) return;

  // ── Detecção de browser ──────────────────────────────────
  function detectBrowser(ua) {
    if (/Edg\//i.test(ua))           return 'Edge';
    if (/OPR\/|Opera/i.test(ua))     return 'Opera';
    if (/SamsungBrowser/i.test(ua))  return 'Samsung';
    if (/Chrome\/[0-9]/i.test(ua) && !/Chromium/i.test(ua)) return 'Chrome';
    if (/Firefox\/[0-9]/i.test(ua))  return 'Firefox';
    if (/Safari\/[0-9]/i.test(ua) && !/Chrome/i.test(ua))   return 'Safari';
    if (/MSIE|Trident/i.test(ua))    return 'IE';
    return 'Unknown';
  }

  // ── Detecção de OS ───────────────────────────────────────
  function detectOS(ua) {
    if (/Windows NT 10/i.test(ua))   return 'Windows 10';
    if (/Windows NT 11/i.test(ua))   return 'Windows 11';
    if (/Windows/i.test(ua))         return 'Windows';
    if (/iPhone|iPad/i.test(ua))     return 'iOS';
    if (/Android/i.test(ua))         return 'Android';
    if (/Mac OS X/i.test(ua) && !/iPhone|iPad/i.test(ua)) return 'macOS';
    if (/Linux/i.test(ua))           return 'Linux';
    return 'Unknown';
  }

  const ua      = navigator.userAgent;
  const browser = detectBrowser(ua);
  const os      = detectOS(ua);

  function send(payload) {
    const data = JSON.stringify(payload);
    if (navigator.sendBeacon) {
      navigator.sendBeacon(API, data);
    } else {
      fetch(API, { method: 'POST', body: data, keepalive: true }).catch(() => {});
    }
  }

  // ── Pageview ─────────────────────────────────────────────
  send({
    type    : 'pageview',
    slug,
    referrer: document.referrer || '',
    browser,
    os,
  });

  // ── Cliques em links e botões ────────────────────────────
  document.addEventListener('click', function (e) {
    const el = e.target.closest('a, button, [data-track]');
    if (!el) return;

    const text = (el.innerText || el.getAttribute('aria-label') || el.getAttribute('data-track') || '').trim().slice(0, 100);
    const type = el.tagName.toLowerCase();
    const href = el.href || '';

    send({
      type        : 'click',
      slug,
      text,
      element_type: type,
      target_url  : href,
    });
  }, true);
})();
