/**
 * Auto-attach session CSRF token to same-origin AJAX (fetch / XHR).
 * Requires window.DMS_CSRF_TOKEN from csrf_head_tags().
 */
(function (window) {
    'use strict';

    var token = String(window.DMS_CSRF_TOKEN || '');
    if (!token) {
        return;
    }

    function appendBodyToken(body) {
        if (body == null || body === '') {
            return 'csrf_token=' + encodeURIComponent(token);
        }
        if (typeof body === 'string') {
            if (/[?&]csrf_token=/.test(body)) {
                return body;
            }
            return body + (body.length ? '&' : '') + 'csrf_token=' + encodeURIComponent(token);
        }
        if (typeof FormData !== 'undefined' && body instanceof FormData) {
            if (!body.has('csrf_token')) {
                body.append('csrf_token', token);
            }
            return body;
        }
        if (typeof URLSearchParams !== 'undefined' && body instanceof URLSearchParams) {
            if (!body.has('csrf_token')) {
                body.append('csrf_token', token);
            }
            return body;
        }
        return body;
    }

    if (window.XMLHttpRequest && window.XMLHttpRequest.prototype) {
        var origOpen = window.XMLHttpRequest.prototype.open;
        var origSend = window.XMLHttpRequest.prototype.send;

        window.XMLHttpRequest.prototype.open = function (method) {
            this.__dmsMethod = String(method || 'GET').toUpperCase();
            return origOpen.apply(this, arguments);
        };

        window.XMLHttpRequest.prototype.send = function (body) {
            if (this.__dmsMethod && this.__dmsMethod !== 'GET' && this.__dmsMethod !== 'HEAD') {
                try {
                    this.setRequestHeader('X-CSRF-Token', token);
                } catch (e) {}
                body = appendBodyToken(body);
            }
            return origSend.call(this, body);
        };
    }

    if (typeof window.fetch === 'function') {
        var origFetch = window.fetch;
        window.fetch = function (input, init) {
            init = init || {};
            var method = String(init.method || 'GET').toUpperCase();
            if (method !== 'GET' && method !== 'HEAD') {
                var headers = new Headers(init.headers || {});
                if (!headers.has('X-CSRF-Token')) {
                    headers.set('X-CSRF-Token', token);
                }
                init.headers = headers;
                if (init.body == null) {
                    init.body = 'csrf_token=' + encodeURIComponent(token);
                    if (!headers.has('Content-Type')) {
                        headers.set('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
                    }
                } else if (typeof init.body === 'string') {
                    init.body = appendBodyToken(init.body);
                } else {
                    init.body = appendBodyToken(init.body);
                }
            }
            return origFetch.call(this, input, init);
        };
    }

    window.dmsCsrfToken = function () {
        return token;
    };
})(window);
