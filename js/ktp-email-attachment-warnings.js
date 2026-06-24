(function (window) {
    'use strict';

    const ARCHIVE_EXTENSIONS = ['.zip', '.rar', '.7z'];

    function getExtension(filename) {
        const name = String(filename || '');
        const index = name.lastIndexOf('.');
        if (index < 0) {
            return '';
        }
        return name.substring(index).toLowerCase();
    }

    function isRiskyArchive(filename) {
        return ARCHIVE_EXTENSIONS.includes(getExtension(filename));
    }

    function hasRiskyArchives(files) {
        return Array.isArray(files) && files.some(function (file) {
            return file && isRiskyArchive(file.name);
        });
    }

    function getRiskyArchiveNames(files) {
        if (!Array.isArray(files)) {
            return [];
        }
        return files
            .filter(function (file) {
                return file && isRiskyArchive(file.name);
            })
            .map(function (file) {
                return file.name;
            });
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function archiveDeliveryWarningText(translateFn) {
        const translate = typeof translateFn === 'function' ? translateFn : function (text) {
            return text;
        };
        return translate('ZIP・RAR・7z などの圧縮ファイルは、Gmail などの宛先でセキュリティ理由により届かないことがあります。可能であれば PDF などに変換するか、クラウドの共有リンクを本文に記載してください。');
    }

    function renderNoticeHtml(files, translateFn) {
        if (!hasRiskyArchives(files)) {
            return '';
        }

        const translate = typeof translateFn === 'function' ? translateFn : function (text) {
            return text;
        };
        const names = getRiskyArchiveNames(files);
        const listHtml = names.length > 0
            ? '<div style="margin-top:6px;font-size:12px;"><strong>' + escapeHtml(translate('対象')) + ':</strong> ' + names.map(escapeHtml).join(', ') + '</div>'
            : '';

        return ''
            + '<div style="'
            + 'margin-bottom: 10px;'
            + 'padding: 10px 12px;'
            + 'background: #fff3cd;'
            + 'border: 1px solid #ffc107;'
            + 'border-radius: 6px;'
            + 'color: #856404;'
            + 'font-size: 13px;'
            + 'line-height: 1.5;'
            + '">'
            + '<div style="font-weight: bold; margin-bottom: 4px;">⚠️ ' + escapeHtml(translate('添付に関するご注意')) + '</div>'
            + '<div>' + escapeHtml(archiveDeliveryWarningText(translate)) + '</div>'
            + listHtml
            + '</div>';
    }

    window.KtpEmailAttachmentWarnings = {
        isRiskyArchive: isRiskyArchive,
        hasRiskyArchives: hasRiskyArchives,
        getRiskyArchiveNames: getRiskyArchiveNames,
        archiveDeliveryWarningText: archiveDeliveryWarningText,
        renderNoticeHtml: renderNoticeHtml,
    };
})(window);
