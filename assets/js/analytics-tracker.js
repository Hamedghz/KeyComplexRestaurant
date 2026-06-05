(function () {
  try {
    var path = window.location.pathname || '/';
    var ignoredPrefixes = ['/admin/', '/api/', '/assets/', '/uploads/'];
    if (path === '/install.php' || ignoredPrefixes.some(function (prefix) { return path.indexOf(prefix) === 0; })) {
      return;
    }

    function uuid() {
      if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
      }
      return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        var r = Math.random() * 16 | 0;
        var v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
      });
    }

    var visitorKey = 'key_analytics_visitor_uuid';
    var sessionKey = 'key_analytics_session_uuid';
    var visitorUuid = window.localStorage.getItem(visitorKey);
    if (!visitorUuid) {
      visitorUuid = uuid();
      window.localStorage.setItem(visitorKey, visitorUuid);
    }
    var sessionUuid = window.sessionStorage.getItem(sessionKey);
    if (!sessionUuid) {
      sessionUuid = uuid();
      window.sessionStorage.setItem(sessionKey, sessionUuid);
    }

    var params = new URLSearchParams(window.location.search || '');
    var payload = {
      visitor_uuid: visitorUuid,
      session_uuid: sessionUuid,
      page_url: window.location.href,
      page_path: path,
      page_title: document.title || '',
      referrer: document.referrer || '',
      screen_width: window.screen && window.screen.width ? window.screen.width : null,
      screen_height: window.screen && window.screen.height ? window.screen.height : null,
      language: navigator.language || '',
      timezone: (window.Intl && Intl.DateTimeFormat) ? Intl.DateTimeFormat().resolvedOptions().timeZone || '' : '',
      utm_source: params.get('utm_source') || '',
      utm_medium: params.get('utm_medium') || '',
      utm_campaign: params.get('utm_campaign') || '',
      utm_term: params.get('utm_term') || '',
      utm_content: params.get('utm_content') || ''
    };

    var body = JSON.stringify(payload);
    var endpoint = 'api/analytics-track.php';
    if (navigator.sendBeacon) {
      var blob = new Blob([body], { type: 'application/json' });
      if (navigator.sendBeacon(endpoint, blob)) {
        return;
      }
    }
    if (window.fetch) {
      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: body,
        keepalive: true,
        credentials: 'same-origin'
      }).catch(function () {});
    }
  } catch (error) {
    // Tracking must never create visible frontend errors.
  }
})();
