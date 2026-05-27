/**
 * app.js — Biryani Shop Manager global JS utilities
 * FIX S-001: getCsrfToken() helper for AJAX requests
 */

'use strict';

// ── Toast Notifications ────────────────────────────────
function createToastContainer() {
    var c = document.getElementById('toastContainer');
    if (!c) {
        c = document.createElement('div');
        c.id = 'toastContainer';
        document.body.appendChild(c);
    }
    return c;
}

/**
 * @param {string} message
 * @param {'success'|'error'|'info'} type
 * @param {number} [duration=3500]
 */
function showToast(message, type, duration) {
    var container = createToastContainer();
    var toast = document.createElement('div');
    toast.className = 'toast toast-' + (type || 'info');
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');

    var icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
    toast.innerHTML = '<span aria-hidden="true">' + icon + '</span> ' + escHtml(message);

    container.appendChild(toast);
    setTimeout(function() {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, duration || 3500);
}

// ── Loading Overlay ─────────────────────────────────────
function showLoader() {
    var el = document.getElementById('loadingOverlay');
    if (!el) {
        el = document.createElement('div');
        el.id = 'loadingOverlay';
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        el.setAttribute('aria-label', 'Loading');
        el.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        document.body.appendChild(el);
    }
    el.classList.add('active');
}

function hideLoader() {
    var el = document.getElementById('loadingOverlay');
    if (el) el.classList.remove('active');
}

// ── CSRF Helper ─────────────────────────────────────────
/**
 * Returns the CSRF token from the page's CSRF_TOKEN global
 * (set inline in each page as: const CSRF_TOKEN = "...";)
 * Falls back to empty string if not available.
 * @returns {string}
 */
function getCsrfToken() {
    return (typeof CSRF_TOKEN !== 'undefined') ? CSRF_TOKEN : '';
}

// ── Fetch with CSRF ─────────────────────────────────────
/**
 * Wrapper around fetch() that automatically adds X-CSRF-TOKEN header
 * for JSON POST requests.
 * @param {string} url
 * @param {object} options  — same as fetch() options; if method=POST and no Content-Type set, defaults to JSON
 * @returns {Promise<Response>}
 */
function csrfFetch(url, options) {
    options = options || {};
    options.headers = options.headers || {};
    var method = (options.method || 'GET').toUpperCase();
    if (method !== 'GET' && method !== 'HEAD') {
        options.headers['X-CSRF-TOKEN'] = getCsrfToken();
    }
    return fetch(url, options);
}

// ── HTML Escape Utility ─────────────────────────────────
function escHtml(s) {
    return String(s)
        .replace(/&/g,  '&amp;')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;')
        .replace(/"/g,  '&quot;')
        .replace(/'/g,  '&#039;');
}

// ── Format Currency (Indian Rupee) ──────────────────────
function fmtINR(n) {
    return '₹' + parseFloat(n || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// ── Tab Component ───────────────────────────────────────
/**
 * Initialises tab panels. Call after DOM is ready.
 * Expects: <div role="tablist">, <button role="tab" data-target="panelId">, <div role="tabpanel" id="panelId">
 */
function initTabs(container) {
    var tabs = (container || document).querySelectorAll('[role="tab"]');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var tablist = this.closest('[role="tablist"]');
            tablist.querySelectorAll('[role="tab"]').forEach(function(t) {
                t.setAttribute('aria-selected', 'false');
                t.classList.remove('active');
            });
            this.setAttribute('aria-selected', 'true');
            this.classList.add('active');
            var panelId = this.dataset.target || this.getAttribute('aria-controls');
            var panels  = document.querySelectorAll('[role="tabpanel"]');
            panels.forEach(function(p) { p.classList.remove('active'); p.setAttribute('hidden', ''); });
            var panel = document.getElementById(panelId);
            if (panel) { panel.classList.add('active'); panel.removeAttribute('hidden'); }
        });
    });
}

// ── DOM Ready ───────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    initTabs();
});
