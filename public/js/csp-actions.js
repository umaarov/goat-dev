// Replaces inline on* handlers so a strict CSP can forbid them.
// Markup: data-click | data-change | data-input | data-submit = "actionName", data-args='["$el","$event",1]'
// Only names in ALLOWED can be triggered from markup; they are resolved on window at call time.
(function () {
    'use strict';

    var ALLOWED = [
        'voteForOption', 'toggleComments', 'sharePost', 'submitComment', 'cancelReply',
        'loadMoreComments', 'toggleCommentLike', 'scrollToComment', 'toggleRepliesContainer',
        'deleteComment', 'loadMoreReplies', 'prepareReply',
        'openImageCropper', 'updateDynamicLinkIcon', 'unlinkProvider', 'previewProfilePicture'
    ];

    var builtins = {
        navigateToValue: function (el) {
            try {
                var url = new URL(el.value, window.location.origin);
                if (url.origin === window.location.origin) window.location.href = url.href;
            } catch (e) { /* ignore */ }
        },
        clickElement: function (id) {
            var target = document.getElementById(id);
            if (target) target.click();
        },
        showAndCrop: function (ev, containerId, checkboxId) {
            var rest = Array.prototype.slice.call(arguments, 3);
            var container = document.getElementById(containerId);
            if (container) container.classList.remove('hidden');
            var checkbox = document.getElementById(checkboxId);
            if (checkbox) checkbox.checked = false;
            if (typeof window.openImageCropper === 'function') window.openImageCropper.apply(null, [ev].concat(rest));
        }
    };

    function resolveArgs(raw, el, ev) {
        var list = [];
        try { list = JSON.parse(raw || '[]'); } catch (e) { list = []; }
        if (!Array.isArray(list)) list = [];
        return list.map(function (a) { return a === '$el' ? el : (a === '$event' ? ev : a); });
    }

    function dispatch(type, ev) {
        var start = ev.target;
        if (!start || !start.closest) return;
        var el = start.closest('[data-' + type + ']');
        if (!el) return;

        var name = el.getAttribute('data-' + type);
        var fn = Object.prototype.hasOwnProperty.call(builtins, name)
            ? builtins[name]
            : (ALLOWED.indexOf(name) !== -1 ? window[name] : null);
        if (typeof fn !== 'function') return;

        if (el.tagName === 'A') ev.preventDefault();
        fn.apply(el, resolveArgs(el.getAttribute('data-args'), el, ev));
    }

    ['click', 'change', 'input', 'submit'].forEach(function (type) {
        document.addEventListener(type, function (ev) { dispatch(type, ev); });
    });

    // <form data-confirm="Are you sure?"> (capture phase, runs before data-submit handlers)
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (form && form.getAttribute && form.getAttribute('data-confirm') && !window.confirm(form.getAttribute('data-confirm'))) {
            ev.preventDefault();
            ev.stopImmediatePropagation();
        }
    }, true);

    // <link rel="stylesheet" media="print" data-async-css>: apply once loaded
    function activateAsyncCss() {
        document.querySelectorAll('link[data-async-css]').forEach(function (link) {
            var apply = function () { link.media = 'all'; };
            if (link.sheet) apply(); else link.addEventListener('load', apply, {once: true});
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', activateAsyncCss);
    else activateAsyncCss();
})();
