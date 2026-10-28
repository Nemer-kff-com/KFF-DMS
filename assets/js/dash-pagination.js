document.addEventListener('DOMContentLoaded', function () {
  var dashRoot = document.querySelector('[data-dash-panels]');
  if (!dashRoot) {
    return;
  }

  var sectionIds = [
    'dash-action-in',
    'dash-action-out',
    'dash-action-internal',
    'dash-recent-in',
    'dash-recent-out',
    'dash-recent-internal',
    'dash-overdue-in',
    'dash-overdue-out',
    'dash-overdue-internal',
  ];
  var loading = false;

  function panelBody(id) {
    var root = document.getElementById(id);
    return root ? root.querySelector('.dash-panel-body') : null;
  }

  function setLoading(id, on) {
    var body = panelBody(id);
    if (body) {
      body.classList.toggle('is-loading', !!on);
    }
  }

  function replaceFromDoc(doc, id) {
    var src = doc.getElementById(id);
    var dst = document.getElementById(id);
    if (!src || !dst) {
      return;
    }
    var srcBody = src.querySelector('.dash-panel-body');
    var dstBody = dst.querySelector('.dash-panel-body');
    if (srcBody && dstBody) {
      dstBody.innerHTML = srcBody.innerHTML;
    }
  }

  function syncDashPanels(url, activeId) {
    if (loading) {
      return Promise.resolve();
    }
    loading = true;
    if (activeId) {
      setLoading(activeId, true);
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
        sectionIds.forEach(function (id) {
          replaceFromDoc(doc, id);
        });
      })
      .catch(function () {
        window.location.assign(fetchUrl);
      })
      .finally(function () {
        loading = false;
        if (activeId) {
          setLoading(activeId, false);
        }
      });
  }

  dashRoot.addEventListener('click', function (event) {
    var link = event.target.closest('.dash-pagination a.page-link[href]');
    if (!link || link.closest('.page-item.disabled')) {
      return;
    }

    var href = link.getAttribute('href');
    if (!href || href.charAt(0) === '#') {
      return;
    }

    var section = link.closest('[id^="dash-"]');
    var activeId = section ? section.id : null;

    event.preventDefault();
    var cleanUrl = href.split('#')[0];

    syncDashPanels(cleanUrl, activeId).then(function () {
      history.pushState({ dashPanels: true }, '', cleanUrl);
    });
  });

  window.addEventListener('popstate', function () {
    if (!document.querySelector('[data-dash-panels]')) {
      return;
    }
    syncDashPanels(location.href, null);
  });
});
