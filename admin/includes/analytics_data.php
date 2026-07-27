<?php
declare(strict_types=1);

/**
 * Analytics aggregates for dashboard + PDF export.
 *
 * @return array{
 *   period: string,
 *   period_label: string,
 *   where_time: string,
 *   kpis: array,
 *   chart: array{labels: string[], views: int[], clicks: int[]},
 *   devices: list<array>,
 *   browsers: list<array>,
 *   os_rows: list<array>,
 *   traffic: list<array>,
 *   countries: list<array>,
 *   cities: list<array>,
 *   top_clicks: list<array>,
 *   heatmap: array,
 *   heatmap_max: int,
 *   totals: array{devices: int, browsers: int, os: int, traffic: int}
 * }
 */
function analytics_load(PDO $pdo, string $slug, string $period = '7d'): array
{
    $periodMap = [
        'today' => "DATE(created_at) = CURDATE()",
        '7d'    => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        '30d'   => "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        '90d'   => "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
        'all'   => "1=1",
    ];
    if (!isset($periodMap[$period])) {
        $period = '7d';
    }
    $whereTime = $periodMap[$period];

    $prevMap = [
        'today' => "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
        '7d'    => "created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)",
        '30d'   => "created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)",
        '90d'   => "created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)",
        'all'   => "1=0",
    ];
    $wherePrev = $prevMap[$period];

    $periodLabels = [
        'today' => 'Hoje',
        '7d'    => '7 dias',
        '30d'   => '30 dias',
        '90d'   => '90 dias',
        'all'   => 'Todo período',
    ];

    $q = static function (PDO $pdo, string $sql, array $p = []): PDOStatement {
        $s = $pdo->prepare($sql);
        $s->execute($p);
        return $s;
    };

    $trend = static function (int $now, int $prev): array {
        if ($prev === 0) {
            return $now > 0 ? ['+∞', true] : ['—', null];
        }
        $pct = (int) round(($now - $prev) / $prev * 100);
        return [($pct >= 0 ? '+' : '') . $pct . '%', $pct >= 0];
    };

    $total_views   = (int) $q($pdo, "SELECT COUNT(*) FROM page_views WHERE page_slug=? AND $whereTime", [$slug])->fetchColumn();
    $uniq_visitors = (int) $q($pdo, "SELECT COUNT(DISTINCT ip_hash) FROM page_views WHERE page_slug=? AND $whereTime", [$slug])->fetchColumn();
    $total_clicks  = (int) $q($pdo, "SELECT COUNT(*) FROM click_events WHERE page_slug=? AND $whereTime", [$slug])->fetchColumn();
    $online_now    = (int) $q($pdo, "SELECT COUNT(DISTINCT ip_hash) FROM page_views WHERE page_slug=? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)", [$slug])->fetchColumn();
    $last_visit    = $q($pdo, "SELECT MAX(created_at) FROM page_views WHERE page_slug=?", [$slug])->fetchColumn();

    $prev_views    = (int) $q($pdo, "SELECT COUNT(*) FROM page_views WHERE page_slug=? AND $wherePrev", [$slug])->fetchColumn();
    $prev_visitors = (int) $q($pdo, "SELECT COUNT(DISTINCT ip_hash) FROM page_views WHERE page_slug=? AND $wherePrev", [$slug])->fetchColumn();
    $prev_clicks   = (int) $q($pdo, "SELECT COUNT(*) FROM click_events WHERE page_slug=? AND $wherePrev", [$slug])->fetchColumn();

    [$trend_views, $tv_up]       = $trend($total_views, $prev_views);
    [$trend_visitors, $ts_up]    = $trend($uniq_visitors, $prev_visitors);
    [$trend_clicks, $tc_up]      = $trend($total_clicks, $prev_clicks);

    $conv_rate = $total_views > 0 ? round($total_clicks / $total_views * 100, 1) : 0.0;
    $prev_conv = $prev_views > 0 ? round($prev_clicks / $prev_views * 100, 1) : 0.0;
    [$trend_conv, $tconv_up] = $trend((int) ($conv_rate * 10), (int) ($prev_conv * 10));

    $views_per_visitor  = $uniq_visitors > 0 ? round($total_views / $uniq_visitors, 1) : 0.0;
    $lastVisitFormatted = $last_visit ? date('d/m/Y \à\s H:i', strtotime((string) $last_visit)) : '—';

    $days_interval = match ($period) {
        'today' => 0,
        '7d'    => 6,
        '30d'   => 29,
        '90d'   => 89,
        'all'   => 29,
        default => 6,
    };

    $daily_views_rows  = $q($pdo, "SELECT DATE(created_at) AS day, COUNT(*) AS total FROM page_views WHERE page_slug=? AND $whereTime GROUP BY day ORDER BY day", [$slug])->fetchAll();
    $daily_clicks_rows = $q($pdo, "SELECT DATE(created_at) AS day, COUNT(*) AS total FROM click_events WHERE page_slug=? AND $whereTime GROUP BY day ORDER BY day", [$slug])->fetchAll();

    $chartLabels = [];
    $chartData = [];
    $chartClicks = [];

    if ($period === 'today') {
        $hourly_v = $q($pdo, "SELECT HOUR(created_at) AS h, COUNT(*) AS total FROM page_views WHERE page_slug=? AND DATE(created_at)=CURDATE() GROUP BY h ORDER BY h", [$slug])->fetchAll();
        $hourly_c = $q($pdo, "SELECT HOUR(created_at) AS h, COUNT(*) AS total FROM click_events WHERE page_slug=? AND DATE(created_at)=CURDATE() GROUP BY h ORDER BY h", [$slug])->fetchAll();
        for ($h = 0; $h < 24; $h++) {
            $chartLabels[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
            $fv = 0;
            foreach ($hourly_v as $r) {
                if ((int) $r['h'] === $h) {
                    $fv = (int) $r['total'];
                    break;
                }
            }
            $fc = 0;
            foreach ($hourly_c as $r) {
                if ((int) $r['h'] === $h) {
                    $fc = (int) $r['total'];
                    break;
                }
            }
            $chartData[] = $fv;
            $chartClicks[] = $fc;
        }
    } else {
        for ($i = $days_interval; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $chartLabels[] = date('d/m', strtotime($date));
            $fv = 0;
            foreach ($daily_views_rows as $d) {
                if ($d['day'] === $date) {
                    $fv = (int) $d['total'];
                    break;
                }
            }
            $fc = 0;
            foreach ($daily_clicks_rows as $d) {
                if ($d['day'] === $date) {
                    $fc = (int) $d['total'];
                    break;
                }
            }
            $chartData[] = $fv;
            $chartClicks[] = $fc;
        }
    }

    $heatmap_rows = $q($pdo, "
        SELECT DAYOFWEEK(created_at) AS dow, HOUR(created_at) AS h, COUNT(*) AS total
        FROM page_views WHERE page_slug=? AND $whereTime GROUP BY dow, h
    ", [$slug])->fetchAll();
    $heatmap = [];
    for ($d = 1; $d <= 7; $d++) {
        for ($h = 0; $h < 24; $h++) {
            $heatmap[$d][$h] = 0;
        }
    }
    foreach ($heatmap_rows as $r) {
        $heatmap[(int) $r['dow']][(int) $r['h']] = (int) $r['total'];
    }
    $heatmap_max = max(array_merge([1], array_map('max', $heatmap)));

    $devices = $q($pdo, "SELECT device, COUNT(*) AS total FROM page_views WHERE page_slug=? AND $whereTime GROUP BY device ORDER BY total DESC", [$slug])->fetchAll();
    $dev_total = array_sum(array_column($devices, 'total')) ?: 1;

    $browsers = $q($pdo, "SELECT COALESCE(browser,'Unknown') AS browser, COUNT(*) AS total FROM page_views WHERE page_slug=? AND $whereTime GROUP BY browser ORDER BY total DESC LIMIT 6", [$slug])->fetchAll();
    $br_total = array_sum(array_column($browsers, 'total')) ?: 1;

    $os_rows = $q($pdo, "SELECT COALESCE(os,'Unknown') AS os, COUNT(*) AS total FROM page_views WHERE page_slug=? AND $whereTime GROUP BY os ORDER BY total DESC LIMIT 6", [$slug])->fetchAll();
    $os_total = array_sum(array_column($os_rows, 'total')) ?: 1;

    $traffic = $q($pdo, "
        SELECT
          CASE
            WHEN referrer='' OR referrer IS NULL THEN 'Direto'
            WHEN referrer REGEXP '(google|bing|yahoo|duckduckgo|baidu|yandex)' THEN 'Buscadores'
            WHEN referrer REGEXP '(instagram|facebook|twitter|tiktok|linkedin|youtube|t\\.co|whatsapp)' THEN 'Redes Sociais'
            ELSE 'Outros'
          END AS source,
          COUNT(*) AS total
        FROM page_views WHERE page_slug=? AND $whereTime GROUP BY source ORDER BY total DESC
    ", [$slug])->fetchAll();
    $tr_total = array_sum(array_column($traffic, 'total')) ?: 1;

    $countries = $q($pdo, "SELECT COALESCE(country,'Desconhecido') AS country, COUNT(DISTINCT ip_hash) AS visitors, COUNT(*) AS views FROM page_views WHERE page_slug=? AND $whereTime GROUP BY country ORDER BY visitors DESC LIMIT 10", [$slug])->fetchAll();
    $cities = $q($pdo, "SELECT COALESCE(city,'Desconhecida') AS city, COALESCE(country,'') AS country, COUNT(DISTINCT ip_hash) AS visitors FROM page_views WHERE page_slug=? AND $whereTime AND city IS NOT NULL GROUP BY city, country ORDER BY visitors DESC LIMIT 10", [$slug])->fetchAll();
    $top_clicks = $q($pdo, "
        SELECT element_text, element_type, COUNT(*) AS total
        FROM click_events WHERE page_slug=? AND $whereTime
        GROUP BY element_text, element_type ORDER BY total DESC LIMIT 10
    ", [$slug])->fetchAll();

    return [
        'period'       => $period,
        'period_label' => $periodLabels[$period],
        'where_time'   => $whereTime,
        'period_labels'=> $periodLabels,
        'kpis' => [
            'total_views'          => $total_views,
            'uniq_visitors'        => $uniq_visitors,
            'total_clicks'         => $total_clicks,
            'online_now'           => $online_now,
            'last_visit'           => $last_visit,
            'last_visit_formatted' => $lastVisitFormatted,
            'conv_rate'            => $conv_rate,
            'views_per_visitor'    => $views_per_visitor,
            'trend_views'          => $trend_views,
            'tv_up'                => $tv_up,
            'trend_visitors'       => $trend_visitors,
            'ts_up'                => $ts_up,
            'trend_clicks'         => $trend_clicks,
            'tc_up'                => $tc_up,
            'trend_conv'           => $trend_conv,
            'tconv_up'             => $tconv_up,
        ],
        'chart' => [
            'labels' => $chartLabels,
            'views'  => $chartData,
            'clicks' => $chartClicks,
        ],
        'devices'     => $devices,
        'browsers'    => $browsers,
        'os_rows'     => $os_rows,
        'traffic'     => $traffic,
        'countries'   => $countries,
        'cities'      => $cities,
        'top_clicks'  => $top_clicks,
        'heatmap'     => $heatmap,
        'heatmap_max' => $heatmap_max,
        'totals' => [
            'devices'  => $dev_total,
            'browsers' => $br_total,
            'os'       => $os_total,
            'traffic'  => $tr_total,
        ],
    ];
}
