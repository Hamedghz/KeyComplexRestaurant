(function () {
  'use strict';

  var endpoint = '/api/analytics-track.php';
  var visitorKey = 'key_analytics_visitor_uuid';
  var sessionKey = 'key_analytics_session_uuid';
  var lastViewKeyPrefix = 'key_analytics_last_pageview:';
  var duplicateWindowMs = 3000;

  function debugEnabled() {
    try {
      return new URLSearchParams(window.location.search || '').get('debug_analytics') === '1';
    } catch (error) {
      return false;
    }
  }

  var debug = debugEnabled();

  function debugLog() {
    if (debug && window.console && typeof window.console.log === 'function') {
      window.console.log.apply(window.console, arguments);
    }
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

  function storageGet(storage, key) {
    try {
      return storage.getItem(key);
    } catch (error) {
      return null;
    }
  }

  function storageSet(storage, key, value) {
    try {
      storage.setItem(key, value);
    } catch (error) {
      // Analytics must not affect the frontend.
    }
  }

  function ignoredPath(path) {
    var cleanPath = '/' + String(path || '/').replace(/^\/+/, '');
    var ignoredPrefixes = ['/admin/', '/api/', '/assets/', '/uploads/'];
    if (cleanPath === '/admin' || cleanPath === '/api' || cleanPath === '/assets' || cleanPath === '/uploads') {
      return true;
    }
    if (cleanPath === '/install.php') {
      return true;
    }
    return ignoredPrefixes.some(function (prefix) {
      return cleanPath.indexOf(prefix) === 0;
    });
  }

  function getStoredUuid(storage, key) {
    var value = storageGet(storage, key);
    if (!value) {
      value = uuid();
      storageSet(storage, key, value);
    }
    return value;
  }

  function isDuplicatePageview(sessionUuid, path) {
    var key = lastViewKeyPrefix + sessionUuid + ':' + path;
    var now = Date.now();
    var last = parseInt(storageGet(window.sessionStorage, key) || '0', 10);
    if (last && now - last < duplicateWindowMs) {
      return true;
    }
    storageSet(window.sessionStorage, key, String(now));
    return false;
  }

  function buildPayload(visitorUuid, sessionUuid, path) {
    var params = new URLSearchParams(window.location.search || '');
    return {
      visitor_uuid: visitorUuid,
      session_uuid: sessionUuid,
      page_url: window.location.href,
      page_path: path,
      page_title: document.title || '',
      referrer: document.referrer || '',
      utm_source: params.get('utm_source') || '',
      utm_medium: params.get('utm_medium') || '',
      utm_campaign: params.get('utm_campaign') || '',
      utm_term: params.get('utm_term') || '',
      utm_content: params.get('utm_content') || '',
      screen_width: window.screen && window.screen.width ? window.screen.width : null,
      screen_height: window.screen && window.screen.height ? window.screen.height : null,
      language: navigator.language || '',
      timezone: (window.Intl && Intl.DateTimeFormat) ? (Intl.DateTimeFormat().resolvedOptions().timeZone || '') : ''
    };
  }

  function sendBeaconFallback(body) {
    if (!navigator.sendBeacon) {
      return;
    }
    try {
      var blob = new Blob([body], { type: 'application/json' });
      var queued = navigator.sendBeacon(endpoint, blob);
      debugLog('[analytics] beacon fallback', { endpoint: endpoint, queued: queued });
    } catch (error) {
      if (debug) {
        debugLog('[analytics] beacon fallback failed', error);
      }
    }
  }

  function sendPayload(payload) {
    var body = JSON.stringify(payload);
    debugLog('[analytics] payload', payload);

    if (window.fetch) {
      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: body,
        keepalive: true,
        credentials: 'same-origin'
      })
        .then(function (response) {
          if (!debug) {
            return null;
          }
          return response.text().then(function (text) {
            var parsed = null;
            try {
              parsed = text ? JSON.parse(text) : null;
            } catch (error) {
              parsed = text;
            }
            debugLog('[analytics] response', {
              endpoint: endpoint,
              status: response.status,
              ok: response.ok,
              body: parsed
            });
            return null;
          });
        })
        .catch(function (error) {
          if (debug) {
            debugLog('[analytics] fetch failed, trying beacon', error);
          }
          sendBeaconFallback(body);
        });
      return;
    }

    sendBeaconFallback(body);
  }

  try {
    var path = window.location.pathname || '/';
    if (ignoredPath(path)) {
      debugLog('[analytics] ignored path', path);
      return;
    }

    var visitorUuid = getStoredUuid(window.localStorage, visitorKey);
    var sessionUuid = getStoredUuid(window.sessionStorage, sessionKey);

    if (isDuplicatePageview(sessionUuid, path)) {
      debugLog('[analytics] duplicate pageview skipped', { session_uuid: sessionUuid, page_path: path });
      return;
    }

    sendPayload(buildPayload(visitorUuid, sessionUuid, path));
  } catch (error) {
    if (debug) {
      debugLog('[analytics] failed', error);
    }
  }
})();
