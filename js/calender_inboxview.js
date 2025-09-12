/* calender_inboxview: client-side UI with debug logging */
(function() {
// --- bottom-dock helpers (without touching skin CSS files) ---
function injectDockCSS(){
  if (document.getElementById('ci-dock-css')) return;
  var css = [
    '#folderlist-content, .scroller.withfooter{display:flex !important; flex-direction:column !important; overflow:hidden !important; min-height:0 !important;}',
    '#folderlist-content > #mailboxlist, .scroller.withfooter > #mailboxlist{flex:1 1 auto !important; overflow:auto !important; min-height:0 !important;}',
    '#ci-upcoming{margin-top:auto !important; flex:0 0 auto !important; position:relative !important; z-index:0 !important;}'
  ].join('\n');
  var style = document.createElement('style');
  style.id = 'ci-dock-css';
  style.type = 'text/css';
  style.appendChild(document.createTextNode(css));
  (document.head || document.documentElement).appendChild(style);
}
function dockIntoFolderList(panel){
  try {
    var content = document.querySelector('#folderlist-content.scroller.withfooter') ||
                  document.querySelector('#folderlist-content') ||
                  document.querySelector('.scroller.withfooter');
    var mailbox = document.getElementById('mailboxlist');
    if (content && mailbox && panel) {
      injectDockCSS();
      if (panel.parentNode !== content || content.lastElementChild !== panel) {
        try { if (panel.parentNode && panel.parentNode !== content) panel.parentNode.removeChild(panel); } catch(e){}
        content.appendChild(panel);
        log('panel_docked_bottom');
      }
      return true;
    }
  } catch(e) { log('dock_error', e); }
  return false;
}
function observeDock(panel){
  var mo = new MutationObserver(function(){ dockIntoFolderList(panel); });
  mo.observe(document.body, {childList:true, subtree:true});
}

  function log() {
    try {
      if (window.rcmail && rcmail.env && rcmail.env.ci_debug) {
        var a = Array.prototype.slice.call(arguments);
        a.unshift('[calender_inboxview]');
        console.log.apply(console, a);
      }
    } catch(e) {}
  }

  function findMountPoint() {
    var selectors = ['#mailboxlist', '#directorylist', '#mailview-left', '#sidebar', /*'#messagelistcontainer'*/];
    for (var i=0; i<selectors.length; i++) {
      var el = document.querySelector(selectors[i]);
      if (el) return el;
    }
    return null;
  }

  function hashCode(str) {
    var h = 5381, i = str ? str.length : 0;
    if (!str) return 0;
    while (i) { h = (h * 33) ^ str.charCodeAt(--i); }
    return h >>> 0;
  }
  function colorFrom(ev) {
    if (ev && ev.color) return '' + ev.color;
    var key = (ev && (ev.src || ev.title)) || 'x';
    var h = hashCode(key) % 360;
    return 'hsl(' + h + ',60%,45%)';
  }
  function renderPanel(root) {
    if (!root) { log('no_mountpoint_abort'); return null; }
    if (document.getElementById('ci-upcoming')) return;
    var wrap = document.createElement('div');
    wrap.id = 'ci-upcoming';
    wrap.className = 'ci-panel';
    wrap.innerHTML = [
      '<div class="ci-hdr">',
        '<span class="ci-title"></span>',
        '<a href="#" class="ci-open-cal" title="Open Calendar" aria-label="Open Calendar">&boxbox;',
      '</div>',
      '<ul class="ci-list" role="list"></ul>',
      '<div class="ci-empty"></div>'
    ].join('');

    var title = (window.rcmail && rcmail.env && rcmail.env.ci_panel_title) || (rcmail && rcmail.gettext && rcmail.gettext('ci_panel_title','calender_inboxview')) || 'Upcoming Events';
    wrap.querySelector('.ci-title').textContent = title;

    wrap.querySelector('.ci-open-cal').addEventListener('click', function(e){
      e.preventDefault();
      if (window.rcmail) rcmail.command('switch-task', 'calendar');
    });

    if (!dockIntoFolderList(wrap)) { root.parentNode.insertBefore(wrap, root.nextSibling); }
    return wrap;
  }

  function esc(s) {
    if (!s && s !== 0) return '';
    var t = document.createElement('div');
    t.textContent = '' + s;
    return t.innerHTML;
  }

  function fmtTime(iso) {
    if (!iso) return '';
    try {
      var dt = new Date(iso);
      return dt.toLocaleString(undefined, {weekday:'short', month:'short', day:'numeric', hour:'numeric', minute:'2-digit'});
    } catch(e) {
      return iso;
    }
  }

  function renderEvents(wrap, events) {
    var ul = wrap.querySelector('.ci-list');
    var empty = wrap.querySelector('.ci-empty');
    ul.innerHTML = '';

    if (!events || !events.length) {
      var emptyLabel = (rcmail && rcmail.gettext) ? rcmail.gettext('ci_no_events', 'calender_inboxview') : 'No upcoming events';
      empty.textContent = emptyLabel;
      wrap.classList.add('ci--empty');
      return;
    }
    wrap.classList.remove('ci--empty');
    empty.textContent = '';

    events.slice(0, 8).forEach(function(ev) {
      var li = document.createElement('li');
      li.className = 'ci-item';
      var when = fmtTime(ev.start);
      var title = ev.title || '(untitled)';
      var tt = title + ' — ' + when + (ev.location ? (' — ' + ev.location) : '');
      var dot = '<span class="ci-dot" style="background:'+esc(colorFrom(ev))+'"></span>'; 
      li.setAttribute('title', tt);
      li.innerHTML = '<div class="ci-when">' + esc(when) + '</div>' +
                     '<div class="ci-titleline">' + dot + esc(title) + '</div>' +
                     (ev.location ? '<div class="ci-loc">' + esc(ev.location) + '</div>' : '');
      li.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); });
      ul.appendChild(li);
    });
  }

  function fetchEvents(days) {
    if (!window.rcmail) return;
    var d = days || rcmail.env.ci_days_ahead || 7;
    log('fetchEvents', d);
    rcmail.http_post('plugin.calender_inboxview.fetch', { days: d });
  }

  if (window.rcmail) {
    rcmail.addEventListener('init', function() {
      if (rcmail.env.task !== 'mail') return;
      if (!rcmail.env.ci_display) { log('display disabled'); return; }

      log('env', rcmail.env.ci_days_ahead, rcmail.env.ci_panel_title);
      var mount = findMountPoint();
      var panel = renderPanel(mount);
      if (!panel) { log('panel_not_mounted'); return; }
      if (rcmail.env.ci_compact) panel.classList.add('ci--compact');
      dockIntoFolderList(panel);
      observeDock(panel);
      fetchEvents(rcmail.env.ci_days_ahead || 7);

      rcmail.addEventListener('plugin.ci_events', function(resp) {
        log('events_resp', resp);
        try {
          renderEvents(panel, resp && resp.events || []);
        } catch(e) {
          log('render_error', e);
        }
      });
    });
  }
})();
