document.addEventListener('DOMContentLoaded', function () {
  var tablist = document.querySelectorAll('.rrhh-tablist li');
  var panes = document.querySelectorAll('.rrhh-tabpane');

  tablist.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var target = tab.getAttribute('data-tab');

      // desactivar tabs
      tablist.forEach(function (t) { t.classList.remove('active'); });
      panes.forEach(function (p) { p.classList.remove('active'); });

      // activar seleccion
      tab.classList.add('active');
      var pane = document.getElementById(target);
      if (pane) pane.classList.add('active');
    });
  });

  // Si hay ?rrhh_tab= en la URL, activar esa pestaña
  function getQueryParam(name) {
    name = name.replace(/[\[\]]/g, '\\$&');
    var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
        results = regex.exec(window.location.href);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, ' '));
  }

  var initial = getQueryParam('rrhh_tab');
  if (initial) {
    var initialTab = document.querySelector('.rrhh-tablist li[data-tab="' + initial + '"]');
    if (initialTab) {
      initialTab.click();
      // Remove param from URL without reloading
      try {
        var url = new URL(window.location.href);
        url.searchParams.delete('rrhh_tab');
        window.history.replaceState({}, document.title, url.toString());
      } catch (e) {}
    }
  }
  
  // Simple tab-pills behavior for the mockup (.tab-pill)
  var tabPills = document.querySelectorAll('.tab-pill');
  if ( tabPills.length ) {
    tabPills.forEach(function(p){
      p.addEventListener('click', function(){
        tabPills.forEach(function(x){ x.classList.remove('active'); });
        p.classList.add('active');
      });
    });
  }
  
  // Tabs in mockup (.tab) with panes (.tab-pane)
  var tabs = document.querySelectorAll('.tab');
  var panes = document.querySelectorAll('.tab-pane');
  if ( tabs.length ) {
    tabs.forEach(function(t){
      t.addEventListener('click', function(){
        tabs.forEach(function(x){ x.classList.remove('active'); });
        panes.forEach(function(p){ p.classList.remove('active'); });
        t.classList.add('active');
        var target = t.getAttribute('data-tab');
        if ( target ) {
          var pane = document.getElementById(target);
          if ( pane ) pane.classList.add('active');
        } else {
          // fallback: show pane by index
          var idx = Array.prototype.indexOf.call(tabs, t);
          if ( panes[idx] ) panes[idx].classList.add('active');
        }
      });
    });
  }

  // Sidebar menu links that target panes
  var menuLinks = document.querySelectorAll('.menu-link');
  if ( menuLinks.length ) {
    menuLinks.forEach(function(link){
      link.addEventListener('click', function(e){
        e.preventDefault();
        var targetId = link.getAttribute('data-tab');
        if ( ! targetId ) return;
        // Try to find a .tab that maps to it and click it, otherwise show pane directly
        var tabElem = document.querySelector('.tab[data-tab="' + targetId + '"]');
        if ( tabElem ) {
          tabElem.click();
          return;
        }
        // deactivate all
        tabs.forEach(function(x){ x.classList.remove('active'); });
        panes.forEach(function(p){ p.classList.remove('active'); });
        // activate pane
        var pane = document.getElementById(targetId);
        if ( pane ) pane.classList.add('active');
      });
    });
  }

  // Simple calendar click-to-prefill behavior
  var calendarDays = document.querySelectorAll('.calendar-day');
  if ( calendarDays.length ) {
    calendarDays.forEach(function(day){
      day.addEventListener('click', function(){
        var date = day.getAttribute('data-date');
        if ( ! date ) return;
        var startInput = document.querySelector('input[name="start_date"]');
        var endInput = document.querySelector('input[name="end_date"]');
        if ( startInput && endInput ) {
          // If start empty, set start. Otherwise set end.
          if ( ! startInput.value ) {
            startInput.value = date;
          } else {
            endInput.value = date;
          }
          // compute days automatically when prefilling
          computeVacationDays();
          // switch to Vacaciones tab if present
          var vacTab = document.querySelector('.tab[data-tab="tab-vacaciones"]');
          if ( vacTab ) vacTab.click();
        }
      });
    });
  }

  // Compute vacation days from start/end (inclusive) and populate days input
  function computeVacationDays() {
    var startInput = document.querySelector('input[name="start_date"]');
    var endInput = document.querySelector('input[name="end_date"]');
    var daysInput = document.querySelector('input[name="days"]');
    var note = document.querySelector('.calculated-days-note');
    if ( ! startInput || ! endInput || ! daysInput ) return;
    var s = startInput.value;
    var e = endInput.value;
    if ( ! s || ! e ) {
      if ( note ) note.textContent = '';
      return;
    }
    var sd = new Date(s + 'T00:00:00');
    var ed = new Date(e + 'T00:00:00');
    if ( isNaN(sd.getTime()) || isNaN(ed.getTime()) ) {
      if ( note ) note.textContent = '';
      return;
    }
    // Count business days (Mon-Fri) inclusive
    var days = 0;
    if ( ed >= sd ) {
      var cur = new Date(sd);
      while ( cur <= ed ) {
        var dow = cur.getDay(); // 0 = Sun, 6 = Sat
        if ( dow !== 0 && dow !== 6 ) days++;
        cur.setDate( cur.getDate() + 1 );
      }
    }
    // allow half-days only if user edits manually; by default set full days
    daysInput.value = days > 0 ? days : '';
    if ( note ) {
      note.textContent = days > 0 ? ('Días hábiles calculados: ' + days) : '';
    }
  }

  // Listen to changes on date inputs to auto-calc days
  var startField = document.querySelector('input[name="start_date"]');
  var endField = document.querySelector('input[name="end_date"]');
  if ( startField ) startField.addEventListener('change', computeVacationDays);
  if ( endField ) endField.addEventListener('change', computeVacationDays);
});
