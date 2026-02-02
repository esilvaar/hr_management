(function(){
    'use strict';

    // Sidebar refresh: no-op placeholder to avoid 404 if not implemented.
    // If you need live refresh behavior, implement AJAX polling here using hrmSidebarData.ajaxUrl
    document.addEventListener('DOMContentLoaded', function(){
        if (typeof console !== 'undefined' && console.debug) console.debug('[HRM] sidebar-refresh loaded (no-op)');
    });
})();
