document.addEventListener('DOMContentLoaded', function () {
  var panels = Array.prototype.slice.call(document.querySelectorAll('[data-list-panel]'));
  if (!panels.length) {
    return;
  }

  var loading = false;

  function panelBody(panel) {
    return panel.querySelector('.list-panel-body');
  }

  function setLoading(panel, on) {
    var body = panelBody(panel);
    if (body) {
      body.classList.toggle('is-loading', !!on);
    }
  }

  // المحتوى المستبدل يفقد تلميحات bootstrap، فتُعاد تهيئتها بعد كل استبدال.
  function initTooltips(scope) {
    if (!window.bootstrap || !bootstrap.Tooltip) {
      return;
    }
    scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      var isBtn = el.matches('button, a.btn, .btn, label.btn');
      var place = el.getAttribute('data-bs-placement') || '';
      var opts = {};
      if (isBtn || place === 'bottom' || place === 'left' || place === 'right') {
        opts.placement = 'top';
        opts.fallbackPlacements = ['top'];
        el.setAttribute('data-bs-placement', 'top');
      }
      bootstrap.Tooltip.getOrCreateInstance(el, opts);
    });
  }

  function replacePanelFromDoc(doc, panel) {
    if (!panel.id) {
      return;
    }
    var src = doc.getElementById(panel.id);
    if (!src) {
      return;
    }
    var srcBody = src.querySelector('.list-panel-body');
    var dstBody = panelBody(panel);
    if (srcBody && dstBody) {
      dstBody.innerHTML = srcBody.innerHTML;
    }
    if (typeof window.dmsIconifyButtons === 'function') {
      window.dmsIconifyButtons(panel);
    }
  }

  function syncListPanels(url, activePanel) {
    if (loading) {
      return Promise.resolve();
    }
    loading = true;
    if (activePanel) {
      setLoading(activePanel, true);
    }

    var fetchUrl = String(url || location.href).split('#')[0];

    return fetch(fetchUrl, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('fetch failed');
        }
        return response.text();
      })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        panels.forEach(function (panel) {
          replacePanelFromDoc(doc, panel);
          initTooltips(panel);
        });
      })
      .catch(function () {
        window.location.assign(fetchUrl);
      })
      .finally(function () {
        loading = false;
        if (activePanel) {
          setLoading(activePanel, false);
        }
      });
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest('[data-list-panel] .dash-pagination a.page-link[href]');
    if (!link || link.closest('.page-item.disabled')) {
      return;
    }

    var panel = link.closest('[data-list-panel]');
    if (!panel) {
      return;
    }

    var href = link.getAttribute('href');
    if (!href || href.charAt(0) === '#') {
      return;
    }

    event.preventDefault();
    var cleanUrl = href.split('#')[0];

    syncListPanels(cleanUrl, panel).then(function () {
      history.pushState({ listPanels: true }, '', cleanUrl);
    });
  });

  window.addEventListener('popstate', function () {
    if (!document.querySelector('[data-list-panel]')) {
      return;
    }
    syncListPanels(location.href, null);
  });
});
