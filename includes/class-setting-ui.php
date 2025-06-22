<?php
/**
 * Handles UI logic for KTPWP settings.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KTPWP_Setting_UI {

    /**
     * Render the settings tab view
     *
     * @param string $tab_name The name of the tab
     * @return string HTML content for the tab
     */
    public static function render_tab_view( $tab_name ) {
        $active_tab = isset( $_COOKIE['active_tab'] ) ? $_COOKIE['active_tab'] : 'Atena';
        $atenaClass = $active_tab == 'Atena' ? 'active' : '';

        $tab_buttons = <<<BUTTONS
        <div class="controller" data-active-tab="$active_tab">
                <button class="tablinks {$atenaClass}" onclick="switchTab(event, 'Atena');" title="印刷テンプレート" style="padding: 6px 10px; font-size: 12px;">
                    <span class="material-symbols-outlined" aria-label="印刷テンプレート">print_add</span>
                </button>
                <button id="ktpwp-preview-btn" title="プレビュー" style="padding: 6px 10px; font-size: 12px;">
                    <span class="material-symbols-outlined" aria-label="プレビュー">preview</span>
                </button>
        </div>

        <div class="workflow">
        </div>
        BUTTONS;

        $tab_script = <<<SCRIPT
        <script>
        var isSettingPreviewOpen = false;
        
        function switchTab(evt, tabName) {
            const tabcontent = document.getElementsByClassName("tabcontent");
            const tablinks = document.getElementsByClassName("tablinks");

            for (let i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }

            for (let i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }

            const targetTab = document.getElementById(tabName);
            if (targetTab) {
                targetTab.style.display = "block";
            }

            if (evt && evt.currentTarget) {
                evt.currentTarget.classList.add("active");
            }

            const activeTabField = document.getElementById('active_tab');
            if (activeTabField) {
                activeTabField.value = tabName;
            }
        }

        function toggleSettingPreview() {
            var previewWindow = document.getElementById("settingPreviewWindow");
            var previewButton = document.getElementById("ktpwp-preview-btn");
            if (isSettingPreviewOpen) {
                previewWindow.style.display = "none";
                previewButton.innerHTML = "<span class=\"material-symbols-outlined\" aria-label=\"プレビュー\">preview</span>";
                isSettingPreviewOpen = false;
                return;
            } else {
                // Get current editor content and update preview
                updatePreviewContent();
                previewWindow.style.display = "block";
                previewButton.innerHTML = "<span class=\"material-symbols-outlined\" aria-label=\"閉じる\">close</span>";
                isSettingPreviewOpen = true;
                return;
            }
        }

        function updatePreviewContent() {
            var previewContainer = document.querySelector("#settingPreviewWindow div div");
            if (!previewContainer) return;

            // Get content from WordPress editor (both visual and text modes)
            var editorContent = '';
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('template_content')) {
                editorContent = tinyMCE.get('template_content').getContent();
            } else {
                var textEditor = document.getElementById('template_content');
                if (textEditor) {
                    editorContent = textEditor.value;
                }
            }

            // Apply replacements (same as PHP logic)
            var replacements = {
                '_%customer%_': 'ダミー顧客名',
                '_%postal_code%_': '123-4567',
                '_%prefecture%_': '東京都',
                '_%city%_': '千代田区',
                '_%address%_': '1-2-3',
                '_%building%_': 'サンプルビル',
                '_%user_name%_': '担当 太郎'
            };

            var previewContent = editorContent;
            for (var placeholder in replacements) {
                previewContent = previewContent.replace(new RegExp(placeholder, 'g'), replacements[placeholder]);
            }

            if (!previewContent.trim()) {
                previewContent = '<p>テンプレートが設定されていません。エディターでテンプレートを作成してください。</p>';
            }

            previewContainer.innerHTML = previewContent;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const activeTabField = document.getElementById('active_tab');
            const activeTab = activeTabField ? (activeTabField.value || 'Atena') : 'Atena';
            switchTab(null, activeTab);

            // Setup preview button
            const previewBtn = document.getElementById('ktpwp-preview-btn');
            if (previewBtn) {
                previewBtn.addEventListener('click', toggleSettingPreview);
            }
        });
        </script>
        SCRIPT;

        return $tab_script . $tab_buttons;
    }
}
