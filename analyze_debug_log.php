<?php
/**
 * デバッグログ分析ツール
 * 大きなログファイルを効率的に分析するためのスクリプト
 */

// ログファイルのパス
$log_file = /Users/kantanpro/Desktop/ktpwp/wordpress/wp-content/debug.log';

if (!file_exists($log_file))[object Object]
    die(ログファイルが見つかりません: $log_file\n);
}

echo "=== デバッグログ分析ツール ===\n;echo ログファイル: $log_file\n";
echo "ファイルサイズ: . number_format(filesize($log_file)) .  bytes\n\n";

//1. エラーレベルの統計
echo === エラーレベル統計 ===\n;$error_levels = ['ERROR,WARNING', NOTICE, DEBUG', 'FATAL'];
$level_counts = array_fill_keys($error_levels, 0);

$handle = fopen($log_file, r);
if ($handle) {
    while (($line = fgets($handle)) !== false)[object Object]        foreach ($error_levels as $level) [object Object]            if (stripos($line, $level) !== false)[object Object]
                $level_counts[$level]++;
                break;
            }
        }
    }
    fclose($handle);
}

foreach ($level_counts as $level => $count) [object Object]
    echo$level: $count\n;
}

//2. 最新のエラー（最後の100行）
echoundefinedn=== 最新のエラー（最後の100） ===\n;$lines = file($log_file);
$recent_lines = array_slice($lines, -100;
foreach ($recent_lines as $line) {
    if (preg_match(/(ERROR|WARNING|FATAL)/i', $line)) {
        echo trim($line) . \n;
    }
}

// 3. 特定のキーワードで検索
echo "\n=== 特定キーワード検索 ===\n;
$keywords = ['KantanPro', KTPWP', database', 'SQL, wpdb', 'plugin'];
$keyword_counts = array_fill_keys($keywords, 0);

$handle = fopen($log_file, r);
if ($handle) {
    while (($line = fgets($handle)) !== false)[object Object]        foreach ($keywords as $keyword) [object Object]            if (stripos($line, $keyword) !== false)[object Object]
                $keyword_counts[$keyword]++;
            }
        }
    }
    fclose($handle);
}

foreach ($keyword_counts as $keyword => $count) {
    echo $keyword: $count 回出現\n;
}

// 4. 時間別エラー分布（最新の100行）
echo \n=== 時間別エラー分布（最新の1000行） ===\n;
$recent_lines = array_slice($lines, -100our_counts = array_fill(0, 240

foreach ($recent_lines as $line) {
    if (preg_match('/\(\d{2}):\d{2}:\d{2}\]/, $line, $matches))[object Object]
        $hour = (int)$matches1      $hour_counts[$hour]++;
    }
}

for ($i =0 $i < 24 $i++) {
    if ($hour_counts[$i] >0      echo sprintf(%02 %d件\n", $i, $hour_counts$i]);
    }
}

// 5 最も頻繁に出現するエラーメッセージ
echo undefinedn=== 頻出エラーメッセージ（最新の1000） ===\n;
$error_messages = [];
foreach ($recent_lines as $line) {
    if (preg_match(/(ERROR|WARNING|FATAL)/i', $line)) [object Object]        $message = trim($line);
        $error_messages[$message] = ($error_messages$message] ??0 + 1
    }
}

arsort($error_messages);
$top_errors = array_slice($error_messages, 0, 10 true);
foreach ($top_errors as $message => $count) {
    echo "($count回) " . substr($message, 0, 10. "...\n;
}

echoundefinedn=== 分析完了 ===\n";
?> 