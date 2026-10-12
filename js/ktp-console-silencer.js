/* class-ktpwp-assets.php から切り出し（以前は PHP の nowdoc で返していた） */
(function(){
    try {
        if (!window.console || !console.log) return;
        // オプトインでログを復活
        var _allow = false;
        try {
            if (window.localStorage && window.localStorage.getItem('ktpwp_console_debug') === '1') _allow = true;
        } catch(e) {}
        try {
            if (/[?&]ktpdebug=1(\b|&|$)/.test(window.location.search)) _allow = true;
        } catch(e) {}
        if (_allow) return;

        var _origLog = console.log;
        var _origInfo = console.info;
        // KTPWP プラグイン系のデバッグログを抑制するパターン
        var _ktpRe = /^(\[(SERVICE|SUPPLIER|INVOICE|COST|CALC|CALCULATION|CLIENT|DELIVERY|EMAIL|ORDER|STAFF|KTPWP|KTP|CHAT|DEBUG|SVG|BALLOON|REPORT|PREVIEW|POPUP)[^\]]*\]|KTPWP\b|KTP\b|Head:\s*AJAX|Footer\s+fallback:|Ajax|AJAX)/i;
        function _shouldSuppress(args) {
            if (!args || args.length === 0) return false;
            var first = args[0];
            if (typeof first !== 'string') return false;
            return _ktpRe.test(first);
        }
        console.log = function() {
            if (_shouldSuppress(arguments)) return;
            return _origLog.apply(console, arguments);
        };
        console.info = function() {
            if (_shouldSuppress(arguments)) return;
            return _origInfo.apply(console, arguments);
        };
    } catch(e) {}
})();
