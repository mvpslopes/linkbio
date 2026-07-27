<?php
declare(strict_types=1);

/**
 * @param 'depoimentos'|'transformacoes' $active
 */
function painel_module_nav(string $active): void
{
    $items = [
        'depoimentos'     => ['href' => 'index.php', 'label' => 'Depoimentos'],
        'transformacoes'  => ['href' => 'transformacoes.php', 'label' => 'Transformações'],
    ];
    echo '<nav class="side-modules" aria-label="Módulos">' . "\n";
    foreach ($items as $key => $item) {
        $cls = $key === $active ? 'active' : '';
        echo '  <a class="' . $cls . '" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . "</a>\n";
    }
    echo "</nav>\n";
}

function painel_css_href(): string
{
    $file = __DIR__ . '/../assets/painel.css';
    $v = is_file($file) ? (string) filemtime($file) : (string) time();
    return 'assets/painel.css?v=' . $v;
}
