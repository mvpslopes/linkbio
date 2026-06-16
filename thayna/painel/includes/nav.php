<?php
/** @var string $active 'clientes'|'relatorios' */

function thayna_painel_menu(): array
{
    return [
        'relatorios' => [
            'href' => '/painel/',
            'label' => 'Relatórios',
            'desc' => 'Análise comportamental',
            'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        ],
        'clientes' => [
            'href' => '/painel/clientes.php',
            'label' => 'Clientes',
            'desc' => 'Termos e cadastro',
            'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        ],
    ];
}

function thayna_painel_css_href(): string
{
    $assets = dirname(__DIR__) . '/assets/painel.css';
    $includes = __DIR__ . '/painel.css';
    if (is_file($assets)) {
        return '/painel/assets/painel.css?v=' . filemtime($assets);
    }
    return '/painel/includes/painel.css?v=' . (is_file($includes) ? filemtime($includes) : time());
}

function thayna_painel_head(): void
{
    echo '<link rel="stylesheet" href="' . htmlspecialchars(thayna_painel_css_href(), ENT_QUOTES, 'UTF-8') . '"/>';
}

function thayna_painel_emit_layout_styles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $file = __DIR__ . '/layout-critical.css';
    if (!is_file($file)) {
        return;
    }
    $css = file_get_contents($file);
    if ($css === false || $css === '') {
        return;
    }
    echo '<style id="painel-layout-critical">' . $css . '</style>';
}

/**
 * @param array{title?:string,subtitle?:string,actions?:string,back_href?:string,back_label?:string,user?:array} $opts
 */
function thayna_painel_layout_start(string $active, array $opts = []): void
{
    thayna_painel_emit_layout_styles();
    $user = $opts['user'] ?? (function_exists('thayna_auth_user') ? thayna_auth_user() : null);
    $title = (string) ($opts['title'] ?? '');
    $subtitle = (string) ($opts['subtitle'] ?? '');
    $actions = (string) ($opts['actions'] ?? '');
    $backHref = (string) ($opts['back_href'] ?? '');
    $backLabel = (string) ($opts['back_label'] ?? 'Voltar');
    $userName = $user ? htmlspecialchars((string) ($user['name'] ?: $user['username']), ENT_QUOTES, 'UTF-8') : '';

    $logoPath = null;
    if (is_file(dirname(__DIR__) . '/assets/logo.png')) {
        $logoPath = '/painel/assets/logo.png';
    } elseif (is_file(dirname(__DIR__, 2) . '/logo/logo.png')) {
        $logoPath = '/logo/logo.png';
    }

    echo '<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>';
    echo '<aside class="sidebar" id="sidebar" aria-label="Menu principal">';

    echo '<div class="sidebar-brand">';
    if ($logoPath) {
        echo '<div class="sidebar-logo-wrap">';
        echo '<img src="' . htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') . '" alt="Confia na Relação" class="sidebar-logo"/>';
        echo '</div>';
    }
    echo '<div class="sidebar-brand-text">';
    echo '<span class="sidebar-brand-name">Thayna Freire</span>';
    echo '<span class="sidebar-brand-role">Painel administrativo</span>';
    echo '</div></div>';

    echo '<nav class="sidebar-nav" aria-label="Navegação">';
    foreach (thayna_painel_menu() as $key => $item) {
        $cls = 'sidebar-link' . ($key === $active ? ' active' : '');
        echo '<a href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '" class="' . $cls . '">';
        echo '<span class="sidebar-link-icon">' . $item['icon'] . '</span>';
        echo '<span class="sidebar-link-body">';
        echo '<span class="sidebar-link-label">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<span class="sidebar-link-desc">' . htmlspecialchars($item['desc'], ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</span></a>';
    }
    echo '</nav>';

    echo '<div class="sidebar-footer">';
    if ($userName !== '') {
        echo '<div class="sidebar-user">';
        echo '<span class="sidebar-user-avatar" aria-hidden="true">' . mb_strtoupper(mb_substr($userName, 0, 1)) . '</span>';
        echo '<span class="sidebar-user-name">' . $userName . '</span>';
        echo '</div>';
    }
    echo '<a href="/painel/logout.php" class="sidebar-logout">';
    echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
    echo 'Sair';
    echo '</a></div>';

    echo '</aside>';

    echo '<div class="app-shell">';
    echo '<header class="app-topbar">';
    echo '<div class="app-topbar-start">';
    echo '<button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="sidebar">';
    echo '<span class="hamburger" aria-hidden="true"><span></span><span></span><span></span></span>';
    echo '</button>';
    if ($backHref !== '') {
        echo '<a href="' . htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') . '" class="topbar-back">' . htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8') . '</a>';
    }
    echo '<div class="topbar-titles">';
    if ($title !== '') {
        echo '<h1 class="topbar-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    }
    if ($subtitle !== '') {
        echo '<p class="topbar-sub">' . $subtitle . '</p>';
    }
    echo '</div></div>';
    if ($actions !== '') {
        echo '<div class="topbar-actions">' . $actions . '</div>';
    }
    echo '</header>';
    echo '<main class="app-main">';
}

function thayna_painel_layout_end(): void
{
    echo '</main></div>';
    echo '<script>(function(){var b=document.body,t=document.getElementById("sidebar-toggle"),o=document.getElementById("sidebar-overlay");function c(){b.classList.remove("sidebar-open");if(t)t.setAttribute("aria-expanded","false");if(t)t.setAttribute("aria-label","Abrir menu");}function g(){var open=b.classList.toggle("sidebar-open");if(t){t.setAttribute("aria-expanded",open?"true":"false");t.setAttribute("aria-label",open?"Fechar menu":"Abrir menu");}}if(t)t.addEventListener("click",g);if(o)o.addEventListener("click",c);document.querySelectorAll(".sidebar-nav a").forEach(function(a){a.addEventListener("click",function(){if(window.matchMedia("(max-width: 959px)").matches)c();});});window.addEventListener("keydown",function(e){if(e.key==="Escape")c();});window.addEventListener("resize",function(){if(window.matchMedia("(min-width: 960px)").matches)c();});})();</script>';
}
