<?php
/**
 * Graph Renderer class for KTPWP plugin
 *
 * Handles the rendering of graphs using Chart.js.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KTPWP_Graph_Renderer' ) ) {

	class KTPWP_Graph_Renderer {

		/**
		 * Render dummy graph content
		 *
		 * @since 1.0.0
		 * @return string HTML content for the dummy graph
		 */
		public function render_dummy_graph() {
			$dummy_graph_url = esc_url( plugins_url( '../images/default/dummy_graph.png', __FILE__ ) );
			$license_url = esc_url( admin_url( 'admin.php?page=ktp-license' ) );

			$content = '<div style="position:relative;max-width:800px;margin:30px auto;">';
			$content .= '<img src="' . $dummy_graph_url . '" alt="' . esc_attr__( 'Report Graph', 'ktpwp' ) . '" style="width:100%;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.1);filter:blur(3px);opacity:0.7;">';
			$content .= '<div style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.3);border-radius:8px;display:flex;flex-direction:column;justify-content:flex-start;align-items:center;text-align:center;padding:20px;">';
			$content .= '<h3 style="margin:50px 0 15px;color:#333;font-size:24px;text-shadow:0 1px 2px rgba(255,255,255,0.8);">' . esc_html__( '高度なグラフレポート機能', 'ktpwp' ) . '</h3>';
			$content .= '<p style="margin:10px 0;color:#555;font-size:16px;text-shadow:0 1px 2px rgba(255,255,255,0.8);">' . esc_html__( 'ライセンス認証が必要です', 'ktpwp' ) . '</p>';
			$content .= '<a href="' . $license_url . '" style="display:inline-block;background:linear-gradient(135deg, #e74c3c, #c0392b);color:#fff;font-size:16px;font-weight:bold;padding:15px 32px;margin-top:10px;border-radius:50px;text-decoration:none;box-shadow:0 4px 15px rgba(231,76,60,0.3);transition:transform 0.3s, box-shadow 0.3s;border:none;text-transform:uppercase;letter-spacing:1px;" onmouseover="this.style.transform=\'translateY(-3px)\';this.style.boxShadow=\'0 8px 20px rgba(231,76,60,0.4)\';" onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 4px 15px rgba(231,76,60,0.3)\';">' . esc_html__( 'ライセンスを認証', 'ktpwp' ) . '</a>';
			$content .= '<p style="margin-top:15px;color:#555;font-size:14px;">' . esc_html__( 'ライセンス認証を完了すると、レポート機能が利用できるようになります', 'ktpwp' ) . '</p>';
			$content .= '</div></div>';

			return $content;
		}

		/**
		 * Render graph content with dummy data
		 *
		 * @since 1.0.0
		 * @return string HTML content for the graphs
		 */
		public function render_graphs() {
			$content = '<div id="report_content" style="background:#fff;padding:32px 12px 32px 12px;max-width:900px;margin:32px auto 0 auto;border-radius:10px;box-shadow:0 2px 8px #eee;">';
			$content .= '<h3 style="margin-bottom:24px;">レポートグラフ（ダミーデータ）</h3>';
			$content .= '<div style="display:flex;flex-wrap:wrap;gap:32px;justify-content:center;">';
			$content .= '<div><canvas id="barChart" width="320" height="240"></canvas><div style="text-align:center;">棒グラフ</div></div>';
			$content .= '<div><canvas id="pieChart" width="320" height="240"></canvas><div style="text-align:center;">円グラフ</div></div>';
			$content .= '<div><canvas id="lineChart" width="320" height="240"></canvas><div style="text-align:center;">折れ線グラフ</div></div>';
			$content .= '<div><canvas id="stackedBarChart" width="320" height="240"></canvas><div style="text-align:center;">帯グラフ</div></div>';
			$content .= '</div></div>';
			$content .= '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
			$content .= '<script>
// 色設定
const colors = ["#e74c3c", "#3498db", "#f1c40f", "#bdc3c7"];

// 棒グラフ
new Chart(document.getElementById("barChart"), {
    type: "bar",
    data: {
        labels: ["A", "B", "C", "D"],
        datasets: [{
            label: "売上",
            data: [120, 190, 300, 250],
            backgroundColor: colors,
            borderColor: "#fff",
            borderWidth: 1
        }]
    },
    options: {
        plugins: { legend: { labels: { color: "#333" } } },
        scales: {
            x: { grid: { color: "#eee" }, ticks: { color: "#333" } },
            y: { grid: { color: "#eee" }, ticks: { color: "#333" } }
        }
    }
});

// 円グラフ
new Chart(document.getElementById("pieChart"), {
    type: "pie",
    data: {
        labels: ["赤", "青", "黄", "グレー"],
        datasets: [{
            data: [30, 40, 20, 10],
            backgroundColor: colors,
            borderColor: "#fff",
            borderWidth: 2
        }]
    },
    options: {
        plugins: { legend: { labels: { color: "#333" } } }
    }
});

// 折れ線グラフ
new Chart(document.getElementById("lineChart"), {
    type: "line",
    data: {
        labels: ["1月", "2月", "3月", "4月"],
        datasets: [{
            label: "推移",
            data: [10, 25, 18, 32],
            borderColor: colors[0],
            backgroundColor: "rgba(231,76,60,0.1)",
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        plugins: { legend: { labels: { color: "#333" } } },
        scales: {
            x: { grid: { color: "#eee" }, ticks: { color: "#333" } },
            y: { grid: { color: "#eee" }, ticks: { color: "#333" } }
        }
    }
});

// 帯グラフ（積み上げ棒グラフ）
new Chart(document.getElementById("stackedBarChart"), {
    type: "bar",
    data: {
        labels: ["Q1", "Q2", "Q3", "Q4"],
        datasets: [
            { label: "赤", data: [10, 20, 30, 40], backgroundColor: colors[0] },
            { label: "青", data: [20, 10, 15, 25], backgroundColor: colors[1] },
            { label: "黄", data: [5, 15, 10, 20], backgroundColor: colors[2] },
            { label: "グレー", data: [8, 12, 6, 10], backgroundColor: colors[3] }
        ]
    },
    options: {
        plugins: { legend: { labels: { color: "#333" } } },
        scales: {
            x: { stacked: true, grid: { color: "#eee" }, ticks: { color: "#333" } },
            y: { stacked: true, grid: { color: "#eee" }, ticks: { color: "#333" } }
        }
    }
});
        </script>';

			return $content;
		}
	}

}
