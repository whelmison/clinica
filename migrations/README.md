# Migracoes

Execute as migracoes manualmente com:

```powershell
php scripts/migrate.php
```

O projeto nao atualiza mais schema automaticamente em cada request.

Se precisar reativar temporariamente o comportamento antigo em ambiente local:

```powershell
$env:APP_AUTO_SCHEMA='1'
```
