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

# Admin (sistema interno)
$adminSrc = Join-Path $root "admin"
$adminDst = Join-Path $dist "admin"
if (Test-Path $adminDst) { Remove-Item $adminDst -Recurse -Force }
Copy-Item -Path $adminSrc -Destination $adminDst -Recurse -Force
Write-Host "  [OK] admin/"

# Subdomínios
$subdomains = @("paty", "marcosblea", "equusvita", "racaemarcha", "cristianoladeira", "puramarcha", "fafs", "topmarchador")
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
