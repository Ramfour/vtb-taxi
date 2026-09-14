$ErrorActionPreference = "Stop"

$cloudflared = Join-Path $PSScriptRoot "tools\cloudflared.exe"

if (-not (Test-Path $cloudflared)) {
    throw "cloudflared.exe не найден: $cloudflared"
}

$env:HTTPS_PROXY = "http://127.0.0.1:10809"
$env:NO_PROXY = "127.0.0.1,localhost"

& $cloudflared tunnel --protocol http2 --url http://127.0.0.1:8088
