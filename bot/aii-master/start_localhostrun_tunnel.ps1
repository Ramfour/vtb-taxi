$ErrorActionPreference = "Stop"

ssh -o StrictHostKeyChecking=no `
    -o ServerAliveInterval=60 `
    -R 80:127.0.0.1:8088 `
    nokey@localhost.run
