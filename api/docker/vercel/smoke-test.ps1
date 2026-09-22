param([string]$Image = 'cuaderno-api-vercel:validation')
$ErrorActionPreference = 'Stop'
$containerName = 'cuaderno-vercel-check-' + [Guid]::NewGuid().ToString('N').Substring(0, 10)
$testKey = 'base64:' + [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
try {
    docker run -d --name $containerName --network none --read-only --tmpfs /tmp:rw,nosuid,size=128m `
        -e "APP_KEY=$testKey" -e DB_HOST=127.0.0.1 -e DB_PORT=1 -e APP_URL=http://localhost $Image
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo iniciar el contenedor.' }
    $ready = $false
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        $running = docker inspect --format '{{.State.Running}}' $containerName
        if ($running -ne 'true') { throw 'El contenedor se detuvo durante el arranque.' }
        $code = docker exec $containerName curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/up
        if ($LASTEXITCODE -eq 0 -and $code -eq '200') { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'El endpoint /up no respondió 200.' }
    foreach ($check in @(@('/up', '200'), @('/api/auth/me', '401'), @('/.env', '403'), @('/composer.json', '404'), @('/test.php', '404'))) {
        $code = docker exec $containerName curl -s -o /dev/null -w '%{http_code}' -H 'Accept: application/json' "http://127.0.0.1$($check[0])"
        if ($LASTEXITCODE -ne 0 -or $code -ne $check[1]) { throw "$($check[0]): esperado $($check[1]), recibido $code" }
        Write-Output "$($check[0]): HTTP $code OK"
    }
    docker exec $containerName sh -c 'test ! -f /var/www/html/.env && php -m | grep -q pdo_pgsql'
    if ($LASTEXITCODE -ne 0) { throw 'Falló la comprobación de secretos/extensión PostgreSQL.' }
    Write-Output 'Validación local aprobada: sin conexión a Supabase y filesystem de solo lectura.'
} catch {
    docker logs --tail 60 $containerName
    throw
} finally {
    docker stop $containerName | Out-Null
    docker rm $containerName | Out-Null
}
