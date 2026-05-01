# Build LinkBio — copia raiz, admin, subdomínios (paty, marcosblea) e assets para dist/
$ErrorActionPreference = "Stop"
$root = $PSScriptRoot
$dist = Join-Path $root "dist"

Write-Host "Build LinkBio -> dist/" -ForegroundColor Cyan
Write-Host ""

# Garantir que dist existe
if (-not (Test-Path $dist)) { New-Item -ItemType Directory -Path $dist | Out-Null }

# Página principal
Copy-Item -Path (Join-Path $root "index.html") -Destination $dist -Force
Write-Host "  [OK] index.html"

# Tracker
Copy-Item -Path (Join-Path $root "tracker.js") -Destination $dist -Force
Write-Host "  [OK] tracker.js"

# Logo (assets do site principal)
$logoSrc = Join-Path $root "logo"
$logoDst = Join-Path $dist "logo"
if (Test-Path $logoSrc) {
    if (Test-Path $logoDst) { Remove-Item $logoDst -Recurse -Force }
    Copy-Item -Path $logoSrc -Destination $logoDst -Recurse -Force
    Write-Host "  [OK] logo/"
}

# Public (assets compartilhados)
$publicSrc = Join-Path $root "public"
$publicDst = Join-Path $dist "public"
if (Test-Path $publicSrc) {
    if (Test-Path $publicDst) { Remove-Item $publicDst -Recurse -Force }
    Copy-Item -Path $publicSrc -Destination $publicDst -Recurse -Force
    Write-Host "  [OK] public/"
}

# Hero (vídeos do hero)
$heroSrc = Join-Path $root "hero"
$heroDst = Join-Path $dist "hero"
if (Test-Path $heroSrc) {
    if (Test-Path $heroDst) { Remove-Item $heroDst -Recurse -Force }
    Copy-Item -Path $heroSrc -Destination $heroDst -Recurse -Force
    Write-Host "  [OK] hero/"
}

# Splash (imagens de splash)
$splashSrc = Join-Path $root "splash"
$splashDst = Join-Path $dist "splash"
if (Test-Path $splashSrc) {
    if (Test-Path $splashDst) { Remove-Item $splashDst -Recurse -Force }
    Copy-Item -Path $splashSrc -Destination $splashDst -Recurse -Force
    Write-Host "  [OK] splash/"
}

# Admin (sistema interno)
$adminSrc = Join-Path $root "admin"
$adminDst = Join-Path $dist "admin"
if (Test-Path $adminDst) { Remove-Item $adminDst -Recurse -Force }
Copy-Item -Path $adminSrc -Destination $adminDst -Recurse -Force
Write-Host "  [OK] admin/"

# Faixa do Cristiano: imagem opcional na raiz do repositório
$eventoBgRoot = Join-Path $root "evento-bg.png"
$eventoBgDest = Join-Path $root "cristianoladeira\evento-bg.png"
if (Test-Path $eventoBgRoot) {
    Copy-Item -Path $eventoBgRoot -Destination $eventoBgDest -Force
    Write-Host "  [OK] evento-bg.png -> cristianoladeira/"
}

# Subdomínios
$subdomains = @("paty", "marcosblea", "equusvita", "racaemarcha", "cristianoladeira", "puramarcha", "fafs", "topmarchador", "alinesantana", "orielsilvano", "jessicapersonal", "emmanueladv", "maludias", "lancamento", "willianpersonal", "nutrisheiladomingues", "aliancce", "masteragro", "haraspariz", "giuliadias")
foreach ($name in $subdomains) {
    $src = Join-Path $root $name
    $dst = Join-Path $dist $name
    if (Test-Path $src) {
        if (Test-Path $dst) { Remove-Item $dst -Recurse -Force }
        Copy-Item -Path $src -Destination $dst -Recurse -Force
        Write-Host "  [OK] $name/"
    }
}

Write-Host ""
Write-Host "Build concluído. Saída em: dist/" -ForegroundColor Green
