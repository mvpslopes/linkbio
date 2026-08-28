<?php

function genealogy_painel_menu(): array
{
    return [
        'pessoas' => [
            'href' => '/painel/',
            'label' => 'Pessoas',
            'desc' => 'Cadastro familiar',
            'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        ],
        'arvore' => [
            'href' => '/',
            'label' => 'Árvore',
            'desc' => 'Visualizar família',
            'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="6" cy="12" r="2"/><circle cx="18" cy="12" r="2"/><circle cx="9" cy="19" r="2"/><circle cx="15" cy="19" r="2"/><path d="M12 7v3M8.5 10.5 7 12M15.5 10.5 17 12M10.5 13.5 9.5 17M13.5 13.5 14.5 17"/></svg>',
        ],
    ];
}

function genealogy_painel_css_href(): string
{
    $file = __DIR__ . '/painel.css';
    return '/painel/includes/painel.css?v=' . (is_file($file) ? filemtime($file) : time());
}

function genealogy_painel_head(): void
{
    echo '<link rel="stylesheet" href="' . htmlspecialchars(genealogy_painel_css_href(), ENT_QUOTES, 'UTF-8') . '"/>';
}

function genealogy_painel_emit_layout_styles(): void
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

function genealogy_painel_layout_start(string $active, array $opts = []): void
{
    genealogy_painel_emit_layout_styles();
    $user = $opts['user'] ?? (function_exists('genealogy_auth_user') ? genealogy_auth_user() : null);
    $title = (string) ($opts['title'] ?? '');
    $subtitle = (string) ($opts['subtitle'] ?? '');
    $actions = (string) ($opts['actions'] ?? '');
    $backHref = (string) ($opts['back_href'] ?? '');
    $backLabel = (string) ($opts['back_label'] ?? 'Voltar');
    $userName = $user ? htmlspecialchars((string) ($user['name'] ?: $user['username']), ENT_QUOTES, 'UTF-8') : '';

    echo '<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>';
    echo '<aside class="sidebar" id="sidebar" aria-label="Menu principal">';

    echo '<div class="sidebar-brand">';
    echo '<div class="sidebar-logo-wrap">';
    echo '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1B3A2D" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="6" cy="12" r="2"/><circle cx="18" cy="12" r="2"/><circle cx="9" cy="19" r="2"/><circle cx="15" cy="19" r="2"/><path d="M12 7v3M8.5 10.5 7 12M15.5 10.5 17 12M10.5 13.5 9.5 17M13.5 13.5 14.5 17"/></svg>';
    echo '</div>';
    echo '<div class="sidebar-brand-text">';
    echo '<span class="sidebar-brand-name">Árvore Genealógica</span>';
    echo '<span class="sidebar-brand-role">Cadastro familiar</span>';
    echo '</div></div>';

    echo '<nav class="sidebar-nav" aria-label="Navegação">';
    foreach (genealogy_painel_menu() as $key => $item) {
        $cls = 'sidebar-link' . ($key === $active ? ' active' : '');
        $target = '';
        echo '<a href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '" class="' . $cls . '"' . $target . '>';
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

function genealogy_painel_layout_end(): void
{
    echo '</main></div>';
    echo '<script>(function(){var b=document.body,t=document.getElementById("sidebar-toggle"),o=document.getElementById("sidebar-overlay");function c(){b.classList.remove("sidebar-open");if(t)t.setAttribute("aria-expanded","false");if(t)t.setAttribute("aria-label","Abrir menu");}function g(){var open=b.classList.toggle("sidebar-open");if(t){t.setAttribute("aria-expanded",open?"true":"false");t.setAttribute("aria-label",open?"Fechar menu":"Abrir menu");}}if(t)t.addEventListener("click",g);if(o)o.addEventListener("click",c);document.querySelectorAll(".sidebar-nav a").forEach(function(a){a.addEventListener("click",function(){if(window.matchMedia("(max-width: 959px)").matches)c();});});window.addEventListener("keydown",function(e){if(e.key==="Escape")c();});window.addEventListener("resize",function(){if(window.matchMedia("(min-width: 960px)").matches)c();});})();</script>';
}
