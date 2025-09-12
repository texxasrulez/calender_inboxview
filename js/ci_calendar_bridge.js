// calender_inboxview: bridge to open a specific event when we land in Calendar task
(function(){
  var payload = null;
  try { payload = JSON.parse(localStorage.getItem('ci_open_event') || 'null'); } catch(e){ payload = null; }
  if (!payload) return;
  localStorage.removeItem('ci_open_event');

  function gotoDate(d) {
    try {
      if (window.$ && rcmail && rcmail.gui_objects && rcmail.gui_objects.calendar) {
        var $cal = $(rcmail.gui_objects.calendar);
        if ($cal && $cal.fullCalendar) {
          $cal.fullCalendar('gotoDate', d);
        }
      }
    } catch(e){}
  }

  function tryOpen() {
    var want = (payload.title || '').trim();
    var when = payload.start ? new Date(payload.start) : null;
    if (when) gotoDate(when);

    // Look for FullCalendar rendered events
    var nodes = document.querySelectorAll('.fc-event');
    var found = null;
    for (var i=0; i<nodes.length; i++) {
      var el = nodes[i];
      var text = (el.textContent || '').trim();
      if (want && text.indexOf(want) !== -1) { found = el; break; }
    }
    if (found) {
      try {
        found.dispatchEvent(new MouseEvent('click', {bubbles:true, cancelable:true}));
      } catch(e) {
        found.click();
      }
      return true;
    }
    return false;
  }

  var tries = 0;
  var ticker = setInterval(function(){
    tries++;
    if (tryOpen() || tries > 40) { // ~10s
      clearInterval(ticker);
    }
  }, 250);
})();