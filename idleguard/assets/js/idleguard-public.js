(function(){
    'use strict';

    if ( typeof IdleGuardSettings === 'undefined' ) {
        return;
    }

    var timeout = parseInt( IdleGuardSettings.timeout, 10 ) || 900;
    var warning = parseInt( IdleGuardSettings.warning_duration, 10 ) || 30;
    var ajaxUrl = IdleGuardSettings.ajax_url;
    var nonce = IdleGuardSettings.nonce;
    var redirectUrl = IdleGuardSettings.redirect_url || '/';

    var idleTimer = null;
    var countdownTimer = null;
    var lastActivity = Date.now();
    var channel = null;
    var isModalVisible = false;

    function init() {
        bindActivityEvents();
        startIdleTimer();
        initMultiTab();
        document.addEventListener('visibilitychange', function(){
            if ( document.visibilityState === 'visible' ) {
                broadcastActivity();
            }
        });
        window.addEventListener('beforeunload', function(){
            // optionally notify other tabs
            broadcastActivity();
        });
    }

    function bindActivityEvents() {
        var events = ['mousemove','keydown','scroll','click','touchstart'];
        var handler = throttle(function(){
            lastActivity = Date.now();
            resetIdleTimer();
            broadcastActivity();
        }, 500);
        events.forEach(function(ev){
            window.addEventListener(ev, handler, {passive:true});
        });
    }

    function startIdleTimer() {
        clearTimeout(idleTimer);
        var ms = timeout * 1000;
        idleTimer = setTimeout(onIdle, ms);
    }

    function resetIdleTimer() {
        if ( isModalVisible ) {
            hideModal();
        }
        startIdleTimer();
    }

    function onIdle() {
        // show warning modal with countdown
        showModal(warning);
    }

    function showModal(seconds) {
        isModalVisible = true;
        var modal = buildModal();
        document.body.appendChild(modal);
        var countdown = seconds;
        updateCountdown(countdown);
        countdownTimer = setInterval(function(){
            countdown -= 1;
            if ( countdown <= 0 ) {
                clearInterval(countdownTimer);
                doLogout();
            } else {
                updateCountdown(countdown);
            }
        }, 1000);
    }

    function hideModal(){
        isModalVisible = false;
        var el = document.getElementById('idleguard-modal');
        if ( el ) el.remove();
        if ( countdownTimer ) { clearInterval(countdownTimer); countdownTimer = null; }
    }

    function updateCountdown(sec){
        var el = document.getElementById('idleguard-countdown');
        if ( el ) el.textContent = sec;
    }

    function buildModal(){
        var container = document.createElement('div');
        container.id = 'idleguard-modal';
        container.className = 'idleguard-modal';
        container.innerHTML = '\n            <div class="idleguard-modal-inner">\n                <h2>Session Expiring</h2>\n                <p>Your session will expire in <span id="idleguard-countdown">'+warning+'</span> seconds.</p>\n                <div class="idleguard-actions">\n                    <button id="idleguard-stay" class="button">Stay Logged In</button>\n                    <button id="idleguard-logout" class="button button-secondary">Log Out Now</button>\n                </div>\n            </div>';
        // accessibility
        container.setAttribute('role','dialog');
        container.setAttribute('aria-modal','true');
        setTimeout(function(){
            var stay = document.getElementById('idleguard-stay');
            if ( stay ) stay.focus();
        },50);
        container.addEventListener('click', function(e){
            if ( e.target === container ) hideModal();
        });
        setTimeout(function(){
            var stayBtn = container.querySelector('#idleguard-stay');
            var logoutBtn = container.querySelector('#idleguard-logout');
            if ( stayBtn ) stayBtn.addEventListener('click', onStay );
            if ( logoutBtn ) logoutBtn.addEventListener('click', doLogout );
        }, 10);
        return container;
    }

    function onStay(){
        // send keepalive and hide modal
        ajaxPost('idleguard_keepalive', {nonce:nonce}, function(){
            hideModal();
            resetIdleTimer();
            broadcastActivity();
        });
    }

    function doLogout(){
        ajaxPost('idleguard_logout', {nonce:nonce}, function(){
            // cleanup and redirect
            localStorage.setItem('idleguard_logout', Date.now());
            window.location.href = redirectUrl;
        });
    }

    function ajaxPost(action, data, cb){
        var body = new FormData();
        body.append('action', action);
        for ( var k in data ) { body.append(k, data[k]); }

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function(resp){
            return resp.json();
        }).then(function(json){
            if ( json.success ) {
                if ( typeof cb === 'function' ) cb(json.data);
            }
        }).catch(function(){
            // network error — still attempt redirect
            if ( action === 'idleguard_logout' ) {
                window.location.href = redirectUrl;
            }
        });
    }

    function initMultiTab(){
        try {
            if ( 'BroadcastChannel' in window ) {
                channel = new BroadcastChannel('idleguard_channel');
                channel.addEventListener('message', function(e){
                    if ( e.data && e.data.type === 'activity' ) {
                        lastActivity = Date.now();
                        resetIdleTimer();
                    }
                    if ( e.data && e.data.type === 'logout' ) {
                        window.location.href = redirectUrl;
                    }
                });
            } else {
                window.addEventListener('storage', function(e){
                    if ( e.key === 'idleguard_activity' ) {
                        lastActivity = Date.now();
                        resetIdleTimer();
                    }
                    if ( e.key === 'idleguard_logout' ) {
                        window.location.href = redirectUrl;
                    }
                });
            }
        } catch (err) {
            // ignore
        }
    }

    function broadcastActivity(){
        try {
            if ( channel ) {
                channel.postMessage({type:'activity', ts:Date.now()});
            } else {
                localStorage.setItem('idleguard_activity', Date.now());
            }
        } catch (e) {}
    }

    // simple throttle
    function throttle(fn, wait){
        var time = Date.now();
        return function(){
            if ( (time + wait - Date.now()) < 0 ) {
                fn.apply(this, arguments);
                time = Date.now();
            }
        };
    }

    // Initialize
    init();
})();
